import * as THREE from 'three';

const UP = new THREE.Vector3(0, 1, 0);

export function rankSpawnSamples(samples, preferredY = 0) {
    return [...samples]
        .filter((sample) => sample.clearance >= 1.9 && sample.upDot >= 0.65)
        .sort((left, right) => {
            const leftScore = Math.abs(left.y - preferredY) * 2 + left.centerDistance;
            const rightScore = Math.abs(right.y - preferredY) * 2 + right.centerDistance;
            return leftScore - rightScore;
        });
}

export function findTechnicalSpawn(scene, bounds, { meshes: suppliedMeshes } = {}) {
    const meshes = suppliedMeshes ? [...suppliedMeshes] : [];
    if (!suppliedMeshes) {
        scene.traverse((object) => {
            if (object.isMesh && !object.isSkinnedMesh) meshes.push(object);
        });
    }

    const center = bounds.getCenter(new THREE.Vector3());
    const size = bounds.getSize(new THREE.Vector3());
    const fractions = [0, -0.12, 0.12, -0.24, 0.24];
    const samples = [];
    const down = new THREE.Raycaster();
    const up = new THREE.Raycaster();

    for (const fx of fractions) {
        for (const fz of fractions) {
            const x = center.x + size.x * fx;
            const z = center.z + size.z * fz;
            down.set(new THREE.Vector3(x, bounds.max.y + 2, z), new THREE.Vector3(0, -1, 0));
            const hits = down.intersectObjects(meshes, false);

            for (const hit of hits) {
                if (!hit.face) continue;
                const normal = hit.face.normal.clone().transformDirection(hit.object.matrixWorld);
                const upDot = normal.dot(UP);
                if (upDot < 0.65) continue;

                up.set(hit.point.clone().addScaledVector(UP, 0.05), UP);
                const ceiling = up.intersectObjects(meshes, false).find((candidate) => candidate.distance > 0.05);
                samples.push({
                    x,
                    y: hit.point.y,
                    z,
                    upDot,
                    clearance: ceiling ? ceiling.distance : Number.POSITIVE_INFINITY,
                    centerDistance: Math.hypot(x - center.x, z - center.z),
                });
            }
        }
    }

    const ranked = rankSpawnSamples(samples, 0);
    if (ranked.length === 0) throw new Error('No temporary floor point with safe capsule clearance could be derived.');

    return ranked[0];
}
