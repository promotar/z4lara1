import * as THREE from 'three';

export function artworkSafetyDistanceForPlacement(_placement, configuredDistance = 1) {
    const distance = Number(configuredDistance);

    // One physical rule for every display, occupied or empty. Frame dimensions
    // must never change the visitor's minimum distance from the frame plane.
    return Number.isFinite(distance) && distance > 0 ? distance : 1;
}

export function placementSlotSafetyPlacements(rooms = [], artwork = {}) {
    const placements = [];
    const artworkWidth = Number(artwork?.dimensions?.width_m);
    const artworkHeight = Number(artwork?.dimensions?.height_m);
    for (const room of rooms) {
        for (const slot of room.slots || []) {
            const maximumWidth = Number(slot?.limits?.max_width_m);
            const maximumHeight = Number(slot?.limits?.max_height_m);
            const width = Number(slot?.limits?.visual_width_m || Math.min(artworkWidth, maximumWidth));
            const height = Number(slot?.limits?.visual_height_m || Math.min(artworkHeight, maximumHeight));
            const position = slot?.transform?.position?.map(Number);
            const quaternion = slot?.transform?.quaternion?.map(Number);
            if (!(width > 0) || !(height > 0) || position?.length !== 3 || quaternion?.length !== 4) continue;
            placements.push({
                id: `SLOT_${slot.code}`,
                slot_code: String(slot.code || ''),
                dimensions: { width_m: width, height_m: height },
                transform: { position, quaternion, outward_offset_m: 0.045 },
            });
        }
    }
    return placements;
}

export function createArtworkSafetyCollision(placement, options = {}) {
    const width = Number(placement?.dimensions?.width_m);
    const height = Number(placement?.dimensions?.height_m);
    const position = placement?.transform?.position?.map(Number);
    const quaternion = placement?.transform?.quaternion?.map(Number);
    if (!(width > 0) || !(height > 0) || position?.length !== 3 || quaternion?.length !== 4) {
        throw new Error('Invalid VE-03 artwork safety placement.');
    }

    const safetyDistance = artworkSafetyDistanceForPlacement(placement, options.distance);
    // Keep the same full-depth physical barrier that was accepted in the
    // artist placement experience. The public and artist viewers both use
    // this shared collider for occupied and empty frames.
    const physicalDepth = safetyDistance;
    const sideMargin = Math.max(0.2, Number(options.sideMargin || 0.4));
    const barrierHeight = Math.max(2.6, height + 1);
    const outwardOffset = Math.max(0, Number(placement.transform.outward_offset_m || 0.035));
    const barrier = new THREE.Mesh(
        new THREE.BoxGeometry(width + sideMargin * 2, barrierHeight, physicalDepth),
        new THREE.MeshBasicMaterial(),
    );
    barrier.name = `VE03_ARTWORK_SAFETY_${placement.id}`;
    barrier.position.fromArray(position);
    barrier.quaternion.fromArray(quaternion).normalize();
    barrier.translateZ(outwardOffset + physicalDepth / 2);
    barrier.visible = false;
    barrier.userData.ve03ArtworkSafety = true;
    barrier.userData.placementId = String(placement.id);
    barrier.userData.safetyDistance = safetyDistance;
    barrier.userData.physicalDepth = physicalDepth;
    // Keep the authored mesh envelope compact for spawn validation and
    // neighbouring-room visibility. Physics expands only its lateral
    // collider to the complete safety distance so a capsule cannot slide
    // around the thin trimesh corner.
    barrier.userData.physicsWidth = width + Math.max(sideMargin, safetyDistance) * 2;
    barrier.updateMatrixWorld(true);

    return barrier;
}

export function addArtworkSafetyCollisions(root, placements = [], options = {}) {
    const barriers = placements.map((placement) => createArtworkSafetyCollision(placement, options));
    if (barriers.length > 0) root.add(...barriers);
    root.updateMatrixWorld(true);

    return barriers;
}

export function positionIntersectsArtworkSafety(position, barriers = [], playerRadius = 0.3) {
    if (!position || !Number.isFinite(Number(position.x)) || !Number.isFinite(Number(position.y)) || !Number.isFinite(Number(position.z))) {
        return false;
    }
    const radius = Math.max(0, Number(playerRadius || 0));
    const worldPoint = new THREE.Vector3(Number(position.x), Number(position.y), Number(position.z));
    const localPoint = new THREE.Vector3();

    return barriers.some((barrier) => {
        const parameters = barrier?.geometry?.parameters;
        if (!parameters) return false;
        barrier.updateWorldMatrix(true, false);
        localPoint.copy(worldPoint);
        barrier.worldToLocal(localPoint);
        return Math.abs(localPoint.x) <= Number(parameters.width || 0) / 2 + radius
            && Math.abs(localPoint.y) <= Number(parameters.height || 0) / 2 + radius
            && Math.abs(localPoint.z) <= Number(parameters.depth || 0) / 2 + radius;
    });
}

export function artworkSafetyClearance(position, placements = []) {
    if (!position || !Number.isFinite(Number(position.x)) || !Number.isFinite(Number(position.z))) {
        return Number.POSITIVE_INFINITY;
    }

    const worldPoint = new THREE.Vector3(Number(position.x), Number(position.y || 0), Number(position.z));
    const framePosition = new THREE.Vector3();
    const inverseRotation = new THREE.Quaternion();
    const localPoint = new THREE.Vector3();
    let minimum = Number.POSITIVE_INFINITY;

    for (const placement of placements) {
        const width = Number(placement?.dimensions?.width_m);
        const translation = placement?.transform?.position?.map(Number);
        const rotation = placement?.transform?.quaternion?.map(Number);
        if (!(width > 0) || translation?.length !== 3 || rotation?.length !== 4) continue;

        framePosition.fromArray(translation);
        inverseRotation.fromArray(rotation).normalize().invert();
        localPoint.copy(worldPoint).sub(framePosition).applyQuaternion(inverseRotation);

        // One shared rule for every display: protect only the visible/front
        // face of the finite frame. The authored quaternion points +Z out of
        // the wall. Treating the normal as an absolute distance also protects
        // the invisible back face and can block stairs or a narrow room on the
        // other side of that wall (WEST-H-08 is the regression case).
        if (localPoint.z < 0) continue;
        const lateral = Math.max(0, Math.abs(localPoint.x) - width / 2);
        const forward = localPoint.z;
        minimum = Math.min(minimum, Math.hypot(lateral, forward));
    }

    return minimum;
}

export function artworkSafetyMargin(position, placements = [], maximumDistance = 1) {
    let minimum = Number.POSITIVE_INFINITY;
    for (const placement of placements) {
        const clearance = artworkSafetyClearance(position, [placement]);
        const required = artworkSafetyDistanceForPlacement(placement, maximumDistance);
        minimum = Math.min(minimum, clearance - required);
    }
    return minimum;
}

export function positionViolatesArtworkSafetyRule(position, placements = [], minimumDistance = 1) {
    return artworkSafetyMargin(position, placements, minimumDistance) < 0;
}

export function artworkSafetyMovementAllowed(from, to, placements = [], minimumDistance = 1) {
    // Evaluate every display independently. Comparing only the worst global
    // margin lets a visitor move out of one overlapping safety zone while
    // moving deeper into a neighbouring frame. For each frame, a valid point
    // may not enter its fixed one-metre zone; a stale point already inside may
    // only stay level or move outward so the visitor can always escape.
    return placements.every((placement) => {
        const required = artworkSafetyDistanceForPlacement(placement, minimumDistance);
        const previousMargin = artworkSafetyClearance(from, [placement]) - required;
        const nextMargin = artworkSafetyClearance(to, [placement]) - required;

        return nextMargin >= 0 || nextMargin + 0.002 >= previousMargin;
    });
}
