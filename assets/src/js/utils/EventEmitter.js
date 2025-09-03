/**
 * Simple EventEmitter implementation
 */
export class EventEmitter {
    constructor() {
        this.events = {};
    }

    on(event, listener) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(listener);
        return this;
    }

    off(event, listenerToRemove) {
        if (!this.events[event]) return this;

        this.events[event] = this.events[event].filter(
            listener => listener !== listenerToRemove
        );
        return this;
    }

    emit(event, ...args) {
        if (!this.events[event]) return false;

        this.events[event].forEach(listener => {
            listener.apply(this, args);
        });
        return true;
    }

    once(event, listener) {
        const onceWrapper = (...args) => {
            listener.apply(this, args);
            this.off(event, onceWrapper);
        };
        this.on(event, onceWrapper);
        return this;
    }

    removeAllListeners(event) {
        if (event) {
            delete this.events[event];
        } else {
            this.events = {};
        }
        return this;
    }
}