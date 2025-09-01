<?php // File: app/services/NotificationService.php
declare(strict_types=1);

class NotificationService
{
    protected $mailConfig;

    public function __construct()
    {
        $this->mailConfig = require __DIR__ . '/../../config/mail.php';
    }

    /**
     * Send email using native mail() (simple) or other transport later.
     */
    public function sendEmail(string $to, string $subject, string $body): bool
    {
        $from = $this->mailConfig['from'] ?? 'no-reply@example.com';
        $fromName = $this->mailConfig['from_name'] ?? '';
        $headers = "From: " . ($fromName ? "{$fromName} <{$from}>" : $from) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        // Native mail() — fine for development
        return mail($to, $subject, $body, $headers);
    }

    /**
     * Send SMS via Twilio if env vars present, else return false.
     * Requires TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM in .env to work.
     */
    public function sendSMS(string $to, string $message): bool
    {
        $sid = getenv('TWILIO_ACCOUNT_SID') ?: getenv('TWILIO_SID');
        $token = getenv('TWILIO_AUTH_TOKEN') ?: getenv('TWILIO_TOKEN');
        $from = getenv('TWILIO_FROM');

        if (!$sid || !$token || !$from) {
            error_log('Twilio credentials not configured. SMS not sent.');
            return false;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';

        $data = http_build_query([
            'From' => $from,
            'To'   => $to,
            'Body' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            error_log('Twilio curl error: ' . $err);
            return false;
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        error_log("Twilio responded with status {$status}: {$resp}");
        return false;
    }
}
