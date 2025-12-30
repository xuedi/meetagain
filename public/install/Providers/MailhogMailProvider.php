<?php declare(strict_types=1);

/**
 * MailHog Mail Provider.
 *
 * Local email testing tool for development.
 * Captures all emails and makes them viewable via web interface.
 */
class MailhogMailProvider implements MailProvider
{
    public function getName(): string
    {
        return 'mailhog';
    }

    public function getDisplayName(): string
    {
        return 'MailHog';
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
        // No validation needed
        return true;
    }

    public function collectConfig(array $postData, Installer $installer): array
    {
        // No configuration needed
        return [];
    }

    public function buildDsn(array $config): string
    {
        return 'smtp://mailhog:1025';
    }

    public function requiresConfiguration(): bool
    {
        return false;
    }
}
