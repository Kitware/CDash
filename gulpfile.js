(function () {

  var gulp = require('gulp'),
      eslint = require('gulp-eslint'),
      del = require('del'),
      sourcemaps = require('gulp-sourcemaps'),
      concat = require('gulp-concat'),
      uglify = require('gulp-uglify'),
      rename = require("gulp-rename"),
      replace = require('gulp-replace'),
      release = false, // Change to true when cutting a release.
      version;

  if (release) {
      // Update the version in package.json before cutting a new release.
      var config = require('./package.json')
      version = config.version;
  } else {
      version = new Date().getTime();
  }


  gulp.task('quality', function() {
    gulp.src(['javascript/**/*_angular.js',
              'javascript/controllers/**.js'])
        .pipe(eslint({}))
        .pipe(eslint.format())
        .pipe(eslint.failAfterError());
  });


  gulp.task('clean', function () {
    return del.sync([
      'public/build/*',
      'public/js/CDash_*.min.js*'
    ]);
  });


 gulp.task('uglify', function() {
   gulp.src(['public/js/jquery-1.10.2.js',
             'public/js/jquery-ui-1.10.4.min.js',
             'public/js/jquery.cookie.js',
             'public/js/jquery.flot.min.js',
             'public/js/jquery.flot.navigate.min.js',
             'public/js/jquery.flot.selection.min.js',
             'public/js/jquery.flot.time.min.js',
             'public/js/jquery.qtip.min.js',
             'public/js/jqModal.js',
             'public/js/bootstrap.min.js',
             'public/js/tooltip.js',
             'public/js/cdashmenu.js',
             'public/js/cdashIndexTable.js',
             'public/js/cdashSortable.js',
             'public/js/tabNavigation.js',
             'public/js/je_compare.js',
             'public/js/d3.min.js',
             'public/js/nv.d3.min.js',
             'public/js/linechart.js',
             'public/js/bulletchart.js',
             'node_modules/as-jqplot/dist/jquery.jqplot.js',
             'node_modules/as-jqplot/dist/plugins/jqplot.dateAxisRenderer.js',
             'node_modules/as-jqplot/dist/plugins/jqplot.highlighter.js',
             'public/js/angular-1.4.7.min.js',
             'public/js/angular-animate.min.js',
             'public/js/angular-ui-sortable.min.js',
             'public/js/ui-bootstrap-tpls-0.14.2.min.js',
             'public/js/cdash_angular.js',
             'public/js/controllers/**.js'])
       .pipe(sourcemaps.init())
       .pipe(uglify({mangle: false})).on('error', function(e) {
           console.log(e);
        })
       .pipe(concat('CDash.concat.js'))
       .pipe(rename('CDash_' + version + '.min.js'))
       .pipe(sourcemaps.write('./'))
       .pipe(gulp.dest('public/js'));
 });


  gulp.task('replace', function(){
    gulp.src(['public/views/*.html'])
        .pipe(replace('@@version', version))
        .pipe(gulp.dest('public/build/views/'));

    gulp.src(['public/local/views/*.html'])
        .pipe(replace('@@version', version))
        .pipe(gulp.dest('public/build/local/views/'));
  });


  gulp.task('default', ['quality', 'clean', 'uglify', 'replace']);
}());
