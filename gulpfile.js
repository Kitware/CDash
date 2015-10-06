(function () {

    var gulp = require('gulp'),
        eslint = require('gulp-eslint');

    gulp.task('quality', function() {

        gulp.src(['javascript/**/*_angular.js',
                  'javascript/controllers/**.js'])
            .pipe(eslint({}))
            .pipe(eslint.format())
            .pipe(eslint.failAfterError());
    });

    gulp.task('default', ['quality']);
}());
