/**
 * Visual Feedback System
 * Provides real-time visual indicators for plugin operations
 */

import { toast } from 'react-hot-toast';
import { Notyf } from 'notyf';
import ProgressBar from '../progress-bar';
import StatusIndicator from './StatusIndicator';
import OperationMonitor from './OperationMonitor';
import { animateElement, fadeIn, fadeOut, slideIn } from '@utils/animations';
import { EventEmitter } from '@utils/EventEmitter';

// Initialize notification system
const notyf = new Notyf({
    duration: 4000,
    position: {
        x: 'right',
        y: 'top'
    },
    types: [
        {
            type: 'info',
            background: '#3b82f6',
            icon: {
                className: 'dashicons dashicons-info',
                tagName: 'span'
            }
        },
        {
            type: 'warning',
            background: '#f59e0b',
            icon: {
                className: 'dashicons dashicons-warning',
                tagName: 'span'
            }
        },
        {
            type: 'progress',
            background: '#8b5cf6',
            icon: false,
            dismissible: false
        }
    ]
});

/**
 * Visual Feedback Manager
 */
class VisualFeedback extends EventEmitter {
    constructor() {
        super();
        this.activeOperations = new Map();
        this.progressBars = new Map();
        this.statusIndicators = new Map();
        this.monitor = null;
        this.initialized = false;
    }

    /**
     * Initialize the visual feedback system
     */
    initialize() {
        if (this.initialized) return;

        // Create main container
        this.createContainer();

        // Initialize operation monitor
        this.monitor = new OperationMonitor(this.container);

        // Set up global listeners
        this.setupListeners();

        this.initialized = true;
        this.emit('initialized');
    }

    /**
     * Create main container for visual elements
     */
    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'wp-autoplugin-visual-feedback';
        this.container.className = 'wp-autoplugin-vf-container';
        document.body.appendChild(this.container);

        // Add styles
        this.injectStyles();
    }

    /**
     * Inject required styles
     */
    injectStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .wp-autoplugin-vf-container {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 999999;
                pointer-events: none;
            }

            .wp-autoplugin-operation {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 12px;
                padding: 16px;
                margin-bottom: 12px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
                min-width: 320px;
                pointer-events: all;
                transition: all 0.3s ease;
            }

            .wp-autoplugin-operation:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 32px rgba(0, 0, 0, 0.15);
            }

            .wp-autoplugin-operation-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 12px;
            }

            .wp-autoplugin-operation-title {
                font-weight: 600;
                color: #1e293b;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .wp-autoplugin-operation-status {
                font-size: 12px;
                padding: 4px 8px;
                border-radius: 4px;
                font-weight: 500;
            }

            .wp-autoplugin-operation-status.pending {
                background: #fef3c7;
                color: #92400e;
            }

            .wp-autoplugin-operation-status.in-progress {
                background: #dbeafe;
                color: #1e40af;
            }

            .wp-autoplugin-operation-status.success {
                background: #d1fae5;
                color: #065f46;
            }

            .wp-autoplugin-operation-status.error {
                background: #fee2e2;
                color: #991b1b;
            }

            .wp-autoplugin-progress-container {
                margin-top: 8px;
            }

            .wp-autoplugin-progress-bar {
                height: 6px;
                background: #e5e7eb;
                border-radius: 3px;
                overflow: hidden;
                position: relative;
            }

            .wp-autoplugin-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
                border-radius: 3px;
                transition: width 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .wp-autoplugin-progress-fill::after {
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
            }

            @keyframes shimmer {
                0% {
                    transform: translateX(-100%);
                }
                100% {
                    transform: translateX(100%);
                }
            }

            .wp-autoplugin-details {
                font-size: 13px;
                color: #64748b;
                margin-top: 8px;
                max-height: 100px;
                overflow-y: auto;
            }

            .wp-autoplugin-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #e5e7eb;
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            .wp-autoplugin-pulse {
                display: inline-block;
                width: 8px;
                height: 8px;
                background: #10b981;
                border-radius: 50%;
                animation: pulse 2s ease-in-out infinite;
            }

            @keyframes pulse {
                0% {
                    opacity: 1;
                    transform: scale(1);
                }
                50% {
                    opacity: 0.5;
                    transform: scale(1.2);
                }
                100% {
                    opacity: 1;
                    transform: scale(1);
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Set up event listeners
     */
    setupListeners() {
        // Listen for WordPress AJAX events
        if (window.jQuery) {
            jQuery(document).ajaxSend((event, jqXHR, settings) => {
                if (settings.data && typeof settings.data === 'string') {
                    const action = this.extractAction(settings.data);
                    if (this.isTrackedAction(action)) {
                        this.startOperation(action, {
                            type: 'ajax',
                            url: settings.url
                        });
                    }
                }
            });

            jQuery(document).ajaxComplete((event, jqXHR, settings) => {
                if (settings.data && typeof settings.data === 'string') {
                    const action = this.extractAction(settings.data);
                    if (this.isTrackedAction(action)) {
                        const success = jqXHR.status >= 200 && jqXHR.status < 300;
                        this.completeOperation(action, success);
                    }
                }
            });
        }

        // Listen for custom events
        window.addEventListener('autoplugin:operation:start', (e) => {
            this.startOperation(e.detail.id, e.detail);
        });

        window.addEventListener('autoplugin:operation:update', (e) => {
            this.updateOperation(e.detail.id, e.detail);
        });

        window.addEventListener('autoplugin:operation:complete', (e) => {
            this.completeOperation(e.detail.id, e.detail.success);
        });
    }

    /**
     * Extract action from AJAX data
     */
    extractAction(data) {
        const match = data.match(/action=([^&]+)/);
        return match ? match[1] : null;
    }

    /**
     * Check if action should be tracked
     */
    isTrackedAction(action) {
        const trackedActions = [
            'wp_autoplugin_generate_plan',
            'wp_autoplugin_generate_code',
            'wp_autoplugin_create_plugin',
            'wp_autoplugin_fix_plugin',
            'wp_autoplugin_extend_plugin',
            'wp_autoplugin_explain_plugin'
        ];
        return trackedActions.includes(action);
    }

    /**
     * Start a new operation
     */
    startOperation(id, options = {}) {
        const operation = {
            id,
            title: options.title || this.getOperationTitle(id),
            status: 'in-progress',
            progress: 0,
            startTime: Date.now(),
            details: options.details || '',
            element: null
        };

        // Create visual element
        operation.element = this.createOperationElement(operation);
        this.container.appendChild(operation.element);

        // Animate in
        animateElement(operation.element, 'slideIn');

        // Store operation
        this.activeOperations.set(id, operation);

        // Create progress bar
        const progressBar = new ProgressBar(operation.element.querySelector('.wp-autoplugin-progress-container'));
        this.progressBars.set(id, progressBar);

        // Show notification
        if (options.showNotification !== false) {
            notyf.open({
                type: 'progress',
                message: `${operation.title} started...`
            });
        }

        this.emit('operation:started', operation);
    }

    /**
     * Update operation progress
     */
    updateOperation(id, updates) {
        const operation = this.activeOperations.get(id);
        if (!operation) return;

        // Update operation data
        Object.assign(operation, updates);

        // Update progress bar
        if (updates.progress !== undefined) {
            const progressBar = this.progressBars.get(id);
            if (progressBar) {
                progressBar.setProgress(updates.progress);
            }
        }

        // Update status
        if (updates.status) {
            this.updateOperationStatus(operation);
        }

        // Update details
        if (updates.details) {
            const detailsEl = operation.element.querySelector('.wp-autoplugin-details');
            if (detailsEl) {
                detailsEl.textContent = updates.details;
            }
        }

        this.emit('operation:updated', operation);
    }

    /**
     * Complete an operation
     */
    completeOperation(id, success = true) {
        const operation = this.activeOperations.get(id);
        if (!operation) return;

        operation.status = success ? 'success' : 'error';
        operation.endTime = Date.now();
        operation.duration = operation.endTime - operation.startTime;

        // Update visual status
        this.updateOperationStatus(operation);

        // Complete progress
        const progressBar = this.progressBars.get(id);
        if (progressBar) {
            progressBar.setProgress(100);
        }

        // Show completion notification
        if (success) {
            notyf.success(`${operation.title} completed successfully!`);
        } else {
            notyf.error(`${operation.title} failed. Please check the details.`);
        }

        // Remove after delay
        setTimeout(() => {
            animateElement(operation.element, 'fadeOut').then(() => {
                operation.element.remove();
                this.activeOperations.delete(id);
                this.progressBars.delete(id);
            });
        }, 3000);

        this.emit('operation:completed', operation);
    }

    /**
     * Create operation element
     */
    createOperationElement(operation) {
        const element = document.createElement('div');
        element.className = 'wp-autoplugin-operation';
        element.dataset.operationId = operation.id;

        element.innerHTML = `
            <div class="wp-autoplugin-operation-header">
                <div class="wp-autoplugin-operation-title">
                    <span class="wp-autoplugin-spinner"></span>
                    <span>${operation.title}</span>
                </div>
                <span class="wp-autoplugin-operation-status ${operation.status}">
                    ${this.getStatusLabel(operation.status)}
                </span>
            </div>
            <div class="wp-autoplugin-progress-container"></div>
            <div class="wp-autoplugin-details">${operation.details}</div>
        `;

        return element;
    }

    /**
     * Update operation status visually
     */
    updateOperationStatus(operation) {
        const statusEl = operation.element.querySelector('.wp-autoplugin-operation-status');
        const iconEl = operation.element.querySelector('.wp-autoplugin-operation-title span:first-child');

        statusEl.className = `wp-autoplugin-operation-status ${operation.status}`;
        statusEl.textContent = this.getStatusLabel(operation.status);

        // Update icon
        switch (operation.status) {
            case 'success':
                iconEl.className = 'dashicons dashicons-yes-alt';
                iconEl.style.color = '#10b981';
                break;
            case 'error':
                iconEl.className = 'dashicons dashicons-dismiss';
                iconEl.style.color = '#ef4444';
                break;
            case 'in-progress':
                iconEl.className = 'wp-autoplugin-spinner';
                break;
        }
    }

    /**
     * Get operation title from ID
     */
    getOperationTitle(id) {
        const titles = {
            'wp_autoplugin_generate_plan': 'Generating Plugin Plan',
            'wp_autoplugin_generate_code': 'Generating Plugin Code',
            'wp_autoplugin_create_plugin': 'Creating Plugin',
            'wp_autoplugin_fix_plugin': 'Fixing Plugin',
            'wp_autoplugin_extend_plugin': 'Extending Plugin',
            'wp_autoplugin_explain_plugin': 'Explaining Plugin'
        };
        return titles[id] || 'Processing';
    }

    /**
     * Get status label
     */
    getStatusLabel(status) {
        const labels = {
            'pending': 'Pending',
            'in-progress': 'In Progress',
            'success': 'Completed',
            'error': 'Failed'
        };
        return labels[status] || status;
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        switch (type) {
            case 'success':
                toast.success(message);
                break;
            case 'error':
                toast.error(message);
                break;
            case 'loading':
                return toast.loading(message);
            default:
                toast(message);
        }
    }

    /**
     * Show progress notification
     */
    showProgress(title, options = {}) {
        const id = options.id || Date.now().toString();
        
        const progressToast = toast.loading(
            <div>
                <h4>{title}</h4>
                <ProgressBar progress={options.progress || 0} />
                {options.details && <p>{options.details}</p>}
            </div>,
            {
                id,
                duration: Infinity
            }
        );

        return {
            update: (progress, details) => {
                toast.loading(
                    <div>
                        <h4>{title}</h4>
                        <ProgressBar progress={progress} />
                        {details && <p>{details}</p>}
                    </div>,
                    { id }
                );
            },
            dismiss: () => toast.dismiss(id),
            success: (message) => {
                toast.success(message || `${title} completed!`, { id });
            },
            error: (message) => {
                toast.error(message || `${title} failed!`, { id });
            }
        };
    }
}

// Create singleton instance
const visualFeedback = new VisualFeedback();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => visualFeedback.initialize());
} else {
    visualFeedback.initialize();
}

export default visualFeedback;
export { VisualFeedback, notyf };