module.exports = function(grunt) {

  var timestamp = new Date().getTime();

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    clean: {
      js: ["public/js/<%= pkg.name %>_*.min.js*"]
    },
    uglify: {
      options: {
        sourceMap: true,
        sourceMapIncludeSources: true,
        mangle: false,
        compress: false,
        beautify: true
      },
      build: {
        src: ['public/js/jquery-1.10.2.js',
              'public/js/jquery-ui-1.10.4.min.js',
              'public/js/jquery.cookie.js',
              'public/js/jqModal.js',
              'public/js/bootstrap.min.js',
              'public/js/tooltip.js',
              'public/js/cdashmenu.js',
              'public/js/tabNavigation.js',
              'public/js/angular-1.4.7.min.js',
              'public/js/angular-animate.min.js',
              'public/js/angular-ui-sortable.min.js',
              'public/js/ui-bootstrap-tpls-0.14.2.min.js',
              'public/js/cdash_angular.js',
              'public/js/controllers/*.js'],
        dest: 'public/js/<%= pkg.name %>_' + timestamp + '.min.js',
      }
    }
  });

  // Load plugins.
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-uglify');

  // Tasks to run by default.
  grunt.registerTask('default', ['clean', 'uglify']);

};
