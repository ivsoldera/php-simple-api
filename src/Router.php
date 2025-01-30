<?php

namespace App;

use App\ExceptionHandler;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;

class Router
{
	private $routes = [];

	public function create(
		string $method,
		string $path,
		callable $callback,
		?array $middleware = null
	) {
		$this->routes[$method][$path] = function() use ($callback, $method, $middleware) {
			return ExceptionHandler::handle(function() use ($callback, $method, $middleware) {
				$params = [];
				$headers = [];

				// Captura os headers da requisição
				foreach ($_SERVER as $key => $value) {
					if (strpos($key, 'HTTP_') === 0) {
						$header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
						$headers[$header] = $value;
					}
				}

                $authenticatedUser = null;
				if ($middleware) {
                    $middlewareClass = $middleware[0];
					$middlewareMethod = $middleware[1];
					
					$authenticatedUser = $middlewareClass::$middlewareMethod($headers);
				}
                

				if ($method === 'GET' || $method === 'DELETE') {
					$params = $_GET;
				}

				if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
					$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
					
					if (strpos($contentType, 'application/json') !== false) {
						$body = file_get_contents('php://input');
						$params = json_decode($body, true) ?? [];
					} elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
						$params = $_POST;
					} elseif (strpos($contentType, 'multipart/form-data') !== false) {
						$params = $_POST;
						$params = array_merge($params, $_FILES);
					} else {
						$body = file_get_contents('php://input');
						parse_str($body, $params);
					}
				}

				return $callback($headers, $params, $authenticatedUser ?? null);
			});
		};
	}

	public function init()
	{
		CorsMiddleware::handle();
	
		$httpMethod = $_SERVER["REQUEST_METHOD"];
		
		// O método atual existe em nossas rotas?
		if (isset($this->routes[$httpMethod])) {
			header('Content-Type: application/json; charset=utf-8');
			
			// Obter o caminho da URL sem a query string
			$requestUri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
			
			// Percore as rotas com o método atual:
			foreach ($this->routes[$httpMethod] as $path => $callback) {
				// Se a rota atual existir, retorna a função...
				if ($path === $requestUri) {
					return $callback();
				}
			}
		}
	
		// Caso não exista a rota/método atual: 
		http_response_code(404);
		return;
	}
}
