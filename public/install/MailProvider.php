<?php

declare(strict_types=1);

/**
 * Mail Provider Interface
 *
 * Defines the contract for all mail provider implementations.
 * Each provider handles its own validation, configuration collection, and DSN building.
 */
interface MailProvider
{
    /**
     * Get the unique identifier for this provider
     *
     * @return string Provider name (e.g., 'smtp', 'sendgrid', 'mailhog')
     */
    public function getName(): string;

    /**
     * Get the display name for this provider
     *
     * @return string Human-readable provider name
     */
    public function getDisplayName(): string;

    /**
     * Get the description for this provider
     *
     * @return string Short description of the provider
     */
    public function getDescription(): string;

    /**
     * Get optional tags for this provider (e.g., 'Docker', 'Testing')
     *
     * @return array<string> List of tags
     */
    public function getTags(): array;

    /**
     * Validate provider-specific configuration from POST data
     *
     * @param array<string, mixed> $postData POST data from the form
     * @param Installer $installer Installer instance for adding errors
     * @return bool True if validation passes, false otherwise
     */
    public function validate(array $postData, Installer $installer): bool;

    /**
     * Collect provider-specific configuration from POST data
     *
     * @param array<string, mixed> $postData POST data from the form
     * @param Installer $installer Installer instance for sanitization
     * @return array<string, mixed> Provider configuration
     */
    public function collectConfig(array $postData, Installer $installer): array;

    /**
     * Build the Symfony Mailer DSN from provider configuration
     *
     * @param array<string, mixed> $config Provider configuration
     * @return string Mailer DSN string
     */
    public function buildDsn(array $config): string;

    /**
     * Check if this provider requires configuration
     *
     * @return bool True if configuration needed, false otherwise
     */
    public function requiresConfiguration(): bool;
}
