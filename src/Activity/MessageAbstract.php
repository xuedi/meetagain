<?php declare(strict_types=1);

namespace App\Activity;

use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class MessageAbstract implements MessageInterface
{
    protected RouterInterface $router;
    protected ImageHtmlRenderer $imageRenderer;
    protected TranslatorInterface $translator;
    protected ?array $meta = [];
    protected array $userNames = [];
    protected array $eventNames = [];

    public function injectServices(
        RouterInterface $router,
        ImageHtmlRenderer $imageRenderer,
        TranslatorInterface $translator,
        ?array $meta = [],
        array $userNames = [],
        array $eventNames = [],
    ): self {
        $this->router = $router;
        $this->imageRenderer = $imageRenderer;
        $this->translator = $translator;
        $this->meta = $meta;
        $this->userNames = $userNames;
        $this->eventNames = $eventNames;

        return $this;
    }

    abstract protected function renderText(): string;

    abstract protected function renderHtml(): string;

    public function render(bool $asHtml = false): string
    {
        return $asHtml ? $this->renderHtml() : $this->renderText();
    }

    protected function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function ensureHasKey(string $key): void
    {
        if (!isset($this->meta[$key])) {
            throw new InvalidArgumentException(sprintf("Missing '%s' in meta in %s", $key, $this->getType()));
        }
    }

    protected function ensureIsNumeric(string $key): void
    {
        if (!is_numeric($this->meta[$key])) {
            throw new InvalidArgumentException(sprintf(
                "Value '%s' has to be numeric in '%s'",
                $key,
                $this->getType(),
            ));
        }
    }
}
