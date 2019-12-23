"use strict";

const webpack = require("webpack");
const autoprefixer = require("autoprefixer");
const AssetsPlugin = require("assets-webpack-plugin");
const BrowserSyncPlugin = require("browser-sync-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CleanWebpackPlugin = require("clean-webpack-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const FriendlyErrorsPlugin = require("friendly-errors-webpack-plugin");
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const CopyWebpackPlugin = require("copy-webpack-plugin");
const path = require("path");
const fs = require("fs");

// Make sure any symlinks in the project folder are resolved:
// https://github.com/facebookincubator/create-react-app/issues/637
const appDirectory = fs.realpathSync(process.cwd());

function resolveApp(relativePath) {
	return path.resolve(appDirectory, relativePath);
}

const paths = {
	appSrc: resolveApp("web/app/themes/presspack/src"),
	appBuild: resolveApp("web/app/themes/presspack/build"),
	appIndexJs: resolveApp("web/app/themes/presspack/src/index.js"),
	appNodeModules: resolveApp("node_modules")
};

const DEV = process.env.NODE_ENV === "development";

module.exports = {
	bail: !DEV,
	mode: DEV ? "development" : "production",
	// We generate sourcemaps in production. This is slow but gives good results.
	// You can exclude the *.map files from the build during deployment.
	target: "web",
	devtool: DEV ? "cheap-eval-source-map" : "source-map",
	entry: {
		main: paths.appIndexJs
		// legacy: paths.appIndexLegacyJs,
	},
	output: {
		path: paths.appBuild,
		filename: DEV ? "bundle.[name].js" : "bundle.[name].[hash:8].js"
	},
	module: {
		rules: [
			// Disable require.ensure as it's not a standard language feature.
			{ parser: { requireEnsure: false } },
			// Transform ES6 with Babel
			{
				test: /\.js?$/,
				loader: "babel-loader",
				include: paths.appSrc
			},
			{
				test: /\.(ttf|eot|svg|png|jpg|woff|woff2|gif|ico)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
				loader: "file-loader"
			},
			{
				// "oneOf" will traverse all following loaders until one will
				// match the requirements. When no loader matches it will fall
				// back to the "file" loader at the end of the loader list.
				oneOf: [
					{
						test: /.scss$/,
						use: [
							MiniCssExtractPlugin.loader,
							{
								loader: "css-loader"
							},
							{
								loader: "postcss-loader",
								options: {
									ident: "postcss", // https://webpack.js.org/guides/migrating/#complex-options
									plugins: loader => [
										require("postcss-import")({ root: loader.resourcePath }),
										require("postcss-flexbugs-fixes"),
										require("postcss-preset-env")({
											autoprefixer: {
												flexbox: "no-2009"
											},
											stage: 3
										}),
										// Adds PostCSS Normalize as the reset css with default options,
										// so that it honors browserslist config in package.json
										// which in turn let's users customize the target behavior as per their needs.
										require("postcss-normalize")()
									],
									options: { sourceMap: !DEV }
								}
							},
							{ loader: "sass-loader" }
						]
					},
					{
						test: /.css$/,
						use: [
							MiniCssExtractPlugin.loader,
							{
								loader: "css-loader"
							},
							{
								loader: "postcss-loader",
								options: {
									ident: "postcss", // https://webpack.js.org/guides/migrating/#complex-options
									plugins: loader => [
										require("postcss-import")({ root: loader.resourcePath }),
										require("tailwindcss")("./tailwind.config.js"),
										require("postcss-flexbugs-fixes"),
										require("postcss-preset-env")({
											autoprefixer: {
												flexbox: "no-2009"
											},
											stage: 3
										}),
										// Adds PostCSS Normalize as the reset css with default options,
										// so that it honors browserslist config in package.json
										// which in turn let's users customize the target behavior as per their needs.
										require("postcss-normalize")()
									],
									options: { sourceMap: !DEV }
								}
							}
						]
					}
				]
			}
		]
	},
	optimization: {
		minimize: !DEV,
		minimizer: [
			new OptimizeCSSAssetsPlugin({
				cssProcessorOptions: {
					map: {
						inline: false,
						annotation: true
					}
				}
			}),
			new TerserPlugin({
				terserOptions: {
					compress: {
						warnings: false
					},
					output: {
						comments: false
					}
				},
				sourceMap: true
			})
		]
	},
	plugins: [
		!DEV && new CleanWebpackPlugin([paths.appBuild]),
		// new CopyWebpackPlugin([
		//   {
		//     from: 'img',
		//     to: 'img',
		//   },
		// ]),
		new MiniCssExtractPlugin({
			filename: DEV ? "bundle.[name].css" : "bundle.[name].[hash:8].css"
		}),
		new webpack.EnvironmentPlugin({
			NODE_ENV: DEV ? "development" : "production", // use 'development' unless process.env.NODE_ENV is defined
			DEBUG: false
		}),
		new AssetsPlugin({
			path: paths.appBuild,
			filename: "assets.json"
		}),
		DEV &&
			new FriendlyErrorsPlugin({
				clearConsole: false
			}),
		DEV &&
			new BrowserSyncPlugin({
				notify: false,
				host: "localhost",
				port: 5000,
				logLevel: "silent",
				files: ["./**/*.php"],
				proxy: "http://localhost:9009/"
			})
	].filter(Boolean)
};
