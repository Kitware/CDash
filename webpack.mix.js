const mix = require('laravel-mix');
mix.disableNotifications();
mix.options({
  clearConsole: false,
});

// Enable source maps for everything Mix builds
mix.sourceMaps(true, 'source-map');

// Hash the built files to create a version identifier.  Use the mix() helper in PHP to automatically append the identifier to a path.
mix.version();

const ReplaceInFileWebpackPlugin = require('replace-in-file-webpack-plugin');

// Determine if this is a git clone of CDash or not.
fs = require('fs');
let git_clone = false;
let version;
if (fs.existsSync('.git')) {
  git_clone = true;
  // If this is a git clone, we will use the `git describe` to generate a version
  // to report in the footer.
  // Use current UNIX timestamp for cache busting.
  version = new Date().getTime().toString();
}
else {
  // Otherwise if this is a release download, use the version from package.json.
  const config = require('./package.json');
  version = config.version;
  fs.writeFileSync('./public/VERSION', `v${version}`);
}

// Webpack plugins.
const webpack_plugins = [];
if (git_clone) {
  const { GitRevisionPlugin } = require('git-revision-webpack-plugin');
  webpack_plugins.push(new GitRevisionPlugin({
    lightweightTags: true,
  }));
}

// Write out version file for angular.js
const dir = 'public/build/js';
if (!fs.existsSync(dir)) {
  fs.mkdirSync(dir);
}
fs.writeFileSync(`${dir}/version.js`, `angular.module('CDash').constant('VERSION', '${version}');`);

// Copy angularjs files to build directory.
mix.copy('public/views/*.html', 'public/build/views/');
mix.copy('public/views/partials/*.html', 'public/build/views/partials/');

// Copy CSS files
mix.css('public/css/cdash.css', 'public/build/css/cdash.css');
mix.css('public/css/colorblind.css', 'public/build/css/colorblind.css');

mix.styles([
  'node_modules/bootstrap/dist/css/bootstrap.css',
  'node_modules/jquery-ui-dist/jquery-ui.css',
  'node_modules/nvd3/build/nv.d3.min.css',
], 'public/build/css/legacy_3rdparty.css');

mix.sass('resources/sass/app.scss', 'public/laravel/css');

// Concatenate and minify 3rd party javascript.
mix.scripts([
  'node_modules/jquery/dist/jquery.min.js',
  'node_modules/jquery-ui-dist/jquery-ui.js',
  'node_modules/flot/lib/jquery.event.drag.js',
  'node_modules/flot/dist/es5/jquery.flot.js',
  'node_modules/flot/source/jquery.flot.pie.js',
  'node_modules/jquery.cookie/jquery.cookie.js',
  'public/js/bootstrap.min.js',
  'public/js/je_compare.js',
  'node_modules/angular/angular.min.js',
  'node_modules/angular-animate/angular-animate.min.js',
  'node_modules/angular-ui-bootstrap/dist/ui-bootstrap.js',
  'node_modules/angular-ui-sortable/dist/sortable.js',
  'node_modules/ansi_up/ansi_up.js',
  'node_modules/as-jqplot/dist/jquery.jqplot.js',
  'node_modules/as-jqplot/dist/plugins/jqplot.dateAxisRenderer.js',
  'node_modules/as-jqplot/dist/plugins/jqplot.highlighter.js',
  'node_modules/d3/d3.js',
  'node_modules/ng-file-upload/dist/ng-file-upload.js',
  'node_modules/nvd3/build/nv.d3.js',
  'public/js/ui-bootstrap-tpls-0.14.2.min.js',
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
  'public/js/controllers/**.js',
], 'public/js/legacy_1stparty.min.js');

// Boilerplate.
mix.js('resources/js/app.js', 'public/laravel/js').vue();

mix.webpackConfig({
  plugins: webpack_plugins,
  stats: {
    children: true,
  },
});
