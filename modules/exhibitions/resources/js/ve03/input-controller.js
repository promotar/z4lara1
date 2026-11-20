const KEY_DIRECTIONS = Object.freeze({
    KeyW: ['forward', 1],
    ArrowUp: ['forward', 1],
    KeyS: ['backward', 1],
    ArrowDown: ['backward', 1],
    KeyA: ['left', 1],
    KeyD: ['right', 1],
    ArrowLeft: ['turn-left', 1],
    ArrowRight: ['turn-right', 1],
});

const KEYBOARD_TURN_SPEED = Math.PI * 0.75;
const MOBILE_JOYSTICK_DEAD_ZONE = 0.12;

export function radialJoystickVector(rawX, rawY, deadZone = MOBILE_JOYSTICK_DEAD_ZONE) {
    const x = Number(rawX || 0);
    const y = Number(rawY || 0);
    const radius = Math.hypot(x, y);
    if (radius <= deadZone) return { x: 0, y: 0, visualX: 0, visualY: 0 };

    const unitX = x / radius;
    const unitY = y / radius;
    const visualRadius = Math.min(1, radius);
    const normalizedRadius = Math.min(1, (radius - deadZone) / (1 - deadZone));
    const response = normalizedRadius * normalizedRadius * (3 - (2 * normalizedRadius));

    return {
        x: unitX * response,
        y: unitY * response,
        visualX: unitX * visualRadius,
        visualY: unitY * visualRadius,
    };
}

export function isMovementShortcut(code) {
    return Object.hasOwn(KEY_DIRECTIONS, code);
}

export function directionFromKeys(keys) {
    return {
        forward: (keys.has('forward') ? 1 : 0) - (keys.has('backward') ? 1 : 0),
        strafe: (keys.has('right') ? 1 : 0) - (keys.has('left') ? 1 : 0),
    };
}

export function yawDeltaFromKeys(keys, fixedDelta, turnSpeed = KEYBOARD_TURN_SPEED) {
    const direction = (keys.has('turn-left') ? 1 : 0) - (keys.has('turn-right') ? 1 : 0);
    return direction * turnSpeed * fixedDelta;
}

export function isFullscreenShortcut(event) {
    const tagName = event.target?.tagName?.toLowerCase();
    const isEditable = event.target?.isContentEditable || ['input', 'select', 'textarea'].includes(tagName);
    return event.code === 'KeyF'
        && !event.repeat
        && !event.altKey
        && !event.ctrlKey
        && !event.metaKey
        && !isEditable;
}

export function shouldReleasePointerLock({
    active = false,
    mobile = false,
    pointerLocked = false,
} = {}) {
    return active && !mobile && pointerLocked;
}

export function cameraMouseAction(button, manualCursorMode = false) {
    if (Number(button) === 2) return 'release';
    if (Number(button) === 0 && manualCursorMode) return 'resume';
    return null;
}

export class InputController {
    constructor(root, canvas, onLook, onActivate, onPause, onFullscreen = () => {}, onMoveIntent = () => {}) {
        this.root = root;
        this.canvas = canvas;
        this.onLook = onLook;
        this.onActivate = onActivate;
        this.onPause = onPause;
        this.onFullscreen = onFullscreen;
        this.onMoveIntent = onMoveIntent;
        this.keys = new Set();
        this.mobile = { forward: 0, strafe: 0 };
        this.bindDesktop();
        this.bindMobile();
    }

    bindDesktop() {
        this.onKeyDown = (event) => {
            const mapping = KEY_DIRECTIONS[event.code];
            if (mapping) {
                this.onMoveIntent(event);
                event.preventDefault();
                this.keys.add(mapping[0]);
            }
            if ((event.code === 'KeyE' || event.code === 'Enter') && !event.repeat) this.onActivate();
            if (isFullscreenShortcut(event)) {
                event.preventDefault();
                this.onFullscreen();
            }
        };
        this.onKeyUp = (event) => {
            const mapping = KEY_DIRECTIONS[event.code];
            if (mapping) this.keys.delete(mapping[0]);
        };
        this.onMouseMove = (event) => {
            if (document.pointerLockElement !== this.canvas) return;
            this.onLook(event.movementX * 0.002, event.movementY * 0.002);
        };
        this.onPointerLock = () => {
            if (document.pointerLockElement !== this.canvas) this.onPause();
        };
        addEventListener('keydown', this.onKeyDown);
        addEventListener('keyup', this.onKeyUp);
        addEventListener('mousemove', this.onMouseMove);
        document.addEventListener('pointerlockchange', this.onPointerLock);
    }

    bindMobile() {
        const joystick = this.root.querySelector('[data-ve03-joystick]');
        const stick = joystick?.querySelector('[data-ve03-stick]');
        const look = this.root.querySelector('[data-ve03-look]');
        let joystickPointer = null;
        let lookPointer = null;
        let lastLook = null;

        const updateJoystick = (event) => {
            const sample = event.getCoalescedEvents?.().at(-1) ?? event;
            const rect = joystick.getBoundingClientRect();
            const rawX = (sample.clientX - rect.left - rect.width / 2) / (rect.width / 2);
            const rawY = (sample.clientY - rect.top - rect.height / 2) / (rect.height / 2);
            const vector = radialJoystickVector(rawX, rawY);
            this.mobile = { forward: -vector.y, strafe: vector.x };
            if (stick) stick.style.transform = `translate(${vector.visualX * 32}px, ${vector.visualY * 32}px)`;
        };

        joystick?.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            joystickPointer = event.pointerId;
            joystick.setPointerCapture(event.pointerId);
            joystick.classList.add('is-active');
            updateJoystick(event);
        });
        joystick?.addEventListener('pointermove', (event) => {
            if (event.pointerId !== joystickPointer) return;
            event.preventDefault();
            updateJoystick(event);
        });
        const resetJoystick = (event) => {
            if (event.pointerId !== joystickPointer) return;
            joystickPointer = null;
            this.mobile = { forward: 0, strafe: 0 };
            joystick.classList.remove('is-active');
            if (stick) stick.style.transform = '';
        };
        joystick?.addEventListener('pointerup', resetJoystick);
        joystick?.addEventListener('pointercancel', resetJoystick);

        look?.addEventListener('pointerdown', (event) => {
            lookPointer = event.pointerId;
            lastLook = { x: event.clientX, y: event.clientY };
            look.setPointerCapture(event.pointerId);
        });
        look?.addEventListener('pointermove', (event) => {
            if (event.pointerId !== lookPointer || !lastLook) return;
            this.onLook((event.clientX - lastLook.x) * 0.004, (event.clientY - lastLook.y) * 0.004);
            lastLook = { x: event.clientX, y: event.clientY };
        });
        const resetLook = (event) => {
            if (event.pointerId !== lookPointer) return;
            lookPointer = null;
            lastLook = null;
        };
        look?.addEventListener('pointerup', resetLook);
        look?.addEventListener('pointercancel', resetLook);
    }

    direction() {
        const keyboard = directionFromKeys(this.keys);
        return {
            forward: Math.max(-1, Math.min(1, keyboard.forward + this.mobile.forward)),
            strafe: Math.max(-1, Math.min(1, keyboard.strafe + this.mobile.strafe)),
        };
    }

    yawDelta(fixedDelta) {
        return yawDeltaFromKeys(this.keys, fixedDelta);
    }

    clear() {
        this.keys.clear();
        this.mobile = { forward: 0, strafe: 0 };
    }

    dispose() {
        removeEventListener('keydown', this.onKeyDown);
        removeEventListener('keyup', this.onKeyUp);
        removeEventListener('mousemove', this.onMouseMove);
        document.removeEventListener('pointerlockchange', this.onPointerLock);
    }
}
