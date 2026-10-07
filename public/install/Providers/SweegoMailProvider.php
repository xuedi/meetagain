<?php declare(strict_types=1);

class SweegoMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'sweego';
    }

    public function getDisplayName(): string
    {
        return 'Sweego';
    }

    public function getDescription(): string
    {
        return 'European email delivery service, with delivery status in the admin send log';
    }

    public function getTags(): array
    {
        return ['EU'];
    }

    public function validate(array $postData, Installer $installer): bool
    {
        $apiKey = $postData['sweego_api_key'] ?? '';

        if (empty($apiKey)) {
            $installer->addError('Sweego API key is required');

            return false;
        }

        return true;
    }

    public function collectConfig(array $postData, Installer $installer): array
    {
        return [
            'api_key' => $postData['sweego_api_key'] ?? '',
        ];
    }

    public function buildDsn(array $config): string
    {
        return sprintf('sweego+api://%s@default', rawurlencode($config['api_key'] ?? ''));
    }

    public function requiresConfiguration(): bool
    {
        return true;
    }
}
