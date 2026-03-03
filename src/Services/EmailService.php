<?php

namespace App\Services;

use App\Config\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private Config $config;
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }

    private function configure(): void
    {
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config->get('mail.host');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config->get('mail.username');
        $this->mailer->Password = $this->config->get('mail.password');
        $this->mailer->SMTPSecure = $this->config->get('mail.encryption');
        $this->mailer->Port = $this->config->get('mail.port');
        $this->mailer->setFrom(
            $this->config->get('mail.from_address'),
            $this->config->get('mail.from_name')
        );
    }

    public function sendVerificationEmail(string $to, string $username, string $token): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = 'Verify your email address';
            
            $verificationUrl = $this->config->get('app.url') . "/verify-email.php?token={$token}";
            
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->getVerificationEmailTemplate($username, $verificationUrl);
            $this->mailer->AltBody = "Hi {$username},\n\nPlease verify your email by visiting: {$verificationUrl}";

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordResetEmail(string $to, string $username, string $token): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = 'Reset your password';
            
            $resetUrl = $this->config->get('app.url') . "/reset-password.php?token={$token}";
            
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->getPasswordResetTemplate($username, $resetUrl);
            $this->mailer->AltBody = "Hi {$username},\n\nReset your password by visiting: {$resetUrl}";

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    private function getVerificationEmailTemplate(string $username, string $url): string
    {
        return "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Welcome to Social Network!</h2>
                <p>Hi {$username},</p>
                <p>Thank you for signing up! Please verify your email address by clicking the button below:</p>
                <p><a href='{$url}' style='background-color: #4CAF50; color: white; padding: 14px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>Verify Email</a></p>
                <p>Or copy and paste this link into your browser: {$url}</p>
                <p>This link will expire in 24 hours.</p>
            </body>
            </html>
        ";
    }

    private function getPasswordResetTemplate(string $username, string $url): string
    {
        return "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Reset Your Password</h2>
                <p>Hi {$username},</p>
                <p>We received a request to reset your password. Click the button below to reset it:</p>
                <p><a href='{$url}' style='background-color: #2196F3; color: white; padding: 14px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>Reset Password</a></p>
                <p>Or copy and paste this link into your browser: {$url}</p>
                <p>If you didn't request a password reset, you can safely ignore this email.</p>
                <p>This link will expire in 1 hour.</p>
            </body>
            </html>
        ";
    }
}
