<?php declare(strict_types=1);

namespace Module\Email\Contract;

interface PreviewSweepInterface
{
    public const string DEFAULT_RECIPIENT_DOMAIN = 'preview.invalid';

    /** @return list<string> */
    public function availableIdentifiers(): array;

    /** @return array<string, EmailInterface> */
    public function typesByIdentifier(): array;

    /**
     * Queues one mock message per email type and language, each to `<identifier>+<locale>[+<tag>...]@<recipientDomain>`,
     * without a push. Refuses to run outside the dev and test environments. An empty list of identifiers or locales
     * means all of them; a non-null origin replaces the sending identity each type reports.
     *
     * @param list<string> $requestedIdentifiers
     * @param list<string> $requestedLocales
     * @param list<string> $recipientTags
     */
    public function sweep(
        array $requestedIdentifiers = [],
        array $requestedLocales = [],
        string $recipientDomain = self::DEFAULT_RECIPIENT_DOMAIN,
        bool $tagSubjects = true,
        ?object $origin = null,
        array $recipientTags = [],
    ): PreviewSweepResult;
}
