module.exports = function(grunt) {

	const sass = require( 'sass' );
	require( 'load-grunt-tasks' )( grunt );

	// Project configuration.
	grunt.initConfig(
        {
		pkg: grunt.file.readJSON( 'package.json' ),

		// Setting folder templates.
		dirs: {
			css: 'assets/css',
			fonts: 'assets/fonts',
			js: 'assets/js',
			php: 'includes',
			scss: 'assets/scss'
		},

		// Compile all .scss files.
		sass: {
			compile: {
				options: {
					implementation: sass
				},
				files: [
				{
					expand: true,
					cwd: '<%= dirs.scss %>/admin/',
					src: ['*.scss'],
					dest: '<%= dirs.css %>/admin/',
					ext: '.css'
				},
				{
					expand: true,
					cwd: '<%= dirs.scss %>/frontend',
					src: ['*.scss'],
					dest: '<%= dirs.css %>/frontend',
					ext: '.css'
				}
				]
			}
		},

		// Generate RTL .css files.
		rtlcss: {
			dist: {
				expand: true,
				src: [
				'<%= dirs.css %>/admin/*.css',
				'!<%= dirs.css %>/admin/*-rtl.css',
				'!<%= dirs.css %>/admin/*.min.css',
				'<%= dirs.css %>/frontend/*.css',
				'!<%= dirs.css %>/frontend/*-rtl.css',
				'!<%= dirs.css %>/frontend/*.min.css'
				],
				ext: '-rtl.css'
			}
		},

		// Minify all .css files.
		cssmin: {
			dist: {
				files: [{
					expand: true,
					src: [
					'<%= dirs.css %>/admin/*.css',
					'!<%= dirs.css %>/admin/*.min.css',
					'<%= dirs.css %>/frontend/*.css',
					'!<%= dirs.css %>/frontend/*.min.css'
					],
					ext: '.min.css'
				}]
			}
		},

		// Autoprefixer.
		postcss: {
			options: {
				processors: [
				require( 'autoprefixer' )
				]
			},
			dist: {
				src: [
				'<%= dirs.css %>/admin/*.css',
				'<%= dirs.css %>/frontend/*.css'
				]
			}
		},

		// Watch changes for assets. The script module (assets/js/modules) is shipped as is, with no build step.
		watch: {
			css: {
				files: [
				'<%= dirs.scss %>/admin/*.scss',
				'<%= dirs.scss %>/frontend/*.scss'
				],
				tasks: ['sass', 'rtlcss', 'postcss', 'cssmin']
			}
		},

		// # Build and release

		// Remove any files in zip destination and build folder
		clean: {
			main: ['build/**']
		},

		// Copy the plugin into the build directory
		copy: {
			main: {
				src: [
				'**',
				'!node_modules/**',
				'!build/**',
				'!deploy/**',
				'!svn/**',
				'!**/*.zip',
				'!**/*.bak',
				'!wp-assets/**',
				'!package-lock.json',
				'!nyp-logo.png',
				'!screenshots/**',
				'!.git/**',
				'!**.md',
				'!Gruntfile.js',
				'!package.json',
				'!gitcreds.json',
				'!.gitcreds',
				'!.gitignore',
				'!.gitmodules',
				'!sftp-config.json',
				'!**.sublime-workspace',
				'!**.sublime-project',
				'!deploy.sh',
				'!**/*~',
				'!phpcs.xml',
				'!composer.json',
				'!composer.lock',
				'!vendor/**',
				'!bin/**',
				'!assets/scss/**',
				'!none',
				'!includes/compatibility/backcompatibility/**'
				],
				dest: 'build/'
			}
		},

		// Make a zipfile.
		compress: {
			main: {
				options: {
					mode: 'zip',
					archive: 'deploy/<%= pkg.name %>-<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'build/',
				src: ['**/*'],
				dest: '/<%= pkg.name %>'
			}
		},

		// # Internationalization

		// Add text domain
		addtextdomain: {
			options: {
				textdomain: '<%= pkg.name %>'    // Project text domain.
			},
			target: {
				files: {
					src: ['*.php', '**/*.php', '**/**/*.php', '!node_modules/**', '!deploy/**', '!build/**', '!vendor/**', '!vendor-prefixed/**']
				}
			}
		},

		// bump version numbers (replace with version in package.json)
		replace: {
			Version: {
				src: [
				'readme.txt',
				'<%= pkg.name %>.php'
				],
				overwrite: true,
				replacements: [
				{
					from: /Stable tag:.*$/m,
					to: "Stable tag: <%= pkg.version %>"
				},
				{
					from: /Version:.*$/m,
					to: "Version: <%= pkg.version %>"
				},
				{
					from: /public \$version = \'.*.'/m,
					to: "public $version = '<%= pkg.version %>'"
				},
				{
					from: /public \$version = \'.*.'/m,
					to: "public $version = '<%= pkg.version %>'"
				},
				{
					from: /public static \$version = \'.*.'/m,
					to: "public static $version = '<%= pkg.version %>'"
				},
				{
					from: /const VERSION = \'.*.'/m,
					to: "const VERSION = '<%= pkg.version %>'"
				}
				]
			}
		}

        }
    );

	// Register tasks.
	grunt.registerTask(
        'default',
        [
		'css',
        ]
    );

	grunt.registerTask(
        'css',
        [
		'sass',
		'rtlcss',
		'postcss',
		'cssmin'
        ]
    );

	grunt.registerTask(
        'assets',
        [
		'css'
        ]
    );

	grunt.registerTask(
        'zip',
        [
		'clean',
		'copy',
		'compress'
        ]
    );

	grunt.registerTask( 'dev', [ 'sass' ] );
	grunt.registerTask( 'build', [ 'replace', 'assets', 'addtextdomain' ] );
	grunt.registerTask( 'release', [ 'build', 'zip', 'clean' ] );

};
