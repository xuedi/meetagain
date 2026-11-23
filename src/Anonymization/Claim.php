<?php declare(strict_types=1);

namespace App\Anonymization;

final readonly class Claim
{
    /**
     * @param list<string> $rewritten
     * @param list<string> $cleared
     * @param list<string> $kept
     */
    private function __construct(
        public string $table,
        public bool $emptied,
        public bool $wholeTableKept,
        public array $rewritten,
        public array $cleared,
        public array $kept,
    ) {}

    public static function emptied(string $table): self
    {
        return new self($table, true, false, [], [], []);
    }

    public static function reference(string $table): self
    {
        return new self($table, false, true, [], [], []);
    }

    /**
     * @param list<string> $rewritten
     * @param list<string> $cleared
     * @param list<string> $kept
     */
    public static function columns(string $table, array $rewritten = [], array $cleared = [], array $kept = []): self
    {
        return new self($table, false, false, $rewritten, $cleared, $kept);
    }

    public function decidesEveryColumn(): bool
    {
        return $this->emptied || $this->wholeTableKept;
    }

    /**
     * @return list<string>
     */
    public function decidedColumns(): array
    {
        return [...$this->rewritten, ...$this->cleared, ...$this->kept];
    }
}
