const { src, dest, parallel, series, watch } = require('gulp');
const browsersync = require('browser-sync').create();
const sass = require('gulp-sass')(require('sass'));
const sourcemaps = require('gulp-sourcemaps');

const themeRoot = 'web/themes/custom/kind_tan/';

function browsersyncServe(cb) {
  browsersync.init({
    proxy: 'ozdorovlenie.ryazanszn.test'
  });
  cb();
}

function styles() {
  return src(themeRoot + 'scss/**/*.scss')
    .pipe(sourcemaps.init())
    .pipe(sass())
    .on('error', sass.logError)
    .pipe(sourcemaps.write('./'))
    .pipe(dest(themeRoot + 'css'))
    .pipe(browsersync.stream());
}

function browsersyncReload(cb) {
  browsersync.reload();
  cb();
}

function watchTask() {
  let files = [
    themeRoot + 'scss/*.scss',
    themeRoot + 'js/*.js',
    themeRoot + 'images/**/*',
    themeRoot + 'templates/**/*.twig'
  ];

  watch(files, series(styles, browsersyncReload));
}

exports.browsersync = browsersync;
exports.styles = styles;
exports.build = series(styles);
exports.default = series(styles, browsersyncServe, watchTask);
