<?php
require_once 'vendor/qobo/phake-builder/Phakefile.php';

function getProjectVersion($app = null) {
	$result = null;

	// If we have $app variables, try to figure out version
	if (!empty($app)) {
		// Use GIT_BRANCH variable ...
		$result = getValue('GIT_BRANCH', $app);
		// ... if empty, use git hash
		if (empty($result)) {
			try {
				$git = new \PhakeBuilder\Git(getValue('SYSTEM_COMMAND_GIT', $app));
				$result = doShellCommand($git->getCurrentHash(), null, true);
			}
			catch (\Exception $e) {
				// ignore
			}
		}
	}

	// ... if empty, use default
	if (empty($result)) {
		$result = 'Unknown';
	}

	return $result;
}

group('app', function() {

	desc('Install application');
	task('install', ':builder:init', function($app) {
		printSeparator();
		printInfo("Task: app:install (Install application)");
	});
	task('install', ':dotenv:create', ':dotenv:reload', ':file:process');
	task('install', ':mysql:database-create');
	task('install', ':cakephp:test-database-create');
	task('install', ':cakephp:test-database-migrate');
	task('install', ':cakephp:install');

	desc('Update application');
	task('update', ':builder:init', function($app) {
		printSeparator();
		printInfo("Task: app:update (Update application)");
	});
	task('update', ':dotenv:create', ':dotenv:reload', ':file:process', ':letsencrypt:symlink');
	task('update', ':cakephp:update');

	desc('Remove application');
	task('remove', ':builder:init', function($app) {
		printSeparator();
		printInfo("Task: app:remove (Update application)");
	});
	task('remove', ':dotenv:delete');
	task('remove', ':mysql:database-drop');
	task('remove', ':cakephp:test-database-drop');

});

/**
 * Grouped CakePHP related tasks
 */
group('cakephp', function() {

	desc('Setting folder permissions');
	task('set-folder-permissions', ':builder:init', function($app) {
		printSeparator();
		printInfo("Task: cakephp:set-folder-permissions (Setting folder permissions)");
		$dirMode = getValue('CHMOD_DIR_MODE', $app);
		$fileMode = getValue('CHMOD_FILE_MODE', $app);
		$user = getValue('CHOWN_USER', $app);
		$group = getValue('CHGRP_GROUP', $app);

		$paths = [
			'tmp',
			'logs',
			'webroot/uploads',
		];
        $failures = 0;
		foreach($paths as $path) {
			$path = __DIR__ . DS . $path;
			if (!file_exists($path)) {
                continue;
            }

            if ($dirMode && $fileMode) {
                try {
                    $result = \PhakeBuilder\FileSystem::chmodPath($path, $dirMode, $fileMode);
                    if (!$result) {
                        throw new \RuntimeException("Failed to change permissions to [$dirMode, $fileMode] on [$path]");
                    }
                } catch (\Exception $e) {
                    $failures++;
                    printWarning($e->getMessage());
                }
            }
            if ($user) {
                try {
                    $result = \PhakeBuilder\FileSystem::chownPath($path, $user);
                    if (!$result) {
                        throw new \RuntimeException("Failed to change user ownership to [$user] on [$path]");
                    }
                } catch (\Exception $e) {
                    $failures++;
                    printWarning($e->getMessage());
                }
            }
            if ($group) {
                try {
                    $result = \PhakeBuilder\FileSystem::chgrpPath($path, $group);
                    if (!$result) {
                        throw new \RuntimeException("Failed to change group ownership to [$group] on [$path]");
                    }
                } catch (\Exception $e) {
                    $failures++;
                    printWarning($e->getMessage());
                }
            }
		}
		printInfo("Set folder permissions has been completed with " . (int)$failures . " warnings.");
	});

	desc('Create CakePHP test database');
	task('test-database-create', ':builder:init', function($app) {
		printSeparator();
		printInfo("Task: cakephp:test-database-create (Create CakePHP test database)");

		$dbTestName = requireValue('DB_NAME', $app) . '_test';
		$query = "CREATE DATABASE " . $dbTestName;
		doMySQLCommand($app, $query, false, true);
	});

	desc('Drop CakePHP test database');
	task('test-database-drop', ':builder:init', function($app) {
		printSeparator();
		printInfo("Task: cakephp:test-database-drop (Drop CakePHP test database)");

		$dbTestName = requireValue('DB_NAME', $app) . '_test';
		$query = "DROP DATABASE " . $dbTestName;
		doMySQLCommand($app, $query, false, true);
	});

	desc('Run migrations for the test database');
	task('test-database-migrate', ':builder:init', function($app) {
		printSeparator();
		printInfo("Task: cakephp:test-database-migrate (Run migrations for the test database)");

		$command = getenv('CAKE_CONSOLE') . ' migrations migrate --connection=test';
		doShellCommand($command);

		/**
		 * shell command for running loaded plugins migrations
		 * @var string
		 */
		$command = getenv('CAKE_CONSOLE') . ' plugin migrations migrate --connection=test';
		doShellCommand($command);
	});


	desc('Run CakePHP migrations task');
	task('migrations', ':builder:init', function() {
		printSeparator();
		printInfo("Task: cakephp:migrations (Run CakePHP migrations task)");

		/**
		 * shell command for running application migrations
		 * @var string
		 */
		$command = getenv('CAKE_CONSOLE') . ' migrations migrate';
		doShellCommand($command);

		/**
		 * shell command for running loaded plugins migrations
		 * @var string
		 */
		$command = getenv('CAKE_CONSOLE') . ' plugin migrations migrate';
		doShellCommand($command);
	});

	desc('Create dev user');
	task('dev-user-create', ':builder:init', function() {
		printSeparator();
		printInfo("Task: cakephp:dev-user-create (Create dev user)");

		$command  = getenv('CAKE_CONSOLE') . ' users addUser';
		$command .= ' --username=' . getenv('DEV_USER');
		$command .= ' --password=' . getenv('DEV_PASS');
		$command .=' --email=' . getenv('DEV_EMAIL');
		doShellCommand($command);
	});

	desc('Run CakePHP clear cache task');
	task('clear-cache', ':builder:init', function() {
		printSeparator();
		printInfo("Task: cakephp:clear-cache (Run CakePHP clear cache task)");

		$command = getenv('CAKE_CONSOLE') . ' clear_cache all';
		doShellCommand($command);
	});

	/**
	 * 'Grouped CakePHP app update related tasks
	 */
	desc('Run CakePHP app update related tasks');
	task(
		'update',
		':builder:init',
		':cakephp:clear-cache',
		':cakephp:migrations',
		':cakephp:set-folder-permissions',
		function($app) {
			printSeparator();
		    printInfo("Task: cakephp:update (Run CakePHP app update related tasks)");
		}
	);

	/**
	 * 'Grouped CakePHP app install related tasks
	 */
	desc('Runs CakePHP app install related tasks');
	task(
		'install',
		':builder:init',
		':cakephp:migrations',
		':cakephp:dev-user-create',
		':cakephp:set-folder-permissions',
		function($app) {
			printSeparator();
 			printInfo("Task: cakephp:install (Run CakePHP app install related tasks)");
		}
	);

	//
	// Save version that we are deploying, both before and after
	//

	after(':builder:init', function($app) {
		$version = getProjectVersion($app);
		// Save the version that we are deploying
		if (file_exists('build/version')) {
			rename('build/version', 'build/version.bak');
		}
		file_put_contents('build/version', $version);
	});

	after('install', function($app) {
		$version = getProjectVersion($app);
		// Save the version that we have deployed
		if (file_exists('build/version.ok')) {
			rename('build/version.ok', 'build/version.ok.bak');
		}
		file_put_contents('build/version.ok', $version);
	});

	after('update', function($app) {
		$version = getProjectVersion($app);
		// Save the version that we have deployed
		if (file_exists('build/version.ok')) {
			rename('build/version.ok', 'build/version.ok.bak');
		}
		file_put_contents('build/version.ok', $version);
	});

});
