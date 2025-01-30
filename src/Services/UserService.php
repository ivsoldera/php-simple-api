<?php

namespace Services;

use Services\DBService;
use Services\EmailService;

// mysql> describe users;
// +------------+--------------+------+-----+-------------------+-------------------+
// | Field      | Type         | Null | Key | Default           | Extra             |
// +------------+--------------+------+-----+-------------------+-------------------+
// | id         | int          | NO   | PRI | NULL              | auto_increment    |
// | username   | varchar(255) | YES  |     | NULL              |                   |
// | email      | varchar(255) | YES  |     | NULL              |                   |
// | password   | varchar(255) | YES  |     | NULL              |                   |
// | phone      | varchar(50)  | YES  |     | NULL              |                   |        |
// | created_at | datetime     | YES  |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
// +------------+--------------+------+-----+-------------------+-------------------+

// mysql> describe password_resets;
// +------------+--------------+------+-----+-------------------+-------------------+
// | Field      | Type         | Null | Key | Default           | Extra             |
// +------------+--------------+------+-----+-------------------+-------------------+
// | id         | int          | NO   | PRI | NULL              | auto_increment    |
// | user_id    | int          | NO   | MUL | NULL              |                   |
// | otp_code   | int          | NO   |     | NULL              |                   |
// | expires_at | datetime     | NO   |     | NULL              |                   |
// | created_at | datetime     | YES  |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
// +------------+--------------+------+-----+-------------------+-------------------+

class UserService {

	private $db;
	private $emailService;


	function __construct(){
		$this->db = new DBService();
		$this->emailService = new EmailService();
		if(!$this->db->connect(getenv('DB_HOST'), getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASSWORD'))){
			throw new \ErrorException('Failed to connect to the database');
		}
	}

    public function login($email, $password) {
        $user = $this->getUserByEmailOrUsername($email);
        if (!$user) {
            throw new \Exception("user_not_found", 404);
        }
        
        if (!$this->verifyPassword($password, $user['password'])) {
            throw new \Exception("invalid_credentials", 401);
        }
        
        $accessToken = $this->generateAccessToken($user);
        $refreshToken = $this->generateRefreshToken($user);

		$this->updateLastLogin($user['id']);
        
        return [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'created_at' => $user['created_at'],
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ];
    }

	private function updateLastLogin($userId){
		$userId = addslashes($userId);
		return $this->db->sql("UPDATE users SET last_login = NOW() WHERE id = '$userId' and deleted_at is null");
	}
    
    private function generateAccessToken($user) {
        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'exp' => time() + (60 * 60)
        ];
        
        return $this->generateJWT($payload);
    }
    
    private function generateRefreshToken($user) {
        $payload = [
            'user_id' => $user['id'],
            'exp' => time() + (7 * 24 * 60 * 60)
        ];
        
        return $this->generateJWT($payload);
    }
    
    private function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, getenv('JWT_SECRET'), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public function verifyJWT($token) {
        $tokenParts = explode('.', $token);
        
        if (count($tokenParts) != 3) {
            throw new \Exception('invalid_token_format');
        }
        
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, getenv('JWT_SECRET'), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($base64UrlSignature !== $signatureProvided) {
            throw new \Exception('invalid_signature');
        }
        
        $payloadObj = json_decode($payload, true);
        
        if (isset($payloadObj['exp']) && $payloadObj['exp'] < time()) {
            throw new \Exception('token_expired');
        }
        
        return $payloadObj;
    }

    public function refreshToken($refreshToken) {
        try {
            $payload = $this->verifyJWT($refreshToken);
            $user = $this->getUserById($payload['user_id']);
            
            if (!$user) {
                throw new \Exception("user_not_found", 404);
            }
            
            $accessToken = $this->generateAccessToken($user);
            
            return [
                'access_token' => $accessToken
            ];
        } catch (\Exception $e) {
            throw new \Exception("invalid_refresh_token", 401);
        }
    }

    public function getRandomPassword($length = 12, $includeNumbers = true, $includeSymbols = true) {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()-_=+[]{}|;:,.<>?';

        $characters = $letters;
        if ($includeNumbers) {
            $characters .= $numbers;
        }
    
        if ($includeSymbols) {
            $characters .= $symbols;
        }
    
        $password = '';
        $maxIndex = strlen($characters) - 1;
    
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $maxIndex)];
        }
    
        return $password;
    }

    private function encryptPassword($password){
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 6]);
    }

    private function verifyPassword($password, $hash){
        return password_verify($password, $hash);
    }

    public function createUser($username, $email, $phone, $password){
        if($this->getUserByEmail($email)){
            throw new \ErrorException('user_already_registered');
        }

        $insert = [
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $this->encryptPassword($password)
        ];

        $password = $this->encryptPassword($password);
        $this->db->sql("INSERT INTO users", $insert);

        return true;
    }

	public function updatePassword($userId, $newPassword){
        $newPasswordEncrypted = $this->encryptPassword($newPassword);

        return $this->db->sql("UPDATE users SET password = '$newPasswordEncrypted' WHERE id = '$userId' and deleted_at is null");
    }

    public function resetPassword($email, $otpCode, $newPassword){
        $newPasswordEncrypted = $this->encryptPassword($newPassword);
		$email = addslashes($email);
        $otpCode = addslashes($otpCode);

        $user = $this->getUserByEmail($email);

        if(!$user){
            throw new \Exception("user_not_found", 404);
        }

        $passwordReset = $this->db->sql("SELECT * FROM password_resets WHERE otp_code = '$otpCode' AND user_id = '$user[id]' AND expires_at > NOW() LIMIT 1");

        if(!$passwordReset){
            throw new \Exception("invalid_otp_code", 401);
        }

        $this->db->sql("DELETE FROM password_resets WHERE id = '$passwordReset[id]'");

        return $this->db->sql("UPDATE users SET password = '$newPasswordEncrypted' WHERE id = '$user[id]' and deleted_at is null");
    }

	public function getUserById($id){
		$id = addslashes($id);
		return $this->db->sql("SELECT * FROM users WHERE id = '$id' and deleted_at is null LIMIT 1");
	}

    public function getUserByEmail($email){
        $email = addslashes($email);
        return $this->db->sql("SELECT * FROM users WHERE email = '$email' and deleted_at is null LIMIT 1");
    }

	public function getUserByEmailOrUsername($value){
		$value = addslashes($value);
		return $this->db->sql("SELECT * FROM users WHERE ( email = '$value' or username = '$value' ) and deleted_at is null LIMIT 1");
	}

	public function getUserFromClothoffWebhook($idGen){
	  $idGen = addslashes($idGen);

	  return $this->db->sql("SELECT m.user_id, u.username FROM media m 
							JOIN users u ON m.user_id = u.id 
							WHERE m.id = '$idGen' LIMIT 1");
	}

    public function deleteAccount($userId){
        $userId = addslashes($userId);
        return $this->db->sql("UPDATE users SET deleted_at = NOW() WHERE id = '$userId' and deleted_at is null");
    }

    public function forgotPassword($email){
        try {
            $email = addslashes($email);
            $randomOtpCode = random_int(10000, 99999);

            $user = $this->getUserByEmail($email);

            if(!$user){
                throw new \Exception("user_not_found", 404);
            }

            $this->db->sql("DELETE FROM password_resets WHERE user_id = '$user[id]'");

            $insert = [
                'user_id' => $user['id'],
                'otp_code' => $randomOtpCode,
                'expires_at' => date('Y-m-d H:i:s', time() + 10 * 60)
            ];

            $this->db->sql("INSERT INTO password_resets", $insert);

            $htmlContent = '
                <html>
                <body>
                    <div class="container">
                        <h1>Password Reset</h1>
                        <p>Hello,</p>
                        <p>You have requested to reset your password. Use the code below to reset your password:</p>
                        <p class="otp-code">' . $randomOtpCode . '</p>
                    </div>
                </body>
                </html>
            ';

            if (!$this->emailService->sendEmail($email, 'Password Reset', $htmlContent)) {
                throw new \Exception("error_sending_email", 500);
            }

            return true;
        } catch (\Exception $e) {
			throw $e;
		}
    }

    public function verifyOtpCode($otpCode, $email){
        $email = addslashes($email);
        $otpCode = addslashes($otpCode);

        $user = $this->getUserByEmail($email);

        if(!$user){
            throw new \Exception("user_not_found", 404);
        }

        $passwordReset = $this->db->sql("SELECT * FROM password_resets WHERE otp_code = '$otpCode' AND user_id = '$user[id]' AND expires_at > NOW() LIMIT 1");

        if(!$passwordReset){
            throw new \Exception("invalid_otp_code", 401);
        }

        return true;
    }
}