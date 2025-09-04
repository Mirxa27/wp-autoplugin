/**
 * Modern API Client for WP-Autoplugin
 * Handles all API communications with enhanced error handling and progress tracking
 */

import visualFeedback from '@components/visual-feedback';
import { EventEmitter } from '@utils/EventEmitter';

class ApiClient extends EventEmitter {
    constructor() {
        super();
        this.baseURL = window.ajaxurl || '/wp-admin/admin-ajax.php';
        this.nonce = window.wpAutoPlugin?.nonce || '';
        this.activeRequests = new Map();
        this.retryConfig = {
            maxRetries: 3,
            retryDelay: 1000,
            backoffMultiplier: 2
        };
    }

    /**
     * Make an API request
     */
    async request(action, data = {}, options = {}) {
        const requestId = this.generateRequestId();
        const controller = new AbortController();

        // Store active request
        this.activeRequests.set(requestId, {
            action,
            controller,
            startTime: Date.now()
        });

        // Show visual feedback if enabled
        const showProgress = options.showProgress !== false;
        let progressHandler = null;

        if (showProgress) {
            progressHandler = visualFeedback.showProgress(
                options.progressTitle || this.getActionTitle(action),
                {
                    id: requestId,
                    details: options.progressDetails || 'Initializing...'
                }
            );
        }

        try {
            const response = await this.executeRequest(
                action,
                data,
                {
                    ...options,
                    signal: controller.signal,
                    onProgress: (progress, details) => {
                        if (progressHandler) {
                            progressHandler.update(progress, details);
                        }
                        this.emit('progress', { action, progress, details });
                    }
                }
            );

            // Success
            if (progressHandler) {
                progressHandler.success(options.successMessage);
            }

            this.emit('success', { action, response });
            return response;

        } catch (error) {
            // Error handling
            if (progressHandler) {
                progressHandler.error(error.message);
            }

            this.emit('error', { action, error });
            throw error;

        } finally {
            // Cleanup
            this.activeRequests.delete(requestId);
        }
    }

    /**
     * Execute the actual request with retry logic
     */
    async executeRequest(action, data, options, retryCount = 0) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);

        // Append data
        Object.keys(data).forEach(key => {
            const value = data[key];
            if (value instanceof File) {
                formData.append(key, value);
            } else if (typeof value === 'object') {
                formData.append(key, JSON.stringify(value));
            } else {
                formData.append(key, value);
            }
        });

        try {
            const response = await fetch(this.baseURL, {
                method: 'POST',
                body: formData,
                signal: options.signal,
                credentials: 'same-origin'
            });

            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Parse response
            const result = await response.json();

            // Check for WordPress error response
            if (!result.success) {
                throw new Error(result.data?.message || 'Request failed');
            }

            return result.data;

        } catch (error) {
            // Don't retry on abort
            if (error.name === 'AbortError') {
                throw error;
            }

            // Retry logic
            if (retryCount < this.retryConfig.maxRetries) {
                const delay = this.retryConfig.retryDelay * Math.pow(this.retryConfig.backoffMultiplier, retryCount);
                
                if (options.onProgress) {
                    options.onProgress(
                        0,
                        `Request failed. Retrying in ${delay / 1000} seconds... (Attempt ${retryCount + 1}/${this.retryConfig.maxRetries})`
                    );
                }

                await this.sleep(delay);
                return this.executeRequest(action, data, options, retryCount + 1);
            }

            throw error;
        }
    }

    /**
     * Cancel an active request
     */
    cancelRequest(requestId) {
        const request = this.activeRequests.get(requestId);
        if (request) {
            request.controller.abort();
            this.activeRequests.delete(requestId);
            this.emit('cancelled', { action: request.action });
        }
    }

    /**
     * Cancel all active requests
     */
    cancelAllRequests() {
        this.activeRequests.forEach((request, id) => {
            this.cancelRequest(id);
        });
    }

    /**
     * Plugin-specific API methods
     */

    /**
     * Generate plugin plan
     */
    async generatePlan(description, options = {}) {
        return this.request('wp_autoplugin_generate_plan', {
            plugin_description: description,
            model: options.model,
            temperature: options.temperature
        }, {
            showProgress: true,
            progressTitle: 'Generating Plugin Plan',
            progressDetails: 'AI is analyzing your requirements...',
            successMessage: 'Plugin plan generated successfully!'
        });
    }

    /**
     * Generate plugin code
     */
    async generateCode(plan, options = {}) {
        return this.request('wp_autoplugin_generate_code', {
            plugin_plan: typeof plan === 'object' ? JSON.stringify(plan) : plan,
            model: options.model,
            temperature: options.temperature
        }, {
            showProgress: true,
            progressTitle: 'Generating Plugin Code',
            progressDetails: 'AI is writing your plugin code...',
            successMessage: 'Plugin code generated successfully!'
        });
    }

    /**
     * Create plugin
     */
    async createPlugin(code, name, options = {}) {
        return this.request('wp_autoplugin_create_plugin', {
            plugin_code: code,
            plugin_name: name,
            activate: options.activate !== false
        }, {
            showProgress: true,
            progressTitle: 'Creating Plugin',
            progressDetails: 'Installing your new plugin...',
            successMessage: 'Plugin created and activated!'
        });
    }

    /**
     * Fix plugin
     */
    async fixPlugin(pluginFile, errorData, options = {}) {
        return this.request('wp_autoplugin_generate_fix_code', {
            plugin_file: pluginFile,
            error_data: JSON.stringify(errorData),
            model: options.model
        }, {
            showProgress: true,
            progressTitle: 'Fixing Plugin',
            progressDetails: 'AI is analyzing and fixing the issues...',
            successMessage: 'Plugin fixed successfully!'
        });
    }

    /**
     * Extend plugin
     */
    async extendPlugin(pluginFile, requirements, options = {}) {
        return this.request('wp_autoplugin_generate_extend_code', {
            plugin_file: pluginFile,
            requirements: requirements,
            model: options.model
        }, {
            showProgress: true,
            progressTitle: 'Extending Plugin',
            progressDetails: 'AI is adding new features to your plugin...',
            successMessage: 'Plugin extended successfully!'
        });
    }

    /**
     * Explain plugin
     */
    async explainPlugin(pluginFile, options = {}) {
        return this.request('wp_autoplugin_explain_plugin', {
            plugin_file: pluginFile,
            model: options.model,
            detail_level: options.detailLevel || 'medium'
        }, {
            showProgress: true,
            progressTitle: 'Analyzing Plugin',
            progressDetails: 'AI is examining your plugin code...',
            successMessage: 'Plugin analysis complete!'
        });
    }

    /**
     * Stream response handler for real-time updates
     */
    async streamRequest(action, data, onChunk) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);
        formData.append('stream', 'true');

        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        const response = await fetch(this.baseURL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    try {
                        const data = JSON.parse(line.slice(6));
                        onChunk(data);
                    } catch (e) {
                        console.error('Failed to parse chunk:', e);
                    }
                }
            }
        }
    }

    /**
     * Helper methods
     */

    generateRequestId() {
        return `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    getActionTitle(action) {
        const titles = {
            'wp_autoplugin_generate_plan': 'Generating Plan',
            'wp_autoplugin_generate_code': 'Generating Code',
            'wp_autoplugin_create_plugin': 'Creating Plugin',
            'wp_autoplugin_generate_fix_code': 'Fixing Plugin',
            'wp_autoplugin_generate_extend_code': 'Extending Plugin',
            'wp_autoplugin_explain_plugin': 'Explaining Plugin'
        };
        return titles[action] || 'Processing';
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Create singleton instance
const apiClient = new ApiClient();

// Export both the instance and the class
export default apiClient;
export { ApiClient };