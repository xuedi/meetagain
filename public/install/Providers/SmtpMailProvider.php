<?php declare(strict_types=1);

/**
 * SMTP Mail Provider.
 *
 * Custom SMTP server configuration with optional connection testing.
 * Supports TLS, SSL, and unencrypted connections.
 */
class SmtpMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'smtp';
    }

    public function getDisplayName(): string
    {
        return 'SMTP Server';
    }

    public function getDescription(): string
    {
        return 'Custom SMTP configuration';
    }

    public function getTags(): array
    {
        return [];
    }

    public function validate(array $postData, Installer $installer): bool
    {
        $host = $postData['smtp_host'] ?? '';

        if (empty($host)) {
            $installer->addError('SMTP host is required');

            return false;
        }

        // Optionally test SMTP connection if requested
        if (!empty($postData['test_smtp'])) {
            $port = (int) ($postData['smtp_port'] ?? 587);
            $user = $postData['smtp_user'] ?? '';
            $password = $postData['smtp_password'] ?? '';
            $encryption = $postData['smtp_encryption'] ?? 'tls';

            return $this->testSmtpConnection($host, $port, $user, $password, $encryption, $installer);
        }

        return true;
    }

    public function collectConfig(array $postData, Installer $installer): array
    {
        return [
            'smtp_host' => $installer->sanitize($postData['smtp_host'] ?? ''),
            'smtp_port' => $installer->sanitizeInt($postData['smtp_port'] ?? 587),
            'smtp_user' => $installer->sanitize($postData['smtp_user'] ?? ''),
            'smtp_password' => $postData['smtp_password'] ?? '',
            'encryption' => $installer->sanitize($postData['smtp_encryption'] ?? 'tls'),
        ];
    }

    public function buildDsn(array $config): string
    {
        $scheme = match ($config['encryption'] ?? 'none') {
            'tls' => 'smtp',
            'ssl' => 'smtps',
            default => 'smtp',
        };

        $auth = '';
        if (!empty($config['smtp_user'])) {
            $auth = urlencode($config['smtp_user']);
            if (!empty($config['smtp_password'])) {
                $auth .= ':' . urlencode($config['smtp_password']);
            }
            $auth .= '@';
        }

        $dsn = sprintf(
            '%s://%s%s:%d',
            $scheme,
            $auth,
            $config['smtp_host'] ?? 'localhost',
            (int) ($config['smtp_port'] ?? 25)
        );

        if (($config['encryption'] ?? 'none') === 'tls') {
            $dsn .= '?encryption=tls';
        }

        return $dsn;
    }

    public function requiresConfiguration(): bool
    {
        return true;
    }

    /**
     * Test SMTP server connection.
     */
    private function testSmtpConnection(
        string $host,
        int $port,
        ?string $user,
        ?string $password,
        string $encryption,
        Installer $installer,
    ): bool {
        try {
            $timeout = 5;
            $socket = @fsockopen(
                ($encryption === 'ssl' ? 'ssl://' : '') . $host,
                $port,
                $errno,
                $errstr,
                $timeout
            );

            if (!$socket) {
                $installer->addError("SMTP connection failed: $errstr ($errno)");

                return false;
            }

            $response = fgets($socket, 512);
            if (strpos($response, '220') !== 0) {
                $installer->addError('SMTP server did not respond correctly');
                fclose($socket);

                return false;
            }

            fclose($socket);

            return true;
        } catch (Exception $e) {
            $installer->addError('SMTP connection failed: ' . $e->getMessage());

            return false;
        }
    }
}
