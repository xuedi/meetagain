<?php

declare(strict_types=1);

/**
 * Amazon SES Mail Provider
 *
 * AWS Simple Email Service with region and access key configuration.
 */
class SesMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'ses';
    }

    public function getDisplayName(): string
    {
        return 'Amazon SES';
    }

    public function getDescription(): string
    {
        return 'AWS email service';
    }

    public function getTags(): array
    {
        return [];
    }

    public function validate(array $postData, Installer $installer): bool
    {
        $accessKey = $postData['ses_access_key'] ?? '';
        $secretKey = $postData['ses_secret_key'] ?? '';

        $hasErrors = false;

        if (empty($accessKey)) {
            $installer->addError('AWS Access Key is required');
            $hasErrors = true;
        }

        if (empty($secretKey)) {
            $installer->addError('AWS Secret Key is required');
            $hasErrors = true;
        }

        return !$hasErrors;
    }

    public function collectConfig(array $postData, Installer $installer): array
    {
        return [
            'region' => $installer->sanitize($postData['ses_region'] ?? 'eu-west-1'),
            'access_key' => $postData['ses_access_key'] ?? '',
            'secret_key' => $postData['ses_secret_key'] ?? '',
        ];
    }

    public function buildDsn(array $config): string
    {
        return sprintf(
            'ses+api://%s:%s@default?region=%s',
            urlencode($config['access_key'] ?? ''),
            urlencode($config['secret_key'] ?? ''),
            $config['region'] ?? 'eu-west-1'
        );
    }

    public function requiresConfiguration(): bool
    {
        return true;
    }
}
