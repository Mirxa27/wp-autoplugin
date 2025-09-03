module.exports = {
    extends: [
        'plugin:@wordpress/eslint-plugin/recommended'
    ],
    parserOptions: {
        ecmaVersion: 2021,
        sourceType: 'module',
        ecmaFeatures: {
            jsx: true
        }
    },
    env: {
        browser: true,
        es2021: true,
        node: true,
        jquery: true
    },
    globals: {
        wp: 'readonly',
        wpAutoPlugin: 'writable',
        ajaxurl: 'readonly'
    },
    rules: {
        'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
        'no-debugger': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
        'comma-dangle': ['error', 'never'],
        'quotes': ['error', 'single'],
        'semi': ['error', 'always'],
        'indent': ['error', 4],
        'no-unused-vars': ['error', { 'argsIgnorePattern': '^_' }]
    }
};