/**
 * Status Indicator Component
 */
export default class StatusIndicator {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            size: options.size || 'medium',
            animated: options.animated !== false,
            showLabel: options.showLabel !== false,
            position: options.position || 'inline',
            ...options
        };
        
        this.status = 'idle';
        this.element = null;
        this.iconElement = null;
        this.labelElement = null;
        
        this.init();
    }

    init() {
        this.element = document.createElement('div');
        this.element.className = `wp-autoplugin-status-indicator ${this.options.size}`;
        
        const sizes = {
            small: { icon: 8, font: 12 },
            medium: { icon: 12, font: 14 },
            large: { icon: 16, font: 16 }
        };
        
        const size = sizes[this.options.size] || sizes.medium;
        
        this.element.style.cssText = `
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: ${size.font}px;
        `;

        // Icon
        this.iconElement = document.createElement('span');
        this.iconElement.className = 'status-icon';
        this.iconElement.style.cssText = `
            display: inline-block;
            width: ${size.icon}px;
            height: ${size.icon}px;
            border-radius: 50%;
            background: #e5e7eb;
            position: relative;
        `;
        
        this.element.appendChild(this.iconElement);

        // Label
        if (this.options.showLabel) {
            this.labelElement = document.createElement('span');
            this.labelElement.className = 'status-label';
            this.labelElement.style.cssText = `
                font-weight: 500;
                color: #64748b;
            `;
            this.element.appendChild(this.labelElement);
        }

        if (this.container) {
            this.container.appendChild(this.element);
        }
    }

    setStatus(status, label) {
        this.status = status;
        
        const statusConfig = {
            idle: {
                color: '#e5e7eb',
                label: 'Idle',
                animation: null
            },
            loading: {
                color: '#3b82f6',
                label: 'Loading...',
                animation: 'pulse'
            },
            processing: {
                color: '#8b5cf6',
                label: 'Processing...',
                animation: 'spin'
            },
            success: {
                color: '#10b981',
                label: 'Success',
                animation: null,
                icon: '✓'
            },
            warning: {
                color: '#f59e0b',
                label: 'Warning',
                animation: null,
                icon: '!'
            },
            error: {
                color: '#ef4444',
                label: 'Error',
                animation: null,
                icon: '✕'
            }
        };
        
        const config = statusConfig[status] || statusConfig.idle;
        
        // Update icon
        this.iconElement.style.background = config.color;
        this.iconElement.innerHTML = '';
        
        if (config.icon) {
            const icon = document.createElement('span');
            icon.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: white;
                font-size: 10px;
                font-weight: bold;
                line-height: 1;
            `;
            icon.textContent = config.icon;
            this.iconElement.appendChild(icon);
        }
        
        // Update animation
        this.iconElement.style.animation = '';
        if (config.animation && this.options.animated) {
            switch (config.animation) {
                case 'pulse':
                    this.iconElement.style.animation = 'pulse 2s ease-in-out infinite';
                    break;
                case 'spin':
                    this.iconElement.style.animation = 'spin 1s linear infinite';
                    break;
            }
        }
        
        // Update label
        if (this.labelElement) {
            this.labelElement.textContent = label || config.label;
            this.labelElement.style.color = config.color;
        }
        
        // Add animations if not present
        this.ensureAnimations();
    }

    ensureAnimations() {
        if (!document.querySelector('#wp-autoplugin-status-animations')) {
            const style = document.createElement('style');
            style.id = 'wp-autoplugin-status-animations';
            style.textContent = `
                @keyframes pulse {
                    0%, 100% {
                        opacity: 1;
                        transform: scale(1);
                    }
                    50% {
                        opacity: 0.5;
                        transform: scale(1.1);
                    }
                }
                
                @keyframes spin {
                    to {
                        transform: rotate(360deg);
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    show() {
        this.element.style.display = 'inline-flex';
    }

    hide() {
        this.element.style.display = 'none';
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