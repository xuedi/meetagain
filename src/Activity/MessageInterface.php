<?php declare(strict_types=1);

namespace App\Activity;

use App\Service\Media\ImageHtmlRenderer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag]
interface MessageInterface
{
    public function injectServices(
        RouterInterface $router,
        ImageHtmlRenderer $imageRenderer,
        TranslatorInterface $translator,
        ?array $meta = [],
        array $userNames = [],
        array $eventNames = [],
    ): self;

    public function getType(): string;

    public function render(bool $asHtml = false): string;

    public function validate(): self;
}
