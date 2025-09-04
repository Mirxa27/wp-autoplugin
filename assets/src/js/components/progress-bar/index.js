/**
 * Progress Bar Component
 */
export default class ProgressBar {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            height: options.height || 6,
            animated: options.animated !== false,
            showPercentage: options.showPercentage || false,
            className: options.className || 'wp-autoplugin-progress-bar',
            ...options
        };
        
        this.progress = 0;
        this.element = null;
        this.fillElement = null;
        this.percentageElement = null;
        
        this.init();
    }

    init() {
        this.element = document.createElement('div');
        this.element.className = this.options.className;
        this.element.style.cssText = `
            height: ${this.options.height}px;
            background: #e5e7eb;
            border-radius: ${this.options.height / 2}px;
            overflow: hidden;
            position: relative;
        `;

        this.fillElement = document.createElement('div');
        this.fillElement.className = `${this.options.className}-fill`;
        this.fillElement.style.cssText = `
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: ${this.options.height / 2}px;
            transition: width 0.3s ease;
            width: 0%;
            position: relative;
            overflow: hidden;
        `;

        if (this.options.animated) {
            this.addShimmerEffect();
        }

        this.element.appendChild(this.fillElement);

        if (this.options.showPercentage) {
            this.percentageElement = document.createElement('div');
            this.percentageElement.className = `${this.options.className}-percentage`;
            this.percentageElement.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 12px;
                font-weight: 600;
                color: #1e293b;
            `;
            this.percentageElement.textContent = '0%';
            this.element.appendChild(this.percentageElement);
        }

        if (this.container) {
            this.container.appendChild(this.element);
        }
    }

    addShimmerEffect() {
        const shimmer = document.createElement('div');
        shimmer.style.cssText = `
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            animation: shimmer 2s infinite;
        `;

        // Add animation keyframes if not already present
        if (!document.querySelector('#wp-autoplugin-shimmer-keyframes')) {
            const style = document.createElement('style');
            style.id = 'wp-autoplugin-shimmer-keyframes';
            style.textContent = `
                @keyframes shimmer {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
            `;
            document.head.appendChild(style);
        }

        this.fillElement.appendChild(shimmer);
    }

    setProgress(progress) {
        this.progress = Math.max(0, Math.min(100, progress));
        this.fillElement.style.width = `${this.progress}%`;
        
        if (this.percentageElement) {
            this.percentageElement.textContent = `${Math.round(this.progress)}%`;
        }

        if (this.options.onProgress) {
            this.options.onProgress(this.progress);
        }

        if (this.progress >= 100 && this.options.onComplete) {
            setTimeout(() => this.options.onComplete(), 300);
        }
    }

    increment(amount = 10) {
        this.setProgress(this.progress + amount);
    }

    reset() {
        this.setProgress(0);
    }

    complete() {
        this.setProgress(100);
    }

    destroy() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }

    getElement() {
        return this.element;
    }
}