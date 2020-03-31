// Load required modules.
const mix = require('laravel-mix');
mix.disableNotifications();

const ReplaceInFileWebpackPlugin = require('replace-in-file-webpack-plugin');

// Generate version string.
var release = false; // Change to true when cutting a release.
if (release) {
  // Update the version in package.json before cutting a new release.
  var config = require('./package.json');
  version = config.version;
} else {
  version = new Date().getTime().toString();
}

// Clean up from previous webpack runs.
del = require('del'),
del.sync('public/build/css');
del.sync('public/build/js');
del.sync('public/build/views');
del.sync('public/js/CDash_*.js');

// Write out version file for angular.js
fs = require('fs');
var dir = 'public/build/js';
if (!fs.existsSync(dir)) {
  fs.mkdirSync(dir);
}
fs.writeFileSync(dir + '/version.js', "angular.module('CDash').constant('VERSION', '" + version + "');");

// Copy angularjs files to build directory.
mix.copy('public/views/*.html', 'public/build/views/');

// Cache busting for angularjs partials.
var glob = require("glob");
glob.sync('public/views/partials/*.html').forEach(function(src) {
  var dest = src.replace('.html', '_' + version + '.html');
  var dest = dest.replace('views', 'build/views');
  mix.copy(src, dest);
});

// Version CSS files.
mix.copy('public/css/cdash.css', 'public/build/css/cdash_' + version + '.css');
mix.copy('public/css/colorblind.css', 'public/build/css/colorblind_' + version + '.css');
mix.copy('public/css/common.css', 'public/build/css/common.css');

// Concatenate and minify 3rd party javascript.
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
], 'public/js/3rdparty.min.js');

// Concatenate and minify 1st party javascript.
mix.scripts([
  'public/js/cdashSortable.js',
  'public/js/tabNavigation.js',
  'public/js/linechart.js',
  'public/js/bulletchart.js',
  'public/js/cdash_angular.js',
  'public/build/js/version.js',
  'public/js/directives/**.js',
  'public/js/filters/**.js',
  'public/js/services/**.js',
  'public/js/controllers/**.js'
], 'public/js/1stparty.min.js');

// Combine 1st and 3rd party into a single file.
mix.scripts([
  'public/js/3rdparty.min.js',
  'public/js/1stparty.min.js',
], 'public/js/CDash_' + version + '.min.js');

// Boilerplate.
mix.js('resources/js/app.js', 'public/laravel/js').version();
mix.sass('resources/sass/app.scss', 'public/laravel/css').version();

// Added this line to get mocha testing working with versioning.
mix.copy('resources/js/app.js', 'public/main.js');

mix.webpackConfig({
  module: {
    rules: [
      {
        // Vuejs linter
        enforce: 'pre',
        test: /\.vue$/,
        loader: 'eslint-loader',
        options: {
          fix: true,
        },
        exclude: /(vendor|node_modules)/
      },
    ]
  },
  plugins: [
    // Replace version string in angular files.
    new ReplaceInFileWebpackPlugin([
      {
        dir: 'public/build/views',
        test: /\.html$/,
        rules: [{
          search: /@@version/g,
          replace: version
        }]
      },
      {
        dir: 'public/js',
        test: /\.js$/,
        rules: [{
          search: /@@cdash_version/g,
          replace: version
        }]
      },
    ])
  ]
});
