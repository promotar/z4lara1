(function () {
    'use strict';

    const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));
    const wrap = (value) => ((value + 180) % 360 + 360) % 360 - 180;
    const radians = (degrees) => degrees * Math.PI / 180;
    const degrees = (angle) => angle * 180 / Math.PI;

    /**
     * Viewer coordinate convention:
     * - yaw 0 is NORTH; positive yaw turns toward EAST.
     * - pitch 0 is the horizon; positive pitch looks toward the CEILING.
     * - screen Y is positive upward.
     * - equirectangular texture V is 0 at the top and 1 at the bottom.
     *
     * The texture upload is intentionally not flipped. Vertical orientation is
     * applied exactly once by v = 0.5 - latitude / PI in the fragment shader.
     */
    const Z4VirtualProjection = Object.freeze({
        directionFromScreen(screenX, screenY, fov, aspect, yaw, pitch) {
            const tanHalfFov = Math.tan(radians(fov) / 2);
            const length = Math.hypot(screenX * aspect * tanHalfFov, screenY * tanHalfFov, -1);
            const camera = {
                x: screenX * aspect * tanHalfFov / length,
                y: screenY * tanHalfFov / length,
                z: -1 / length,
            };
            const cp = Math.cos(radians(pitch)), sp = Math.sin(radians(pitch));
            const pitched = {
                x: camera.x,
                y: camera.y * cp - camera.z * sp,
                z: camera.y * sp + camera.z * cp,
            };
            const cy = Math.cos(radians(yaw)), sy = Math.sin(radians(yaw));

            return {
                x: pitched.x * cy - pitched.z * sy,
                y: pitched.y,
                z: pitched.x * sy + pitched.z * cy,
            };
        },

        screenToSpherical(screenX, screenY, fov, aspect, yaw, pitch) {
            const direction = this.directionFromScreen(screenX, screenY, fov, aspect, yaw, pitch);

            return {
                yaw: wrap(degrees(Math.atan2(direction.x, -direction.z))),
                pitch: clamp(degrees(Math.asin(clamp(direction.y, -1, 1))), -89, 89),
            };
        },

        sphericalToScreen(targetYaw, targetPitch, viewYaw, viewPitch, fov, aspect) {
            const longitude = radians(targetYaw), latitude = radians(targetPitch);
            const world = {
                x: Math.sin(longitude) * Math.cos(latitude),
                y: Math.sin(latitude),
                z: -Math.cos(longitude) * Math.cos(latitude),
            };
            const cy = Math.cos(radians(viewYaw)), sy = Math.sin(radians(viewYaw));
            const yawLocal = {
                x: world.x * cy + world.z * sy,
                y: world.y,
                z: -world.x * sy + world.z * cy,
            };
            const cp = Math.cos(radians(viewPitch)), sp = Math.sin(radians(viewPitch));
            const camera = {
                x: yawLocal.x,
                y: yawLocal.y * cp + yawLocal.z * sp,
                z: -yawLocal.y * sp + yawLocal.z * cp,
            };
            if (camera.z >= -0.000001) return { visible: false, depth: -camera.z };

            const tanHalfFov = Math.tan(radians(fov) / 2);
            const screenX = camera.x / (-camera.z * aspect * tanHalfFov);
            const screenY = camera.y / (-camera.z * tanHalfFov);

            return {
                visible: Math.abs(screenX) <= 1.2 && Math.abs(screenY) <= 1.2,
                screenX,
                screenY,
                left: 50 + screenX * 50,
                top: 50 - screenY * 50,
                depth: -camera.z,
            };
        },
    });

    Object.defineProperty(globalThis, 'Z4VirtualProjection', { configurable: true, value: Z4VirtualProjection });

    class Z4VirtualViewer {
        constructor(root) {
            this.root = root;
            this.canvas = root.querySelector('canvas');
            this.overlay = root.querySelector('[data-viewer-overlays]');
            this.loading = root.querySelector('[data-viewer-loading]');
            this.error = root.querySelector('[data-viewer-error]');
            this.config = JSON.parse(root.querySelector('[data-viewer-config]').textContent || '{}');
            this.yaw = Number(this.config.initialYaw || 0);
            this.pitch = Number(this.config.initialPitch || 0);
            this.fov = Number(this.config.initialFov || 75);
            this.minimumFov = Number(this.config.minimumFov || 35);
            this.maximumFov = Number(this.config.maximumFov || 110);
            this.pointers = new Map();
            this.dragged = false;
            this.autoRotate = Boolean(this.config.autoRotate) && !matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.autoRotateSpeed = Number(this.config.autoRotateSpeed || 2);
            this.lastInteraction = performance.now();
            this.lastFrame = performance.now();
            this.hotspots = [];
            root.__z4VirtualViewer = this;
            this.init();
        }

        init() {
            try {
                this.gl = this.canvas.getContext('webgl', { alpha: false, antialias: true, preserveDrawingBuffer: false });
                if (!this.gl) throw new Error('WebGL is unavailable.');
                this.program = this.createProgram();
                this.createGeometry();
                this.texture = this.gl.createTexture();
                this.bindEvents();
                this.createHotspots();
                this.loadPanorama();
            } catch (error) {
                this.fail(error instanceof Error ? error.message : 'The 360 viewer could not start.');
            }
        }

        createProgram() {
            const vertex = 'attribute vec2 aPosition; varying vec2 vUv; void main(){vUv=(aPosition+1.0)*0.5;gl_Position=vec4(aPosition,0.0,1.0);}';
            const fragment = 'precision mediump float; varying vec2 vUv; uniform sampler2D uPanorama; uniform float uYaw; uniform float uPitch; uniform float uTan; uniform float uAspect; void main(){vec2 p=(vUv-0.5)*2.0; vec3 direction=normalize(vec3(p.x*uAspect*uTan,p.y*uTan,-1.0)); float cp=cos(uPitch),sp=sin(uPitch); direction=vec3(direction.x,direction.y*cp-direction.z*sp,direction.y*sp+direction.z*cp); float cy=cos(uYaw),sy=sin(uYaw); direction=vec3(direction.x*cy-direction.z*sy,direction.y,direction.x*sy+direction.z*cy); float longitude=atan(direction.x,-direction.z); float latitude=asin(clamp(direction.y,-1.0,1.0)); float v=0.5-latitude/3.14159265359; vec2 uv=vec2(fract(longitude/6.28318530718+0.5),v); gl_FragColor=texture2D(uPanorama,uv);}';
            const compile = (type, source) => {
                const shader = this.gl.createShader(type);
                this.gl.shaderSource(shader, source);
                this.gl.compileShader(shader);
                if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) throw new Error(this.gl.getShaderInfoLog(shader) || 'Viewer shader failed.');
                return shader;
            };
            const program = this.gl.createProgram();
            this.gl.attachShader(program, compile(this.gl.VERTEX_SHADER, vertex));
            this.gl.attachShader(program, compile(this.gl.FRAGMENT_SHADER, fragment));
            this.gl.linkProgram(program);
            if (!this.gl.getProgramParameter(program, this.gl.LINK_STATUS)) throw new Error(this.gl.getProgramInfoLog(program) || 'Viewer program failed.');
            return program;
        }

        createGeometry() {
            const buffer = this.gl.createBuffer();
            this.gl.bindBuffer(this.gl.ARRAY_BUFFER, buffer);
            this.gl.bufferData(this.gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), this.gl.STATIC_DRAW);
            this.gl.useProgram(this.program);
            const location = this.gl.getAttribLocation(this.program, 'aPosition');
            this.gl.enableVertexAttribArray(location);
            this.gl.vertexAttribPointer(location, 2, this.gl.FLOAT, false, 0, 0);
        }

        loadPanorama() {
            const image = new Image();
            image.decoding = 'async';
            image.onload = () => {
                this.gl.bindTexture(this.gl.TEXTURE_2D, this.texture);
                this.gl.pixelStorei(this.gl.UNPACK_FLIP_Y_WEBGL, false);
                this.gl.texImage2D(this.gl.TEXTURE_2D, 0, this.gl.RGBA, this.gl.RGBA, this.gl.UNSIGNED_BYTE, image);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_S, this.gl.CLAMP_TO_EDGE);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_T, this.gl.CLAMP_TO_EDGE);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MIN_FILTER, this.gl.LINEAR);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MAG_FILTER, this.gl.LINEAR);
                this.loading.hidden = true;
                this.root.dataset.ready = 'true';
                this.resize();
                requestAnimationFrame((time) => this.frame(time));
            };
            image.onerror = () => this.fail('The panorama could not be loaded. Use the accessible artwork list below.');
            image.src = this.config.panoramaUrl;
        }

        createHotspots() {
            (this.config.placements || []).forEach((placement) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'z4-ve-artwork-plane';
                button.setAttribute('aria-label', placement.title + ' by ' + placement.artist);
                button.dataset.placement = placement.id;
                if (placement.media_url) {
                    const image = document.createElement('img');
                    image.src = placement.media_url;
                    image.alt = '';
                    image.draggable = false;
                    button.appendChild(image);
                } else {
                    button.classList.add('is-slot');
                    const label = document.createElement('strong'); label.textContent = placement.name || placement.title || 'Display slot'; button.appendChild(label);
                }
                if (placement.availability === 'Sold') {
                    const badge = document.createElement('span'); badge.textContent = 'Sold'; button.appendChild(badge);
                }
                button.addEventListener('pointerenter', () => { this.hotspotInteraction = true; this.lastInteraction = performance.now(); });
                button.addEventListener('pointerleave', () => { this.hotspotInteraction = false; this.lastInteraction = performance.now(); });
                button.addEventListener('focus', () => { this.hotspotInteraction = true; this.lastInteraction = performance.now(); });
                button.addEventListener('blur', () => { this.hotspotInteraction = false; this.lastInteraction = performance.now(); });
                button.addEventListener('click', () => {
                    if (this.dragged) return;
                    const panel = document.getElementById('placement-' + placement.id);
                    if (panel && typeof panel.showModal === 'function') { panel.showModal(); panel.querySelector('button, a, input')?.focus(); }
                });
                this.overlay.appendChild(button);
                this.hotspots.push({ element: button, data: placement });
            });
        }

        bindEvents() {
            const interact = () => { this.lastInteraction = performance.now(); };
            this.canvas.addEventListener('pointerdown', (event) => {
                this.canvas.setPointerCapture(event.pointerId); this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY }); this.dragged = false; interact();
            });
            this.canvas.addEventListener('pointermove', (event) => {
                if (!this.pointers.has(event.pointerId)) return;
                const previous = this.pointers.get(event.pointerId);
                const dx = event.clientX - previous.x, dy = event.clientY - previous.y;
                this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                if (this.pointers.size === 1) {
                    this.yaw = wrap(this.yaw - dx * this.fov / Math.max(240, this.canvas.clientWidth));
                    this.pitch = clamp(this.pitch - dy * this.fov / Math.max(180, this.canvas.clientHeight), -89, 89);
                    this.dragged = this.dragged || Math.abs(dx) + Math.abs(dy) > 2;
                } else if (this.pointers.size === 2) {
                    const points = [...this.pointers.values()];
                    const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
                    if (this.pinchDistance) this.fov = clamp(this.fov * this.pinchDistance / distance, this.minimumFov, this.maximumFov);
                    this.pinchDistance = distance;
                }
                interact();
            });
            const endPointer = (event) => { this.pointers.delete(event.pointerId); if (this.pointers.size < 2) this.pinchDistance = null; };
            this.canvas.addEventListener('pointerup', endPointer); this.canvas.addEventListener('pointercancel', endPointer);
            this.canvas.addEventListener('wheel', (event) => { event.preventDefault(); this.fov = clamp(this.fov + Math.sign(event.deltaY) * 5, this.minimumFov, this.maximumFov); interact(); }, { passive: false });
            this.canvas.addEventListener('dblclick', () => this.root.requestFullscreen?.());
            this.canvas.addEventListener('click', (event) => {
                if (!this.root.hasAttribute('data-layout-editor') || this.dragged) return;
                const rect = this.canvas.getBoundingClientRect();
                const screenX = ((event.clientX - rect.left) / rect.width) * 2 - 1;
                const screenY = 1 - ((event.clientY - rect.top) / rect.height) * 2;
                const detail = Z4VirtualProjection.screenToSpherical(screenX, screenY, this.fov, rect.width / rect.height, this.yaw, this.pitch);
                this.root.dispatchEvent(new CustomEvent('z4viewerpick', { detail }));
            });
            this.root.querySelector('[data-viewer-fullscreen]')?.addEventListener('click', () => document.fullscreenElement ? document.exitFullscreen() : this.root.requestFullscreen?.());
            this.root.querySelector('[data-viewer-auto-rotate]')?.addEventListener('click', (event) => { this.autoRotate = !this.autoRotate; event.currentTarget.setAttribute('aria-pressed', String(this.autoRotate)); interact(); });
            this.root.querySelector('[data-viewer-zoom-in]')?.addEventListener('click', () => { this.fov = clamp(this.fov - 8, this.minimumFov, this.maximumFov); interact(); });
            this.root.querySelector('[data-viewer-zoom-out]')?.addEventListener('click', () => { this.fov = clamp(this.fov + 8, this.minimumFov, this.maximumFov); interact(); });
            addEventListener('resize', () => this.resize());
            document.addEventListener('visibilitychange', () => { this.lastFrame = performance.now(); });
            this.canvas.addEventListener('webglcontextlost', (event) => { event.preventDefault(); this.fail('The 360 rendering context was lost. Reload or use the artwork list.'); });
        }

        resize() {
            const ratio = Math.min(2, devicePixelRatio || 1);
            const width = Math.max(1, Math.floor(this.canvas.clientWidth * ratio));
            const height = Math.max(1, Math.floor(this.canvas.clientHeight * ratio));
            if (this.canvas.width !== width || this.canvas.height !== height) { this.canvas.width = width; this.canvas.height = height; }
            this.gl.viewport(0, 0, width, height);
        }

        frame(time) {
            if (!this.gl || this.error?.hidden === false) return;
            const seconds = Math.min(0.1, (time - this.lastFrame) / 1000); this.lastFrame = time;
            if (this.autoRotate && !this.hotspotInteraction && this.pointers.size === 0 && time - this.lastInteraction > Number(this.config.autoRotateDelay || 4000)) this.yaw = wrap(this.yaw + this.autoRotateSpeed * seconds);
            this.draw(); this.positionHotspots(); requestAnimationFrame((next) => this.frame(next));
        }

        draw() {
            this.resize();
            this.gl.useProgram(this.program);
            this.gl.uniform1f(this.gl.getUniformLocation(this.program, 'uYaw'), radians(this.yaw));
            this.gl.uniform1f(this.gl.getUniformLocation(this.program, 'uPitch'), radians(this.pitch));
            this.gl.uniform1f(this.gl.getUniformLocation(this.program, 'uTan'), Math.tan(radians(this.fov) / 2));
            this.gl.uniform1f(this.gl.getUniformLocation(this.program, 'uAspect'), this.canvas.width / this.canvas.height);
            this.gl.activeTexture(this.gl.TEXTURE0); this.gl.bindTexture(this.gl.TEXTURE_2D, this.texture);
            this.gl.drawArrays(this.gl.TRIANGLES, 0, 6);
        }

        positionHotspots() {
            const aspect = this.canvas.clientWidth / Math.max(1, this.canvas.clientHeight);
            const tanHalfFov = Math.tan(radians(this.fov) / 2);
            this.hotspots.forEach(({ element, data }) => {
                const projected = Z4VirtualProjection.sphericalToScreen(Number(data.yaw), Number(data.pitch), this.yaw, this.pitch, this.fov, aspect);
                const visible = data.enabled !== false && projected.visible;
                element.hidden = !visible;
                if (!visible) { element.style.display = 'none'; return; }
                element.style.removeProperty('display');
                const angularWidth = clamp(Number(data.width || 1), 0.1, 179);
                const width = clamp(this.canvas.clientWidth * Math.tan(radians(angularWidth) / 2) / (aspect * tanHalfFov), 44, this.canvas.clientWidth * 0.7);
                const ratio = clamp(Number(data.media_width || 4) / Math.max(1, Number(data.media_height || 3)), 0.2, 5);
                let height = width / ratio;
                if (data.maximum_height) {
                    const maximumHeight = clamp(Number(data.maximum_height), 0.1, 179);
                    height = Math.min(height, this.canvas.clientHeight * Math.tan(radians(maximumHeight) / 2) / tanHalfFov);
                }
                element.style.cssText = `left:${projected.left}%;top:${projected.top}%;width:${width}px;height:${height}px;transform:translate(-50%,-50%) rotate(${Number(data.roll || 0)}deg);`;
            });
        }

        fail(message) {
            if (this.loading) this.loading.hidden = true;
            if (this.error) { this.error.hidden = false; const text = this.error.querySelector('[data-viewer-error-message]'); if (text) text.textContent = message; }
            this.root.dataset.failed = 'true';
        }

        setPlacementPreview(slotId, artwork) {
            this.hotspots.forEach((hotspot) => {
                if (!hotspot.data.preview) return;
                hotspot.data.enabled = hotspot.data.previewSlot === slotId;
                if (hotspot.data.enabled && artwork) {
                    hotspot.data.title = artwork.title; hotspot.data.artist = artwork.artist;
                    hotspot.data.media_url = artwork.mediaUrl; hotspot.data.media_width = artwork.width; hotspot.data.media_height = artwork.height;
                    let image = hotspot.element.querySelector('img');
                    if (!image) { image = document.createElement('img'); image.alt = ''; hotspot.element.replaceChildren(image); }
                    image.src = artwork.mediaUrl;
                    hotspot.element.setAttribute('aria-label', artwork.title + ' preview in ' + (hotspot.data.name || 'display slot'));
                }
            });
        }
    }

    document.querySelectorAll('[data-z4-virtual-viewer]').forEach((root) => new Z4VirtualViewer(root));

    document.querySelectorAll('[data-layout-slot-form]').forEach((form) => {
        const viewer = document.querySelector('[data-layout-editor]');
        viewer?.addEventListener('z4viewerpick', (event) => {
            form.querySelector('[name="anchor_yaw"]').value = event.detail.yaw.toFixed(3);
            form.querySelector('[name="anchor_pitch"]').value = event.detail.pitch.toFixed(3);
            form.querySelector('[name="name"]')?.focus();
        });
    });

    document.querySelectorAll('[data-placement-builder]').forEach((form) => {
        const viewer = document.querySelector('[data-z4-virtual-viewer]')?.__z4VirtualViewer;
        const slot = form.querySelector('[name="slot_public_id"]');
        const artwork = form.querySelector('[name="artwork_public_id"]');
        const update = () => {
            const option = artwork?.selectedOptions?.[0];
            viewer?.setPlacementPreview(slot?.value || '', option ? { title: option.dataset.title, artist: option.dataset.artist, mediaUrl: option.dataset.mediaUrl, width: Number(option.dataset.width || 4), height: Number(option.dataset.height || 3) } : null);
        };
        slot?.addEventListener('change', update); artwork?.addEventListener('change', update); update();
    });

    document.querySelectorAll('[data-buy-now-form]').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]'); button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Store rejected the cart request.');
            location.assign(form.dataset.checkoutUrl);
        } catch (error) {
            const output = form.querySelector('[data-purchase-error]'); if (output) output.textContent = error.message || 'Purchasing is temporarily unavailable.';
            button.disabled = false;
        }
    }));

    document.querySelectorAll('dialog[data-placement-dialog]').forEach((dialog) => {
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
        dialog.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('cancel', () => dialog.close());
    });
})();
