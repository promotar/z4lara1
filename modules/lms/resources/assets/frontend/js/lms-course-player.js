(function () {
    const root = document.querySelector('[data-lms-player]');

    if (!root) {
        return;
    }

    const courseId = root.dataset.courseId || 'course';
    const lessonId = root.dataset.lessonId || 'lesson';
    const storageKey = `ainpa:lms-player:${courseId}`;
    const notesKey = `${storageKey}:notes:${lessonId}`;
    const shell = root.querySelector('[data-video-shell]');
    const mount = root.querySelector('[data-video-mount]');
    const watchBar = root.querySelector('[data-watch-bar]');
    const currentTime = root.querySelector('[data-current-time]');
    const timeline = root.querySelector('.ainpa-player-timeline');
    const timelineHandle = timeline?.querySelector('button');
    const playButtons = root.querySelectorAll('[data-video-play], [data-video-play-small]');
    const speedButton = root.querySelector('[data-speed-toggle]');
    const volumeButton = root.querySelector('[data-volume-toggle]');
    const completionRequirement = root.dataset.completionRequirement || 'manual';
    let youtubeApiPromise = null;
    let activeBackend = null;
    let videoInitializationPromise = null;
    let progressTimer = null;
    let speedIndex = 1;
    let completedLessons = [];

    const speeds = [1, 1.25, 1.5, 2];
    const parseCompletedSeed = () => {
        try {
            return JSON.parse(root.dataset.completedSeed || '[]').map(value => String(value));
        } catch (error) {
            return [];
        }
    };

    completedLessons = [...new Set(parseCompletedSeed())];

    const readState = () => {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '{}');
        } catch (error) {
            return {};
        }
    };

    const writeState = state => {
        localStorage.setItem(storageKey, JSON.stringify(state));
    };

    const getCompleted = () => {
        return [...completedLessons];
    };

    const setCompleted = completed => {
        completedLessons = [...new Set(completed.map(value => String(value)))];
        root.dataset.completedSeed = JSON.stringify(completedLessons);
    };

    const updateProgress = () => {
        const completed = getCompleted();
        const rows = [...root.querySelectorAll('[data-lesson-row]')];
        const total = rows.length || 1;
        const percent = Math.round((completed.length / total) * 100);

        rows.forEach(row => {
            row.classList.toggle('is-complete', completed.includes(String(row.dataset.lessonId)));
        });

        root.querySelectorAll('[data-progress-label]').forEach(item => {
            item.textContent = String(percent);
        });
        root.querySelectorAll('[data-progress-mini], [data-progress-top]').forEach(item => {
            item.style.width = `${percent}%`;
        });

    };

    const formatTime = seconds => {
        const total = Math.max(0, Math.floor(Number(seconds) || 0));
        const minutes = String(Math.floor(total / 60)).padStart(2, '0');
        const remainder = String(total % 60).padStart(2, '0');
        return `${minutes}:${remainder}`;
    };

    const setPlaybackVisual = isRunning => {
        shell?.classList.toggle('is-video-running', isRunning);
    };

    const updateWatchUI = (position, duration) => {
        const safeDuration = Math.max(1, Number(duration) || 1);
        const safePosition = Math.max(0, Math.min(Number(position) || 0, safeDuration));
        const percent = Math.min(100, Math.max(0, (safePosition / safeDuration) * 100));

        if (watchBar) {
            watchBar.style.width = `${percent}%`;
        }
        if (timelineHandle) {
            timelineHandle.style.left = `${percent}%`;
        }
        if (currentTime) {
            currentTime.textContent = formatTime(safePosition);
        }
    };

    const extractYouTubeId = value => {
        const input = String(value || '').trim();
        if (!input) {
            return '';
        }
        if (/^[a-zA-Z0-9_-]{6,}$/.test(input) && !input.includes('/')) {
            return input;
        }

        try {
            const parsed = new URL(input);
            if (parsed.hostname.includes('youtu.be')) {
                return parsed.pathname.split('/').filter(Boolean).shift() || '';
            }
            if (parsed.hostname.includes('youtube.com')) {
                return parsed.searchParams.get('v')
                    || parsed.pathname.split('/').filter(Boolean).pop()
                    || '';
            }
        } catch (error) {
            return '';
        }

        return '';
    };

    const parseVideoSource = () => {
        const provider = root.dataset.videoProvider || '';
        const token = root.dataset.videoToken || '';
        const source = root.dataset.videoSource || '';
        const legacyUrl = root.dataset.videoUrl || '';

        if ((provider === 'provider_a' || provider === 'platform_youtube') && token) {
            return { type: 'youtube', id: extractYouTubeId(token) };
        }
        if ((provider === 'provider_b' || provider === 'platform_vimeo') && token) {
            return { type: 'vimeo', id: token };
        }
        if (provider === 'peertube' && source) {
            return { type: 'peertube', src: source };
        }
        if ((provider === 'protected_file' || provider === 'direct_file') && source) {
            return { type: 'file', src: source };
        }
        if (provider === 'remote_embed' && source) {
            return { type: 'embed', src: source };
        }
        if (legacyUrl) {
            const youtubeId = extractYouTubeId(legacyUrl);
            if (youtubeId) {
                return { type: 'youtube', id: youtubeId };
            }
            if (/\.(mp4|webm|ogg)(\?.*)?$/i.test(legacyUrl)) {
                return { type: 'file', src: legacyUrl };
            }
            return { type: 'embed', src: legacyUrl };
        }

        return { type: 'none' };
    };

    const showVideoMessage = message => {
        if (!shell || !mount) {
            return;
        }
        shell.classList.add('is-playing');
        shell.classList.remove('is-video-running');
        mount.className = 'ainpa-player-video-mount';
        mount.innerHTML = `<div class="ainpa-player-video-fallback">${message}</div>`;
    };

    const clearProgressTimer = () => {
        if (progressTimer) {
            window.clearInterval(progressTimer);
            progressTimer = null;
        }
    };

    const startProgressTimer = getter => {
        clearProgressTimer();
        progressTimer = window.setInterval(() => {
            const state = getter();
            updateWatchUI(state.current, state.duration);
        }, 500);
    };

    const loadYouTubeApi = () => {
        if (window.YT && window.YT.Player) {
            return Promise.resolve(window.YT);
        }
        if (youtubeApiPromise) {
            return youtubeApiPromise;
        }

        youtubeApiPromise = new Promise((resolve, reject) => {
            const previousCallback = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                if (typeof previousCallback === 'function') {
                    previousCallback();
                }
                resolve(window.YT);
            };

            if (!document.querySelector('script[data-ainpa-youtube-api]')) {
                const script = document.createElement('script');
                script.src = 'https://www.youtube.com/iframe_api';
                script.async = true;
                script.dataset.ainpaYoutubeApi = 'true';
                script.onerror = () => reject(new Error('Could not load video API'));
                document.head.appendChild(script);
            }

            window.setTimeout(() => {
                if (!(window.YT && window.YT.Player)) {
                    reject(new Error('Video API timeout'));
                }
            }, 12000);
        });

        return youtubeApiPromise;
    };

    const createYouTubeBackend = async source => {
        if (!source.id) {
            showVideoMessage('No video source');
            return null;
        }

        mount.className = 'ainpa-player-video-mount is-platform-video is-provider-a';
        mount.innerHTML = '<div class="ainpa-player-youtube-target"></div>';
        shell.classList.add('is-playing');

        const target = mount.querySelector('.ainpa-player-youtube-target');
        const yt = await loadYouTubeApi();

        return await new Promise(resolve => {
            const player = new yt.Player(target, {
                host: 'https://www.youtube-nocookie.com',
                videoId: source.id,
                playerVars: {
                    autoplay: 1,
                    cc_load_policy: 0,
                    color: 'white',
                    controls: 0,
                    disablekb: 1,
                    enablejsapi: 1,
                    fs: 0,
                    iv_load_policy: 3,
                    modestbranding: 1,
                    origin: window.location.origin,
                    playsinline: 1,
                    rel: 0,
                    showinfo: 0,
                    widget_referrer: window.location.origin
                },
                events: {
                    onReady: event => {
                        const frame = player.getIframe?.();
                        frame?.setAttribute('tabindex', '-1');
                        frame?.setAttribute('aria-label', 'Protected lesson video');
                        frame?.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                        const backend = {
                            type: 'youtube',
                            player,
                            play: () => player.playVideo(),
                            pause: () => player.pauseVideo(),
                            seek: seconds => player.seekTo(seconds, true),
                            duration: () => player.getDuration() || 1,
                            current: () => player.getCurrentTime() || 0,
                            mute: () => player.mute(),
                            unmute: () => player.unMute(),
                            isMuted: () => player.isMuted(),
                            setSpeed: speed => player.setPlaybackRate(speed),
                            isPlaying: () => player.getPlayerState() === yt.PlayerState.PLAYING
                        };
                        event.target.playVideo();
                        startProgressTimer(() => ({
                            current: backend.current(),
                            duration: backend.duration()
                        }));
                        resolve(backend);
                    },
                    onStateChange: event => {
                        setPlaybackVisual(event.data === yt.PlayerState.PLAYING);
                    }
                }
            });
        });
    };

    const createFileBackend = source => {
        mount.className = 'ainpa-player-video-mount is-platform-video';
        mount.innerHTML = '';
        shell.classList.add('is-playing');

        const video = document.createElement('video');
        video.src = source.src;
        video.autoplay = true;
        video.controls = false;
        video.playsInline = true;
        video.preload = 'metadata';
        video.setAttribute('controlsList', 'nodownload nofullscreen noplaybackrate noremoteplayback');
        video.setAttribute('disablePictureInPicture', 'true');
        video.setAttribute('disableRemotePlayback', 'true');
        video.disablePictureInPicture = true;
        video.disableRemotePlayback = true;
        video.addEventListener('contextmenu', event => event.preventDefault());
        video.addEventListener('dragstart', event => event.preventDefault());
        mount.appendChild(video);

        const backend = {
            type: 'file',
            element: video,
            play: () => video.play(),
            pause: () => video.pause(),
            seek: seconds => {
                video.currentTime = seconds;
            },
            duration: () => video.duration || 1,
            current: () => video.currentTime || 0,
            mute: () => {
                video.muted = true;
            },
            unmute: () => {
                video.muted = false;
            },
            isMuted: () => video.muted,
            setSpeed: speed => {
                video.playbackRate = speed;
            },
            isPlaying: () => !video.paused
        };

        video.addEventListener('play', () => setPlaybackVisual(true));
        video.addEventListener('pause', () => setPlaybackVisual(false));
        video.addEventListener('timeupdate', () => updateWatchUI(backend.current(), backend.duration()));
        video.addEventListener('loadedmetadata', () => updateWatchUI(backend.current(), backend.duration()));
        video.play().catch(() => setPlaybackVisual(false));
        startProgressTimer(() => ({
            current: backend.current(),
            duration: backend.duration()
        }));

        return backend;
    };

    const createPeerTubeChannel = frame => {
        const origin = new URL(frame.src, window.location.href).origin;
        const scope = 'peertube';
        const pending = new Map();
        const listeners = new Map();
        let transactionId = Math.floor(Math.random() * 1000000) + 1;
        let channelReady = false;
        let resolveChannelReady;
        let rejectChannelReady;

        const ready = new Promise((resolve, reject) => {
            resolveChannelReady = resolve;
            rejectChannelReady = reject;
        });
        const readyTimeout = window.setTimeout(() => {
            rejectChannelReady(new Error('PeerTube connection timeout'));
        }, 12000);

        const post = message => {
            frame.contentWindow?.postMessage(JSON.stringify(message), origin);
        };
        const markReady = () => {
            if (channelReady) {
                return;
            }
            channelReady = true;
            window.clearTimeout(readyTimeout);
            resolveChannelReady();
        };
        const announce = () => {
            post({
                method: `${scope}::__ready`,
                params: { type: 'publish-request', publish: [] }
            });
        };
        const onMessage = event => {
            if (event.source !== frame.contentWindow || event.origin !== origin) {
                return;
            }

            let message;
            try {
                message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
            } catch (error) {
                return;
            }

            if (!message || typeof message !== 'object') {
                return;
            }

            if (typeof message.method === 'string') {
                const separator = message.method.indexOf('::');
                const messageScope = separator === -1 ? '' : message.method.slice(0, separator);
                const method = separator === -1 ? message.method : message.method.slice(separator + 2);

                if (messageScope !== scope) {
                    return;
                }

                if (method === '__ready') {
                    markReady();
                    if (message.params?.type === 'publish-request') {
                        post({
                            method: `${scope}::__ready`,
                            params: { type: 'publish-reply', publish: [] }
                        });
                    }
                    return;
                }

                (listeners.get(method) || []).forEach(listener => listener(message.params));
                if (Object.prototype.hasOwnProperty.call(message, 'id')) {
                    post({ id: message.id, result: null });
                }
                return;
            }

            if (!Object.prototype.hasOwnProperty.call(message, 'id') || !pending.has(message.id)) {
                return;
            }

            const transaction = pending.get(message.id);
            pending.delete(message.id);
            window.clearTimeout(transaction.timeout);

            if (message.error) {
                transaction.reject(new Error(message.message || message.error));
            } else {
                transaction.resolve(message.result);
            }
        };

        window.addEventListener('message', onMessage);
        frame.addEventListener('load', announce);
        window.setTimeout(announce, 0);

        return {
            ready,
            call(method, params, timeoutMs = 5000) {
                return new Promise((resolve, reject) => {
                    const id = transactionId++;
                    const timeout = window.setTimeout(() => {
                        pending.delete(id);
                        reject(new Error(`PeerTube ${method} timeout`));
                    }, timeoutMs);

                    pending.set(id, { resolve, reject, timeout });
                    post({ id, method: `${scope}::${method}`, params });
                });
            },
            on(method, listener) {
                const registered = listeners.get(method) || [];
                registered.push(listener);
                listeners.set(method, registered);
            },
            destroy() {
                window.clearTimeout(readyTimeout);
                window.removeEventListener('message', onMessage);
                frame.removeEventListener('load', announce);
                pending.forEach(transaction => {
                    window.clearTimeout(transaction.timeout);
                    transaction.reject(new Error('PeerTube player closed'));
                });
                pending.clear();
                listeners.clear();
            }
        };
    };

    const createPeerTubeBackend = async source => {
        mount.className = 'ainpa-player-video-mount is-platform-video is-peertube';
        mount.innerHTML = '';
        shell.classList.add('is-playing');

        const frame = document.createElement('iframe');
        frame.src = source.src;
        frame.title = 'PeerTube lesson video';
        frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
        frame.allowFullscreen = true;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.tabIndex = -1;
        frame.setAttribute('aria-hidden', 'true');
        mount.appendChild(frame);

        const channel = createPeerTubeChannel(frame);
        const state = {
            current: 0,
            duration: 1,
            volume: 1,
            previousVolume: 1,
            playbackState: 'unstarted'
        };

        channel.on('playbackStatusUpdate', status => {
            state.current = Math.max(0, Number(status?.position) || 0);
            state.duration = Math.max(1, Number(status?.duration) || 1);
            state.volume = Math.max(0, Math.min(1, Number(status?.volume) || 0));
            state.playbackState = status?.playbackState || state.playbackState;
            updateWatchUI(state.current, state.duration);
        });
        channel.on('playbackStatusChange', playbackState => {
            state.playbackState = String(playbackState || 'paused');
            setPlaybackVisual(state.playbackState === 'playing');
        });
        channel.on('volumeChange', volume => {
            state.volume = Math.max(0, Math.min(1, Number(volume) || 0));
            volumeButton?.classList.toggle('is-muted', state.volume === 0);
        });

        await channel.ready;

        let playerReady = false;
        for (let attempt = 0; attempt < 30; attempt++) {
            try {
                playerReady = Boolean(await channel.call('isReady', undefined, 1500));
            } catch (error) {
                playerReady = false;
            }
            if (playerReady) {
                break;
            }
            await new Promise(resolve => window.setTimeout(resolve, 250));
        }

        if (!playerReady) {
            channel.destroy();
            throw new Error('PeerTube player is not ready');
        }

        try {
            state.volume = Math.max(0, Math.min(1, Number(await channel.call('getVolume')) || 0));
            state.previousVolume = state.volume > 0 ? state.volume : 1;
        } catch (error) {
            state.volume = 1;
        }

        const backend = {
            type: 'peertube',
            play: () => channel.call('play'),
            pause: () => channel.call('pause'),
            seek: seconds => channel.call('seek', Math.max(0, Number(seconds) || 0)),
            duration: () => state.duration,
            current: () => state.current,
            mute: () => {
                if (state.volume > 0) {
                    state.previousVolume = state.volume;
                }
                state.volume = 0;
                return channel.call('setVolume', 0);
            },
            unmute: () => {
                state.volume = state.previousVolume || 1;
                return channel.call('setVolume', state.volume);
            },
            isMuted: () => state.volume === 0,
            setSpeed: speed => channel.call('setPlaybackRate', speed),
            isPlaying: () => state.playbackState === 'playing',
            async toggleCaptions() {
                const captions = await channel.call('getCaptions');
                if (!Array.isArray(captions) || captions.length === 0) {
                    return false;
                }
                const activeCaption = captions.find(caption => caption.mode === 'showing');
                await channel.call('setCaption', activeCaption ? '' : captions[0].id);
                return !activeCaption;
            },
            destroy: () => channel.destroy()
        };

        try {
            await backend.play();
        } catch (error) {
            setPlaybackVisual(false);
        }

        return backend;
    };

    const createEmbedBackend = source => {
        const src = source.type === 'vimeo'
            ? `https://player.vimeo.com/video/${encodeURIComponent(source.id)}?autoplay=1&controls=0&title=0&byline=0&portrait=0`
            : source.src;

        mount.className = 'ainpa-player-video-mount is-platform-video';
        mount.innerHTML = `<iframe src="${src}" title="Protected lesson video" allow="autoplay; encrypted-media; picture-in-picture"></iframe>`;
        shell.classList.add('is-playing');
        setPlaybackVisual(true);
        startProgressTimer(() => {
            const current = Math.min(480, Number(root.__embedProgress || 0) + 1);
            root.__embedProgress = current;
            return { current, duration: 480 };
        });

        return {
            type: 'embed',
            play: () => setPlaybackVisual(true),
            pause: () => setPlaybackVisual(false),
            seek: () => {},
            duration: () => 480,
            current: () => Number(root.__embedProgress || 0),
            mute: () => {},
            unmute: () => {},
            isMuted: () => false,
            setSpeed: () => {},
            isPlaying: () => shell.classList.contains('is-video-running')
        };
    };

    const initializeVideo = async () => {
        if (!shell || !mount) {
            return null;
        }

        const source = parseVideoSource();

        try {
            if (source.type === 'youtube') {
                activeBackend = await createYouTubeBackend(source);
            } else if (source.type === 'file') {
                activeBackend = createFileBackend(source);
            } else if (source.type === 'peertube') {
                activeBackend = await createPeerTubeBackend(source);
            } else if (source.type === 'vimeo' || source.type === 'embed') {
                activeBackend = createEmbedBackend(source);
            } else {
                showVideoMessage('No video source');
            }
        } catch (error) {
            showVideoMessage('Video could not be loaded');
        }

        return activeBackend;
    };

    const toggleVideo = async () => {
        if (!activeBackend) {
            if (!videoInitializationPromise) {
                videoInitializationPromise = initializeVideo().finally(() => {
                    videoInitializationPromise = null;
                });
            }
            await videoInitializationPromise;
            return;
        }

        if (activeBackend.isPlaying()) {
            await activeBackend.pause();
            setPlaybackVisual(false);
        } else {
            await activeBackend.play();
            setPlaybackVisual(true);
        }
    };

    playButtons.forEach(button => {
        button.addEventListener('click', () => {
            toggleVideo().catch(() => setPlaybackVisual(false));
        });
    });

    shell?.addEventListener('click', event => {
        if (activeBackend?.type !== 'peertube' || event.target.closest('button, .ainpa-player-controls')) {
            return;
        }
        toggleVideo().catch(() => setPlaybackVisual(false));
    });

    shell?.addEventListener('contextmenu', event => event.preventDefault());
    shell?.addEventListener('dragstart', event => event.preventDefault());
    shell?.addEventListener('selectstart', event => event.preventDefault());

    window.addEventListener('pagehide', () => {
        clearProgressTimer();
        activeBackend?.destroy?.();
        const video = mount?.querySelector('video');
        if (video) {
            video.pause();
            video.removeAttribute('src');
            video.load();
        }
    });

    timeline?.addEventListener('click', event => {
        if (!activeBackend) {
            return;
        }
        const rect = timeline.getBoundingClientRect();
        const percent = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
        const nextTime = activeBackend.duration() * percent;
        Promise.resolve(activeBackend.seek(nextTime)).catch(() => {});
        updateWatchUI(nextTime, activeBackend.duration());
    });

    const saveLessonProgress = async completed => {
        const endpoint = root.dataset.progressEndpoint;
        if (!endpoint) {
            return null;
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': root.dataset.csrfToken || '',
            },
            body: JSON.stringify({ completed }),
        });
        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || payload.error || 'Could not save lesson progress');
        }

        return payload.data || null;
    };

    root.querySelector('[data-complete-and-next]')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        if (completionRequirement !== 'manual') {
            return;
        }
        const completed = getCompleted();
        const current = String(lessonId);
        if (completed.includes(current)) {
            return;
        }

        const next = [...completed, current];
        const label = button?.querySelector('[data-complete-next-label]');
        const originalLabel = label?.textContent || 'Complete & Continue';
        const hasNextLesson = Boolean(button?.dataset.nextUrl);

        setCompleted(next);
        updateProgress();

        if (button) {
            button.disabled = true;
            button.classList.add('is-loading');
        }
        if (label) {
            label.textContent = hasNextLesson ? 'Saving progress...' : 'Finishing course...';
        }

        try {
            const data = await saveLessonProgress(true);
            if (data && Array.isArray(data.completed_lessons)) {
                setCompleted(data.completed_lessons);
                updateProgress();
            }
            const destinationUrl = data?.next_lesson_url
                || data?.course_complete_url
                || button?.dataset.nextUrl
                || '';
            if (destinationUrl) {
                window.location.assign(destinationUrl);
                return;
            }
            if (label) {
                label.textContent = 'Course Completed';
            }
            button?.classList.add('is-complete');
        } catch (error) {
            setCompleted(completed);
            updateProgress();
            if (label) {
                label.textContent = 'Could not save. Try again';
                window.setTimeout(() => {
                    label.textContent = originalLabel;
                }, 1800);
            }
        } finally {
            if (button) {
                button.classList.remove('is-loading');
                button.disabled = getCompleted().includes(current);
            }
        }
    });

    root.querySelectorAll('[data-side-tab]').forEach(button => {
        button.addEventListener('click', () => {
            const target = button.dataset.sideTab;
            root.querySelectorAll('[data-side-tab]').forEach(item => item.classList.toggle('is-active', item === button));
            root.querySelectorAll('[data-side-panel]').forEach(panel => {
                panel.classList.toggle('is-active', panel.dataset.sidePanel === target);
            });
        });
    });

    const playerSections = [...root.querySelectorAll('[data-player-section]')];
    if (!playerSections.some(section => section.classList.contains('is-open'))) {
        playerSections[0]?.classList.add('is-open');
    }

    root.querySelectorAll('[data-toggle-player-section]').forEach(button => {
        button.addEventListener('click', () => {
            const section = button.closest('[data-player-section]');
            if (!section) {
                return;
            }
            const willOpen = !section.classList.contains('is-open');
            section.classList.toggle('is-open', willOpen);
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    const sidebarToggles = [...root.querySelectorAll('[data-toggle-sidebar]')];
    const isCompactSidebar = () => window.matchMedia('(max-width: 1180px)').matches;
    const syncSidebarToggleState = () => {
        const isExpanded = isCompactSidebar()
            ? root.classList.contains('is-sidebar-open')
            : !root.classList.contains('is-sidebar-collapsed');

        sidebarToggles.forEach(button => {
            button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    };

    sidebarToggles.forEach(button => {
        button.addEventListener('click', () => {
            if (isCompactSidebar()) {
                root.classList.toggle('is-sidebar-open');
                root.classList.remove('is-sidebar-collapsed');
            } else {
                root.classList.toggle('is-sidebar-collapsed');
                root.classList.remove('is-sidebar-open');
            }
            syncSidebarToggleState();
        });
    });

    window.addEventListener('resize', syncSidebarToggleState);

    const notesInput = root.querySelector('[data-notes-input]');
    const notesStatus = root.querySelector('[data-notes-status]');
    if (notesInput) {
        notesInput.value = localStorage.getItem(notesKey) || '';
        notesInput.addEventListener('input', () => {
            localStorage.setItem(notesKey, notesInput.value);
            if (notesStatus) {
                notesStatus.textContent = 'Saved.';
                window.clearTimeout(root.__notesTimer);
                root.__notesTimer = window.setTimeout(() => {
                    notesStatus.textContent = 'Notes are saved in this browser.';
                }, 1400);
            }
        });
    }

    root.querySelector('[data-bookmark]')?.addEventListener('click', event => {
        const state = readState();
        state.bookmarks = state.bookmarks || [];
        const current = String(lessonId);
        state.bookmarks = state.bookmarks.includes(current)
            ? state.bookmarks.filter(value => value !== current)
            : [...state.bookmarks, current];
        writeState(state);
        event.currentTarget.classList.toggle('is-active', state.bookmarks.includes(current));
    });

    const themeToggle = root.querySelector('[data-theme-toggle]');
    const themeStorageKey = 'ainpa:lms-theme';
    const applyTheme = theme => {
        const isDark = theme === 'dark';
        root.classList.toggle('is-dark', isDark);
        if (themeToggle) {
            const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
            themeToggle.setAttribute('aria-label', label);
            themeToggle.setAttribute('title', label);
        }
    };
    let preferredTheme = 'light';
    try {
        preferredTheme = localStorage.getItem(themeStorageKey)
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    } catch (error) {
        preferredTheme = 'light';
    }
    applyTheme(preferredTheme);
    themeToggle?.addEventListener('click', () => {
        preferredTheme = root.classList.contains('is-dark') ? 'light' : 'dark';
        applyTheme(preferredTheme);
        try { localStorage.setItem(themeStorageKey, preferredTheme); } catch (error) {}
    });

    const notificationRoot = root.querySelector('[data-notifications]');
    const notificationButton = notificationRoot?.querySelector('[data-notification-button]');
    const notificationPanel = notificationRoot?.querySelector('[data-notification-panel]');
    const notificationList = notificationRoot?.querySelector('[data-notification-list]');
    const notificationCount = notificationRoot?.querySelector('[data-notification-count]');
    const notificationSummary = notificationRoot?.querySelector('[data-notification-summary]');
    const readAllButton = notificationRoot?.querySelector('[data-notifications-read-all]');
    let readAllUrl = '';
    let notificationsLoaded = false;
    let unreadNotifications = 0;

    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': root.dataset.csrfToken || '',
                ...(options.headers || {})
            }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Request failed');
        return payload;
    };
    const updateNotificationCount = count => {
        const value = Math.max(0, Number(count) || 0);
        unreadNotifications = value;
        if (notificationCount) {
            notificationCount.textContent = value > 99 ? '99+' : String(value);
            notificationCount.hidden = value === 0;
        }
        if (notificationSummary) notificationSummary.textContent = value ? `${value} unread` : 'You are all caught up';
    };
    const markNotificationRead = async item => {
        if (item.is_read || !item.read_url) return;
        await requestJson(item.read_url, {method: 'PATCH'});
        item.is_read = true;
        updateNotificationCount(unreadNotifications - 1);
    };
    const renderNotifications = items => {
        if (!notificationList) return;
        notificationList.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'ainpa-notification-empty';
            empty.textContent = 'No LMS notifications yet.';
            notificationList.appendChild(empty);
            return;
        }
        items.forEach(item => {
            const action = document.createElement(item.action_url ? 'a' : 'button');
            action.className = `ainpa-notification-item${item.is_read ? '' : ' is-unread'}`;
            if (item.action_url) action.href = item.action_url;
            else action.type = 'button';
            const title = document.createElement('strong');
            const body = document.createElement('span');
            const time = document.createElement('small');
            title.textContent = item.title || 'LMS notification';
            body.textContent = item.body || '';
            time.textContent = item.created_at || '';
            action.append(title, body, time);
            action.addEventListener('click', async event => {
                if (item.action_url) event.preventDefault();
                try { await markNotificationRead(item); } catch (error) {}
                if (item.action_url) window.location.assign(item.action_url);
            });
            notificationList.appendChild(action);
        });
    };
    const loadNotifications = async () => {
        if (!root.dataset.notificationsEndpoint || !notificationRoot) return;
        try {
            const payload = await requestJson(root.dataset.notificationsEndpoint);
            readAllUrl = payload.read_all_url || '';
            renderNotifications(Array.isArray(payload.data) ? payload.data : []);
            updateNotificationCount(payload.unread_count);
            notificationsLoaded = true;
        } catch (error) {
            if (notificationList) notificationList.textContent = 'Notifications could not be loaded.';
            if (notificationSummary) notificationSummary.textContent = 'Unavailable';
        }
    };
    notificationButton?.addEventListener('click', async () => {
        const willOpen = notificationPanel.hidden;
        notificationPanel.hidden = !willOpen;
        notificationButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen && !notificationsLoaded) await loadNotifications();
    });
    readAllButton?.addEventListener('click', async () => {
        if (!readAllUrl) return;
        readAllButton.disabled = true;
        try {
            await requestJson(readAllUrl, {method: 'POST'});
            await loadNotifications();
        } finally {
            readAllButton.disabled = false;
        }
    });
    document.addEventListener('click', event => {
        if (!notificationRoot || notificationRoot.contains(event.target) || notificationPanel?.hidden) return;
        notificationPanel.hidden = true;
        notificationButton?.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !notificationPanel || notificationPanel.hidden) return;
        notificationPanel.hidden = true;
        notificationButton?.setAttribute('aria-expanded', 'false');
        notificationButton?.focus();
    });
    loadNotifications();

    speedButton?.addEventListener('click', event => {
        speedIndex = (speedIndex + 1) % speeds.length;
        const speed = speeds[speedIndex];
        event.currentTarget.textContent = `${speed}x`;
        Promise.resolve(activeBackend?.setSpeed(speed)).catch(() => {});
    });

    root.querySelector('[data-caption-toggle]')?.addEventListener('click', async event => {
        if (typeof activeBackend?.toggleCaptions === 'function') {
            try {
                const enabled = await activeBackend.toggleCaptions();
                event.currentTarget.classList.toggle('is-active', enabled);
            } catch (error) {
                event.currentTarget.classList.remove('is-active');
            }
            return;
        }
        event.currentTarget.classList.toggle('is-active');
    });

    volumeButton?.addEventListener('click', event => {
        if (!activeBackend) {
            return;
        }
        if (activeBackend.isMuted()) {
            Promise.resolve(activeBackend.unmute()).catch(() => {});
            event.currentTarget.classList.remove('is-muted');
        } else {
            Promise.resolve(activeBackend.mute()).catch(() => {});
            event.currentTarget.classList.add('is-muted');
        }
    });

    root.querySelector('[data-settings-toggle]')?.addEventListener('click', event => {
        event.currentTarget.classList.toggle('is-active');
    });

    root.querySelectorAll('[data-fullscreen]').forEach(button => {
        button.addEventListener('click', async () => {
            const target = root.querySelector('[data-video-shell]') || root;
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else if (target.requestFullscreen) {
                await target.requestFullscreen();
            }
        });
    });

    const initialState = readState();
    if ((initialState.bookmarks || []).includes(String(lessonId))) {
        root.querySelector('[data-bookmark]')?.classList.add('is-active');
    }

    updateWatchUI(0, 1);
    updateProgress();
})();
