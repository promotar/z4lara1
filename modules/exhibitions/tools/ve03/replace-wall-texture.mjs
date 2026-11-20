import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { NodeIO } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { MeshoptDecoder } from 'meshoptimizer';

const [sourcePath, texturePath, outputPath] = process.argv.slice(2);
if (!sourcePath || !texturePath || !outputPath) {
  throw new Error('Usage: node replace-wall-texture.mjs <source.glb> <wall.jpg> <output.glb>');
}

await MeshoptDecoder.ready;
const io = new NodeIO()
  .registerExtensions(ALL_EXTENSIONS)
  .registerDependencies({ 'meshopt.decoder': MeshoptDecoder });
const document = await io.read(sourcePath);
const wallMaterial = document.getRoot().listMaterials().find((material) => material.getName() === 'WallBaked');
const wallTexture = wallMaterial?.getBaseColorTexture();
if (!wallTexture) throw new Error('WallBaked base-color texture was not found.');
wallTexture.setImage(await readFile(texturePath)).setMimeType('image/jpeg');
await mkdir(path.dirname(outputPath), { recursive: true });
await io.write(outputPath, document);
