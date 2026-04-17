const path = require('path');
const { WebpackManifestPlugin } = require('webpack-manifest-plugin');

module.exports = {
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader"
        }
      }
    ]
  },
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: '[name]-[contenthash].js',
    clean: true,
  },
  plugins: [
    new WebpackManifestPlugin()
  ]
};
