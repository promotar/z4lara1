import { readFile } from 'node:fs/promises';
import { NodeIO } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { MeshoptDecoder } from 'meshoptimizer';
import { Matrix4, Quaternion, Ray, Triangle, Vector2, Vector3 } from 'three';

const [sourcePath, metadataPath] = process.argv.slice(2);
if (!sourcePath || !metadataPath) {
  throw new Error('Usage: node locate-wall-display-uvs.mjs <source.glb> <metadata.json>');
}

await MeshoptDecoder.ready;
const io = new NodeIO()
  .registerExtensions(ALL_EXTENSIONS)
  .registerDependencies({ 'meshopt.decoder': MeshoptDecoder });
const document = await io.read(sourcePath);
const metadata = JSON.parse(await readFile(metadataPath, 'utf8'));
const wallNodes = document.getRoot().listNodes().filter((node) => node.getMesh()?.listPrimitives()
  .some((primitive) => primitive.getMaterial()?.getName() === 'WallBaked'));

const results = metadata.slots.map((slot) => {
  const origin = new Vector3(...slot.translation);
  const outward = new Vector3(0, 0, 1).applyQuaternion(new Quaternion(...slot.rotation)).normalize();
  const ray = new Ray(origin.clone().addScaledVector(outward, 0.25), outward.clone().negate());
  let nearest = null;

  for (const node of wallNodes) {
    const matrix = new Matrix4().fromArray(node.getWorldMatrix());
    for (const primitive of node.getMesh().listPrimitives()) {
      if (primitive.getMaterial()?.getName() !== 'WallBaked') continue;
      const position = primitive.getAttribute('POSITION');
      const texcoord = primitive.getAttribute('TEXCOORD_0');
      const indices = primitive.getIndices();
      if (!position || !texcoord) continue;
      const count = indices ? indices.getCount() : position.getCount();
      for (let index = 0; index < count; index += 3) {
        const ia = indices ? indices.getScalar(index) : index;
        const ib = indices ? indices.getScalar(index + 1) : index + 1;
        const ic = indices ? indices.getScalar(index + 2) : index + 2;
        const a = new Vector3(...position.getElement(ia, [])).applyMatrix4(matrix);
        const b = new Vector3(...position.getElement(ib, [])).applyMatrix4(matrix);
        const c = new Vector3(...position.getElement(ic, [])).applyMatrix4(matrix);
        const hit = ray.intersectTriangle(a, b, c, false, new Vector3());
        if (!hit) continue;
        const distance = ray.origin.distanceTo(hit);
        const score = Math.abs(distance - 0.285);
        if (distance > 0.6 || (nearest && nearest.score <= score)) continue;
        const barycentric = new Vector3();
        Triangle.getBarycoord(hit, a, b, c, barycentric);
        const ua = new Vector2(...texcoord.getElement(ia, []));
        const ub = new Vector2(...texcoord.getElement(ib, []));
        const uc = new Vector2(...texcoord.getElement(ic, []));
        nearest = {
          distance,
          score,
          uv: ua.multiplyScalar(barycentric.x)
            .add(ub.multiplyScalar(barycentric.y))
            .add(uc.multiplyScalar(barycentric.z)),
        };
      }
    }
  }

  return {
    slot_code: slot.slot_code,
    max_width_m: slot.metadata?.max_width_m,
    max_height_m: slot.metadata?.max_height_m,
    uv: nearest ? [nearest.uv.x, nearest.uv.y] : null,
    distance: nearest?.distance ?? null,
  };
});

process.stdout.write(`${JSON.stringify(results, null, 2)}\n`);
