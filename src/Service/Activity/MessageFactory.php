<?php declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\Activity;
use App\Repository\EventRepository;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use App\Service\ImageService;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

readonly class MessageFactory
{
    public function __construct(
        #[AutowireIterator(MessageInterface::class)]
        private iterable $messages,
        private RouterInterface $router,
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private RequestStack $requestStack,
        private ImageService $imageService,
    ) {
    }

    public function build(Activity $activity): MessageInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request instanceof Request ? $request->getLocale() : 'en';

        foreach ($this->messages as $message) {
            if ($message instanceof MessageInterface && $message->getType() === $activity->getType()) {
                return $message->injectServices(
                    $this->router,
                    $this->imageService,
                    $activity->getMeta(),
                    $this->userRepository->getUserNameList(),
                    $this->eventRepository->getEventNameList($locale),
                );
            }
        }
        throw new Exception('Cound not find message for activity type: ' . $activity->getType()->name);
    }
}
