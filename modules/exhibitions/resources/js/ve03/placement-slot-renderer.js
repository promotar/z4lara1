import * as THREE from 'three';
import { createArtworkDisplay } from './artwork-renderer.js';
import { disposeObjectTree } from './resource-disposer.js';

const COLORS = Object.freeze({
    available: 0x28a66d,
    selected_by_you: 0x28a66d,
    occupied_by_you: 0x187a50,
    held: 0xb2141b,
    incompatible: 0xb2141b,
    occupied: 0xb2141b,
    sold: 0x7b1113,
});

const BLOCKED_STATES = new Set(['held', 'incompatible', 'occupied', 'sold']);
const BLOCKED_FOCUS_COLOR = 0xd51f2a;
const PLAYER_COLLIDER_RADIUS = 0.3;
const centimeters = (meters) => Math.round(Number(meters || 0) * 100);
const physicalDimensions = (artwork = {}) => {
    const width = Number(artwork.dimensions?.width_m);
    const height = Number(artwork.dimensions?.height_m);
    if (!Number.isFinite(width) || width <= 0 || !Number.isFinite(height) || height <= 0) {
        throw new Error('Artwork dimensions are missing. Add a valid width and height before placing it in a 3D exhibition.');
    }
    return { width, height };
};

function freestandingSupport(slot, slotWidth) {
    if (slot?.display?.support !== 'freestanding-panel') return null;
    const color = String(slot.display.support_color || '#d8c9b4');
    const panelWidth = Math.max(1.72, slotWidth + 0.38);
    const group = new THREE.Group();
    group.name = `VE03_SLOT_SUPPORT_${slot.code}`;
    const material = new THREE.MeshStandardMaterial({ color, roughness: 0.76, metalness: 0.02 });
    const panel = new THREE.Mesh(new THREE.BoxGeometry(panelWidth, 3.25, 0.11), material);
    panel.position.set(0, -0.095, -0.09);
    panel.userData.ve03PlacementSupport = true;
    const foot = new THREE.Mesh(
        new THREE.BoxGeometry(panelWidth + 0.28, 0.12, 0.52),
        new THREE.MeshStandardMaterial({ color: 0x2a2522, roughness: 0.64, metalness: 0.08 }),
    );
    foot.position.set(0, -1.66, -0.04);
    foot.userData.ve03PlacementSupport = true;
    group.add(panel, foot);
    return group;
}

export const dimensionRangeLabel = (limits = {}) => {
    const maximumWidth = Number(limits.max_width_m || 0);
    const maximumHeight = Number(limits.max_height_m || 0);
    return `Space up to ${centimeters(maximumWidth)} × ${centimeters(maximumHeight)} cm`;
};

export function placementActivationDistance(viewer = {}) {
    const configured = Math.max(0.5, Number(viewer.interaction_distance || 3));
    const safetyDistance = Math.max(0.6, Number(viewer.artwork_safety_distance || 1));
    // The accepted physical barrier is a full metre deep. Rapier stops the
    // camera when the player's 30 cm capsule touches its front edge, so the
    // action ray must reach that same boundary plus a small aiming tolerance.
    return Math.min(configured, Math.max(0.9, safetyDistance + PLAYER_COLLIDER_RADIUS + 0.15));
}

export function allowsPlacementCursorMovement(placementMode, confirmationOpen = false) {
    // Visitor artwork controls and artist placement controls both release
    // pointer lock so their on-screen action remains clickable. Keyboard
    // movement must stay active in either mode until confirmation blocks it.
    return !confirmationOpen;
}

export function placementInterfaceBlocksMovement(placementMode, confirmationOpen = false, artworkPickerOpen = false) {
    return Boolean(placementMode && (confirmationOpen || artworkPickerOpen));
}

function badgeTexture(label, background = '#7b1113') {
    if (typeof document === 'undefined') {
        const texture = new THREE.DataTexture(new Uint8Array([123, 17, 19, 255]), 1, 1, THREE.RGBAFormat);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.needsUpdate = true;
        return texture;
    }
    const canvas = document.createElement('canvas');
    canvas.width = 768;
    canvas.height = 160;
    const context = canvas.getContext('2d');
    context.fillStyle = background;
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#ffffff';
    context.font = label.length > 24 ? '700 42px system-ui, sans-serif' : '700 58px system-ui, sans-serif';
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(label, canvas.width / 2, canvas.height / 2);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.needsUpdate = true;
    return texture;
}
const visualState = (state) => {
    const blocked = BLOCKED_STATES.has(state);
    return {
        blocked,
        color: COLORS[state],
        opacity: ['available', 'selected_by_you'].includes(state) ? 0.045 : 0.08,
        borderOpacity: 0.98,
    };
};

function badgeDescriptor(slot) {
    return ({
        available: { label: 'Available', background: '#187a50' },
        selected_by_you: { label: 'Reserved for you', background: '#187a50' },
        occupied_by_you: { label: 'Your artwork · published', background: '#187a50' },
        held: { label: 'Reserved · another artist', background: '#9f1118' },
        occupied: { label: 'Occupied · another artist', background: '#9f1118' },
        sold: { label: 'SOLD · unavailable', background: '#6f0e11' },
        incompatible: { label: dimensionRangeLabel(slot.limits), background: '#7b1113' },
    })[slot.state] || null;
}

export class PlacementSlotRenderer {
    constructor(scene, rooms = [], artwork = {}, options = {}) {
        this.scene = scene;
        this.group = new THREE.Group();
        this.group.name = 'VE03_ARTIST_PLACEMENT_SLOTS';
        this.targets = [];
        this.focusedTarget = null;
        this.raycaster = new THREE.Raycaster();
        this.preview = null;
        this.previewTarget = null;
        this.previewRequest = 0;
        this.textureLoader = new THREE.TextureLoader();
        this.onPreviewError = typeof options.onPreviewError === 'function' ? options.onPreviewError : null;
        const { width, height } = physicalDimensions(artwork);
        for (const room of rooms) {
            for (const slot of room.slots || []) this.add(slot, room, width, height);
        }
        scene.add(this.group);
    }

    add(slot, room, width, height) {
        if (!Object.hasOwn(COLORS, slot.state)) return;
        const appearance = visualState(slot.state);
        const blocked = appearance.blocked;
        const slotWidth = Math.max(0.2, Number(slot.limits?.visual_width_m || Math.min(width, Number(slot.limits?.max_width_m || width))));
        const slotHeight = Math.max(0.2, Number(slot.limits?.visual_height_m || Math.min(height, Number(slot.limits?.max_height_m || height))));
        const material = new THREE.MeshBasicMaterial({
            color: appearance.color, transparent: true,
            opacity: appearance.opacity,
            side: THREE.DoubleSide, depthWrite: false, toneMapped: false,
        });
        const plane = new THREE.Mesh(new THREE.PlaneGeometry(slotWidth, slotHeight), material);
        plane.position.fromArray(slot.transform.position.map(Number));
        plane.quaternion.fromArray(slot.transform.quaternion.map(Number));
        plane.translateZ(0.045);
        plane.userData.placementSlot = { ...slot, room_title: room.title };
        plane.renderOrder = 20;
        const border = new THREE.LineSegments(
            new THREE.EdgesGeometry(plane.geometry),
            new THREE.LineBasicMaterial({ color: appearance.color, transparent: true, opacity: appearance.borderOpacity, toneMapped: false }),
        );
        plane.add(border);
        plane.userData.slotVisual = {
            border,
            statusBadge: null,
            badgeKey: null,
            dimensionLabel: null,
            baseColor: appearance.color,
            baseOpacity: appearance.opacity,
            baseBorderOpacity: appearance.borderOpacity,
            blocked,
        };
        const support = freestandingSupport(slot, slotWidth);
        if (support) {
            plane.add(support);
            plane.userData.slotVisual.support = support;
        }
        this.syncBadge(plane, slot);
        this.group.add(plane);
        this.targets.push(plane);
    }

    update(rooms = [], artwork = {}) {
        const slots = new Map();
        const { width: artworkWidth, height: artworkHeight } = physicalDimensions(artwork);
        for (const room of rooms) {
            for (const slot of room.slots || []) slots.set(slot.code, { ...slot, room_title: room.title });
        }
        for (const target of this.targets) {
            const current = target.userData.placementSlot;
            const next = slots.get(current.code);
            if (!next || !Object.hasOwn(COLORS, next.state)) continue;
            target.userData.placementSlot = next;
            const appearance = visualState(next.state);
            Object.assign(target.userData.slotVisual, {
                baseColor: appearance.color,
                baseOpacity: appearance.opacity,
                baseBorderOpacity: appearance.borderOpacity,
                blocked: appearance.blocked,
            });
            const width = Math.max(0.2, Number(next.limits?.visual_width_m || Math.min(artworkWidth, Number(next.limits?.max_width_m || artworkWidth))));
            const height = Math.max(0.2, Number(next.limits?.visual_height_m || Math.min(artworkHeight, Number(next.limits?.max_height_m || artworkHeight))));
            if (Math.abs(Number(target.geometry.parameters.width) - width) > 0.0001
                || Math.abs(Number(target.geometry.parameters.height) - height) > 0.0001) {
                target.geometry.dispose();
                target.geometry = new THREE.PlaneGeometry(width, height);
                target.userData.slotVisual.border.geometry.dispose();
                target.userData.slotVisual.border.geometry = new THREE.EdgesGeometry(target.geometry);
                target.userData.slotVisual.badgeKey = null;
            }
            this.syncBadge(target, next);
            this.restore(target);
        }
        if (this.focusedTarget) {
            const focused = this.focusedTarget;
            this.focusedTarget = null;
            this.focus(focused);
        }
    }

    syncBadge(target, slot) {
        const visual = target.userData.slotVisual;
        const width = Number(target.geometry.parameters.width || 0.8);
        const height = Number(target.geometry.parameters.height || 0.6);
        const descriptor = badgeDescriptor(slot);
        const key = descriptor ? `${slot.state}|${descriptor.label}` : null;
        if (visual.badgeKey === key) return;
        if (visual.statusBadge) {
            target.remove(visual.statusBadge);
            visual.statusBadge.material?.map?.dispose?.();
            visual.statusBadge.material?.dispose?.();
            visual.statusBadge.geometry?.dispose?.();
        }
        visual.statusBadge = null;
        visual.badgeKey = key;
        visual.dimensionLabel = null;
        if (!descriptor) return;
        const badge = new THREE.Mesh(
            new THREE.PlaneGeometry(Math.min(1.55, Math.max(0.78, width * 1.02)), 0.18),
            new THREE.MeshBasicMaterial({
                map: badgeTexture(descriptor.label, descriptor.background), transparent: true,
                depthWrite: false, toneMapped: false,
            }),
        );
        badge.position.set(0, -(height / 2 + 0.13), 0.008);
        badge.renderOrder = 22;
        badge.userData.ve03StatusLabel = descriptor.label;
        target.add(badge);
        visual.statusBadge = badge;
        if (slot.state === 'incompatible') visual.dimensionLabel = descriptor.label;
    }

    focus(target) {
        if (this.focusedTarget === target) return;
        this.restore(this.focusedTarget);
        this.focusedTarget = target || null;
        const visual = this.focusedTarget?.userData?.slotVisual;
        if (!visual?.blocked) return;
        this.focusedTarget.material.color.setHex(BLOCKED_FOCUS_COLOR);
        this.focusedTarget.material.opacity = 0.13;
        visual.border.material.color.setHex(BLOCKED_FOCUS_COLOR);
        visual.border.material.opacity = 1;
    }

    restore(target) {
        const visual = target?.userData?.slotVisual;
        if (!visual) return;
        target.material.color.setHex(visual.baseColor);
        target.material.opacity = visual.baseOpacity;
        visual.border.material.color.setHex(visual.baseColor);
        visual.border.material.opacity = visual.baseBorderOpacity;
    }

    targetFromCamera(camera, blockers, maximumDistance = 3) {
        this.raycaster.setFromCamera(new THREE.Vector2(0, 0), camera);
        this.raycaster.far = maximumDistance;
        const slotHit = this.raycaster.intersectObjects(this.targets, false)[0];
        if (!slotHit) {
            this.focus(null);
            return null;
        }
        const physicalBlockers = (blockers || []).filter((object) => !object.userData?.ve03ArtworkSafety);
        const blockerHit = this.raycaster.intersectObjects(physicalBlockers, true)[0];
        if (blockerHit && blockerHit.distance + 0.03 < slotHit.distance) {
            this.focus(null);
            return null;
        }
        this.focus(slotHit.object);
        return { ...slotHit.object.userData.placementSlot, distance: slotHit.distance };
    }

    async previewSelection(detail) {
        const request = ++this.previewRequest;
        if (this.preview) {
            this.group.remove(this.preview.group);
            disposeObjectTree(this.preview.group);
            this.preview = null;
        }
        if (this.previewTarget) {
            this.previewTarget.visible = true;
            this.previewTarget = null;
        }
        if (!detail?.slot_code || !detail?.preview_url) return;
        const target = this.targets.find((candidate) => candidate.userData?.placementSlot?.code === detail.slot_code);
        if (!target) return;
        try {
            const texture = await this.textureLoader.loadAsync(detail.preview_url);
            if (request !== this.previewRequest) {
                texture.dispose();
                return;
            }
            const slot = target.userData.placementSlot;
            const display = createArtworkDisplay({
                id: `PREVIEW_${slot.code}`,
                slot_code: slot.code,
                room_code: '',
                dimensions: detail.dimensions,
                transform: { ...slot.transform, outward_offset_m: 0.035 },
                frame: { style: detail.frame_style, color: detail.frame_color, backing_depth_m: 0.04 },
                interactive: false,
                artwork: { sold: false },
            }, texture, { showBacking: true });
            this.preview = { ...display, texture };
            this.previewTarget = target;
            target.visible = false;
            this.group.add(display.group);
        } catch (error) {
            if (request !== this.previewRequest) return;
            this.onPreviewError?.(error);
        }
    }

    dispose() {
        this.previewRequest++;
        if (this.preview) {
            this.preview = null;
        }
        this.previewTarget = null;
        this.group.traverse((object) => {
            object.geometry?.dispose?.();
            object.material?.dispose?.();
        });
        this.scene.remove(this.group);
        this.targets = [];
        this.focusedTarget = null;
    }
}
