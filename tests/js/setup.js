/**
 * Test setup file
 */

// Mock WordPress globals
global.wp = {
    i18n: {
        __: (text) => text,
        _n: (single, plural, number) => number === 1 ? single : plural,
        _x: (text, context) => text,
        sprintf: require('sprintf-js').sprintf
    },
    element: require('@wordpress/element'),
    components: require('@wordpress/components'),
    hooks: {
        addAction: jest.fn(),
        addFilter: jest.fn(),
        doAction: jest.fn(),
        applyFilters: jest.fn((name, value) => value)
    },
    data: {
        select: jest.fn(),
        dispatch: jest.fn()
    }
};

// Mock browser APIs
global.fetch = jest.fn(() =>
    Promise.resolve({
        ok: true,
        json: () => Promise.resolve({}),
        text: () => Promise.resolve('')
    })
);

// Mock localStorage
const localStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn()
};
global.localStorage = localStorageMock;

// Mock WordPress admin globals
global.ajaxurl = 'http://localhost/wp-admin/admin-ajax.php';
global.wpAutoPlugin = {
    ajaxUrl: 'http://localhost/wp-admin/admin-ajax.php',
    nonce: 'test-nonce',
    settings: {
        apiProvider: 'openai',
        defaultModel: 'gpt-4o',
        enableVisualFeedback: true,
        enableNotifications: true
    },
    models: {
        'gpt-4o': 'GPT-4o',
        'gpt-3.5-turbo': 'GPT-3.5 Turbo'
    },
    i18n: {
        generating: 'Generating...',
        success: 'Success!',
        error: 'Error'
    }
};

// Mock DOM methods
if (!Element.prototype.animate) {
    Element.prototype.animate = jest.fn(() => ({
        onfinish: null,
        finished: Promise.resolve()
    }));
}

// Performance API mock
global.performance = {
    now: jest.fn(() => Date.now())
};