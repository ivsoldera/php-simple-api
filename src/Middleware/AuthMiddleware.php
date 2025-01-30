<?php

namespace App\Middleware;

use Services\UserService;

class AuthMiddleware {
    private static $UserService;

    public static function init() {
        if (!self::$UserService) {
            self::$UserService = new UserService();
        }
    }
  
    public static function authenticate($headers) {
        self::init();
        $authHeader = $headers['Authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new \Exception('No token provided', 401);
        }

        $token = $matches[1];

        try {
            $payload = self::$UserService->verifyJWT($token);
            return $payload;
        } catch (\Exception $e) {
            throw new \Exception('Invalid token', 401);
        }
    }
}