<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use Plugin\Filmclub\Enum\ViewType;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ViewTypeResolver
{
    private const SESSION_PREFIX = 'filmclub.viewType.';

    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function get(string $context, ViewType $default = ViewType::Tiles): ViewType
    {
        $session = $this->requestStack->getSession();
        $stored = $session->get(self::SESSION_PREFIX . $context);

        return $stored !== null ? (ViewType::tryFrom($stored) ?? $default) : $default;
    }

    public function set(string $context, ViewType $type): void
    {
        $this->requestStack->getSession()->set(self::SESSION_PREFIX . $context, $type->value);
    }
}
