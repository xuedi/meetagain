<?php declare(strict_types=1);

/**
 * Null Mail Provider.
 *
 * Disables email sending - useful for testing environments.
 */
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
        return 'null://null';
    }

    public function requiresConfiguration(): bool
    {
        return false;
    }
}
