<?php

use Controllers\UserController;
use App\Middleware\AuthMiddleware;

$userController = new UserController();

$router->create("POST", "/login", [$userController, 'login']);

$router->create("POST", "/refresh-token", [$userController, 'refreshToken']);

$router->create("PUT", "/update-password", [$userController, 'updatePassword'], [AuthMiddleware::class, 'authenticate']);

$router->create("PUT", "/reset-password", [$userController, 'resetPassword']);

$router->create("DELETE", "/delete-account", [$userController, 'deleteAccount'], [AuthMiddleware::class, 'authenticate']);

$router->create("POST", "/forgot-password", [$userController, 'forgotPassword']);

$router->create("POST", "/verify-otp-code", [$userController, 'verifyOtpCode']);
