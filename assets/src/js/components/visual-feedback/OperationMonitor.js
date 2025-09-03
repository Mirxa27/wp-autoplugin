/**
 * Operation Monitor Component
 * Displays real-time operation status and history
 */
import StatusIndicator from './StatusIndicator';
import { animateElement, createSkeleton } from '@utils/animations';

export default class OperationMonitor {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            maxOperations: options.maxOperations || 5,
            showHistory: options.showHistory !== false,
            autoCollapse: options.autoCollapse !== false,
            collapseDelay: options.collapseDelay || 5000,
            ...options
        };
        
        this.operations = [];
        this.element = null;
        this.headerElement = null;
        this.listElement = null;
        this.isCollapsed = false;
        
        this.init();
    }

    init() {
        this.element = document.createElement('div');
        this.element.className = 'wp-autoplugin-operation-monitor';
        this.element.style.cssText = `
            position: fixed;
            top: 32px;
            right: 20px;
            width: 360px;
            max-height: 600px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 999998;
        `;

        // Header
        this.createHeader();

        // Operation list
        this.listElement = document.createElement('div');
        this.listElement.className = 'operation-list';
        this.listElement.style.cssText = `
            max-height: 500px;
            overflow-y: auto;
            padding: 0;
        `;

        this.element.appendChild(this.listElement);

        // Initially hidden
        this.element.style.display = 'none';

        if (this.container) {
            this.container.appendChild(this.element);
        }
    }

    createHeader() {
        this.headerElement = document.createElement('div');
        this.headerElement.className = 'monitor-header';
        this.headerElement.style.cssText = `
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            user-select: none;
        `;

        const title = document.createElement('h3');
        title.style.cssText = `
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        `;
        title.textContent = 'Operation Monitor';

        const toggleButton = document.createElement('button');
        toggleButton.className = 'toggle-button';
        toggleButton.style.cssText = `
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: transform 0.3s ease;
        `;
        toggleButton.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `;

        this.headerElement.appendChild(title);
        this.headerElement.appendChild(toggleButton);
        this.element.appendChild(this.headerElement);

        // Toggle functionality
        this.headerElement.addEventListener('click', () => this.toggle());
    }

    addOperation(operation) {
        // Show monitor if hidden
        if (this.element.style.display === 'none') {
            this.element.style.display = 'block';
            animateElement(this.element, 'slideIn');
        }

        // Create operation element
        const operationEl = this.createOperationElement(operation);
        
        // Add to list
        this.listElement.insertBefore(operationEl, this.listElement.firstChild);
        animateElement(operationEl, 'slideIn');

        // Store operation
        this.operations.unshift({
            ...operation,
            element: operationEl,
            timestamp: Date.now()
        });

        // Limit operations
        if (this.operations.length > this.options.maxOperations) {
            const removed = this.operations.pop();
            animateElement(removed.element, 'fadeOut').then(() => {
                removed.element.remove();
            });
        }

        // Auto-collapse after delay
        if (this.options.autoCollapse && !this.isCollapsed) {
            clearTimeout(this.collapseTimeout);
            this.collapseTimeout = setTimeout(() => {
                if (this.getActiveOperations().length === 0) {
                    this.collapse();
                }
            }, this.options.collapseDelay);
        }
    }

    createOperationElement(operation) {
        const element = document.createElement('div');
        element.className = 'monitor-operation';
        element.dataset.operationId = operation.id;
        element.style.cssText = `
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.2s ease;
        `;

        // Header with title and status
        const header = document.createElement('div');
        header.style.cssText = `
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        `;

        const title = document.createElement('div');
        title.style.cssText = `
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
        `;
        title.textContent = operation.title;

        const statusContainer = document.createElement('div');
        const statusIndicator = new StatusIndicator(statusContainer, {
            size: 'small',
            showLabel: true
        });
        statusIndicator.setStatus(operation.status);

        header.appendChild(title);
        header.appendChild(statusContainer);
        element.appendChild(header);

        // Details
        if (operation.details) {
            const details = document.createElement('div');
            details.className = 'operation-details';
            details.style.cssText = `
                font-size: 12px;
                color: #64748b;
                margin-top: 4px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            `;
            details.textContent = operation.details;
            element.appendChild(details);
        }

        // Progress bar (if needed)
        if (operation.showProgress) {
            const progressContainer = document.createElement('div');
            progressContainer.className = 'operation-progress';
            progressContainer.style.cssText = `
                margin-top: 8px;
                height: 4px;
                background: #f3f4f6;
                border-radius: 2px;
                overflow: hidden;
            `;

            const progressFill = document.createElement('div');
            progressFill.className = 'progress-fill';
            progressFill.style.cssText = `
                height: 100%;
                background: #3b82f6;
                transition: width 0.3s ease;
                width: ${operation.progress || 0}%;
            `;

            progressContainer.appendChild(progressFill);
            element.appendChild(progressContainer);
        }

        // Store references
        element._statusIndicator = statusIndicator;
        element._progressFill = element.querySelector('.progress-fill');

        return element;
    }

    updateOperation(id, updates) {
        const operation = this.operations.find(op => op.id === id);
        if (!operation) return;

        // Update data
        Object.assign(operation, updates);

        // Update visual elements
        if (updates.status && operation.element._statusIndicator) {
            operation.element._statusIndicator.setStatus(updates.status);
        }

        if (updates.progress !== undefined && operation.element._progressFill) {
            operation.element._progressFill.style.width = `${updates.progress}%`;
        }

        if (updates.details) {
            const detailsEl = operation.element.querySelector('.operation-details');
            if (detailsEl) {
                detailsEl.textContent = updates.details;
            }
        }
    }

    removeOperation(id) {
        const index = this.operations.findIndex(op => op.id === id);
        if (index === -1) return;

        const operation = this.operations[index];
        animateElement(operation.element, 'fadeOut').then(() => {
            operation.element.remove();
        });

        this.operations.splice(index, 1);

        // Hide monitor if no operations
        if (this.operations.length === 0) {
            setTimeout(() => {
                if (this.operations.length === 0) {
                    animateElement(this.element, 'fadeOut').then(() => {
                        this.element.style.display = 'none';
                    });
                }
            }, 1000);
        }
    }

    getActiveOperations() {
        return this.operations.filter(op => 
            op.status === 'pending' || op.status === 'in-progress'
        );
    }

    toggle() {
        if (this.isCollapsed) {
            this.expand();
        } else {
            this.collapse();
        }
    }

    collapse() {
        this.isCollapsed = true;
        this.listElement.style.display = 'none';
        this.element.style.width = '200px';
        
        const toggleButton = this.headerElement.querySelector('.toggle-button');
        toggleButton.style.transform = 'rotate(-90deg)';
    }

    expand() {
        this.isCollapsed = false;
        this.listElement.style.display = 'block';
        this.element.style.width = '360px';
        
        const toggleButton = this.headerElement.querySelector('.toggle-button');
        toggleButton.style.transform = 'rotate(0)';
    }

    clear() {
        this.operations.forEach(op => {
            op.element.remove();
        });
        this.operations = [];
    }

    destroy() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }
}