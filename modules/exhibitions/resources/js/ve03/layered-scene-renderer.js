export function renderLayeredScene(renderer, scene, camera) {
    const previousMask = camera.layers.mask;
    const previousBackground = scene.background;
    const previousAutoClear = renderer.autoClear;

    try {
        camera.layers.set(0);
        renderer.autoClear = true;
        renderer.render(scene, camera);

        camera.layers.set(1);
        renderer.autoClear = false;
        scene.background = null;
        renderer.render(scene, camera);
    } finally {
        scene.background = previousBackground;
        renderer.autoClear = previousAutoClear;
        camera.layers.mask = previousMask;
    }
}
