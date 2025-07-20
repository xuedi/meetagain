<?php declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\ActivityType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouterInterface;

#[AutoconfigureTag]
interface MessageInterface
{
    public function injectServices(
        RouterInterface $router,
        ?array $meta = [],
        array $userNames = [],
        array $eventNames = [],
    ): self;

    public function getType(): ActivityType;

    public function render(bool $asHtml = false): string;

    public function validate(): bool;
}
