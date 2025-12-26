<?php

declare(strict_types=1);

/**
 * Mailgun Mail Provider
 *
 * Email automation service with API key, domain, and region configuration.
 */
class MailgunMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'mailgun';
    }

    public function getDisplayName(): string
    {
        return 'Mailgun';
    }

    public function getDescription(): string
    {
        return 'Email automation service';
    }

    public function getTags(): array
    {
        return [];
    }

    public function validate(array $postData, Installer $installer): bool
    {
        $apiKey = $postData['mailgun_api_key'] ?? '';
        $domain = $postData['mailgun_domain'] ?? '';

        $hasErrors = false;

        if (empty($apiKey)) {
            $installer->addError('Mailgun API key is required');
            $hasErrors = true;
        }

        if (empty($domain)) {
            $installer->addError('Mailgun domain is required');
            $hasErrors = true;
        }

        return !$hasErrors;
    }

    public function collectConfig(array $postData, Installer $installer): array
    {
        return [
            'api_key' => $postData['mailgun_api_key'] ?? '',
            'domain' => $installer->sanitize($postData['mailgun_domain'] ?? ''),
            'region' => $installer->sanitize($postData['mailgun_region'] ?? 'us'),
        ];
    }

    public function buildDsn(array $config): string
    {
        return sprintf(
            'mailgun+api://%s:%s@default?region=%s',
            urlencode($config['api_key'] ?? ''),
            urlencode($config['domain'] ?? ''),
            $config['region'] ?? 'us'
        );
    }

    public function requiresConfiguration(): bool
    {
        return true;
    }
}
