// Load required modules.
const mix = require('laravel-mix');
mix.disableNotifications();

const ESLintPlugin = require('eslint-webpack-plugin');
const ReplaceInFileWebpackPlugin = require('replace-in-file-webpack-plugin');

// Clean up from previous webpack runs.
del = require('del'),
del.sync('public/build/css');
del.sync('public/build/js');
del.sync('public/build/views');
del.sync('public/js/CDash_*.js');

// Determine if this is a git clone of CDash or not.
fs = require('fs');
var git_clone = false;
if (fs.existsSync('.git')) {
  var git_clone = true;
  // If this is a git clone, we will use the `git describe` to generate a version
  // to report in the footer.
  // Use current UNIX timestamp for cache busting.
  version = new Date().getTime().toString();
} else {
  // Otherwise if this is a release download, use the version from package.json.
  var config = require('./package.json');
  version = config.version;
  fs.writeFileSync('./public/VERSION', 'v' + version);
}

// Write out version file for angular.js
var dir = 'public/build/js';
if (!fs.existsSync(dir)) {
  fs.mkdirSync(dir);
}
fs.writeFileSync(dir + '/version.js', "angular.module('CDash').constant('VERSION', '" + version + "');");

// Webpack plugins.
var webpack_plugins = [
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
  ]),

  // Linter for Vuejs files.
  new ESLintPlugin({
    exclude: ['node_modules', 'vendor'],
    extensions: 'vue',
    fix: true,
  }),
];

if (git_clone) {
  const GitRevisionPlugin = require('git-revision-webpack-plugin')
  webpack_plugins.push(new GitRevisionPlugin());
}

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
mix.styles([
  'node_modules/bootstrap/dist/css/bootstrap.css',
  'node_modules/bootstrap-vue/dist/bootstrap-vue.css',
  'node_modules/jquery-ui-dist/jquery-ui.css'
], 'public/build/css/3rdparty.css').version();

// Concatenate and minify 3rd party javascript.
mix.scripts([
  'node_modules/jquery/dist/jquery.min.js',
  'node_modules/jquery-ui-dist/jquery-ui.js',
  'node_modules/flot/lib/jquery.event.drag.js',
  'node_modules/flot/dist/es5/jquery.flot.js',
  'node_modules/flot/source/jquery.flot.pie.js',
  'node_modules/jquery.cookie/jquery.cookie.js',
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
mix.js('resources/js/app.js', 'public/laravel/js').vue().version();
mix.sass('resources/sass/app.scss', 'public/laravel/css').version();

// Added this line to get mocha testing working with versioning.
mix.copy('resources/js/app.js', 'public/main.js');

mix.webpackConfig({
  plugins: webpack_plugins
});
