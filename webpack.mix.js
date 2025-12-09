const mix = require('laravel-mix');
mix.disableNotifications();
mix.options({
  clearConsole: false,
});

// Enable source maps for everything Mix builds
mix.sourceMaps(true, 'source-map');

// Hash the built files to create a version identifier.  Use the mix() helper in PHP to automatically append the identifier to a path.
mix.version();

// Write out version file for angular.js
const fs = require('fs');
const dir = 'public/assets/js/angular';
if (!fs.existsSync(dir)) {
  fs.mkdirSync(dir, { recursive: true });
}
fs.writeFileSync(`${dir}/version.js`, `angular.module('CDash').constant('VERSION', '${new Date().getTime().toString()}');`);

// Copy angularjs files to build directory.
mix.copy('resources/js/angular/views/*.html', 'public/assets/js/angular/views/');
mix.copy('resources/js/angular/views/partials/*.html', 'public/assets/js/angular/views/partials/');
mix.copy('resources/js/angular/controllers/*.js', 'public/assets/js/angular/controllers/');
mix.copy('resources/js/angular/directives/*.js', 'public/assets/js/angular/directives/');
mix.copy('resources/js/angular/filters/*.js', 'public/assets/js/angular/filters/');
mix.copy('resources/js/angular/services/*.js', 'public/assets/js/angular/services/');
mix.copy('resources/js/angular/*.js', 'public/assets/js/angular/');

// Copy CSS files
mix.css('resources/css/cdash.css', 'public/assets/css/cdash.css');
mix.css('resources/css/colorblind.css', 'public/assets/css/colorblind.css');
mix.css('resources/css/jquery.dataTables.css', 'public/assets/css/jquery.dataTables.css');
mix.css('resources/css/bootstrap.min.css', 'public/assets/css/bootstrap.min.css');
mix.css('resources/css/vue_common.css', 'public/assets/css/vue_common.css');

mix.styles([
  'node_modules/bootstrap/dist/css/bootstrap.css',
  'node_modules/jquery-ui-dist/jquery-ui.css',
  'node_modules/nvd3/build/nv.d3.min.css',
], 'public/assets/css/legacy_3rdparty.css');

mix.sass('resources/sass/app.scss', 'public/assets/css/app.css');

// Concatenate and minify 3rd party javascript.
mix.scripts([
  'node_modules/jquery/dist/jquery.min.js',
  'node_modules/jquery-ui-dist/jquery-ui.js',
  'node_modules/flot/lib/jquery.event.drag.js',
  'node_modules/flot/dist/es5/jquery.flot.js',
  'node_modules/flot/source/jquery.flot.pie.js',
  'node_modules/jquery.cookie/jquery.cookie.js',
  'resources/js/angular/bootstrap.min.js',
  'resources/js/angular/je_compare.js',
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
  'resources/js/angular/ui-bootstrap-tpls-0.14.2.min.js',
], 'public/assets/js/3rdparty.min.js');

// Concatenate and minify 1st party javascript.
mix.scripts([
  'resources/js/angular/cdashSortable.js',
  'resources/js/angular/tabNavigation.js',
  'resources/js/angular/linechart.js',
  'resources/js/angular/bulletchart.js',
  'resources/js/angular/cdash_angular.js',
  'resources/js/angular/jquery.tablesorter.js',
  'resources/js/angular/jquery.metadata.js',
  'public/assets/js/angular/version.js',
  'resources/js/angular/directives/**.js',
  'resources/js/angular/filters/**.js',
  'resources/js/angular/services/**.js',
  'resources/js/angular/controllers/**.js',
], 'public/assets/js/legacy_1stparty.min.js');

mix.copy('resources/js/angular/jquery.dataTables.min.js', 'public/assets/js/jquery.dataTables.min.js');

// Boilerplate.
mix.js('resources/js/vue/app.js', 'public/assets/js').vue();

mix.webpackConfig({
  stats: {
    children: true,
  },
  output: {
    chunkFilename: 'assets/js/[contenthash].js',
  },
  optimization: {
    runtimeChunk: false,
  },
});
