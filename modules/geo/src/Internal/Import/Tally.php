<?php declare(strict_types=1);

namespace Module\Geo\Internal\Import;

final class Tally
{
    private int $created = 0;

    private int $updated = 0;

    private int $unchanged = 0;

    private int $skipped = 0;

    private int $rows = 0;

    public function created(): void
    {
        ++$this->created;
    }

    public function updated(): void
    {
        ++$this->updated;
    }

    public function unchanged(): void
    {
        ++$this->unchanged;
    }

    public function skipped(): void
    {
        ++$this->skipped;
    }

    public function row(): int
    {
        return $this->rows++;
    }

    public function written(): int
    {
        return $this->created + $this->updated;
    }

    public function report(Dataset $dataset): Report
    {
        return new Report($dataset, $this->created, $this->updated, $this->unchanged, $this->skipped, $this->rows);
    }
}
