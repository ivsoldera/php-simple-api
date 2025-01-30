
<?php
date_default_timezone_set('America/Sao_Paulo');

include __DIR__ . '/../vendor/autoload.php';

use App\Application;

$app = new Application();

$app->start();
