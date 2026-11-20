import * as THREE from 'three';
import { artworkMaterialOptions } from './artwork-spike.js';
import { disposeObjectTree } from './resource-disposer.js';

const ARTWORK_LAYER = 1;
const FRAME_COLORS = Object.freeze({
    black: 0x171311,
    white: 0xf6f3ed,
    gold: 0xb18a35,
    silver: 0xb8bec4,
    taupe: 0x8b7c70,
    gray: 0x666a70,
});

export function normalizedArtworkPlacement(placement) {
    const width = Number(placement?.dimensions?.width_m);
    const height = Number(placement?.dimensions?.height_m);
    const position = placement?.transform?.position?.map(Number);
    const quaternion = placement?.transform?.quaternion?.map(Number);
    if (!(width > 0) || !(height > 0) || position?.length !== 3 || quaternion?.length !== 4) {
        throw new Error('Invalid VE-03 artwork placement.');
    }

    return {
        ...placement,
        dimensions: { width_m: width, height_m: height },
        transform: {
            position,
            quaternion,
            outward_offset_m: Math.max(0.005, Number(placement.transform.outward_offset_m || 0.035)),
        },
    };
}

function soldTexture() {
    if (typeof document === 'undefined') {
        const texture = new THREE.DataTexture(new Uint8Array([123, 17, 19, 255]), 1, 1, THREE.RGBAFormat);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.needsUpdate = true;
        return texture;
    }
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 160;
    const context = canvas.getContext('2d');
    context.fillStyle = '#7b1113';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#ffffff';
    context.font = '700 72px system-ui, sans-serif';
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText('SOLD', canvas.width / 2, canvas.height / 2);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.needsUpdate = true;
    return texture;
}

function createSoldSign(placement) {
    const width = placement.dimensions.width_m;
    const height = placement.dimensions.height_m;
    const sign = new THREE.Mesh(
        new THREE.PlaneGeometry(Math.min(0.42, Math.max(0.24, width * 0.42)), 0.13),
        new THREE.MeshBasicMaterial(artworkMaterialOptions(soldTexture())),
    );
    sign.name = `VE03_SOLD_SIGN_${placement.id}`;
    sign.position.set(0, -(height / 2 + 0.12), placement.transform.outward_offset_m + 0.002);
    sign.layers.set(ARTWORK_LAYER);
    sign.userData.ve03SoldSign = true;

    return sign;
}

function createFreestandingSupport(placement, width) {
    if (placement?.display?.support !== 'freestanding-panel') return null;
    const color = String(placement.display.support_color || '#d8c9b4');
    const panelWidth = Math.max(1.72, width + 0.38);
    const group = new THREE.Group();
    group.name = `VE03_FREESTANDING_SUPPORT_${placement.id}`;
    const panel = new THREE.Mesh(
        new THREE.BoxGeometry(panelWidth, 3.25, 0.11),
        new THREE.MeshStandardMaterial({ color, roughness: 0.76, metalness: 0.02 }),
    );
    panel.position.set(0, 1.625 - placement.transform.position[1], -0.09);
    const foot = new THREE.Mesh(
        new THREE.BoxGeometry(panelWidth + 0.28, 0.12, 0.52),
        new THREE.MeshStandardMaterial({ color: 0x2a2522, roughness: 0.64, metalness: 0.08 }),
    );
    foot.position.set(0, 0.06 - placement.transform.position[1], -0.04);
    group.add(panel, foot);
    return group;
}

export function createArtworkDisplay(rawPlacement, texture, { showBacking = true } = {}) {
    const placement = normalizedArtworkPlacement(rawPlacement);
    const { width_m: width, height_m: height } = placement.dimensions;
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 1;
    texture.needsUpdate = true;

    const group = new THREE.Group();
    group.name = `VE03_ARTWORK_${placement.id}`;
    group.position.fromArray(placement.transform.position);
    group.quaternion.fromArray(placement.transform.quaternion).normalize();
    group.userData.ve03ArtworkGroup = true;
    group.userData.roomCode = placement.room_code;

    const displaySupport = createFreestandingSupport(placement, width);
    if (displaySupport) group.add(displaySupport);

    const frameStyle = ['none', 'thin', 'wide'].includes(placement.frame?.style)
        ? placement.frame.style
        : (showBacking ? 'legacy' : 'none');
    const frameColor = FRAME_COLORS[placement.frame?.color] ?? FRAME_COLORS.black;
    const frameWidth = frameStyle === 'wide' ? 0.075 : 0.025;
    const backingDepth = Math.max(0.02, Number(placement.frame?.backing_depth_m || 0.035));
    let backing = null;
    if (showBacking && frameStyle !== 'none') {
        if (frameStyle === 'legacy') {
            backing = new THREE.Mesh(
                new THREE.BoxGeometry(width + 0.08, height + 0.08, backingDepth),
                new THREE.MeshStandardMaterial({ color: FRAME_COLORS.black, roughness: 0.78, metalness: 0.05 }),
            );
        } else {
            backing = new THREE.Group();
        }
        backing.name = `VE03_FRAME_${placement.id}`;
        if (frameStyle !== 'legacy') {
            const horizontal = new THREE.BoxGeometry(width + frameWidth * 2, frameWidth, backingDepth);
            const vertical = new THREE.BoxGeometry(frameWidth, height, backingDepth);
            // Frame colors are an explicit artist choice, so they must remain
            // visually identifiable even when a display is in a dark part of
            // the panorama. A lit metallic material made gold and silver look
            // almost black without an environment map.
            const material = new THREE.MeshBasicMaterial({
                color: frameColor,
                toneMapped: false,
                fog: false,
            });
            const top = new THREE.Mesh(horizontal, material);
            const bottom = new THREE.Mesh(horizontal, material);
            const left = new THREE.Mesh(vertical, material);
            const right = new THREE.Mesh(vertical, material);
            top.position.set(0, height / 2 + frameWidth / 2, 0);
            bottom.position.set(0, -(height / 2 + frameWidth / 2), 0);
            left.position.set(-(width / 2 + frameWidth / 2), 0, 0);
            right.position.set(width / 2 + frameWidth / 2, 0, 0);
            [top, bottom, left, right].forEach((part) => {
                part.userData.ve03Artwork = true;
                part.userData.interactive = Boolean(placement.interactive && !placement.artwork?.sold);
                part.userData.placement = placement;
            });
            backing.add(top, bottom, left, right);
        }
        backing.position.z = frameStyle === 'legacy'
            ? -backingDepth / 2
            : placement.transform.outward_offset_m - backingDepth / 2;
        // Treat the narrow backing rim as part of the artwork interaction target.
        // Otherwise aiming a few pixels above the image (while still clearly
        // looking at the framed work) hides the View artwork action.
        backing.userData.ve03Artwork = true;
        backing.userData.interactive = Boolean(placement.interactive && !placement.artwork?.sold);
        backing.userData.placement = placement;
        group.add(backing);
    }

    const image = new THREE.Mesh(
        new THREE.PlaneGeometry(width, height),
        new THREE.MeshBasicMaterial(artworkMaterialOptions(texture)),
    );
    image.name = `VE03_ARTWORK_IMAGE_${placement.id}`;
    image.position.z = placement.transform.outward_offset_m;
    image.layers.set(ARTWORK_LAYER);
    image.userData.ve03Artwork = true;
    image.userData.interactive = Boolean(placement.interactive && !placement.artwork?.sold);
    image.userData.placement = placement;
    group.add(image);

    let soldSign = null;
    if (placement.artwork?.sold) {
        soldSign = createSoldSign(placement);
        group.add(soldSign);
    }

    return { group, backing, image, soldSign, displaySupport, placement };
}

export class ArtworkRenderer {
    constructor(scene, options = {}) {
        this.scene = scene;
        this.options = options;
        this.root = new THREE.Group();
        this.root.name = 'VE03_DYNAMIC_ARTWORKS';
        this.scene.add(this.root);
        this.loader = new THREE.TextureLoader();
        this.raycaster = new THREE.Raycaster();
        this.raycaster.layers.enableAll();
        this.displays = new Map();
        this.disposed = false;
    }

    async load(placements = []) {
        const settled = await Promise.allSettled(placements.map(async (placement) => {
            const texture = await this.loader.loadAsync(placement.artwork.room_texture_url);
            const display = createArtworkDisplay(placement, texture, this.options);
            this.root.add(display.group);
            this.displays.set(String(placement.id), {
                ...display,
                roomTexture: texture,
                nearLoading: false,
                nearLoaded: false,
            });
        }));

        return {
            loaded: settled.filter((result) => result.status === 'fulfilled').length,
            failed: settled.filter((result) => result.status === 'rejected').length,
        };
    }

    targetFromCamera(camera, maximumDistance) {
        this.raycaster.far = Math.max(0.1, Number(maximumDistance || 3));
        this.raycaster.setFromCamera(new THREE.Vector2(0, 0), camera);
        const hit = this.raycaster.intersectObjects(this.scene.children, true)
            .find((intersection) => intersection.object.visible && intersection.distance <= this.raycaster.far);
        if (!hit?.object?.userData?.ve03Artwork || !hit.object.userData.interactive) return null;

        return hit.object.userData.placement;
    }

    updateNearTextures(cameraPosition, distance = 2.4) {
        for (const display of this.displays.values()) {
            if (display.nearLoaded || display.nearLoading || !display.placement.artwork?.near_texture_url) continue;
            const world = display.group.getWorldPosition(new THREE.Vector3());
            if (world.distanceTo(cameraPosition) > distance) continue;
            display.nearLoading = true;
            this.loader.loadAsync(display.placement.artwork.near_texture_url).then((texture) => {
                if (this.disposed) {
                    texture.dispose();
                    return;
                }
                texture.colorSpace = THREE.SRGBColorSpace;
                texture.needsUpdate = true;
                display.image.material.map = texture;
                display.image.material.needsUpdate = true;
                display.roomTexture.dispose();
                display.roomTexture = texture;
                display.nearLoaded = true;
            }).catch(() => {}).finally(() => {
                display.nearLoading = false;
            });
        }
    }

    markAvailability(change) {
        const display = this.displays.get(String(change?.placement_id || ''));
        if (!display) return false;
        const sold = Boolean(change.sold || String(change.availability).toLowerCase() === 'sold');
        const availability = String(change.availability || '').trim().toLowerCase();
        const label = sold
            ? 'Sold'
            : (availability ? availability.charAt(0).toUpperCase() + availability.slice(1) : (change.interactive ? 'Available' : 'Unavailable'));
        display.placement.interactive = Boolean(change.interactive) && !sold;
        display.placement.artwork.sold = sold;
        display.placement.artwork.availability = label;
        display.image.userData.interactive = display.placement.interactive;

        if (sold && !display.soldSign) {
            display.soldSign = createSoldSign(display.placement);
            display.group.add(display.soldSign);
        } else if (!sold && display.soldSign) {
            display.group.remove(display.soldSign);
            disposeObjectTree(display.soldSign);
            display.soldSign = null;
        }

        return true;
    }

    dispose() {
        if (this.disposed) return;
        this.disposed = true;
        this.scene.remove(this.root);
        disposeObjectTree(this.root);
        this.displays.clear();
    }
}
