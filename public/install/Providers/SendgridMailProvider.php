<?php

declare(strict_types=1);

/**
 * SendGrid Mail Provider
 *
 * Cloud email delivery service using API key authentication.
 */
class SendgridMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'sendgrid';
    }

    public function getDisplayName(): string
    {
        return 'SendGrid';
    }

    public function getDescription(): string
    {
        return 'Cloud email delivery service';
    }

    public function getTags(): array
    {
        return [];
    }

    public function validate(array $postData, Installer $installer): bool
    {
        $apiKey = $postData['sendgrid_api_key'] ?? '';

        if (empty($apiKey)) {
            $installer->addError('SendGrid API key is required');
            return false;
        }

        return true;
    }

    public function collectConfig(array $postData, Installer $installer): array
    {
        return [
            'api_key' => $postData['sendgrid_api_key'] ?? '',
        ];
    }

    public function buildDsn(array $config): string
    {
        return sprintf('sendgrid+api://%s@default', urlencode($config['api_key'] ?? ''));
    }

    public function requiresConfiguration(): bool
    {
        return true;
    }
}
