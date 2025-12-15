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
fs.writeFileSync(`${dir}/version.js`, `export const VERSION = '${new Date().getTime().toString()}';`);

// Copy angularjs files to build directory.
mix.copy('resources/js/angular/views/*.html', 'public/assets/js/angular/views/');
mix.copy('resources/js/angular/views/partials/*.html', 'public/assets/js/angular/views/partials/');

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

mix.copy('resources/js/angular/jquery.dataTables.min.js', 'public/assets/js/jquery.dataTables.min.js');
mix.copy('resources/js/angular/cdashCoverageGraph.js', 'public/assets/js/angular/cdashCoverageGraph.js');
mix.copy('resources/js/angular/cdashFilters.js', 'public/assets/js/angular/cdashFilters.js');
mix.copy('resources/js/angular/cdashViewCoverage.js', 'public/assets/js/angular/cdashViewCoverage.js');

// Boilerplate.
mix.js('resources/js/vue/app.js', 'public/assets/js').vue();
mix.js('resources/js/angular/legacy.js', 'public/assets/js/legacy.js');

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
