//Install dependencies: npm install

var gulp = require('gulp');
var watch = require('gulp-watch');
var livereload = require('gulp-livereload');
var notify = require('gulp-notify');
var sourcemaps = require('gulp-sourcemaps');
var filter = require('gulp-filter');
var plumber = require('gulp-plumber');

gulp.task('sass', function () {
  var sass = require('gulp-sass');
  var autoprefixer = require('gulp-autoprefixer');

  gulp.src('src/scss/**/*.scss')
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'expanded',
      // outputStyle: 'compressed'
    }).on('error', function(error){
      notify().write(error);
    }))
    .pipe(autoprefixer({
      remove: false,
    }))
    .pipe(sourcemaps.write('./'))
    .pipe(gulp.dest('./css'))
    .pipe(filter('**/*.css'))
    .pipe(livereload());
});

gulp.task('sass:watch', function() {
  livereload.listen();
  gulp.watch('src/scss/**/*.scss', ['sass']);
});

gulp.task('scripts', function() {
  var concat = require('gulp-concat');
  var rename = require('gulp-rename');
  var uglify = require('gulp-uglify');

  return gulp.src('src/js/**/*.js')
    .pipe(plumber({
      errorHandler: function(err) {
        notify().write(err);
      }
    }))
    .pipe(sourcemaps.init())
    .pipe(concat('scripts.js'))
    .pipe(gulp.dest('js'))
    .pipe(rename({suffix: '.min'}))
    .pipe(uglify())
    .pipe(sourcemaps.write('./'))
    .pipe(gulp.dest('js'))
    .pipe(filter('**/*.js'))
    .pipe(livereload());
});

gulp.task('scripts:watch', function() {
  livereload.listen();
  gulp.watch('src/js/**/*.js', ['scripts']);
});

gulp.task('watch', function() {
  livereload.listen();
  gulp.watch('src/js/**/*.js', ['scripts']);
  gulp.watch('src/scss/**/*.scss', ['sass']);
});

gulp.task('list-browsers', function() {
  var browserslist = require('browserslist');
  var gutil = require('gulp-util');
  var currentConfig = browserslist.findConfig('.').defaults;
  var param = encodeURI(currentConfig.join(", ").trim());
  gutil.log("Browserslist query: \"" + currentConfig + "\"");
  gutil.log("http://browserl.ist/?q=" + param);
});

gulp.task('polyfills', function() {
  var autopolyfiller = require('gulp-autopolyfiller');
  return gulp.src('./js/scripts.js')
    .pipe(plumber({
      errorHandler: function(err) {
        notify().write(err);
      }
    }))
    .pipe(autopolyfiller('polyfills.js'))
    .pipe(gulp.dest('./js'));
});

gulp.task('default', ['list-browsers', 'sass', 'scripts', 'watch']);
gulp.task('css', ['sass', 'sass:watch']);
gulp.task('js', ['scripts', 'scripts:watch']);
gulp.task('polyfill', ['scripts', 'polyfills']);
