<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\Entity\BlockType\BlockType;
use App\Entity\Image as ImageEntity;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\CmsBlock\FieldType;
use App\Exception\BlockValidationException;

readonly class BlockHydrator
{
    /**
     * Validates $data against the block type's field definitions, applies defaults
     * for optional missing fields, then calls fromJson() to build the block object.
     *
     * @throws BlockValidationException when required fields are missing
     */
    public function hydrate(CmsBlockType $type, array $data, ?ImageEntity $image = null): BlockType
    {
        $blockClass  = $type->getBlockClass();
        $definitions = $blockClass::getFieldDefinitions();
        $errors      = [];
        $resolved    = [];

        foreach ($definitions as $field) {
            if (!array_key_exists($field->name, $data)) {
                if ($field->required && $field->default === null) {
                    $errors[] = sprintf('Missing required field "%s"', $field->name);
                    continue;
                }
                $resolved[$field->name] = $field->default;
                continue;
            }

            $resolved[$field->name] = match ($field->type) {
                FieldType::Boolean   => (bool) $data[$field->name],
                FieldType::ImageList => is_array($data[$field->name]) ? $data[$field->name] : [],
                default              => (string) $data[$field->name],
            };
        }

        if ($errors !== []) {
            throw new BlockValidationException($errors);
        }

        return $blockClass::fromJson(array_merge($data, $resolved), $image);
    }
}
