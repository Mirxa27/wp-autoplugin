module.exports = {
    testEnvironment: 'jsdom',
    moduleNameMapper: {
        '^@/(.*)$': '<rootDir>/assets/src/js/$1',
        '^@components/(.*)$': '<rootDir>/assets/src/js/components/$1',
        '^@utils/(.*)$': '<rootDir>/assets/src/js/utils/$1',
        '^@features/(.*)$': '<rootDir>/assets/src/js/features/$1',
        '^@api/(.*)$': '<rootDir>/assets/src/js/api/$1',
        '^@hooks/(.*)$': '<rootDir>/assets/src/js/hooks/$1',
        '\\.(css|less|scss|sass)$': 'identity-obj-proxy'
    },
    transform: {
        '^.+\\.(js|jsx)$': 'babel-jest'
    },
    setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],
    testMatch: [
        '<rootDir>/tests/js/**/*.test.js'
    ],
    collectCoverageFrom: [
        'assets/src/js/**/*.{js,jsx}',
        '!assets/src/js/**/*.test.{js,jsx}',
        '!assets/src/js/index.js'
    ],
    coverageDirectory: '<rootDir>/coverage',
    globals: {
        wp: {
            i18n: {
                __: (text) => text,
                _n: (single, plural, number) => number === 1 ? single : plural
            },
            element: require('@wordpress/element'),
            components: require('@wordpress/components')
        },
        wpAutoPlugin: {
            ajaxUrl: 'http://localhost/wp-admin/admin-ajax.php',
            nonce: 'test-nonce',
            settings: {
                defaultModel: 'gpt-4o'
            }
        }
    }
};