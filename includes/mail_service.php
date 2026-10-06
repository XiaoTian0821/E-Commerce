<?php
/**
 * Mail notification service
 * Uses PHPMailer when available, falls back to mail() or silent mode.
 */
defined('APP_START') or die();

class MailService
{
    private bool $smtpEnabled;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->smtpEnabled  = defined('SMTP_ENABLED')  && SMTP_ENABLED;
        $this->fromEmail    = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@localhost';
        $this->fromName     = defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME  : APP_NAME;
    }

    /**
     * Send a plain-text email
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $headers = [
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        if ($this->smtpEnabled) {
            return $this->sendViaSmtp($to, $subject, $body, $headers);
        }

        // Fallback to PHP mail() – may not work on all local setups
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private function sendViaSmtp(string $to, string $subject, string $body, array $headers): bool
    {
        // Minimal SMTP send using stream_socket_client
        $host     = defined('SMTP_HOST')     ? SMTP_HOST     : '';
        $port     = defined('SMTP_PORT')     ? SMTP_PORT     : 587;
        $username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $enc      = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';

        if (empty($host) || empty($username)) {
            return false;
        }

        $socketHost = ($enc === 'ssl') ? 'ssl://' . $host : $host;
        $ctx        = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $sock = @stream_socket_client(
            $socketHost . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$sock) {
            logMessage('error', "SMTP connection failed: $errno $errstr");
            return false;
        }

        $response = '';
        $this->readStream($sock, $response);

        $ehlo = "EHLO localhost\r\n";
        $this->writeStream($sock, $ehlo);
        $this->readStream($sock, $response);

        if ($enc === 'tls') {
            fwrite($sock, "STARTTLS\r\n");
            $this->readStream($sock, $response);
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        fwrite($sock, "AUTH LOGIN\r\n");
        $this->readStream($sock, $response);
        fwrite($sock, base64_encode($username) . "\r\n");
        $this->readStream($sock, $response);
        fwrite($sock, base64_encode($password) . "\r\n");
        $this->readStream($sock, $response);

        fwrite($sock, "MAIL FROM:<" . $this->fromEmail . ">\r\n");
        $this->readStream($sock, $response);
        fwrite($sock, "RCPT TO:<" . $to . ">\r\n");
        $this->readStream($sock, $response);
        fwrite($sock, "DATA\r\n");
        $this->readStream($sock, $response);

        $fullMessage = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
        fwrite($sock, $fullMessage);
        $this->readStream($sock, $response);

        fwrite($sock, "QUIT\r\n");
        fclose($sock);

        return str_contains($response, '250');
    }

    private function writeStream($sock, string $data): void
    {
        fwrite($sock, $data);
    }

    private function readStream($sock, &$response): void
    {
        $response = '';
        while ($line = fgets($sock, 512)) {
            $response .= $line;
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
    }
}
