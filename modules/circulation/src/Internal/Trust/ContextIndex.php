<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Trust;

use App\Item\TypeRegistry;
use Module\Circulation\Internal\ContextResolver;
use Module\Circulation\Internal\Repository\CopyRepository;

class ContextIndex
{
    /** @var array<string, string>|null context => item type */
    private ?array $memo = null;

    public function __construct(
        private readonly TypeRegistry $itemTypes,
        private readonly ContextResolver $contextResolver,
        private readonly EnabledResolver $enabled,
        private readonly CopyRepository $copies,
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
