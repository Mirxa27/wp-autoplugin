const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: {
            'admin': './assets/src/js/admin/index.js',
            'generator': './assets/src/js/features/generator/index.js',
            'fixer': './assets/src/js/features/fixer/index.js',
            'extender': './assets/src/js/features/extender/index.js',
            'explainer': './assets/src/js/features/explainer/index.js',
            'hooks-extender': './assets/src/js/features/hooks-extender/index.js',
            'visual-feedback': './assets/src/js/components/visual-feedback/index.js',
            'admin-styles': './assets/src/scss/admin.scss',
            'frontend-styles': './assets/src/scss/frontend.scss'
        },
        output: {
            path: path.resolve(__dirname, 'assets/dist'),
            filename: 'js/[name].bundle.js',
            clean: true,
            publicPath: '/wp-content/plugins/wp-autoplugin/assets/dist/'
        },
        module: {
            rules: [
                {
                    test: /\.(js|jsx)$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: [
                                '@babel/preset-env',
                                '@babel/preset-react'
                            ]
                        }
                    }
                },
                {
                    test: /\.(scss|css)$/,
                    use: [
                        isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
                        'css-loader',
                        {
                            loader: 'postcss-loader',
                            options: {
                                postcssOptions: {
                                    plugins: [
                                        'autoprefixer'
                                    ]
                                }
                            }
                        },
                        'sass-loader'
                    ]
                }
            ]
        },
        plugins: [
            new MiniCssExtractPlugin({
                filename: 'css/[name].css'
            })
        ],
        optimization: {
            minimizer: [
                new TerserPlugin({
                    terserOptions: {
                        compress: {
                            drop_console: isProduction,
                            drop_debugger: isProduction
                        }
                    }
                }),
                new CssMinimizerPlugin()
            ],
            splitChunks: {
                chunks: 'all',
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendor',
                        priority: 10
                    }
                }
            }
        },
        resolve: {
            extensions: ['.js', '.jsx'],
            alias: {
                '@': path.resolve(__dirname, 'assets/src/js'),
                '@components': path.resolve(__dirname, 'assets/src/js/components'),
                '@utils': path.resolve(__dirname, 'assets/src/js/utils'),
                '@features': path.resolve(__dirname, 'assets/src/js/features'),
                '@api': path.resolve(__dirname, 'assets/src/js/api'),
                '@hooks': path.resolve(__dirname, 'assets/src/js/hooks'),
                '@styles': path.resolve(__dirname, 'assets/src/scss')
            }
        },
        devtool: isProduction ? false : 'source-map',
        externals: {
            'jquery': 'jQuery',
            'wp': 'wp'
        }
    };
};