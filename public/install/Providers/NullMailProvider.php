<?php declare(strict_types=1);

class NullMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'null';
    }

    public function getDisplayName(): string
    {
        return 'Disabled';
    }

    public function getDescription(): string
    {
        return 'No emails will be sent';
    }

    public function getTags(): array
    {
        return ['Testing'];
    }

    public function validate(array $postData, Installer $installer): bool
    {
        return true;
    }

    public function collectConfig(array $postData, Installer $installer): array
    {
        return [];
    }

    public function buildDsn(array $config): string
    {
        return 'null://null';
    }

    public function requiresConfiguration(): bool
    {
        return false;
    }
}
