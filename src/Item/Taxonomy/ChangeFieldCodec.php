<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final readonly class ChangeFieldCodec
{
    public function parse(string $field): ?ChangeField
    {
        $parts = [];
        if (preg_match('/^(tag)_add_(\d+)_([a-z]{2,8})_(\d+)$/', $field, $parts) === 1) {
            return new ChangeField(
                Axis::from($parts[1]),
                ChangeOperation::Add,
                locale: $parts[3],
                index: (int) $parts[4],
                parent: (int) $parts[2],
            );
        }

        if (preg_match('/^(category|tag)_add_([a-z]{2,8})_(\d+)$/', $field, $parts) === 1) {
            return new ChangeField(Axis::from($parts[1]), ChangeOperation::Add, locale: $parts[2], index: (int) $parts[3]);
        }

        if (preg_match('/^(category|tag)_rename_(\d+)_([a-z]{2,8})$/', $field, $parts) === 1) {
            return new ChangeField(Axis::from($parts[1]), ChangeOperation::Rename, id: (int) $parts[2], locale: $parts[3]);
        }

        if (preg_match('/^(category|tag)_remove_(\d+)$/', $field, $parts) === 1) {
            return new ChangeField(Axis::from($parts[1]), ChangeOperation::Remove, id: (int) $parts[2]);
        }

        return null;
    }
}
