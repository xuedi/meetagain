<?php declare(strict_types=1);

/**
 * Mail Provider Interface
 *
 * Defines the contract for all mail provider implementations.
 * Each provider handles its own validation, configuration collection, and DSN building.
 */
interface MailProvider
{
    /**
     * Get the unique identifier for this provider (e.g., 'smtp', 'sendgrid', 'mailhog')
     */
    public function getName(): string;

    /**
     * Get the human-readable display name for this provider
     */
    public function getDisplayName(): string;

    /**
     * Get a short description of this provider
     */
    public function getDescription(): string;

    /**
     * Get optional tags for this provider (e.g., 'Docker', 'Testing')
     *
     * @return array<string>
     */
    public function getTags(): array;

    /**
     * Validate provider-specific configuration from POST data
     *
     * @param array<string, mixed> $postData
     */
    public function validate(array $postData, Installer $installer): bool;

    /**
     * Collect provider-specific configuration from POST data
     *
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    public function collectConfig(array $postData, Installer $installer): array;

    /**
     * Build the Symfony Mailer DSN from provider configuration
     *
     * @param array<string, mixed> $config
     */
    public function buildDsn(array $config): string;

    /**
     * Check if this provider requires configuration
     */
    public function requiresConfiguration(): bool;
}
