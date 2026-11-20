import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { Document, NodeIO } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import {
  copyToDocument,
  createDefaultPropertyResolver,
  dequantize,
  dedup,
  getBounds,
  meshopt,
  prune,
  weld,
} from '@gltf-transform/functions';
import { MeshoptDecoder, MeshoptEncoder } from 'meshoptimizer';
import validator from 'gltf-validator';

const args = Object.fromEntries(process.argv.slice(2).map((arg) => {
  const separator = arg.indexOf('=');
  if (!arg.startsWith('--') || separator < 3) throw new Error(`Invalid argument: ${arg}`);
  return [arg.slice(2, separator), arg.slice(separator + 1)];
}));

for (const required of ['input', 'output', 'spec', 'ktx-bin']) {
  if (!args[required]) throw new Error(`Missing --${required}=...`);
}

const inputPath = path.resolve(args.input);
const outputDirectory = path.resolve(args.output);
const specPath = path.resolve(args.spec);
const ktxBin = path.resolve(args['ktx-bin']);
const intermediatePath = path.join(outputDirectory, 'environment.meshopt.glb');
const ktxIntermediatePath = path.join(outputDirectory, 'environment.ktx2.glb');
const runtimePath = path.join(outputDirectory, 'environment.glb');
const collisionPath = path.join(outputDirectory, 'collision.glb');
const cliPath = path.resolve('../.tools/gltf-transform-4.4.2/node_modules/@gltf-transform/cli/bin/cli.js');

await mkdir(outputDirectory, { recursive: true });
await Promise.all([MeshoptDecoder.ready, MeshoptEncoder.ready]);

const io = new NodeIO()
  .registerExtensions(ALL_EXTENSIONS)
  .registerDependencies({
    'meshopt.decoder': MeshoptDecoder,
    'meshopt.encoder': MeshoptEncoder,
  });

const [sourceBytes, specBytes] = await Promise.all([readFile(inputPath), readFile(specPath)]);
const spec = JSON.parse(specBytes.toString('utf8'));
const sourceHash = sha256(sourceBytes);
const specificationHash = sha256(specBytes);
const sourceValidation = await validate(sourceBytes, path.basename(inputPath));
if ((sourceValidation.issues?.numErrors ?? 0) > 0) {
  throw new Error(`Source validation failed with ${sourceValidation.issues.numErrors} errors.`);
}

const document = await io.read(inputPath);
const root = document.getRoot();
const scene = root.getDefaultScene() ?? root.listScenes()[0];
if (!scene) throw new Error('The source GLB has no scene.');

const sourceStats = collectStats(document);
const structuralNodes = [];
const slotDrafts = [];

for (const node of [...root.listNodes()]) {
  const mesh = node.getMesh();
  if (!mesh) continue;
  const materialNames = mesh.listPrimitives()
    .map((primitive) => primitive.getMaterial()?.getName() ?? '')
    .filter(Boolean);

  if (materialNames.some((name) => spec.collision.included_materials.includes(name))) {
    structuralNodes.push({
      node,
      matrix: [...node.getWorldMatrix()],
      materialNames,
    });
  }

  const artworkMaterial = materialNames.find((name) => /^Image_[HV]_\d+$/i.test(name));
  if (artworkMaterial) {
    const bounds = getBounds(node);
    const size = bounds.max.map((value, index) => value - bounds.min[index]);
    const center = bounds.min.map((value, index) => value + size[index] / 2);
    const width = Math.max(size[0], size[2]);
    const height = size[1];
    const roomCode = center[0] < spec.slot_assignment.west_gallery_max_x ? 'west-gallery' : 'main-hall';
    const outward = outwardVector(center, spec.interior_anchor, size);
    const yaw = Math.atan2(outward[0], outward[2]);
    const quaternion = [0, Math.sin(yaw / 2), 0, Math.cos(yaw / 2)];
    const suffix = artworkMaterial.replace(/^Image_/i, '').replace('_', '-').toUpperCase();
    const prefix = roomCode === 'west-gallery' ? 'WEST' : 'MAIN';

    slotDrafts.push({
      name: `SLOT_${prefix}_${suffix.replace('-', '_')}`,
      translation: center.map((value, index) => +(value + outward[index] * spec.slot_assignment.outward_offset_m).toFixed(6)),
      rotation: quaternion.map((value) => +value.toFixed(8)),
      metadata: {
        type: 'artwork_slot',
        schema_version: spec.schema_version,
        slot_code: `${prefix}-${suffix}`,
        room_code: roomCode,
        max_width_m: +width.toFixed(3),
        max_height_m: +height.toFixed(3),
        min_width_m: spec.slot_assignment.minimum_width_m,
        min_height_m: spec.slot_assignment.minimum_height_m,
        allowed_orientations: orientation(width, height),
        frame_profile: 'standard',
        enabled_by_default: true,
        source_anchor: artworkMaterial,
      },
    });
    node.dispose();
  }
}

if (slotDrafts.length !== spec.slot_assignment.expected_slot_count) {
  throw new Error(`Expected ${spec.slot_assignment.expected_slot_count} authored display anchors; found ${slotDrafts.length}.`);
}
if (structuralNodes.length !== spec.collision.included_materials.length) {
  throw new Error(`Expected ${spec.collision.included_materials.length} structural collision nodes; found ${structuralNodes.length}.`);
}

for (const animation of [...root.listAnimations()]) animation.dispose();
for (const node of [...root.listNodes()]) {
  if (node.getSkin()) node.dispose();
  if (node.getCamera()) node.setCamera(null);
}
for (const camera of [...root.listCameras()]) camera.dispose();
for (const skin of [...root.listSkins()]) skin.dispose();

const originalChildren = [...scene.listChildren()];
const artinpaRoot = document.createNode('ARTINPA_ROOT').setExtras({
  artinpa: {
    type: 'environment_root',
    schema_version: spec.schema_version,
    environment_code: spec.environment_code,
    pipeline_revision: spec.pipeline_revision,
    coordinate_system: spec.coordinate_system,
    viewer_compatibility_version: spec.viewer_compatibility_version,
    source_sha256: sourceHash,
    specification_sha256: specificationHash,
    attribution: spec.license.attribution,
  },
});
const visibleRoot = document.createNode('ENV_VISIBLE').setExtras({ artinpa: { type: 'visible_root' } });
const collisionReferenceRoot = document.createNode('COLLISION_ROOT').setExtras({
  artinpa: { type: 'collision_root', separate_asset: 'collision.glb' },
});
const roomsRoot = document.createNode('ROOMS_ROOT').setExtras({ artinpa: { type: 'rooms_root' } });
const spawnsRoot = document.createNode('SPAWNS_ROOT').setExtras({ artinpa: { type: 'spawns_root' } });
const slotsRoot = document.createNode('SLOTS_ROOT').setExtras({ artinpa: { type: 'slots_root' } });

scene.addChild(artinpaRoot);
artinpaRoot.addChild(visibleRoot).addChild(collisionReferenceRoot).addChild(roomsRoot).addChild(spawnsRoot).addChild(slotsRoot);
for (const child of originalChildren) visibleRoot.addChild(child);

for (const room of spec.rooms) {
  const center = room.bounds.min.map((value, index) => (value + room.bounds.max[index]) / 2);
  const size = room.bounds.max.map((value, index) => value - room.bounds.min[index]);
  roomsRoot.addChild(document.createNode(`ROOM_${room.code.replaceAll('-', '_').toUpperCase()}`)
    .setTranslation(center)
    .setScale(size)
    .setExtras({
      artinpa: {
        type: 'room',
        schema_version: spec.schema_version,
        code: room.code,
        title: room.title,
        sort_order: room.sort_order,
        bounds: room.bounds,
      },
    }));
}

const yaw = spec.spawn.yaw_degrees * Math.PI / 180;
spawnsRoot.addChild(document.createNode('SPAWN_MAIN')
  .setTranslation(spec.spawn.translation)
  .setRotation([0, Math.sin(yaw / 2), 0, Math.cos(yaw / 2)])
  .setExtras({ artinpa: { type: 'spawn', schema_version: spec.schema_version, ...spec.spawn } }));

for (const slot of slotDrafts.sort((a, b) => a.metadata.slot_code.localeCompare(b.metadata.slot_code))) {
  slotsRoot.addChild(document.createNode(slot.name)
    .setTranslation(slot.translation)
    .setRotation(slot.rotation)
    .setExtras({ artinpa: slot.metadata }));
}

await document.transform(
  dedup(),
  prune({ keepLeaves: true, keepExtras: true }),
  weld(),
  meshopt({ encoder: MeshoptEncoder, level: 'medium' }),
);
await io.write(intermediatePath, document);

const collisionDocument = new Document();
const collisionScene = collisionDocument.createScene('ARTINPA_COLLISION_SCENE');
const collisionRoot = collisionDocument.createNode('ARTINPA_COLLISION_ROOT').setExtras({
  artinpa: {
    type: 'collision_root',
    schema_version: spec.schema_version,
    environment_code: spec.environment_code,
    source_sha256: sourceHash,
  },
});
collisionScene.addChild(collisionRoot);
const collisionSourceDocument = await io.read(inputPath);
const collisionStructuralNodes = [];
for (const node of collisionSourceDocument.getRoot().listNodes()) {
  const mesh = node.getMesh();
  if (!mesh) continue;
  const materialNames = mesh.listPrimitives()
    .map((primitive) => primitive.getMaterial()?.getName() ?? '')
    .filter(Boolean);
  if (materialNames.some((name) => spec.collision.included_materials.includes(name))) {
    collisionStructuralNodes.push({
      node,
      matrix: [...node.getWorldMatrix()],
      materialNames,
    });
  }
}
if (collisionStructuralNodes.length !== spec.collision.included_materials.length) {
  throw new Error(`Expected ${spec.collision.included_materials.length} collision source nodes; found ${collisionStructuralNodes.length}.`);
}
const resolver = createDefaultPropertyResolver(collisionDocument, collisionSourceDocument);
const copied = copyToDocument(
  collisionDocument,
  collisionSourceDocument,
  collisionStructuralNodes.map(({ node }) => node.getMesh()).filter(Boolean),
  resolver,
);

for (const source of collisionStructuralNodes) {
  const sourceMesh = source.node.getMesh();
  const mesh = sourceMesh ? copied.get(sourceMesh) : null;
  if (!mesh) throw new Error(`Unable to copy collision mesh ${source.node.getName()}.`);
  for (const primitive of mesh.listPrimitives()) {
    primitive.setMaterial(null);
    for (const semantic of primitive.listSemantics()) {
      if (semantic !== 'POSITION') primitive.setAttribute(semantic, null);
    }
  }
  const materialCode = source.materialNames[0].replace(/[^A-Za-z0-9]+/g, '_').toUpperCase();
  collisionRoot.addChild(collisionDocument.createNode(`COLLISION_${materialCode}`)
    .setMatrix(source.matrix)
    .setMesh(mesh)
    .setExtras({
      artinpa: {
        type: 'collision',
        schema_version: spec.schema_version,
        source_materials: source.materialNames,
      },
    }));
}

await collisionDocument.transform(
  dequantize(),
  prune({ keepLeaves: true, keepExtras: true, keepAttributes: false }),
  weld(),
);
await io.write(collisionPath, collisionDocument);

const command = spawnSync(process.execPath, [
  cliPath,
  'etc1s',
  intermediatePath,
  ktxIntermediatePath,
  '--quality', '200',
  '--jobs', '1',
  '--verbose',
], {
  cwd: process.cwd(),
  encoding: 'utf8',
  env: {
    ...process.env,
    PATH: `${path.dirname(ktxBin)};C:\\Windows\\System32;${process.env.PATH ?? ''}`,
  },
});
if (command.status !== 0) {
  throw new Error(`KTX2 conversion failed: ${command.stderr || command.stdout}`);
}

const ktxDocument = await io.read(ktxIntermediatePath);
await ktxDocument.transform(meshopt({ encoder: MeshoptEncoder, level: 'medium' }));
await io.write(runtimePath, ktxDocument);

const [runtimeBytes, collisionBytes] = await Promise.all([readFile(runtimePath), readFile(collisionPath)]);
const [runtimeValidation, collisionValidation] = await Promise.all([
  validate(runtimeBytes, path.basename(runtimePath)),
  validate(collisionBytes, path.basename(collisionPath)),
]);
if ((runtimeValidation.issues?.numErrors ?? 0) > 0 || (collisionValidation.issues?.numErrors ?? 0) > 0) {
  throw new Error('A Phase 2 runtime output failed Khronos validation.');
}

const runtimeDocument = await io.read(runtimePath);
const collisionRuntimeDocument = await io.read(collisionPath);
const runtimeStats = collectStats(runtimeDocument);
const collisionStats = collectStats(collisionRuntimeDocument);
const metadata = collectMetadata(runtimeDocument);
validateMetadata(metadata, spec);

const reports = {
  source_validation: sourceValidation,
  runtime_validation: runtimeValidation,
  collision_validation: collisionValidation,
  stats: {
    source: sourceStats,
    runtime: runtimeStats,
    collision: collisionStats,
  },
  optimization: {
    pipeline: 'Art INPA VE-03 Phase 2 / glTF Transform 4.4.2 / KTX-Software 4.4.2',
    removed_authored_artwork_planes: slotDrafts.length,
    removed_animations: sourceStats.animations,
    removed_skins: sourceStats.skins,
    removed_cameras: sourceStats.cameras,
    meshopt: true,
    ktx2: true,
    texture_codec: 'ETC1S',
    ktx_jobs: 1,
    source_bytes: sourceBytes.byteLength,
    runtime_bytes: runtimeBytes.byteLength,
    collision_bytes: collisionBytes.byteLength,
    collision_geometry_compression: spec.collision.geometry_compression,
    source_sha256: sourceHash,
    specification_sha256: specificationHash,
    runtime_sha256: sha256(runtimeBytes),
    collision_sha256: sha256(collisionBytes),
  },
  metadata,
  license: spec.license,
};

for (const [name, value] of Object.entries(reports)) {
  await writeFile(path.join(outputDirectory, `${name.replaceAll('_', '-')}.json`), `${JSON.stringify(value, null, 2)}\n`);
}

await writeFile(path.join(outputDirectory, 'runtime-manifest.json'), `${JSON.stringify({
  schema_version: spec.schema_version,
  environment_code: spec.environment_code,
  viewer_compatibility_version: spec.viewer_compatibility_version,
  source: { filename: path.basename(inputPath), bytes: sourceBytes.byteLength, sha256: sourceHash },
  runtime: { filename: 'environment.glb', bytes: runtimeBytes.byteLength, sha256: sha256(runtimeBytes) },
  collision: { filename: 'collision.glb', bytes: collisionBytes.byteLength, sha256: sha256(collisionBytes) },
  rooms: metadata.rooms,
  slots: metadata.slots,
  spawn: metadata.spawns[0],
  license: spec.license,
}, null, 2)}\n`);

console.log(JSON.stringify({
  ok: true,
  output: outputDirectory,
  runtime_sha256: sha256(runtimeBytes),
  collision_sha256: sha256(collisionBytes),
  runtime_bytes: runtimeBytes.byteLength,
  collision_bytes: collisionBytes.byteLength,
  rooms: metadata.rooms.length,
  slots: metadata.slots.length,
  collision_triangles: collisionStats.triangles,
  runtime_errors: runtimeValidation.issues?.numErrors ?? 0,
  collision_errors: collisionValidation.issues?.numErrors ?? 0,
}));

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

async function validate(bytes, uri) {
  return validator.validateBytes(new Uint8Array(bytes), {
    uri,
    format: 'glb',
    maxIssues: 10000,
  });
}

function orientation(width, height) {
  const ratio = width / height;
  if (ratio > 1.12) return ['landscape'];
  if (ratio < 0.88) return ['portrait'];
  return ['square'];
}

function outwardVector(center, interior, size) {
  const axis = size[0] < 0.001 ? 0 : 2;
  const direction = interior[axis] >= center[axis] ? 1 : -1;
  return axis === 0 ? [direction, 0, 0] : [0, 0, direction];
}

function collectStats(document) {
  const root = document.getRoot();
  let triangles = 0;
  let vertices = 0;
  let primitives = 0;
  for (const mesh of root.listMeshes()) {
    for (const primitive of mesh.listPrimitives()) {
      primitives++;
      const position = primitive.getAttribute('POSITION');
      const indices = primitive.getIndices();
      vertices += position?.getCount() ?? 0;
      triangles += Math.floor((indices?.getCount() ?? position?.getCount() ?? 0) / 3);
    }
  }
  const extensionsUsed = root.listExtensionsUsed().map((extension) => extension.extensionName).sort();
  const extensionsRequired = root.listExtensionsRequired().map((extension) => extension.extensionName).sort();
  return {
    scenes: root.listScenes().length,
    nodes: root.listNodes().length,
    meshes: root.listMeshes().length,
    primitives,
    vertices,
    triangles,
    materials: root.listMaterials().length,
    textures: root.listTextures().length,
    ktx2_textures: root.listTextures().filter((texture) => texture.getMimeType() === 'image/ktx2').length,
    animations: root.listAnimations().length,
    skins: root.listSkins().length,
    cameras: root.listCameras().length,
    extensions_used: extensionsUsed,
    extensions_required: extensionsRequired,
  };
}

function collectMetadata(document) {
  const result = { roots: [], rooms: [], slots: [], spawns: [], collisions: [] };
  for (const node of document.getRoot().listNodes()) {
    const data = node.getExtras()?.artinpa;
    if (!data || typeof data !== 'object') continue;
    const record = {
      name: node.getName(),
      translation: [...node.getWorldTranslation()].map((value) => +value.toFixed(6)),
      rotation: [...node.getWorldRotation()].map((value) => +value.toFixed(8)),
      ...data,
    };
    if (data.type === 'room') result.rooms.push(record);
    else if (data.type === 'artwork_slot') result.slots.push(record);
    else if (data.type === 'spawn') result.spawns.push(record);
    else if (data.type === 'collision') result.collisions.push(record);
    else result.roots.push(record);
  }
  result.rooms.sort((a, b) => a.sort_order - b.sort_order);
  result.slots.sort((a, b) => a.slot_code.localeCompare(b.slot_code));
  return result;
}

function validateMetadata(metadata, spec) {
  const requiredRoots = ['ARTINPA_ROOT', 'ENV_VISIBLE', 'COLLISION_ROOT', 'ROOMS_ROOT', 'SPAWNS_ROOT', 'SLOTS_ROOT'];
  const rootNames = new Set(metadata.roots.map((record) => record.name));
  for (const name of requiredRoots) {
    if (!rootNames.has(name)) throw new Error(`Missing required root node ${name}.`);
  }
  if (metadata.spawns.length !== 1) throw new Error('Exactly one Phase 2 spawn is required.');
  const roomCodes = new Set(metadata.rooms.map((room) => room.code));
  if (roomCodes.size !== spec.rooms.length) throw new Error('Room metadata is incomplete or duplicated.');
  const slotCodes = new Set(metadata.slots.map((slot) => slot.slot_code));
  if (slotCodes.size !== metadata.slots.length || slotCodes.size !== spec.slot_assignment.expected_slot_count) {
    throw new Error('Slot codes are incomplete or duplicated.');
  }
  for (const slot of metadata.slots) {
    if (!roomCodes.has(slot.room_code)) throw new Error(`Slot ${slot.slot_code} references an invalid room.`);
    if (slot.max_width_m < slot.min_width_m || slot.max_height_m < slot.min_height_m) {
      throw new Error(`Slot ${slot.slot_code} has invalid physical dimensions.`);
    }
  }
  if (!roomCodes.has(metadata.spawns[0].room_code)) throw new Error('The spawn references an invalid room.');
}
