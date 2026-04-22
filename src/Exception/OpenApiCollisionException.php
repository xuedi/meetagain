<?php declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Thrown when two plugins (or core + a plugin) declare the same OpenAPI path,
 * schema, or tag. Surfaces a configuration bug loudly instead of letting one
 * silently overwrite the other.
 */
final class OpenApiCollisionException extends RuntimeException
{
}
