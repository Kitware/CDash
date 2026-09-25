const mix = require('laravel-mix');
const path = require('path');
mix.disableNotifications();
mix.options({
  clearConsole: false,
});

// Enable source maps for everything Mix builds
mix.sourceMaps(true, 'source-map');

// Hash the built files to create a version identifier.  Use the mix() helper in PHP to automatically append the identifier to a path.
mix.version();

// Copy angularjs files to build directory.
mix.copy('resources/js/angular/views/*.html', 'public/assets/js/angular/views/');

// Copy CSS files
mix.css('resources/css/cdash.css', 'public/assets/css/cdash.css');
mix.css('resources/css/colorblind.css', 'public/assets/css/colorblind.css');

mix.css('resources/css/legacy.css', 'public/assets/css/legacy.css');
mix.sass('resources/sass/app.scss', 'public/assets/css/app.css');

// Boilerplate.
mix.js('resources/js/vue/app.js', 'public/assets/js').vue();
mix.js('resources/js/angular/legacy.js', 'public/assets/js/legacy.js');

const partialsDir = path.resolve(__dirname, 'resources/js/angular/views/partials');

mix.webpackConfig({
  stats: {
    children: true,
  },
  module: {
    rules: [
      {
        // Import AngularJS partial templates as raw strings, so their content
        // is inlined into legacy.js and covered by its own content hash.
        test: /\.html$/,
        include: partialsDir,
        type: 'asset/source',
      },
    ],
  },
  output: {
    chunkFilename: 'assets/js/[contenthash].js',
  },
  optimization: {
    runtimeChunk: false,
  },
});

// Mix registers its own project-wide rule sending every *.html file through
// html-loader (for Vue's <template src> imports). Webpack runs a module through
// every rule that matches it, so without this exclusion our partials would be
// wrapped by html-loader *and* by the asset/source rule above, leaving AngularJS
// with html-loader's generated JS source text instead of the template's HTML.
mix.override((webpackConfig) => {
  for (const rule of webpackConfig.module.rules) {
    const usesHtmlLoader = Array.isArray(rule.use) && rule.use.some((u) => String(u.loader || u).includes('html-loader'));
    if (usesHtmlLoader) {
      rule.exclude = partialsDir;
    }
  }
});
