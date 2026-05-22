<?php declare(strict_types=1);

namespace App\ValueObject;

use DateTimeImmutable;
use Throwable;

class LogEntry
{
    private readonly DateTimeImmutable $date;
    private readonly string $type;
    private readonly string $level;
    private string $message;
    private ?string $json = null;

    public function __construct(string $line)
    {
        $this->date = new DateTimeImmutable(substr($line, 1, strpos($line, ']') - 1));
        $line = substr($line, strpos($line, ']') + 2);

        $chunk = substr($line, 0, strpos($line, ':'));
        [$type, $level] = explode('.', $chunk);
        $this->type = $type;
        $this->level = $level;
        $line = substr($line, strpos($line, ':') + 2);

        if (!str_contains($line, '{')) {
            $this->message = $line;
            return;
        }
        $this->message = trim(substr($line, 0, strpos($line, '{')));
        $this->json = trim(substr($line, strpos($line, '{') - 1));
    }

    public static function fromString(string $line): self
    {
        return new self($line);
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getJson(): ?string
    {
        return $this->json;
    }

    public function getHash(): string
    {
        return substr(
            hash(
                'sha256',
                $this->date->format('c')
                . '|'
                . $this->type
                . '|'
                . $this->level
                . '|'
                . $this->message
                . '|'
                . ($this->json ?? ''),
            ),
            0,
            16,
        );
    }

    /**
     * Splits the raw json tail (Monolog's `{context} {extra}` shape) into individual
     * top-level JSON blocks and decodes each. Skips empty `[]` / `{}` extras and
     * undecodable fragments. On any unexpected error, falls back to returning the
     * raw tail as a single string element so the caller can still display it.
     *
     * @return list<mixed>
     */
    public function getContextChunks(): array
    {
        if ($this->json === null) {
            return [];
        }

        try {
            $chunks = [];
            $depth = 0;
            $inString = false;
            $escape = false;
            $start = null;
            $length = strlen($this->json);

            for ($i = 0; $i < $length; $i++) {
                $char = $this->json[$i];

                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = !$inString;
                    continue;
                }
                if ($inString) {
                    continue;
                }
                if ($char === '{' || $char === '[') {
                    if ($depth === 0) {
                        $start = $i;
                    }
                    $depth++;
                    continue;
                }
                if (($char === '}' || $char === ']') && $depth > 0) {
                    $depth--;
                    if ($depth === 0 && $start !== null) {
                        $chunks[] = substr($this->json, $start, $i - $start + 1);
                        $start = null;
                    }
                }
            }

            $decoded = [];
            foreach ($chunks as $chunk) {
                $value = json_decode($chunk, true);
                if ($value === null && $chunk !== 'null') {
                    continue;
                }
                if (is_array($value) && $value === []) {
                    continue;
                }
                $decoded[] = $value;
            }

            return $decoded;
        } catch (Throwable) {
            return [$this->json];
        }
    }

    public function toArray(): array
    {
        $context = null;
        if ($this->json !== null) {
            $decoded = json_decode($this->json, true);
            $context = $decoded !== null ? $decoded : $this->json;
        }

        return [
            'date' => $this->date->format('c'),
            'channel' => $this->type,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $context,
        ];
    }
}
