<?php

declare(strict_types=1);

use App\Bootstrap;

// Composer autoload
require __DIR__ . '/../vendor/autoload.php';


/**
 * Application runner class to manage Nette application lifecycle.
 */
class ApplicationRunner
{
	private Bootstrap $bootstrap;


	public function __construct()
	{
		// Initialize the Bootstrap class for app configuration
		$this->bootstrap = new Bootstrap;
	}


	/**
	 * Run the Nette application.
	 * @throws Throwable
	 */
	public function run(): void
	{
		// Create the container and get the application service
		$config = __DIR__ . '/../var/installed.lock';
		if (is_file($config)) {
			$container = $this->bootstrap
				->bootWebApplication();

		} else {
			$container = $this->bootstrap
				->bootInstallApplication();
		}

		$app = $container->getByType(Nette\Application\Application::class);
		$app->run();
	}
}

// Initialize and run the application
(new ApplicationRunner)->run();
