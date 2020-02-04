const mix = require('laravel-mix');
mix.disableNotifications();

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.scripts([
    'public/js/jquery-1.10.2.js',
    'public/js/jquery-ui-1.10.4.min.js',
    'public/js/jquery.cookie.js',
    'public/js/jquery.flot.min.js',
    'public/js/jquery.flot.navigate.min.js',
    'public/js/jquery.flot.selection.min.js',
    'public/js/jquery.flot.symbol.min.js',
    'public/js/jquery.flot.time.min.js',
    'public/js/jquery.qtip.min.js',
    'public/js/jqModal.js',
    'public/js/bootstrap.min.js',
    'public/js/tooltip.js',
    'public/js/je_compare.js',
    'node_modules/angular/angular.js',
    'node_modules/angular-animate/angular-animate.js',
    'node_modules/angular-clipboard/angular-clipboard.js',
    'node_modules/angular-ui-bootstrap/dist/ui-bootstrap.js',
    'node_modules/angular-ui-sortable/dist/sortable.js',
    'node_modules/ansi_up/ansi_up.js',
    'node_modules/as-jqplot/dist/jquery.jqplot.js',
    'node_modules/as-jqplot/dist/plugins/jqplot.dateAxisRenderer.js',
    'node_modules/as-jqplot/dist/plugins/jqplot.highlighter.js',
    'node_modules/d3/d3.js',
    'node_modules/ng-file-upload/dist/ng-file-upload.js',
    'node_modules/nvd3/build/nv.d3.js',
    'public/js/ui-bootstrap-tpls-0.14.2.min.js'
], 'public/js/3rdparty.js');

mix.js('resources/js/app.js', 'public/laravel/js')
   .sass('resources/sass/app.scss', 'public/laravel/css');

mix.webpackConfig({
  module: {
    rules: [
      {
        enforce: 'pre',
        test: /\.vue$/,
        loader: 'eslint-loader',
        options: {
          fix: true,
        },
        exclude: /(vendor|node_modules)/
      }
    ]
  }
})
