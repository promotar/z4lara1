export function detectCapabilities(canvas, environment = globalThis) {
    const context = canvas?.getContext?.('webgl2', {
        alpha: false,
        antialias: false,
        powerPreference: 'high-performance',
        preserveDrawingBuffer: false,
    }) ?? null;
    if (!context) {
        return { supported: false, reason: 'webgl2_unavailable', context: null };
    }

    const media = environment.matchMedia?.bind(environment);
    return {
        supported: true,
        reason: null,
        context,
        maximum_texture_size: Number(context.getParameter(context.MAX_TEXTURE_SIZE) || 0),
        screen_width: Number(environment.screen?.width || 0),
        screen_height: Number(environment.screen?.height || 0),
        pixel_ratio: Number(environment.devicePixelRatio || 1),
        touch: Number(environment.navigator?.maxTouchPoints || 0) > 0 || Boolean(media?.('(pointer: coarse)').matches),
        memory: Number(environment.navigator?.deviceMemory || 4),
        cores: Number(environment.navigator?.hardwareConcurrency || 4),
        reduced_motion: Boolean(media?.('(prefers-reduced-motion: reduce)').matches),
    };
}

export function capabilityProfile(capabilities) {
    return {
        mobile: capabilities.touch,
        memory: capabilities.memory,
        cores: capabilities.cores,
        reducedMotion: capabilities.reduced_motion,
    };
}
