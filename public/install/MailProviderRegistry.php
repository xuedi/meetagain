<?php

declare(strict_types=1);

/**
 * Mail Provider Registry
 *
 * Central registry for all mail provider implementations.
 * Manages provider registration and retrieval.
 */
class MailProviderRegistry
{
    /** @var array<string, MailProvider> */
    private array $providers = [];

    /**
     * Register a mail provider
     */
    public function register(MailProvider $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Get a mail provider by name
     *
     * @throws InvalidArgumentException if provider not found
     */
    public function getProvider(string $name): MailProvider
    {
        if (!isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown mail provider: {$name}");
        }

        return $this->providers[$name];
    }

    /**
     * Get all registered providers
     *
     * @return array<string, MailProvider>
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /**
     * Check if a provider exists
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * Create and configure the default registry with all available providers
     */
    public static function createDefault(): self
    {
        $registry = new self();

        // Register all available providers
        $registry->register(new MailhogMailProvider());
        $registry->register(new SmtpMailProvider());
        $registry->register(new SendgridMailProvider());
        $registry->register(new MailgunMailProvider());
        $registry->register(new SesMailProvider());
        $registry->register(new NullMailProvider());

        return $registry;
    }
}
