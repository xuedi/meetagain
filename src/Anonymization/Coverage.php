<?php declare(strict_types=1);

namespace App\Anonymization;

use App\DependencyInjection\SeesInactivePlugins;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[SeesInactivePlugins]
readonly class Coverage
{
    public const array TEXT_TYPES = [
        Types::STRING,
        Types::ASCII_STRING,
        Types::TEXT,
        Types::JSON,
        Types::BLOB,
        Types::BINARY,
        Types::GUID,
    ];

    /**
     * @param iterable<AnonymizerInterface> $anonymizers
     */
    public function __construct(
        private EntityManagerInterface $em,
        #[AutowireIterator(AnonymizerInterface::class)]
        private iterable $anonymizers,
    ) {}

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        $claims = [];
        foreach ($this->anonymizers as $anonymizer) {
            foreach ($anonymizer->getClaims() as $claim) {
                if (isset($claims[$claim->table])) {
                    $problems[] = sprintf('Table "%s" is claimed twice.', $claim->table);
                    continue;
                }
                $claims[$claim->table] = $claim;
            }
        }

        [$mapped, $joinTables] = $this->mappedTables();
        foreach ($mapped as $table => $shape) {
            $claim = $claims[$table] ?? null;
            unset($claims[$table]);
            if (!$claim instanceof Claim) {
                $problems[] = sprintf('Table "%s" (%s) is not claimed by any anonymizer.', $table, $shape['entity']);
                continue;
            }

            array_push($problems, ...$this->columnProblems($claim, $shape['text'], $shape['columns']));
        }

        foreach ($this->unmappedTables($mapped, $joinTables) as $table) {
            $claim = $claims[$table] ?? null;
            unset($claims[$table]);
            if (!$claim instanceof Claim) {
                $problems[] = sprintf('Table "%s" (no entity) is not claimed by any anonymizer.', $table);
            } elseif (!$claim->decidesEveryColumn()) {
                $problems[] = sprintf('Table "%s" has no entity, so it can only be claimed as emptied or as reference data.', $table);
            }
        }

        foreach ($claims as $table => $claim) {
            if (!$claim->wholeTableKept) {
                $problems[] = sprintf('Table "%s" is claimed but does not exist.', $table);
            }
        }

        return $problems;
    }

    /**
     * @param list<string> $textColumns
     * @param list<string> $columns
     * @return list<string>
     */
    private function columnProblems(Claim $claim, array $textColumns, array $columns): array
    {
        if ($claim->decidesEveryColumn()) {
            return [];
        }

        $problems = [];
        $decided = $claim->decidedColumns();
        foreach (array_unique(array_diff_assoc($decided, array_unique($decided))) as $column) {
            $problems[] = sprintf('Column "%s.%s" is decided twice.', $claim->table, $column);
        }
        foreach (array_diff($textColumns, $decided) as $column) {
            $problems[] = sprintf('Column "%s.%s" is not decided: rewrite, clear or keep it.', $claim->table, $column);
        }
        foreach (array_diff($decided, $columns) as $column) {
            $problems[] = sprintf('Column "%s.%s" is claimed but does not exist.', $claim->table, $column);
        }

        return $problems;
    }

    /**
     * @return array{array<string, array{entity: string, text: list<string>, columns: list<string>}>, array<string, true>}
     */
    private function mappedTables(): array
    {
        $tables = [];
        $joinTables = [];
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                continue;
            }

            $table = $metadata->getTableName();
            $shape = $tables[$table] ?? ['entity' => $metadata->getName(), 'text' => [], 'columns' => []];
            foreach ($metadata->fieldMappings as $field) {
                $shape['columns'][] = $field->columnName;
                if ($field->enumType === null && in_array($field->type, self::TEXT_TYPES, true)) {
                    $shape['text'][] = $field->columnName;
                }
            }
            foreach ($metadata->associationMappings as $association) {
                if ($association instanceof ToOneOwningSideMapping) {
                    foreach ($association->joinColumns as $joinColumn) {
                        $shape['columns'][] = $joinColumn->name;
                    }
                }
                if ($association instanceof ManyToManyOwningSideMapping) {
                    $joinTables[$association->joinTable->name] = true;
                }
            }
            $shape['text'] = array_values(array_unique($shape['text']));
            $shape['columns'] = array_values(array_unique($shape['columns']));
            $tables[$table] = $shape;
        }

        return [$tables, $joinTables];
    }

    /**
     * @param array<string, mixed> $mapped
     * @param array<string, true> $joinTables
     * @return list<string>
     */
    private function unmappedTables(array $mapped, array $joinTables): array
    {
        $tables = [];
        foreach ($this->em->getConnection()->createSchemaManager()->introspectTableNames() as $name) {
            $table = $name->getUnqualifiedName()->getValue();
            if (!isset($mapped[$table]) && !isset($joinTables[$table])) {
                $tables[] = $table;
            }
        }

        return $tables;
    }
}
