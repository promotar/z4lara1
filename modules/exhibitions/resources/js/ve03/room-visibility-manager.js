import * as THREE from 'three';

// Keep the room beyond a doorway rendered while the visitor is still
// approaching it. A very small culling margin exposes the dark scene
// background through the opening and looks like missing illumination.
export const ADJACENT_ROOM_VISIBILITY_MARGIN = 12;

function boxFromRoom(room) {
    const minimum = room?.bounds?.min;
    const maximum = room?.bounds?.max;
    if (!Array.isArray(minimum) || !Array.isArray(maximum) || minimum.length !== 3 || maximum.length !== 3) return null;
    return new THREE.Box3(new THREE.Vector3(...minimum), new THREE.Vector3(...maximum));
}

function distanceToBox(point, box) {
    return point.distanceTo(box.clampPoint(point, new THREE.Vector3()));
}

export class RoomVisibilityManager {
    constructor(environment, rooms, { margin = ADJACENT_ROOM_VISIBILITY_MARGIN } = {}) {
        this.environment = environment;
        this.margin = margin;
        this.rooms = rooms.map((room) => ({ ...room, box: boxFromRoom(room) })).filter((room) => room.box);
        this.assignments = new Map();
        this.authoredVisibility = new Map();
        this.currentRoom = null;
        this.assignMeshes();
    }

    assignMeshes() {
        this.environment.updateMatrixWorld(true);
        this.environment.traverse((object) => {
            if (!object.isMesh || !object.geometry) return;
            // Some visitor-only layers (such as the authored empty display
            // frames) are intentionally hidden before room culling starts.
            // Remember that authored visibility so room updates never revive
            // them while the visitor walks between rooms.
            this.authoredVisibility.set(object, object.visible !== false);
            const box = new THREE.Box3().setFromObject(object);
            const matches = this.rooms.filter((room) => room.box.intersectsBox(box));
            this.assignments.set(object, matches.length === 1 ? matches[0].code : null);
        });
    }

    update(position, aggressive = false) {
        const point = position?.isVector3 ? position : new THREE.Vector3(position.x, position.y, position.z);
        const containing = this.rooms.find((room) => room.box.containsPoint(point));
        const nearest = containing ?? [...this.rooms].sort((left, right) => distanceToBox(point, left.box) - distanceToBox(point, right.box))[0] ?? null;
        const previous = this.currentRoom;
        this.currentRoom = nearest?.code ?? null;

        const visibleRooms = new Set(this.rooms
            .filter((room) => !aggressive || distanceToBox(point, room.box) <= this.margin)
            .map((room) => room.code));
        for (const [mesh, roomCode] of this.assignments) {
            const authoredVisible = this.authoredVisibility.get(mesh) !== false;
            mesh.visible = authoredVisible && (roomCode === null || !aggressive || visibleRooms.has(roomCode));
        }

        return { current: this.currentRoom, changed: previous !== this.currentRoom, previous };
    }

    restore() {
        for (const mesh of this.assignments.keys()) mesh.visible = this.authoredVisibility.get(mesh) !== false;
    }
}
