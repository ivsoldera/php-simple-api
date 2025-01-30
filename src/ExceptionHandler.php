<?php

namespace App;

use Exception;

class ExceptionHandler
{
	public static function handle(callable $callback)
	{
		try {
			return $callback();
		} catch (Exception $e) {
			$statusCode = $e->getCode() ?: 500;
			$message = $e->getMessage();

			http_response_code($statusCode);
			header('Content-Type: application/json');
			echo json_encode(['error' => $message]);
			exit;
		}
	}
}