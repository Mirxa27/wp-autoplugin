/**
 * ApiClient Tests
 */

import apiClient, { ApiClient } from '../../../assets/src/js/api';

describe('ApiClient', () => {
    let client;

    beforeEach(() => {
        // Reset fetch mock
        global.fetch.mockClear();
        global.fetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true, data: {} })
        });

        // Create fresh instance
        client = new ApiClient();
    });

    describe('request', () => {
        it('should make a successful request', async () => {
            const mockResponse = { plan: 'test plan' };
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ success: true, data: mockResponse })
            });

            const result = await client.request('test_action', { test: 'data' });

            expect(global.fetch).toHaveBeenCalledWith(
                expect.any(String),
                expect.objectContaining({
                    method: 'POST',
                    body: expect.any(FormData),
                    credentials: 'same-origin'
                })
            );
            expect(result).toEqual(mockResponse);
        });

        it('should handle errors gracefully', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: false,
                status: 500
            });

            await expect(client.request('test_action')).rejects.toThrow('HTTP error! status: 500');
        });

        it('should retry on failure', async () => {
            // First two calls fail, third succeeds
            global.fetch
                .mockResolvedValueOnce({
                    ok: false,
                    status: 500
                })
                .mockResolvedValueOnce({
                    ok: false,
                    status: 500
                })
                .mockResolvedValueOnce({
                    ok: true,
                    json: () => Promise.resolve({ success: true, data: { result: 'success' } })
                });

            client.retryConfig.retryDelay = 10; // Speed up test
            const result = await client.request('test_action');

            expect(global.fetch).toHaveBeenCalledTimes(3);
            expect(result).toEqual({ result: 'success' });
        });
    });

    describe('generatePlan', () => {
        it('should generate a plugin plan', async () => {
            const mockPlan = {
                plugin_name: 'Test Plugin',
                features: ['Feature 1', 'Feature 2']
            };

            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ success: true, data: { plan: mockPlan } })
            });

            const result = await client.generatePlan('Test plugin description');

            expect(result.plan).toEqual(mockPlan);
        });
    });

    describe('generateCode', () => {
        it('should generate plugin code', async () => {
            const mockCode = '<?php\n// Plugin code';
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ success: true, data: { code: mockCode } })
            });

            const result = await client.generateCode({ plugin_name: 'Test' });

            expect(result.code).toEqual(mockCode);
        });
    });

    describe('cancelRequest', () => {
        it('should cancel active requests', () => {
            // Mock abort controller
            const abortMock = jest.fn();
            global.AbortController = jest.fn(() => ({
                abort: abortMock,
                signal: {}
            }));

            // Start a request
            client.request('test_action');

            // Get request ID
            const requestId = Array.from(client.activeRequests.keys())[0];

            // Cancel it
            client.cancelRequest(requestId);

            expect(abortMock).toHaveBeenCalled();
            expect(client.activeRequests.has(requestId)).toBe(false);
        });
    });
});