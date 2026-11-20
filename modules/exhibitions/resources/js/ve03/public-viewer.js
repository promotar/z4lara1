import * as THREE from 'three';
import { MeshoptDecoder } from 'three/addons/libs/meshopt_decoder.module.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { KTX2Loader } from 'three/addons/loaders/KTX2Loader.js';
import { ArtworkPopup } from './artwork-popup.js';
import { ArtworkRenderer } from './artwork-renderer.js';
import { ExhibitionCart } from './exhibition-cart.js';
import { AvailabilitySync } from './availability-sync.js';
import { addArtworkSafetyCollisions, artworkSafetyMovementAllowed, placementSlotSafetyPlacements, positionViolatesArtworkSafetyRule } from './artwork-safety-collision.js';
import { capabilityProfile, detectCapabilities } from './capability-detector.js';
import { cameraMouseAction, InputController, shouldReleasePointerLock } from './input-controller.js';
import { renderLayeredScene } from './layered-scene-renderer.js';
import { PerformanceSampler } from './performance-sampler.js';
import { allowsPlacementCursorMovement, placementActivationDistance, placementInterfaceBlocksMovement, PlacementSlotRenderer } from './placement-slot-renderer.js';
import { initializeRapier, TechnicalPhysicsWorld } from './physics-world.js';
import { chooseRequestedQualityTier } from './quality-tier.js';
import { disposeObjectTree } from './resource-disposer.js';
import { RoomVisibilityManager } from './room-visibility-manager.js';
import { ViewerStateMachine } from './viewer-state-machine.js';
import { hideVisitorEmptyFrameMeshes } from './visitor-frame-visibility.js';

const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));
const wrapRadians = (value) => Math.atan2(Math.sin(value), Math.cos(value));
const availabilityLabel = (change) => {
    if (change?.sold) return 'Sold';
    const value = String(change?.availability || '').trim().toLowerCase();
    if (value) return value.charAt(0).toUpperCase() + value.slice(1);
    return change?.interactive ? 'Available' : 'Unavailable';
};

class Ve03Telemetry {
    constructor(url, csrfToken) {
        this.url = url;
        this.csrfToken = csrfToken;
    }

    emit(event, metrics = {}) {
        if (!this.url) return;
        const body = JSON.stringify({ event, metrics });
        fetch(this.url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
            },
        }).catch(() => {});
    }
}

class Ve03PublicViewer {
    constructor(root) {
        this.root = root;
        this.canvas = root.querySelector('[data-ve03-canvas]');
        this.status = root.querySelector('[data-ve03-status]');
        this.progress = root.querySelector('[data-ve03-progress]');
        this.enterPanel = root.querySelector('[data-ve03-enter-panel]');
        this.enterButton = root.querySelector('[data-ve03-enter]');
        this.pausePanel = root.querySelector('[data-ve03-pause]');
        this.errorPanel = root.querySelector('[data-ve03-error]');
        this.errorMessage = root.querySelector('[data-ve03-error-message]');
        this.roomOutput = root.querySelector('[data-ve03-room]');
        this.qualitySelect = root.querySelector('[data-ve03-quality]');
        this.artworkIndicator = root.querySelector('[data-ve03-artwork-indicator]');
        this.artworkDialog = root.querySelector('[data-ve03-artwork-popup]');
        this.liveStatus = root.querySelector('[data-ve03-live-status]');
        this.config = JSON.parse(root.querySelector('[data-ve03-config]')?.textContent || '{}');
        this.telemetry = new Ve03Telemetry(this.config.telemetry_url, this.config.csrf_token);
        this.machine = new ViewerStateMachine();
        this.clock = new THREE.Timer();
        this.clock.connect(document);
        this.performance = new PerformanceSampler();
        this.fixedAccumulator = 0;
        this.lastRenderAt = 0;
        this.yaw = 0;
        this.pitch = 0;
        this.currentRoom = null;
        this.recoveryAttempts = 0;
        this.renderedFrames = 0;
        this.adaptiveSampleComplete = false;
        this.activeArtwork = null;
        this.artworkCursorMode = false;
        this.manualCursorMode = false;
        this.suppressedArtworkCursorId = null;
        this.suppressedPlacementSlotCode = null;
        this.disposed = false;
        this.machine.subscribe(({ current }) => this.reflectState(current));
        this.bindLifecycle();
        this.boot();
    }

    async boot() {
        try {
            this.machine.transition('capability_check');
            this.bindCart();
            const probe = document.createElement('canvas');
            this.capabilities = detectCapabilities(probe);
            if (!this.capabilities.supported) throw new Error('WebGL2 is not available on this device.');
            this.profile = capabilityProfile(this.capabilities);
            this.quality = chooseRequestedQualityTier(this.profile, this.qualitySelect?.value || 'auto');
            this.createRenderer();
            this.machine.transition('loading_environment');
            this.setStatus('Loading the 3D exhibition…');
            this.manifest = await this.fetchManifest();
            await this.loadEnvironment();
            this.bindInput();
            this.availabilitySync = new AvailabilitySync(this.config, (payload, source) => this.applyAvailability(payload, source));
            this.availabilitySync.start();
            this.machine.transition('ready_to_enter');
            this.enterButton.disabled = false;
            this.setStatus('The exhibition is ready. Select Enter Exhibition.');
            this.telemetry.emit('viewer_ready', {
                quality_tier: this.quality.level,
                load_duration_ms: Math.round(this.loadDuration),
                ...this.performance.snapshot(this.renderer, { loadDuration: this.loadDuration }),
            });
            this.animate();
        } catch (error) {
            this.fail(error, 'boot_failed');
        }
    }

    createRenderer() {
        const context = this.canvas.getContext('webgl2', {
            alpha: false,
            antialias: this.quality.antialias,
            powerPreference: 'high-performance',
            preserveDrawingBuffer: false,
        });
        if (!context) throw new Error('The WebGL2 rendering context could not be created.');
        this.renderer = new THREE.WebGLRenderer({ canvas: this.canvas, context, antialias: this.quality.antialias });
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1;
        this.renderer.shadowMap.enabled = false;
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x151313);
        this.camera = new THREE.PerspectiveCamera(70, 1, 0.05, 300);
        this.camera.rotation.order = 'YXZ';
        this.scene.add(new THREE.HemisphereLight(0xffffff, 0x403838, 0.7));
        const key = new THREE.DirectionalLight(0xffffff, 1.1);
        key.position.set(4, 8, 2);
        this.scene.add(key);
        this.applyQuality();
        this.resize();
    }

    async fetchManifest() {
        const response = await fetch(this.config.manifest_url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error(`The runtime manifest returned HTTP ${response.status}.`);
        const manifest = await response.json();
        if (manifest?.schema_version !== 1
            || !manifest.environment?.url
            || !manifest.environment?.collision_url
            || !Array.isArray(manifest.spawn?.position)
            || manifest.spawn.position.length !== 3
            || !Array.isArray(manifest.rooms)
            || manifest.rooms.length < 2) {
            throw new Error('The runtime manifest is incomplete.');
        }
        return manifest;
    }

    async loadEnvironment(restoredPosition = null) {
        const started = performance.now();
        await initializeRapier();
        this.ktx2Loader = new KTX2Loader()
            .setTranscoderPath(this.config.transcoder_base_url)
            .setWorkerLimit(this.quality.level === 'low' ? 1 : 2)
            .detectSupport(this.renderer);
        const loader = new GLTFLoader()
            .setKTX2Loader(this.ktx2Loader)
            .setMeshoptDecoder(MeshoptDecoder);
        const progress = { environment: 0, collision: 0 };
        const updateProgress = (key, event) => {
            progress[key] = event.total > 0 ? event.loaded / event.total : progress[key];
            const percentage = Math.round((progress.environment * 0.85 + progress.collision * 0.15) * 100);
            this.progress.value = percentage;
            this.progress.textContent = `${percentage}%`;
            this.setStatus(`Loading the 3D exhibition: ${percentage}%`);
        };
        const [environmentGltf, collisionGltf] = await Promise.all([
            loader.loadAsync(this.manifest.environment.url, (event) => updateProgress('environment', event)),
            loader.loadAsync(this.manifest.environment.collision_url, (event) => updateProgress('collision', event)),
        ]);

        this.environment = environmentGltf.scene;
        this.environment.name = 'VE03_RUNTIME_ENVIRONMENT';
        hideVisitorEmptyFrameMeshes(
            this.environment,
            this.manifest.environment.hidden_empty_frame_materials || [],
            this.manifest.placements || [],
            Boolean(this.manifest.placement_mode),
        );
        this.scene.add(this.environment);
        this.environment.updateMatrixWorld(true);
        this.performance.observeScene(this.environment);
        this.collisionEnvironment = collisionGltf.scene;
        this.collisionEnvironment.updateMatrixWorld(true);
        this.collisionMeshes = [];
        this.collisionEnvironment.traverse((object) => {
            if (object.isMesh && object.geometry) this.collisionMeshes.push(object);
        });
        const safetyPlacements = this.manifest.placement_mode
            ? placementSlotSafetyPlacements(this.manifest.rooms, this.manifest.artwork)
            : (this.manifest.placements || []);
        this.artworkSafetyPlacements = safetyPlacements;
        this.artworkSafetyDistance = Number(this.manifest.viewer.artwork_safety_distance || 1);
        this.artworkSafetyMeshes = addArtworkSafetyCollisions(
            this.collisionEnvironment,
            safetyPlacements,
            {
                distance: Number(this.manifest.viewer.artwork_safety_distance || 1),
                sideMargin: Number(this.manifest.viewer.artwork_safety_side_margin || 0.4),
            },
        );
        this.collisionMeshes.push(...this.artworkSafetyMeshes);
        if (this.collisionMeshes.length === 0) throw new Error('The published collision asset has no collision meshes.');

        this.bounds = new THREE.Box3().setFromObject(this.collisionEnvironment);
        const [x, y, z] = this.manifest.spawn.position.map(Number);
        this.spawn = { x, y, z };
        this.physics = new TechnicalPhysicsWorld(this.collisionEnvironment, this.spawn, this.bounds, {
            collisionMeshes: this.collisionMeshes,
            player: {
                walkSpeed: Number(this.manifest.viewer.walk_speed || 2.2),
                maxStep: Number(this.manifest.viewer.max_step_height || 0.28),
                maxSlope: Number(this.manifest.viewer.max_slope_degrees || 42) * Math.PI / 180,
            },
        });
        const returnContext = !restoredPosition && this.config.return_context;
        const returnPosition = Array.isArray(returnContext?.position) && returnContext.position.length === 3
            ? { x: Number(returnContext.position[0]), y: Number(returnContext.position[1]), z: Number(returnContext.position[2]) }
            : null;
        const requestedPosition = restoredPosition || returnPosition;
        const restoreAccepted = requestedPosition
            && !positionViolatesArtworkSafetyRule(requestedPosition, this.artworkSafetyPlacements, this.artworkSafetyDistance)
            && this.physics.acceptsCameraPosition(requestedPosition);
        if (restoreAccepted) this.physics.setCameraPosition(requestedPosition);
        const cameraPosition = this.physics.cameraPosition();
        this.camera.position.set(cameraPosition.x, cameraPosition.y, cameraPosition.z);
        this.previousPhysicsPosition = this.camera.position.clone();
        this.currentPhysicsPosition = this.camera.position.clone();
        this.yaw = restoreAccepted
            ? (restoredPosition?.yaw ?? Number(returnContext?.yaw ?? this.manifest.spawn.yaw ?? 0))
            : Number(this.manifest.spawn.yaw ?? 0);
        this.pitch = restoreAccepted
            ? (restoredPosition?.pitch ?? Number(returnContext?.pitch ?? this.manifest.spawn.pitch ?? 0))
            : Number(this.manifest.spawn.pitch ?? 0);
        this.roomVisibility = new RoomVisibilityManager(this.environment, this.manifest.rooms);
        this.artworkRenderer = new ArtworkRenderer(this.scene);
        this.artworkLoad = await this.artworkRenderer.load(this.manifest.placements || []);
        if (this.manifest.placement_mode) {
            this.placementSlots = new PlacementSlotRenderer(this.scene, this.manifest.rooms, this.manifest.artwork, {
                onPreviewError: () => this.root.dispatchEvent(new CustomEvent('ve03-placement-preview-error', {
                    bubbles: true,
                    detail: { message: 'The artwork image could not be loaded for preview. Choose another image or try again.' },
                })),
            });
        }
        this.applyQuality();
        this.updateRoom();
        this.loadDuration = performance.now() - started;
        this.progress.value = 100;
    }

    bindInput() {
        this.input = new InputController(
            this.root,
            this.canvas,
            (yawDelta, pitchDelta) => this.look(yawDelta, pitchDelta),
            () => this.activateArtwork(),
            () => this.handlePointerLockReleased(),
            () => this.toggleFullscreen(),
            () => this.dismissPlacementTargetForMovement(),
        );
        this.artworkPopup = new ArtworkPopup(
            this.root,
            this.artworkDialog,
            (reason) => this.onArtworkPopupClosed(reason),
            this.cart?.canAdd() ? (payload) => this.cart.add(payload) : null,
        );
        this.enterButton.addEventListener('click', () => this.enter());
        this.root.querySelector('[data-ve03-resume]')?.addEventListener('click', () => this.enter());
        this.canvas.addEventListener('click', () => {
            const confirmationOpen = this.root.closest('[data-ve03-placement]')
                ?.querySelector('[data-confirm-panel]')?.hidden === false;
            if (confirmationOpen) return;
            if (this.machine.state === 'active' && this.manualCursorMode) {
                this.resumeManualCameraControl();
                return;
            }
            if (this.machine.state === 'paused') {
                this.enter();
                return;
            }
            if (this.machine.state === 'active' && this.artworkCursorMode) {
                if (this.activePlacementSlot) {
                    this.suppressedPlacementSlotCode = String(this.activePlacementSlot.code || '');
                    this.activePlacementSlot = null;
                } else {
                    this.suppressedArtworkCursorId = String(this.activeArtwork?.id || '');
                }
                this.artworkCursorMode = false;
                this.artworkIndicator.hidden = true;
                this.canvas.requestPointerLock?.();
                this.setStatus('Mouse look resumed. Aim away and return to reopen the artwork control.');
            }
        });
        this.onCanvasMouseDown = (event) => {
            const action = cameraMouseAction(event.button, this.manualCursorMode);
            if (action === 'release') {
                if (this.releaseManualCameraControl()) event.preventDefault();
                return;
            }
            if (action === 'resume') {
                event.preventDefault();
                this.resumeManualCameraControl();
            }
        };
        this.canvas.addEventListener('mousedown', this.onCanvasMouseDown);
        this.onCanvasContextMenu = (event) => {
            if (this.manualCursorMode || this.releaseManualCameraControl()) event.preventDefault();
        };
        this.canvas.addEventListener('contextmenu', this.onCanvasContextMenu);
        this.root.querySelectorAll('[data-ve03-pause-fullscreen]').forEach((button) => {
            button.addEventListener('click', () => this.toggleFullscreen());
        });
        this.artworkIndicator?.addEventListener('click', () => this.activateArtwork());
        this.onPlacementManifestUpdated = (event) => {
            this.placementSlots?.update(event.detail?.rooms || [], event.detail?.artwork || {});
        };
        this.root.addEventListener('ve03-placement-manifest-updated', this.onPlacementManifestUpdated);
        this.onPlacementResume = (event) => {
            this.suppressedPlacementSlotCode = String(event.detail?.slot_code || '');
            this.activePlacementSlot = null;
            this.artworkCursorMode = false;
            this.artworkIndicator.hidden = true;
            this.input?.clear();
            this.manualCursorMode = false;
            if (this.machine.state === 'paused') {
                this.enter();
                return;
            }
            const request = this.canvas.requestPointerLock?.();
            request?.catch?.(() => this.setStatus('Select the exhibition to continue exploring.'));
        };
        this.root.addEventListener('ve03-placement-resume', this.onPlacementResume);
        this.onPlacementFramePreview = (event) => {
            this.placementSlots?.previewSelection?.(event.detail || null);
        };
        this.root.addEventListener('ve03-placement-frame-preview', this.onPlacementFramePreview);
        this.updateFullscreenControls();
        this.root.querySelector('[data-ve03-retry]')?.addEventListener('click', () => location.reload());
        this.qualitySelect?.addEventListener('change', () => {
            this.quality = chooseRequestedQualityTier(this.profile, this.qualitySelect.value);
            this.applyQuality();
            this.setStatus(`Quality set to ${this.quality.level}.`);
        });
    }

    bindCart() {
        if (this.cart) return;
        this.cart = new ExhibitionCart(this.root, this.config, {
            onOpened: () => this.onCartOpened(),
            onClosed: () => this.onCartClosed(),
            onAdded: () => this.telemetry.emit('cart_item_added', { room_code: this.currentRoom || '' }),
            onRemoved: () => this.telemetry.emit('cart_item_removed', { room_code: this.currentRoom || '' }),
            onCheckout: () => this.telemetry.emit('checkout_started', { room_code: this.currentRoom || '' }),
            returnContext: () => this.cartReturnContext(),
        });
    }

    bindLifecycle() {
        this.onResize = () => this.resize();
        this.onContextLost = (event) => {
            event.preventDefault();
            if (this.machine.can('context_lost')) this.machine.transition('context_lost');
            this.input?.clear();
            this.telemetry.emit('context_lost', this.performance.snapshot(this.renderer));
            this.setStatus('Graphics context lost. Recovering…');
        };
        this.onContextRestored = () => this.recoverContext();
        this.onPageHide = () => this.dispose();
        this.onFullscreenChange = () => {
            this.updateFullscreenControls();
            this.resize();
        };
        addEventListener('resize', this.onResize);
        addEventListener('orientationchange', this.onResize);
        addEventListener('pagehide', this.onPageHide, { once: true });
        document.addEventListener('fullscreenchange', this.onFullscreenChange);
        this.canvas.addEventListener('webglcontextlost', this.onContextLost);
        this.canvas.addEventListener('webglcontextrestored', this.onContextRestored);
    }

    reflectState(state) {
        this.root.dataset.viewerState = state;
        this.enterPanel.hidden = state !== 'ready_to_enter';
        this.pausePanel.hidden = state !== 'paused' && state !== 'context_lost' && state !== 'recovering';
        this.errorPanel.hidden = state !== 'fatal_error';
        this.root.querySelectorAll('[data-ve03-mobile-control]').forEach((control) => {
            control.hidden = state !== 'active' || !this.profile?.mobile;
        });
        if (state !== 'active' && this.artworkIndicator) this.artworkIndicator.hidden = true;
        if (this.root.querySelector('[data-ve03-loading]')) {
            this.root.querySelector('[data-ve03-loading]').hidden = !['booting', 'capability_check', 'loading_environment'].includes(state);
        }
    }

    enter() {
        if (!['ready_to_enter', 'paused'].includes(this.machine.state)) return;
        this.artworkCursorMode = false;
        this.manualCursorMode = false;
        this.suppressedArtworkCursorId = null;
        this.suppressedPlacementSlotCode = null;
        let activated = false;
        const activate = () => {
            if (activated) return;
            activated = true;
            if (this.machine.can('active')) this.machine.transition('active');
            this.clock.reset();
            this.setStatus('Exploration active. Press Escape to pause.');
            this.telemetry.emit('exhibition_entered', { quality_tier: this.quality.level, room_code: this.currentRoom || '' });
        };
        const activateKeyboardFallback = () => {
            activate();
            this.setStatus('Keyboard exploration active. Select the canvas to retry mouse look.');
        };
        if (this.profile.mobile) {
            activate();
            return;
        }
        const request = this.canvas.requestPointerLock?.();
        if (request instanceof Promise) {
            const timeout = setTimeout(activateKeyboardFallback, 750);
            request.then(() => {
                clearTimeout(timeout);
                activate();
            }).catch(() => {
                clearTimeout(timeout);
                activateKeyboardFallback();
            });
        } else {
            activate();
        }
    }

    pause(message) {
        if (this.machine.state !== 'active') return;
        this.artworkCursorMode = false;
        this.manualCursorMode = false;
        if (this.machine.can('paused')) this.machine.transition('paused');
        this.input?.clear();
        this.setStatus(message);
    }

    look(yawDelta, pitchDelta) {
        if (this.machine.state !== 'active') return;
        this.yaw = wrapRadians(this.yaw - yawDelta);
        this.pitch = clamp(this.pitch - pitchDelta, -Math.PI * 0.47, Math.PI * 0.47);
    }

    animate(timestamp = performance.now()) {
        if (this.disposed) return;
        requestAnimationFrame((next) => this.animate(next));
        this.clock.update(timestamp);
        const delta = Math.min(0.1, this.clock.getDelta());
        this.fixedAccumulator += delta;
        if (this.machine.state === 'active') {
            while (this.fixedAccumulator >= 1 / 60) {
                const confirmationOpen = this.placementConfirmationOpen();
                const artworkPickerOpen = this.root.closest('[data-ve03-placement]')
                    ?.classList.contains('is-artwork-picker-open') === true;
                const interfaceBlocksMovement = placementInterfaceBlocksMovement(
                    this.manifest.placement_mode,
                    confirmationOpen,
                    artworkPickerOpen,
                );
                const cursorMovement = allowsPlacementCursorMovement(this.manifest.placement_mode, confirmationOpen);
                if (!interfaceBlocksMovement && (!this.artworkCursorMode || cursorMovement)) {
                    this.yaw = wrapRadians(this.yaw + this.input.yawDelta(1 / 60));
                }
                const direction = interfaceBlocksMovement || (this.artworkCursorMode && !cursorMovement)
                    ? { forward: 0, strafe: 0 }
                    : this.input.direction();
                let position = this.physics.step(direction, this.yaw, 1 / 60);
                if (!artworkSafetyMovementAllowed(
                    this.currentPhysicsPosition,
                    position,
                    this.artworkSafetyPlacements,
                    this.artworkSafetyDistance,
                )) {
                    this.physics.setCameraPosition(this.currentPhysicsPosition);
                    position = {
                        x: this.currentPhysicsPosition.x,
                        y: this.currentPhysicsPosition.y,
                        z: this.currentPhysicsPosition.z,
                    };
                }
                this.previousPhysicsPosition.copy(this.currentPhysicsPosition);
                this.currentPhysicsPosition.set(position.x, position.y, position.z);
                this.fixedAccumulator -= 1 / 60;
            }
            this.camera.position.lerpVectors(
                this.previousPhysicsPosition,
                this.currentPhysicsPosition,
                clamp(this.fixedAccumulator / (1 / 60), 0, 1),
            );
            this.updateRoom();
            this.updateArtworkTarget();
            this.artworkRenderer?.updateNearTextures(this.camera.position);
        } else {
            this.fixedAccumulator = 0;
        }
        this.camera.rotation.set(this.pitch, this.yaw, 0);
        const minimumFrameInterval = 1000 / this.quality.maxFps;
        if (timestamp - this.lastRenderAt >= minimumFrameInterval) {
            this.renderScene();
            this.performance.frame(timestamp);
            this.renderedFrames += 1;
            this.adaptQualityOnce();
            this.lastRenderAt = timestamp;
        }
    }

    renderScene() {
        renderLayeredScene(this.renderer, this.scene, this.camera);
    }

    updateArtworkTarget() {
        if (!this.artworkRenderer || !this.artworkIndicator) return;
        if (this.manifest.placement_mode) {
            const slotTarget = this.placementSlots?.targetFromCamera(
                this.camera,
                this.collisionMeshes,
                placementActivationDistance(this.manifest.viewer),
            );
            if (slotTarget) {
                const suppressed = String(slotTarget.code) === this.suppressedPlacementSlotCode;
                if (suppressed) {
                    this.activePlacementSlot = null;
                    this.activeArtwork = null;
                    this.artworkIndicator.hidden = true;
                    return;
                }
                this.activePlacementSlot = slotTarget;
                this.activeArtwork = null;
                this.artworkIndicator.hidden = false;
                this.artworkIndicator.dataset.slotState = slotTarget.state;
                const blockedMessage = {
                    held: 'Another artist is using this display now',
                    occupied_by_you: 'Your artwork is published in this display',
                    occupied: 'This display already contains another artist\u2019s artwork',
                    sold: 'This display contains a sold artwork',
                    incompatible: 'The safe free wall space is too tight for this artwork',
                }[slotTarget.state];
                this.artworkIndicator.textContent = blockedMessage || 'Select this display (E)';
                this.artworkIndicator.setAttribute('aria-disabled', String(!slotTarget.selectable));
                if (!this.profile.mobile && !this.artworkCursorMode && document.pointerLockElement === this.canvas) {
                    this.artworkCursorMode = true;
                    document.exitPointerLock?.();
                    this.setStatus(slotTarget.reason
                        ? `${slotTarget.reason} You can keep moving with the keyboard.`
                        : 'Display in range. Keep moving with the keyboard, or select it with the mouse.');
                }
                return;
            }
            const leftPlacementZone = this.artworkCursorMode && Boolean(this.activePlacementSlot);
            this.activePlacementSlot = null;
            this.suppressedPlacementSlotCode = null;
            delete this.artworkIndicator.dataset.slotState;
            this.artworkIndicator.setAttribute('aria-disabled', 'false');
            this.artworkIndicator.textContent = 'Select this display (E)';
            if (leftPlacementZone && !this.placementConfirmationOpen()) {
                this.artworkCursorMode = false;
                this.artworkIndicator.hidden = true;
                const request = this.canvas.requestPointerLock?.();
                request?.catch?.(() => this.setStatus('Display range left. Select the exhibition to restore mouse look.'));
            }
        }
        const target = this.artworkRenderer.targetFromCamera(
            this.camera,
            placementActivationDistance(this.manifest.viewer),
        );
        const leftArtworkZone = this.artworkCursorMode && Boolean(this.activeArtwork);
        this.activeArtwork = target;
        if (!target) {
            this.suppressedArtworkCursorId = null;
            this.artworkIndicator.hidden = true;
            if (leftArtworkZone) {
                this.artworkCursorMode = false;
                const request = this.canvas.requestPointerLock?.();
                request?.catch?.(() => this.setStatus('Artwork range left. Select the exhibition to restore mouse look.'));
                this.setStatus('Artwork range left. Mouse look restored.');
            }
            return;
        }
        const suppressed = String(target.id) === this.suppressedArtworkCursorId;
        this.artworkIndicator.hidden = suppressed;
        if (!suppressed
            && !this.profile.mobile
            && !this.artworkCursorMode
            && document.pointerLockElement === this.canvas) {
            this.artworkCursorMode = true;
            this.input?.clear();
            document.exitPointerLock?.();
            this.setStatus('Artwork in range. Use the mouse to select View artwork, or press E.');
        }
    }

    handlePointerLockReleased() {
        if (this.manualCursorMode) return;
        const confirmationOpen = this.placementConfirmationOpen();
        const artworkPickerOpen = this.root.closest('[data-ve03-placement]')
            ?.classList.contains('is-artwork-picker-open');
        if (confirmationOpen || artworkPickerOpen) return;
        if (this.artworkCursorMode && (this.activeArtwork?.interactive || this.activePlacementSlot)) return;
        this.pause('Movement paused.');
    }

    releaseManualCameraControl() {
        const releasePointer = shouldReleasePointerLock({
            active: this.machine.state === 'active',
            mobile: this.profile.mobile,
            pointerLocked: document.pointerLockElement === this.canvas,
        });
        if (!releasePointer) return false;

        this.manualCursorMode = true;
        this.input?.clear();
        if (this.artworkIndicator) this.artworkIndicator.hidden = true;
        document.exitPointerLock?.();
        this.setStatus('Mouse cursor released. Left-click the exhibition to resume camera control.');
        return true;
    }

    resumeManualCameraControl() {
        if (this.machine.state !== 'active' || !this.manualCursorMode) return false;

        this.manualCursorMode = false;
        if (this.activePlacementSlot) {
            this.suppressedPlacementSlotCode = String(this.activePlacementSlot.code || '');
            this.activePlacementSlot = null;
        } else if (this.activeArtwork) {
            this.suppressedArtworkCursorId = String(this.activeArtwork.id || '');
        }
        if (this.artworkIndicator) this.artworkIndicator.hidden = true;
        try {
            const request = this.canvas.requestPointerLock?.();
            if (request instanceof Promise) {
                request.then(() => this.setStatus('Camera control resumed.')).catch(() => {
                    this.manualCursorMode = true;
                    this.setStatus('Left-click the exhibition again to resume camera control.');
                });
            } else {
                this.setStatus('Camera control resumed.');
            }
        } catch {
            this.manualCursorMode = true;
            this.setStatus('Left-click the exhibition again to resume camera control.');
        }
        return true;
    }

    dismissPlacementTargetForMovement() {
        const confirmationOpen = this.placementConfirmationOpen();
        if (!this.manifest?.placement_mode
            || this.machine.state !== 'active'
            || !this.artworkCursorMode
            || !this.activePlacementSlot
            || confirmationOpen) return;

        this.suppressedPlacementSlotCode = String(this.activePlacementSlot.code || '');
        this.activePlacementSlot = null;
        this.artworkCursorMode = false;
        this.artworkIndicator.hidden = true;
        const request = this.canvas.requestPointerLock?.();
        request?.catch?.(() => this.setStatus('Movement resumed. Select the exhibition to restore mouse look.'));
        this.setStatus('Movement resumed. Aim at a display again whenever you want to select it.');
    }

    placementConfirmationOpen() {
        return this.root.closest('[data-ve03-placement]')
            ?.querySelector('[data-confirm-panel]')?.hidden === false;
    }

    async activateArtwork() {
        if (this.machine.state === 'active' && this.activePlacementSlot) {
            this.root.dispatchEvent(new CustomEvent('ve03-placement-slot', { detail: this.activePlacementSlot, bubbles: true }));
            return;
        }
        if (this.machine.state !== 'active' || !this.activeArtwork?.interactive) return;
        await this.availabilitySync?.refresh().catch(() => null);
        if (!this.activeArtwork?.interactive) {
            this.showLiveStatus('This artwork is no longer available to purchase. It remains on display.');
            return;
        }
        this.artworkCursorMode = false;
        this.suppressedArtworkCursorId = null;
        document.exitPointerLock?.();
        this.input?.clear();
        this.machine.transition('artwork_popup_open');
        this.artworkIndicator.hidden = true;
        this.artworkPopup.open(this.activeArtwork);
    }

    onArtworkPopupClosed(reason = 'user') {
        this.artworkCursorMode = false;
        this.activeArtwork = null;

        if (reason === 'user' && this.machine.state === 'artwork_popup_open' && this.machine.can('active')) {
            this.manualCursorMode = true;
            this.machine.transition('active');
            this.clock.reset();
            this.input?.clear();

            if (this.profile.mobile) {
                this.manualCursorMode = false;
                this.setStatus('Artwork details closed. Exploration resumed.');
                return;
            }

            try {
                const request = this.canvas.requestPointerLock?.();
                if (request instanceof Promise) {
                    request.then(() => {
                        this.manualCursorMode = false;
                        this.setStatus('Artwork details closed. Exploration resumed.');
                    }).catch(() => {
                        this.setStatus('Artwork details closed. Left-click the exhibition to resume camera control.');
                    });
                } else {
                    this.manualCursorMode = false;
                    this.setStatus('Artwork details closed. Exploration resumed.');
                }
            } catch {
                this.setStatus('Artwork details closed. Left-click the exhibition to resume camera control.');
            }
            return;
        }

        if (this.machine.state === 'artwork_popup_open') this.machine.transition('paused');
        this.manualCursorMode = false;
        this.setStatus(reason === 'availability'
            ? 'Artwork availability changed. Select Continue to resume exploration.'
            : 'Artwork details closed. Select Continue to resume exploration.');
    }

    onCartOpened() {
        if (this.artworkDialog?.open) this.artworkPopup.close('cart');
        if (this.machine.state === 'active' || this.machine.state === 'paused') {
            if (this.machine.can('cart_drawer_open')) this.machine.transition('cart_drawer_open');
        }
        document.exitPointerLock?.();
        this.input?.clear();
        this.telemetry.emit('cart_drawer_opened', { room_code: this.currentRoom || '' });
        this.setStatus('Artwork cart open. Movement is paused until you close the cart.');
        this.availabilitySync?.refresh().then(() => this.cart?.refresh()).catch(() => {});
    }

    applyAvailability(payload, source = 'poll') {
        const items = Array.isArray(payload?.items) ? payload.items : [];
        let materialChange = false;
        items.forEach((change) => {
            const placement = this.manifest?.placements?.find((item) => String(item.id) === String(change.placement_id));
            if (!placement) return;
            const wasInteractive = Boolean(placement.interactive);
            placement.interactive = Boolean(change.interactive);
            placement.artwork.sold = Boolean(change.sold);
            placement.artwork.availability = availabilityLabel(change);
            this.artworkRenderer?.markAvailability(change);
            this.updateFallbackArtwork(change);
            if (wasInteractive && !placement.interactive) materialChange = true;
            const heldInCurrentCart = this.cart?.containsVariant(placement.artwork?.store_variant_public_id) === true;
            if (this.activeArtwork
                && String(this.activeArtwork.id) === String(change.placement_id)
                && !placement.interactive
                && !heldInCurrentCart) {
                this.activeArtwork = null;
                this.artworkCursorMode = false;
                this.artworkIndicator.hidden = true;
            }
            if (!placement.interactive && !heldInCurrentCart) {
                this.artworkPopup?.closeForAvailability(change.placement_id);
            }
        });
        if (this.manifest) this.manifest.availability_version = Number(payload?.version || this.manifest.availability_version || 0);
        if (materialChange && source === 'realtime') {
            this.showLiveStatus('An artwork was just sold. It remains visible in the exhibition, and purchasing has been disabled.');
        }
        if (items.length > 0) this.cart?.refresh().catch(() => {});
    }

    updateFallbackArtwork(change) {
        const card = [...document.querySelectorAll('[data-ve03-fallback-artwork]')]
            .find((item) => item.dataset.placementId === String(change.placement_id));
        if (!card) return;
        const label = availabilityLabel(change);
        const status = card.querySelector('[data-ve03-fallback-availability]');
        const action = card.querySelector('[data-ve03-fallback-action]');
        if (status) status.textContent = label;
        if (action) action.hidden = !change.interactive;
        card.dataset.availability = label.toLowerCase();
    }

    showLiveStatus(message) {
        if (!this.liveStatus) return;
        this.liveStatus.textContent = message;
        this.liveStatus.hidden = false;
        clearTimeout(this.liveStatusTimer);
        this.liveStatusTimer = setTimeout(() => { this.liveStatus.hidden = true; }, 6500);
    }

    onCartClosed() {
        if (this.machine.state === 'cart_drawer_open' && this.machine.can('paused')) this.machine.transition('paused');
        this.setStatus('Cart closed. Select Continue to resume exploration.');
    }

    cartReturnContext() {
        const position = this.physics?.cameraPosition() || this.camera?.position || this.spawn || { x: 0, y: 0, z: 0 };
        return {
            owner_code: 'exhibitions',
            type: 'virtual_exhibition',
            reference: String(this.manifest?.exhibition?.id || ''),
            return_path: `/exhibitions/virtual/${encodeURIComponent(String(this.manifest?.exhibition?.slug || ''))}`,
            state_json: JSON.stringify({
                environment_version_id: String(this.manifest?.environment?.version_id || ''),
                room_code: String(this.currentRoom || this.manifest?.spawn?.room_code || ''),
                position: [Number(position.x), Number(position.y), Number(position.z)],
                yaw: Number(this.yaw || 0),
                pitch: Number(this.pitch || 0),
            }),
        };
    }

    adaptQualityOnce() {
        if (this.adaptiveSampleComplete || this.renderedFrames < 120 || (this.qualitySelect?.value || 'auto') !== 'auto') return;
        this.adaptiveSampleComplete = true;
        const sample = this.performance.snapshot(this.renderer);
        const requested = this.quality.level === 'high' && sample.fps_average < 45
            ? 'medium'
            : (this.quality.level === 'medium' && sample.fps_average < 28 ? 'low' : null);
        if (!requested) return;
        this.quality = chooseRequestedQualityTier(this.profile, requested);
        this.applyQuality();
        this.setStatus(`Quality automatically reduced to ${this.quality.level} for stable movement.`);
    }

    updateRoom() {
        if (!this.roomVisibility) return;
        const state = this.roomVisibility.update(this.camera.position, this.quality.aggressiveRoomCulling);
        if (!state.current) return;
        const room = this.manifest.rooms.find((candidate) => candidate.code === state.current);
        this.roomOutput.textContent = room?.title || state.current;
        if (state.changed) {
            this.currentRoom = state.current;
            this.telemetry.emit('room_entered', { room_code: state.current, quality_tier: this.quality.level });
        }
    }

    applyQuality() {
        if (!this.renderer || !this.quality) return;
        this.renderer.setPixelRatio(Math.min(devicePixelRatio || 1, this.quality.pixelRatio));
        const maximum = this.renderer.capabilities.getMaxAnisotropy();
        this.environment?.traverse((object) => {
            const materials = Array.isArray(object.material) ? object.material : [object.material];
            for (const material of materials) {
                if (!material) continue;
                for (const value of Object.values(material)) {
                    if (value?.isTexture) value.anisotropy = Math.min(maximum, this.quality.anisotropy);
                }
            }
        });
        this.resize();
        if (this.roomVisibility) this.roomVisibility.update(this.camera.position, this.quality.aggressiveRoomCulling);
    }

    resize() {
        if (!this.renderer || !this.camera) return;
        const rect = this.canvas.getBoundingClientRect();
        const width = Math.max(1, Math.round(rect.width));
        const height = Math.max(1, Math.round(rect.height));
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
    }

    async toggleFullscreen() {
        try {
            if (document.fullscreenElement === this.root) {
                await document.exitFullscreen?.();
            } else {
                await this.root.requestFullscreen?.();
            }
            this.updateFullscreenControls();
        } catch {
            this.setStatus('Fullscreen could not be opened. Press F11 for browser fullscreen.');
        }
    }

    updateFullscreenControls() {
        const active = document.fullscreenElement === this.root;
        this.root.querySelectorAll('[data-ve03-pause-fullscreen]').forEach((button) => {
            button.textContent = active ? 'Exit fullscreen (F)' : 'Fullscreen (F)';
            button.setAttribute('aria-label', active ? 'Exit fullscreen' : 'Enter fullscreen');
            button.setAttribute('aria-pressed', String(active));
        });
    }

    async recoverContext() {
        if (this.machine.state !== 'context_lost' || this.recoveryAttempts >= 1) {
            this.fail(new Error('Graphics context recovery failed.'), 'context_recovery_failed');
            return;
        }
        this.recoveryAttempts += 1;
        const position = this.physics?.cameraPosition();
        const restored = position ? { ...position, yaw: this.yaw, pitch: this.pitch } : null;
        try {
            this.machine.transition('recovering');
            this.disposeLoadedResources();
            await this.loadEnvironment(restored);
            this.machine.transition('paused');
            this.telemetry.emit('context_recovered', { room_code: this.currentRoom || '' });
            this.setStatus('Graphics restored. Select Continue to resume.');
        } catch (error) {
            this.fail(error, 'context_recovery_failed');
        }
    }

    disposeLoadedResources() {
        this.placementSlots?.dispose();
        this.placementSlots = null;
        this.artworkRenderer?.dispose();
        this.artworkRenderer = null;
        this.physics?.dispose();
        this.physics = null;
        this.roomVisibility?.restore();
        this.roomVisibility = null;
        if (this.environment) {
            this.scene.remove(this.environment);
            disposeObjectTree(this.environment);
            this.environment = null;
        }
        if (this.collisionEnvironment) {
            disposeObjectTree(this.collisionEnvironment);
            this.collisionEnvironment = null;
        }
        this.ktx2Loader?.dispose();
        this.ktx2Loader = null;
    }

    dispose() {
        if (this.disposed) return;
        this.telemetry.emit('session_closed', {
            quality_tier: this.quality?.level || 'unknown',
            room_code: this.currentRoom || '',
            ...this.performance.snapshot(this.renderer, { loadDuration: this.loadDuration }),
        });
        this.disposed = true;
        this.performance.stop();
        this.clock.dispose();
        this.input?.dispose();
        this.artworkPopup?.dispose();
        this.cart?.dispose();
        this.availabilitySync?.stop();
        clearTimeout(this.liveStatusTimer);
        if (this.onPlacementManifestUpdated) {
            this.root.removeEventListener('ve03-placement-manifest-updated', this.onPlacementManifestUpdated);
        }
        if (this.onPlacementResume) {
            this.root.removeEventListener('ve03-placement-resume', this.onPlacementResume);
        }
        if (this.onPlacementFramePreview) {
            this.root.removeEventListener('ve03-placement-frame-preview', this.onPlacementFramePreview);
        }
        if (this.onCanvasContextMenu) {
            this.canvas.removeEventListener('contextmenu', this.onCanvasContextMenu);
        }
        if (this.onCanvasMouseDown) {
            this.canvas.removeEventListener('mousedown', this.onCanvasMouseDown);
        }
        this.disposeLoadedResources();
        this.renderer?.dispose();
        removeEventListener('resize', this.onResize);
        removeEventListener('orientationchange', this.onResize);
        document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        this.canvas.removeEventListener('webglcontextlost', this.onContextLost);
        this.canvas.removeEventListener('webglcontextrestored', this.onContextRestored);
        if (this.machine.can('closed')) this.machine.transition('closed');
    }

    setStatus(message) {
        this.status.textContent = message;
    }

    fail(error, code) {
        const message = error instanceof Error ? error.message : 'The 3D exhibition could not start.';
        if (this.machine.can('fatal_error')) this.machine.transition('fatal_error', { message });
        this.input?.clear();
        this.errorMessage.textContent = `${message} Use the accessible artwork list below or retry.`;
        this.setStatus(message);
        this.telemetry.emit('viewer_error', { error_code: code, quality_tier: this.quality?.level || 'unknown' });
    }
}

const root = document.querySelector('[data-ve03-public-viewer]');
if (root) root.__ve03PublicViewer = new Ve03PublicViewer(root);
