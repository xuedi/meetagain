<?php declare(strict_types=1);

namespace Module\Task\Contract;

final readonly class ActionParameter
{
    public const string CHOICE = 'choice';
    public const string TEXT = 'text';
    public const string NUMBER = 'number';
    public const string FLAG = 'flag';

    /**
     * @param array<string, string> $choices value keyed by its label
     */
    private function __construct(
        public string $key,
        public string $label,
        public string $kind,
        public string|int|bool|null $default,
        public bool $remembered,
        public array $choices = [],
    ) {}

    /**
     * @param array<string, string> $choices value keyed by its label
     */
    public static function choice(string $key, string $label, array $choices, ?string $default = null, bool $remembered = false): self
    {
        return new self($key, $label, self::CHOICE, $default, $remembered, $choices);
    }

    public static function text(string $key, string $label, ?string $default = null, bool $remembered = false): self
    {
        return new self($key, $label, self::TEXT, $default, $remembered);
    }

    public static function number(string $key, string $label, ?int $default = null, bool $remembered = false): self
    {
        return new self($key, $label, self::NUMBER, $default, $remembered);
    }

    public static function flag(string $key, string $label, bool $default = false, bool $remembered = false): self
    {
        return new self($key, $label, self::FLAG, $default, $remembered);
    }
}
