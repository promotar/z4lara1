export const VE03_STATES = Object.freeze([
    'booting',
    'capability_check',
    'loading_environment',
    'ready_to_enter',
    'active',
    'paused',
    'artwork_popup_open',
    'cart_drawer_open',
    'context_lost',
    'recovering',
    'fatal_error',
    'closed',
]);

const TRANSITIONS = Object.freeze({
    booting: ['capability_check', 'fatal_error'],
    capability_check: ['loading_environment', 'fatal_error'],
    loading_environment: ['ready_to_enter', 'fatal_error'],
    ready_to_enter: ['active', 'closed'],
    active: ['paused', 'artwork_popup_open', 'cart_drawer_open', 'context_lost', 'fatal_error', 'closed'],
    paused: ['active', 'artwork_popup_open', 'cart_drawer_open', 'context_lost', 'fatal_error', 'closed'],
    artwork_popup_open: ['active', 'paused', 'context_lost', 'fatal_error', 'closed'],
    cart_drawer_open: ['paused', 'context_lost', 'fatal_error', 'closed'],
    context_lost: ['recovering', 'fatal_error', 'closed'],
    recovering: ['paused', 'fatal_error', 'closed'],
    fatal_error: ['closed'],
    closed: [],
});

export class ViewerStateMachine {
    #state = 'booting';
    #listeners = new Set();

    get state() {
        return this.#state;
    }

    can(next) {
        return VE03_STATES.includes(next) && TRANSITIONS[this.#state].includes(next);
    }

    transition(next, detail = {}) {
        if (!this.can(next)) {
            throw new Error(`Invalid VE-03 state transition: ${this.#state} -> ${next}`);
        }

        const previous = this.#state;
        this.#state = next;
        for (const listener of this.#listeners) listener({ previous, current: next, detail });
        return this.#state;
    }

    subscribe(listener) {
        this.#listeners.add(listener);
        return () => this.#listeners.delete(listener);
    }
}
