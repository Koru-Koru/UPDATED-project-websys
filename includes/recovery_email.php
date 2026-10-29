<?php
require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';

function recovery_email_config(): array {
    $path = __DIR__ . '/mail.local.php';
    $config = is_file($path) ? require $path : [];
    if (!is_array($config) || !filter_var($config['username'] ?? '', FILTER_VALIDATE_EMAIL)
        || trim($config['password'] ?? '') === '') {
        throw new RuntimeException('Email is not configured. Set the Gmail address and app password in includes/mail.local.php.');
    }
    $url = rtrim($config['app_url'] ?? '', '/');
    if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
        || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
        throw new RuntimeException('Set the public HTTPS website URL in includes/mail.local.php so the customer can open the reset link.');
    }
    $config['app_url'] = $url;
    return $config;
}

function send_customer_reset_email(array $config, string $email, string $name, string $token): void {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username = $config['username'];
    $mail->Password = preg_replace('/\s+/', '', $config['password']);
    $mail->Timeout = 20;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['username'], $config['from_name'] ?? "Philippines' Best Coffee");
    $mail->addAddress($email, $name);
    $mail->Subject = "Reset your Philippines' Best Coffee password";
    $link = $config['app_url'] . '/customer/reset_password.php?token=' . rawurlencode($token);
    $mail->Body = "Hello $name,\n\nYour identity has been verified by our staff. Use this link to reset your password:\n\n$link\n\nThis link expires in 30 minutes and can only be used once.\nIf you did not request a password reset, contact our staff.\n\nPhilippines' Best Coffee";
    $mail->send();
}
