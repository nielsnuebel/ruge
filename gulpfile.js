var gulp            = require('gulp'),
    shell           = require('gulp-shell'),
    browserSync     = require('browser-sync').create();

/* dirs */
var	assetsDir			= 'assets',
    bowerDir			= 'bower_components',
    targetSass			= '_sass',
    targetFont			= assetsDir + '/fonts',
    targetJs			= assetsDir + '/js';

// Task for building blog when something changed:
gulp.task('build', shell.task(['jekyll build --watch']));
// Or if you don't use bundle:
// gulp.task('build', shell.task(['jekyll build --watch']));

// Task for serving blog with Browsersync
gulp.task('serve', function () {
    browserSync.init({
        server: {baseDir: '_site/'}
    });
    gulp.watch('_site/**/*.*').on('change', browserSync.reload);
});

gulp.task('default', ['build', 'serve']);

gulp.task('copy_files', function() {
    gulp.src(bowerDir + '/font-awesome/scss/**/*')
        .pipe(gulp.dest(targetSass + '/font-awesome'));
    gulp.src(bowerDir + '/bootstrap/scss/**/*')
        .pipe(gulp.dest(targetSass + '/bootstrap'));
    gulp.src(bowerDir + '/font-awesome/fonts/**/*.{otf,eot,svg,ttf,woff,woff2}')
        .pipe(gulp.dest(targetFont));
    gulp.src(bowerDir + '/jquery/dist/jquery.min.js')
        .pipe(gulp.dest(targetJs));
    gulp.src(bowerDir + '/bootstrap/dist/js/bootstrap.min.js')
        .pipe(gulp.dest(targetJs));
});


