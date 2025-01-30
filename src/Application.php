<?php

namespace App;

use App\BaseDefinitions;
use App\Router;

class Application {
	private $router;

	public function __construct() {
		BaseDefinitions::initialize();
		$this->router = new Router();
	}

	public function start() {
		$router = $this->router;

		$routeFiles = glob(__DIR__ . '/routes/*.php');
		foreach ($routeFiles as $routeFile) {
			require_once $routeFile;
		}

		$this->router->init();
	}
}