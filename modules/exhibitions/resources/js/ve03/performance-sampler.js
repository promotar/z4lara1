export class PerformanceSampler {
    constructor(startedAt = performance.now()) {
        this.startedAt = startedAt;
        this.lastFrame = startedAt;
        this.frameTimes = [];
        this.baselineDeltaTotal = 0;
        this.baselineFrameCount = 0;
        this.gpuMemoryEstimateBytes = 0;
        this.longTaskCount = 0;
        this.longTaskDurationMs = 0;
        this.longTaskObserver = null;
        this.observeLongTasks();
    }

    frame(now = performance.now()) {
        const delta = now - this.lastFrame;
        this.lastFrame = now;
        if (delta > 0 && delta < 1000) {
            const elapsed = now - this.startedAt;
            this.frameTimes.push({ delta, elapsed });
            if (elapsed <= 60_000) {
                this.baselineDeltaTotal += delta;
                this.baselineFrameCount += 1;
            }
        }
        if (this.frameTimes.length > 3600) this.frameTimes.shift();
    }

    observeScene(scene) {
        const geometries = new Set();
        const textures = new Set();
        let bytes = 0;
        scene?.traverse?.((object) => {
            const geometry = object?.geometry;
            if (geometry && !geometries.has(geometry)) {
                geometries.add(geometry);
                for (const attribute of Object.values(geometry.attributes || {})) {
                    bytes += Number(attribute?.array?.byteLength || 0);
                }
                bytes += Number(geometry.index?.array?.byteLength || 0);
            }
            const materials = Array.isArray(object?.material) ? object.material : [object?.material];
            for (const material of materials) {
                if (!material) continue;
                for (const value of Object.values(material)) {
                    if (!value?.isTexture || textures.has(value)) continue;
                    textures.add(value);
                    const image = value.image || value.source?.data;
                    const width = Number(image?.width || image?.videoWidth || 0);
                    const height = Number(image?.height || image?.videoHeight || 0);
                    bytes += Math.round(width * height * 4 * (4 / 3));
                }
            }
        });
        this.gpuMemoryEstimateBytes = bytes;
    }

    observeLongTasks() {
        if (typeof PerformanceObserver === 'undefined'
            || !PerformanceObserver.supportedEntryTypes?.includes('longtask')) return;
        this.longTaskObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                this.longTaskCount += 1;
                this.longTaskDurationMs += Number(entry.duration || 0);
            }
        });
        this.longTaskObserver.observe({ type: 'longtask', buffered: true });
    }

    stop() {
        this.longTaskObserver?.disconnect();
        this.longTaskObserver = null;
    }

    snapshot(renderer, extra = {}) {
        const deltas = this.frameTimes.map((sample) => sample.delta);
        const sorted = [...deltas].sort((a, b) => b - a);
        const averageMs = this.frameTimes.length
            ? deltas.reduce((sum, value) => sum + value, 0) / deltas.length
            : 0;
        const lowWindow = sorted.slice(0, Math.max(1, Math.ceil(sorted.length * 0.01)));
        const lowMs = lowWindow.length ? lowWindow.reduce((sum, value) => sum + value, 0) / lowWindow.length : 0;
        const sessionDuration = performance.now() - this.startedAt;
        const recent = this.frameTimes.filter((sample) => sample.elapsed >= sessionDuration - 60_000).map((sample) => sample.delta);
        const baselineFps = this.baselineFrameCount > 0
            ? 1000 / (this.baselineDeltaTotal / this.baselineFrameCount)
            : 0;
        const recentFps = this.averageFps(recent);
        const thermalChange = sessionDuration >= 600_000 && baselineFps > 0
            ? ((recentFps - baselineFps) / baselineFps) * 100
            : 0;

        return {
            load_duration_ms: Math.round(extra.loadDuration || 0),
            session_duration_ms: Math.round(sessionDuration),
            fps_average: averageMs ? Number((1000 / averageMs).toFixed(1)) : 0,
            fps_low_1_percent: lowMs ? Number((1000 / lowMs).toFixed(1)) : 0,
            draw_calls: Number(renderer?.info?.render?.calls || 0),
            triangles: Number(renderer?.info?.render?.triangles || 0),
            textures: Number(renderer?.info?.memory?.textures || 0),
            gpu_memory_estimate_mb: Number((this.gpuMemoryEstimateBytes / 1024 / 1024).toFixed(2)),
            long_task_count: this.longTaskCount,
            long_task_duration_ms: Math.round(this.longTaskDurationMs),
            thermal_fps_change_percent: Number(thermalChange.toFixed(1)),
        };
    }

    averageFps(samples) {
        if (samples.length === 0) return 0;
        const average = samples.reduce((sum, value) => sum + value, 0) / samples.length;
        return average > 0 ? 1000 / average : 0;
    }
}
