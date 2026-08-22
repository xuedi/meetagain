<?php declare(strict_types=1);

class MailProviderRegistry
{
    /** @var array<string, MailProvider> */
    private array $providers = [];

    public function register(MailProvider $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /** @throws InvalidArgumentException */
    public function getProvider(string $name): MailProvider
    {
        if (!isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown mail provider: {$name}");
        }

        return $this->providers[$name];
    }

    /** @return array<string, MailProvider> */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public static function createDefault(): self
    {
        $registry = new self();

        $registry->register(new MailpitMailProvider());
        $registry->register(new SmtpMailProvider());
        $registry->register(new SendgridMailProvider());
        $registry->register(new MailgunMailProvider());
        $registry->register(new SesMailProvider());
        $registry->register(new NullMailProvider());

        return $registry;
    }
}
