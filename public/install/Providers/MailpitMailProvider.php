<?php declare(strict_types=1);

class MailpitMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'mailpit';
    }

    public function getDisplayName(): string
    {
        return 'Mailpit';
    }

    public function getDescription(): string
    {
        return 'Local email testing tool';
    }

    public function getTags(): array
    {
        return ['Docker'];
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
        return 'smtp://mailpit:1025';
    }

    public function requiresConfiguration(): bool
    {
        return false;
    }
}
