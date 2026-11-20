import * as THREE from 'three';

export function artworkMaterialOptions(texture) {
    return {
        map: texture,
        color: 0xffffff,
        side: THREE.FrontSide,
        toneMapped: false,
        fog: false,
    };
}
