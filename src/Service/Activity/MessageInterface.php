<?php declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\ActivityType;
use App\Service\ImageHtmlRenderer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouterInterface;

#[AutoconfigureTag]
interface MessageInterface
{
    public function injectServices(
        RouterInterface $router,
        ImageHtmlRenderer $imageRenderer,
        null|array $meta = [],
        array $userNames = [],
        array $eventNames = [],
    ): self;

    public function getType(): ActivityType;

    public function render(bool $asHtml = false): string;

    public function validate(): self;
}
