import { copyFile, mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

const source = resolve('node_modules/three/examples/jsm/libs/basis');
const target = resolve('modules/exhibitions/resources/private-assets/ve03/basis');
await mkdir(target, { recursive: true });
for (const filename of ['basis_transcoder.js', 'basis_transcoder.wasm']) {
    await copyFile(resolve(source, filename), resolve(target, filename));
}
console.log(JSON.stringify({ ok: true, target, files: ['basis_transcoder.js', 'basis_transcoder.wasm'] }));
