<?php 

namespace Controllers;

use Services\UserService;

class UserController {
	private $UserService;

	public function __construct() {
		$this->UserService = new UserService();
	}

	public function login($headers, $params) {
		try {
			$email = $params['email'] ?? null;
			$password = $params['password'] ?? null;
			
			if (!$email || !$password) {
				throw new \Exception("email_and_password_are_required", 400);
			}
	
			$result = $this->UserService->login($email, $password);
	
			http_response_code(200);
			echo json_encode($result);
			return;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function refreshToken($headers, $params) {
		try {
			$authHeader = $headers['Authorization'] ?? null;
			$refreshToken = null;
			
			if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
				$refreshToken = $matches[1];
			}
			
			if (!$refreshToken) {
				throw new \Exception("refresh_token_is_required", 400);
			}

			$result = $this->UserService->refreshToken($refreshToken);

			http_response_code(200);
			echo json_encode($result);
			return;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function updatePassword($headers, $params, $authenticatedUser) {
		try {
			$newPassword = $params['newPassword'] ?? null;
			$userId = $authenticatedUser['user_id'] ?? null;
			
			if (!$newPassword) {
				throw new \Exception("new_password_is_required", 400);
			}

			if (!$userId) {
				throw new \Exception("user_id_is_required", 400);
			}

			$this->UserService->updatePassword($userId, $newPassword);

			http_response_code(200);
			echo json_encode(['message' => 'Password updated successfully']);
			return;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function resetPassword($headers, $params, $authenticatedUser) {
		try {
			$newPassword = $params['newPassword'] ?? null;
			$email = $params['email'] ?? null;
			$otpCode = $params['otpCode'] ?? null;
			
			if (!$newPassword) {
				throw new \Exception("new_password_is_required", 400);
			}
			
			if (!$email) {
				throw new \Exception("email_is_required", 400);
			}

			if (!$otpCode) {
				throw new \Exception("otp_code_is_required", 400);
			}

			$this->UserService->resetPassword($email, $otpCode, $newPassword);

			http_response_code(200);
			echo json_encode(['message' => 'Password updated successfully']);
			return;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function deleteAccount($headers, $params, $authenticatedUser) {
		try {
			$userId = $authenticatedUser['user_id'] ?? null;

			$this->UserService->deleteAccount($userId);

			http_response_code(200);
			echo json_encode(['message' => 'Account deleted successfully']);
			return;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function forgotPassword($headers, $params) {
		try {
			$email = $params['email'] ?? null;
			
			if (!$email) {
				throw new \Exception("email_is_required", 400);
			}

			$this->UserService->forgotPassword($email);

			http_response_code(200);
			echo json_encode(['message' => 'Password reset email sent']);
			return;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function verifyOtpCode($headers, $params) {
		try {
			$otpCode = $params['otpCode'] ?? null;
			$email = $params['email'] ?? null;

			if (!$email) {
				throw new \Exception("email_is_required", 400);
			}

			if (!$otpCode) {
				throw new \Exception("otp_code_is_required", 400);
			}

			$this->UserService->verifyOtpCode($otpCode, $email);

			http_response_code(200);
			echo json_encode(['message' => 'OTP code verified successfully']);
			return;
		} catch (\Exception $e) {
			throw $e;
		}
	}
}