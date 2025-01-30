<?php

namespace Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
    }

    public function sendEmail($to, $subject, $body) {
        try {
            $this->mail->isSMTP();
            $this->mail->Host       = getenv('SMTP_HOST');
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = getenv('SMTP_USERNAME');
            $this->mail->Password   = getenv('SMTP_PASSWORD');
            $this->mail->SMTPSecure = 'tls'; 
            $this->mail->Port       = getenv('SMTP_PORT');
        
            $this->mail->CharSet = "UTF-8";
        
            $this->mail->setFrom(getenv('SMTP_USERNAME'), getenv('SMTP_NAME'));
            $this->mail->addAddress($to);
        
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
        
            $this->mail->send();
            error_log("Email sent successfully!");

            return true;
        } catch (\Exception $e) {
            error_log("Erro ao enviar e-mail: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
