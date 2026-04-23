<?php declare(strict_types=1);

namespace App\Activity;

use App\Service\Media\ImageHtmlRenderer;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Autoconfigure(autowire: false)]
class UnknownActivityMessage implements MessageInterface
{
    public function __construct(private readonly string $type) {}

    public function injectServices(
        RouterInterface $router,
        ImageHtmlRenderer $imageRenderer,
        TranslatorInterface $translator,
        ?array $meta = [],
        array $userNames = [],
        array $eventNames = [],
    ): self {
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function render(bool $asHtml = false): string
    {
        $parts = explode('.', $this->type, 2);
        $namespace = $parts[0] ;
        $action = $parts[1] ?? '';

        return $action !== ''
            ? sprintf('[%s] %s (plugin inactive)', $namespace, $action)
            : sprintf('[unknown] %s (plugin inactive)', $this->type);
    }

    public function validate(): self
    {
        return $this;
    }
}
