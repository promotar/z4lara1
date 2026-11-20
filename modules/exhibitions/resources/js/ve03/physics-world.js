import RAPIER from '@dimforge/rapier3d-compat';
import * as THREE from 'three';
import { selectTechnicalCollisionMeshes } from './collision-policy.js';

const PLAYER = Object.freeze({
    radius: 0.30,
    bodyHeight: 1.70,
    cameraHeight: 1.62,
    walkSpeed: 2.2,
    gravity: -9.81,
    maxStep: 0.28,
    maxSlope: Math.PI * 42 / 180,
    wallComfortDistance: 0.75,
});

let initialization;

export function initializeRapier() {
    initialization ??= RAPIER.init();
    return initialization;
}

export function clampMovementVector(input = {}) {
    const forward = Number(input.forward || 0);
    const strafe = Number(input.strafe || 0);
    const length = Math.hypot(forward, strafe);
    if (length === 0) return { forward: 0, strafe: 0 };
    const scale = length > 1 ? 1 / length : 1;
    return { forward: forward * scale, strafe: strafe * scale };
}

function meshColliderData(mesh) {
    const geometry = mesh.userData?.ve03ArtworkSafety
        && Number(mesh.userData.physicsWidth) > Number(mesh.geometry?.parameters?.width || 0)
        ? new THREE.BoxGeometry(
            Number(mesh.userData.physicsWidth),
            Number(mesh.geometry?.parameters?.height || 0),
            Number(mesh.geometry?.parameters?.depth || 0),
        )
        : mesh.geometry;
    const position = geometry?.getAttribute('position');
    if (!position || position.count < 3) return null;

    const vertices = new Float32Array(position.count * 3);
    const point = new THREE.Vector3();
    for (let index = 0; index < position.count; index += 1) {
        point.fromBufferAttribute(position, index).applyMatrix4(mesh.matrixWorld);
        vertices[index * 3] = point.x;
        vertices[index * 3 + 1] = point.y;
        vertices[index * 3 + 2] = point.z;
    }

    const sourceIndex = geometry.getIndex();
    const indices = sourceIndex
        ? Uint32Array.from(sourceIndex.array)
        : Uint32Array.from({ length: position.count }, (_, index) => index);

    const data = indices.length >= 3 ? { vertices, indices } : null;
    if (geometry !== mesh.geometry) geometry.dispose();

    return data;
}

export class TechnicalPhysicsWorld {
    constructor(scene, spawn, bounds, { collisionMeshes, player = {} } = {}) {
        this.scene = scene;
        this.spawn = { ...spawn };
        this.bounds = bounds.clone();
        this.collisionMeshes = collisionMeshes || selectTechnicalCollisionMeshes(scene);
        this.player = Object.freeze({ ...PLAYER, ...player });
        this.world = new RAPIER.World({ x: 0, y: this.player.gravity, z: 0 });
        this.world.timestep = 1 / 60;
        this.environmentColliderCount = 0;
        this.environmentTriangleCount = 0;
        this.verticalVelocity = 0;
        this.grounded = false;
        this.fallRecoveries = 0;
        this.edgeFallPreventions = 0;
        this.collisionFrames = 0;
        this.blockingCollisionFrames = 0;
        this.wallComfortColliderHandles = new Set();
        this.supportColliderHandles = new Set();
        this.createEnvironmentColliders();
        this.createPlayer();
    }

    createEnvironmentColliders() {
        this.scene.updateMatrixWorld(true);
        const body = this.world.createRigidBody(RAPIER.RigidBodyDesc.fixed());

        for (const object of this.collisionMeshes) {
            const data = meshColliderData(object);
            if (!data) continue;

            const description = RAPIER.ColliderDesc.trimesh(data.vertices, data.indices)
                .setFriction(0.8)
                .setRestitution(0);
            const collider = this.world.createCollider(description, body);
            this.supportColliderHandles.add(collider.handle);
            if (!object.userData?.ve03ArtworkSafety) {
                this.wallComfortColliderHandles.add(collider.handle);
            }
            this.environmentColliderCount += 1;
            this.environmentTriangleCount += Math.floor(data.indices.length / 3);
        }

        if (this.environmentColliderCount === 0) {
            throw new Error('The normalized candidate produced no usable temporary collision mesh.');
        }
    }

    createPlayer() {
        const halfHeight = (this.player.bodyHeight - this.player.radius * 2) / 2;
        const centerY = this.spawn.y + this.player.bodyHeight / 2 + 0.03;
        this.body = this.world.createRigidBody(
            RAPIER.RigidBodyDesc.kinematicPositionBased().setTranslation(this.spawn.x, centerY, this.spawn.z),
        );
        this.collider = this.world.createCollider(
            RAPIER.ColliderDesc.capsule(halfHeight, this.player.radius).setFriction(0).setRestitution(0),
            this.body,
        );
        this.lastSafeTranslation = { x: this.spawn.x, y: centerY, z: this.spawn.z };
        this.controller = this.world.createCharacterController(0.01);
        this.controller.enableAutostep(this.player.maxStep, 0.18, false);
        this.controller.enableSnapToGround(0.25);
        this.controller.setMaxSlopeClimbAngle(this.player.maxSlope);
        this.controller.setMinSlopeSlideAngle(Math.PI * 46 / 180);
        this.controller.setSlideEnabled(true);
    }

    wallComfortMovement(horizontal, current) {
        const length = Math.hypot(horizontal.x, horizontal.z);
        const comfortDistance = Math.max(this.player.radius, Number(this.player.wallComfortDistance || 0));
        if (length <= 0 || comfortDistance <= this.player.radius) return horizontal;

        const direction = { x: horizontal.x / length, y: 0, z: horizontal.z / length };
        const cameraY = current.y - this.player.bodyHeight / 2 + this.player.cameraHeight;
        const hit = this.world.castRayAndGetNormal(
            new RAPIER.Ray({ x: current.x, y: cameraY - 0.45, z: current.z }, direction),
            comfortDistance * 4 + length,
            true,
            undefined,
            undefined,
            this.collider,
            this.body,
            (collider) => this.wallComfortColliderHandles.has(collider.handle),
        );
        if (!hit || Math.abs(hit.normal.y) >= 0.5) return horizontal;

        const normalLength = Math.hypot(hit.normal.x, hit.normal.z);
        if (normalLength <= 0) return horizontal;
        const normal = { x: hit.normal.x / normalLength, z: hit.normal.z / normalLength };
        const inward = -(horizontal.x * normal.x + horizontal.z * normal.z);
        if (inward <= 0) return horizontal;

        const rayIntoWall = Math.max(0, -(direction.x * normal.x + direction.z * normal.z));
        const allowedInward = Math.max(0, hit.timeOfImpact * rayIntoWall - comfortDistance);
        const correction = Math.max(0, inward - allowedInward);

        return {
            x: horizontal.x + normal.x * correction,
            z: horizontal.z + normal.z * correction,
        };
    }

    hasFloorSupport(translation) {
        const footY = translation.y - this.player.bodyHeight / 2;
        const probeHeight = this.player.maxStep + 0.12;
        const probeDistance = this.player.maxStep * 2 + 0.32;
        const offset = this.player.radius * 0.65;

        return [
            [0, 0],
            [offset, 0],
            [-offset, 0],
            [0, offset],
            [0, -offset],
        ].some(([x, z]) => Boolean(this.world.castRay(
            new RAPIER.Ray(
                { x: translation.x + x, y: footY + probeHeight, z: translation.z + z },
                { x: 0, y: -1, z: 0 },
            ),
            probeDistance,
            true,
            undefined,
            undefined,
            this.collider,
            this.body,
            (collider) => this.supportColliderHandles.has(collider.handle),
        )));
    }

    step(input, yaw, fixedDelta = 1 / 60) {
        const { forward: localForward, strafe: localStrafe } = clampMovementVector(input);
        const sin = Math.sin(yaw);
        const cos = Math.cos(yaw);
        let horizontal = {
            x: (localStrafe * cos - localForward * sin) * this.player.walkSpeed * fixedDelta,
            z: (localStrafe * sin - localForward * cos) * this.player.walkSpeed * fixedDelta,
        };
        const current = this.body.translation();
        const wasGrounded = this.grounded;
        horizontal = this.wallComfortMovement(horizontal, current);

        this.verticalVelocity = this.grounded && this.verticalVelocity < 0
            ? -0.5
            : Math.max(-20, this.verticalVelocity + this.player.gravity * fixedDelta);
        this.controller.computeColliderMovement(this.collider, {
            x: horizontal.x,
            y: this.verticalVelocity * fixedDelta,
            z: horizontal.z,
        });

        const movement = this.controller.computedMovement();
        this.grounded = this.controller.computedGrounded();

        const nextTranslation = {
            x: current.x + movement.x,
            y: current.y + movement.y,
            z: current.z + movement.z,
        };

        // A walkable hall must never let the visitor enter an unmodelled seam or
        // open floor edge. Five downward probes cover the capsule footprint, so
        // narrow authored seams and legitimate stairs remain traversable.
        if (wasGrounded
            && Math.hypot(horizontal.x, horizontal.z) > 0
            && !this.hasFloorSupport(nextTranslation)) {
            const safe = this.lastSafeTranslation;
            this.body.setTranslation({ x: safe.x, y: safe.y, z: safe.z }, true);
            this.body.setNextKinematicTranslation({ x: safe.x, y: safe.y, z: safe.z });
            this.verticalVelocity = 0;
            this.grounded = true;
            this.edgeFallPreventions += 1;
            return this.cameraPosition();
        }
        if (this.grounded && movement.y >= -0.03) this.verticalVelocity = 0;
        const collisionCount = this.controller.numComputedCollisions();
        if (collisionCount > 0) this.collisionFrames += 1;
        for (let index = 0; index < collisionCount; index += 1) {
            const collision = this.controller.computedCollision(index);
            if (collision && Math.abs(collision.normal1.y) < 0.5) {
                this.blockingCollisionFrames += 1;
                break;
            }
        }

        this.body.setNextKinematicTranslation(nextTranslation);
        this.world.step();

        const position = this.body.translation();
        if (this.grounded && this.hasFloorSupport(position)) {
            this.lastSafeTranslation = { x: position.x, y: position.y, z: position.z };
        }
        if (position.y < this.bounds.min.y - 3) this.recoverFromFall();
        return this.cameraPosition();
    }

    cameraPosition() {
        const position = this.body.translation();
        return {
            x: position.x,
            y: position.y - this.player.bodyHeight / 2 + this.player.cameraHeight,
            z: position.z,
        };
    }

    recoverFromFall() {
        const centerY = this.spawn.y + this.player.bodyHeight / 2 + 0.03;
        this.body.setTranslation({ x: this.spawn.x, y: centerY, z: this.spawn.z }, true);
        this.body.setNextKinematicTranslation({ x: this.spawn.x, y: centerY, z: this.spawn.z });
        this.verticalVelocity = 0;
        this.lastSafeTranslation = { x: this.spawn.x, y: centerY, z: this.spawn.z };
        this.fallRecoveries += 1;
    }

    setCameraPosition(position) {
        const centerY = position.y - this.player.cameraHeight + this.player.bodyHeight / 2;
        const translation = { x: position.x, y: centerY, z: position.z };
        this.body.setTranslation(translation, true);
        this.body.setNextKinematicTranslation(translation);
        this.verticalVelocity = 0;
        if (this.hasFloorSupport(translation)) this.lastSafeTranslation = { ...translation };
    }

    acceptsCameraPosition(position) {
        const centerY = position.y - this.player.cameraHeight + this.player.bodyHeight / 2;
        const candidate = { x: position.x, y: centerY, z: position.z };
        const overlap = this.world.intersectionWithShape(
            candidate,
            { x: 0, y: 0, z: 0, w: 1 },
            this.collider.shape,
            undefined,
            undefined,
            this.collider,
            this.body,
        );

        if (overlap !== null) return false;

        const shoulderY = position.y - 0.45;
        const wallClearance = this.player.radius + 0.12;
        for (const direction of [
            { x: 1, y: 0, z: 0 },
            { x: -1, y: 0, z: 0 },
            { x: 0, y: 0, z: 1 },
            { x: 0, y: 0, z: -1 },
        ]) {
            const hit = this.world.castRay(
                new RAPIER.Ray({ x: position.x, y: shoulderY, z: position.z }, direction),
                wallClearance,
                true,
                undefined,
                undefined,
                this.collider,
                this.body,
            );
            if (hit) return false;
        }

        return true;
    }

    diagnostics() {
        return {
            engine: 'Rapier 3D 0.19.3',
            temporary_colliders: this.environmentColliderCount,
            temporary_collision_triangles: this.environmentTriangleCount,
            grounded: this.grounded,
            contact_frames: this.collisionFrames,
            blocking_collision_frames: this.blockingCollisionFrames,
            fall_recoveries: this.fallRecoveries,
            edge_fall_preventions: this.edgeFallPreventions,
            player: this.player,
        };
    }

    dispose() {
        this.controller?.free();
        this.world?.free();
    }
}
