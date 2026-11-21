<?php
declare(strict_types=1);

/**
 * Small SMTP client (AUTH LOGIN, STARTTLS or SSL) for the "smtp" mail driver - no external library.
 *   (new SmtpClient($host, 587, 'tls', $user, $pass))->send($from, $fromName, $to, $subject, $html);
 * @throws RuntimeException with the server's reply when something fails
 */
final class SmtpClient
{
    /** @var resource|null */
    private $sock = null;
    private array $log = [];

    public function __construct(
        private string $host,
        private int $port = 587,
        private string $encryption = 'tls',
        private string $username = '',
        private string $password = '',
        private int $timeout = 15
    ) {
    }

    private function read(): string
    {
        $data = '';
        while (($line = fgets($this->sock, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $this->log[] = 'S: ' . trim($data);
        return $data;
    }

    private function cmd(string $cmd, array $expect, bool $secret = false): string
    {
        fwrite($this->sock, $cmd . "\r\n");
        $this->log[] = 'C: ' . ($secret ? '***' : $cmd);
        $reply = $this->read();
        if (!in_array((int) substr($reply, 0, 3), $expect, true)) {
            throw new RuntimeException('SMTP: ' . trim($reply));
        }
        return $reply;
    }

    public function send(string $from, string $fromName, string $to, string $subject, string $html): string
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $this->sock = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) {
            throw new RuntimeException("SMTP: cannot connect to {$this->host}:{$this->port} ({$errstr})");
        }
        stream_set_timeout($this->sock, $this->timeout);
        try {
            $greet = $this->read();
            if ((int) substr($greet, 0, 3) !== 220) {
                throw new RuntimeException('SMTP: ' . trim($greet));
            }
            $me = gethostname() ?: 'localhost';
            $this->cmd('EHLO ' . $me, [250]);
            if ($this->encryption === 'tls') {
                $this->cmd('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('SMTP: TLS negotiation failed');
                }
                $this->cmd('EHLO ' . $me, [250]);
            }
            if ($this->username !== '') {
                $this->cmd('AUTH LOGIN', [334]);
                $this->cmd(base64_encode($this->username), [334], true);
                $this->cmd(base64_encode($this->password), [235], true);
            }
            $this->cmd('MAIL FROM:<' . $from . '>', [250]);
            $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $this->cmd('DATA', [354]);
            $msg = self::message($from, $fromName, $to, $subject, $html);
            $msg = preg_replace('/^\./m', '..', $msg); // dot-stuffing
            $reply = $this->cmd($msg . "\r\n.", [250]);
            $this->cmd('QUIT', [221]);
            return trim($reply);
        } finally {
            if (is_resource($this->sock)) {
                fclose($this->sock);
            }
        }
    }

    /** RFC 5322 message with a UTF-8 HTML body and a plain-text alternative. */
    public static function message(string $from, string $fromName, string $to, string $subject, string $html): string
    {
        $b = 'b' . bin2hex(random_bytes(8));
        $enc = static fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $enc($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $enc($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
        ];
        return implode("\r\n", $headers) . "\r\n\r\n"
            . "--{$b}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n"
            . "--{$b}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$b}--";
    }
}
