import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export class AvailabilitySync {
    constructor(config, onChange) {
        this.config = config || {};
        this.onChange = onChange;
        this.echo = null;
        this.timer = null;
        this.fingerprint = null;
    }

    start() {
        this.connect();
        this.refresh().catch(() => {});
        const seconds = Math.min(30, Math.max(20, Number(this.config.availability_poll_seconds || 25)));
        this.timer = setInterval(() => this.refresh().catch(() => {}), seconds * 1000);
    }

    connect() {
        const realtime = this.config.realtime || {};
        if (!realtime.enabled || !realtime.key || !realtime.channel) return;
        try {
            if (typeof window !== 'undefined') window.Pusher = Pusher;
            const tls = realtime.scheme === 'https';
            this.echo = new Echo({
                broadcaster: 'reverb',
                key: realtime.key,
                wsHost: realtime.host,
                wsPort: Number(realtime.port || 80),
                wssPort: Number(realtime.port || 443),
                forceTLS: tls,
                enabledTransports: tls ? ['wss'] : ['ws'],
                disableStats: true,
            });
            this.echo.channel(realtime.channel).listen(realtime.event || '.artwork.availability.changed', (payload) => {
                this.apply(payload, 'realtime');
            });
        } catch {
            this.echo = null;
        }
    }

    async refresh() {
        if (!this.config.availability_url) return null;
        const response = await fetch(this.config.availability_url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error(`Availability returned HTTP ${response.status}.`);
        const payload = await response.json();
        this.apply(payload, 'poll');
        return payload;
    }

    apply(payload, source) {
        const items = Array.isArray(payload?.items) ? payload.items : [];
        const fingerprint = JSON.stringify(items.map((item) => [
            item.placement_id, item.availability, Boolean(item.sold), Boolean(item.interactive),
        ]));
        if (source === 'poll' && fingerprint === this.fingerprint) return;
        this.fingerprint = fingerprint;
        this.onChange?.({ ...payload, items }, source);
    }

    stop() {
        if (this.timer) clearInterval(this.timer);
        this.timer = null;
        if (this.echo) {
            if (this.config.realtime?.channel) this.echo.leave(this.config.realtime.channel);
            this.echo.disconnect();
        }
        this.echo = null;
    }
}
