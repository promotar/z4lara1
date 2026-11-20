export function chooseQualityTier({ mobile = false, memory = 8, cores = 8, reducedMotion = false } = {}) {
    if (mobile || memory <= 4 || cores <= 4) {
        // Keep authored room geometry visible on every automatic quality tier.
        // Distance-only room culling can expose the dark scene background
        // through a doorway until the visitor crosses the culling margin.
        return Object.freeze({ name: 'mobile', level: 'low', pixelRatio: 1, antialias: false, maxFps: 45, shadows: false, anisotropy: 2, aggressiveRoomCulling: false });
    }

    if (reducedMotion || memory <= 8 || cores <= 8) {
        return Object.freeze({ name: 'balanced', level: 'medium', pixelRatio: 1.25, antialias: true, maxFps: 60, shadows: false, anisotropy: 4, aggressiveRoomCulling: false });
    }

    return Object.freeze({ name: 'desktop', level: 'high', pixelRatio: 1.5, antialias: true, maxFps: 60, shadows: false, anisotropy: 8, aggressiveRoomCulling: false });
}

const QUALITY_RANK = Object.freeze({ low: 0, medium: 1, high: 2 });

export function chooseRequestedQualityTier(profile, requested = 'auto') {
    const automatic = chooseQualityTier(profile);
    if (requested === 'auto' || !(requested in QUALITY_RANK) || QUALITY_RANK[requested] >= QUALITY_RANK[automatic.level]) return automatic;
    if (requested === 'low') return chooseQualityTier({ mobile: true, memory: 4, cores: 4, reducedMotion: profile.reducedMotion });
    return chooseQualityTier({ mobile: false, memory: 8, cores: 8, reducedMotion: profile.reducedMotion });
}

export function currentDeviceProfile() {
    const mobile = matchMedia('(pointer: coarse)').matches || navigator.maxTouchPoints > 0;
    return {
        mobile,
        memory: Number(navigator.deviceMemory || 4),
        cores: Number(navigator.hardwareConcurrency || 4),
        reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
    };
}
