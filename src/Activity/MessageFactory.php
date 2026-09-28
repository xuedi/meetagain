<?php declare(strict_types=1);

namespace App\Activity;

use App\Entity\Activity;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Media\ImageHtmlRenderer;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageFactory
{
    /** @var array<string, MessageInterface>|null */
    private ?array $messagesByType = null;

    public function __construct(
        #[AutowireIterator(MessageInterface::class)]
        private readonly iterable $messages,
        private readonly RouterInterface $router,
        private readonly UserRepository $userRepository,
        private readonly EventRepository $eventRepository,
        private readonly RequestStack $requestStack,
        private readonly ImageHtmlRenderer $imageRenderer,
        private readonly TranslatorInterface $translator,
    ) {}

    public function build(Activity $activity): MessageInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request instanceof Request ? $request->getLocale() : 'en';

        $message = $this->messagesByType()[$activity->getType() ?? ''] ?? null;
        if ($message === null) {
            return new UnknownMessage($activity->getType() ?? 'unknown');
        }

        return $message->injectServices(
            $this->router,
            $this->imageRenderer,
            $this->translator,
            $activity->getMeta(),
            $this->userRepository->getUserNameList(),
            $this->eventRepository->getEventNameList($locale),
        );
    }

    /**
     * @return array<string, MessageInterface>
     */
    private function messagesByType(): array
    {
        if ($this->messagesByType !== null) {
            return $this->messagesByType;
        }

        $this->messagesByType = [];
        foreach ($this->messages as $message) {
            if ($message instanceof MessageInterface) {
                $this->messagesByType[$message->getType()] ??= $message;
            }
        }

        return $this->messagesByType;
    }
}
