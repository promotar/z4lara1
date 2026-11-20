import * as THREE from 'three';

const COLLISION_MATERIAL_PATTERN = /(collision|floor|ground|wall|wood|stair|step|teras|terrace|glass)/i;
const SPAWN_MATERIAL_PATTERN = /(floor|ground)/i;
const DECORATIVE_MATERIAL_PATTERN = /(^image(?:[_\s-]|$)|picture|frame|deco|lamp|sky|background|environment)/i;

function materialNames(mesh) {
    const materials = Array.isArray(mesh.material) ? mesh.material : [mesh.material];
    return materials
        .map((material) => String(material?.name || '').trim())
        .filter(Boolean);
}

export function collisionRole(mesh) {
    if (!mesh?.isMesh || mesh.isSkinnedMesh || mesh.userData?.ve03Artwork) return 'excluded';
    if (mesh.userData?.ve03Collision === false) return 'excluded';
    if (mesh.userData?.ve03Collision === true) return 'structural';

    const names = materialNames(mesh);
    if (names.some((name) => DECORATIVE_MATERIAL_PATTERN.test(name))) return 'decorative';
    if (names.some((name) => COLLISION_MATERIAL_PATTERN.test(name))) return 'structural';
    return 'unknown';
}

export function selectTechnicalCollisionMeshes(scene) {
    const structural = [];
    const fallback = [];

    scene.traverse((object) => {
        const role = collisionRole(object);
        if (role === 'structural') structural.push(object);
        if (role === 'unknown') fallback.push(object);
    });

    return structural.length > 0 ? structural : fallback;
}

export function selectTechnicalSpawnMeshes(collisionMeshes) {
    const floors = collisionMeshes.filter((mesh) => materialNames(mesh)
        .some((name) => SPAWN_MATERIAL_PATTERN.test(name)));

    return floors.length > 0 ? floors : collisionMeshes;
}

export function boundsForMeshes(meshes) {
    const bounds = new THREE.Box3();
    for (const mesh of meshes) bounds.union(new THREE.Box3().setFromObject(mesh));
    if (bounds.isEmpty()) throw new Error('The candidate produced no usable structural collision bounds.');
    return bounds;
}

export function collisionSelectionDiagnostics(scene, collisionMeshes) {
    const selected = new Set(collisionMeshes);
    const diagnostics = {
        selected_meshes: collisionMeshes.length,
        excluded_meshes: 0,
        selected_materials: new Set(),
        excluded_materials: new Set(),
    };

    scene.traverse((object) => {
        if (!object.isMesh || object.isSkinnedMesh || object.userData?.ve03Artwork) return;
        const target = selected.has(object) ? diagnostics.selected_materials : diagnostics.excluded_materials;
        for (const name of materialNames(object)) target.add(name || '(unnamed)');
        if (!selected.has(object)) diagnostics.excluded_meshes += 1;
    });

    return {
        selected_meshes: diagnostics.selected_meshes,
        excluded_meshes: diagnostics.excluded_meshes,
        selected_materials: [...diagnostics.selected_materials].sort(),
        excluded_materials: [...diagnostics.excluded_materials].sort(),
    };
}
