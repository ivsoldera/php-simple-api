<?php

namespace App;

class BaseDefinitions
{
	public static function loadEnv($caminho) {
		if (!file_exists($caminho)) {
			throw new \Exception("The .env file was not found in: $caminho");
		}

		$linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($linhas as $linha) {
			if (strpos($linha, '=') !== false) {
				list($chave, $valor) = explode('=', $linha, 2);
				$chave = trim($chave);
				$valor = trim($valor);
				
				if (!array_key_exists($chave, $_ENV)) {
					putenv("$chave=$valor");
					$_ENV[$chave] = $valor;
				}
			}
		}
	}

	public static function initialize() {
		$caminhoEnv = __DIR__ . '/../.env';
		self::loadEnv($caminhoEnv);

        date_default_timezone_set('America/Sao_Paulo');
	}
}
