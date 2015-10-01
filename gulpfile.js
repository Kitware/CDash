(function () {

    var gulp = require('gulp'),
        eslint = require('gulp-eslint');

    gulp.task('quality', function() {

        gulp.src(['javascript/**/*_angular.js',
                  'javascript/controllers/**.js'])
            .pipe(eslint({

            }))
            .pipe(eslint.format());
    });

    gulp.task('default', ['quality']);
}());
