<?php declare(strict_types=1);

namespace App\Admin\Top;

final readonly class AdminTop
{
    /**
     * @param list<AdminTopInfoInterface>   $info
     * @param list<AdminTopActionInterface> $actions
     */
    public function __construct(
        public array $info = [],
        public array $actions = [],
    ) {}
}
