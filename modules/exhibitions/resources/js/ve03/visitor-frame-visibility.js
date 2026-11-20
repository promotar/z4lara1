function materialNames(material) {
    return (Array.isArray(material) ? material : [material])
        .filter(Boolean)
        .map((entry) => String(entry.name || '').trim())
        .filter(Boolean);
}

/**
 * The authored GLB stores decorative frames in Picture_Frame and the fourteen
 * default black artwork planes in Image_H_* / Image_V_* materials. Both layers
 * are hidden in every mode. Published artwork is rendered dynamically, while
 * artist placement mode adds only the interactive slot outlines.
 */
export function hideVisitorEmptyFrameMeshes(environment, hiddenMaterialNames = [], _placements = [], _placementMode = false) {
    if (!environment?.traverse) return 0;

    const configuredNames = new Set(
        (hiddenMaterialNames || []).map((name) => String(name).trim().toLowerCase()).filter(Boolean),
    );
    let count = 0;

    environment.traverse((object) => {
        if (!object?.isMesh) return;
        const names = materialNames(object.material);
        const authoredFrameLayer = names.some((name) => {
            const normalized = name.toLowerCase();
            return configuredNames.has(normalized)
                || /picture[_\s-]*frame/i.test(name)
                || /^image_[hv]_\d+/i.test(name);
        });
        if (!authoredFrameLayer) return;

        object.visible = false;
        object.userData.ve03VisitorHiddenEmptyFrames = true;
        count++;
    });

    return count;
}
