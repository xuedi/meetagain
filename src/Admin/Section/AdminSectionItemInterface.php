<?php declare(strict_types=1);

namespace App\Admin\Section;

interface AdminSectionItemInterface
{
    public function getTemplate(): string;
}
