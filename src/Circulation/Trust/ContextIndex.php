<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use App\Circulation\ContextResolver;
use App\Item\TypeRegistry;
use App\Repository\CirculationCopyRepository;

class ContextIndex
{
    /** @var array<string, string>|null context => item type */
    private ?array $memo = null;

    public function __construct(
        private readonly TypeRegistry $itemTypes,
        private readonly ContextResolver $contextResolver,
        private readonly EnabledResolver $enabled,
        private readonly CirculationCopyRepository $copies,
    ) {}

    /**
     * @return array<string, string> every context circulation scores, mapped to its item type
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $map = [];
        foreach ($this->itemTypes->all() as $provider) {
            $itemType = $provider->getKey();
            if (!$this->enabled->isEnabled($itemType)) {
                continue;
            }

            $map[$this->contextResolver->resolve($itemType)] = $itemType;
            foreach ($this->copies->findDistinctContexts($itemType) as $context) {
                $map[$context] = $itemType;
            }
        }

        return $this->memo = $map;
    }

    public function itemTypeFor(string $context): ?string
    {
        return $this->all()[$context] ?? null;
    }
}
