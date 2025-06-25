<?php declare(strict_types=1);

namespace App\Factory;

use App\Entity\Activity;
use App\Entity\Activity\MessageInterface;
use App\Entity\Activity\Messages\ChangedUsername;
use App\Entity\Activity\Messages\EventImageUploaded;
use App\Entity\Activity\Messages\FollowedUser;
use App\Entity\Activity\Messages\Login;
use App\Entity\Activity\Messages\PasswordReset;
use App\Entity\Activity\Messages\PasswordResetRequest;
use App\Entity\Activity\Messages\Registered;
use App\Entity\Activity\Messages\RegistrationEmailConfirmed;
use App\Entity\Activity\Messages\ReportedImage;
use App\Entity\Activity\Messages\RsvpNo;
use App\Entity\Activity\Messages\RsvpYes;
use App\Entity\Activity\Messages\SendMessage;
use App\Entity\ActivityType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\GlobalService;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\RouterInterface;

readonly class ActivityMessageFactory
{
    public function __construct(
        #[AutowireIterator(MessageInterface::class)]
        private iterable $messages,
        private RouterInterface $router,
        private UserRepository $userRepository,
        private EventRepository $eventRepository,
        private GlobalService $globalService,
    )
    {
    }

    // TODO: foreach element autowired into here via tag
    public function build(Activity $activity): MessageInterface
    {
        $params = [
            $this->router,
            $activity->getMeta(),
            $this->userRepository->getUserNameList(),
            $this->eventRepository->getEventNameList($this->globalService->getCurrentLocale()),
        ];

        foreach ($this->messages as $message) {
            if ($message instanceof MessageInterface && $message->getType() === $activity->getType()) {
                return $message->injectServices(...$params);
            }
        }
        throw new Exception('Cound not find message for activity type: ' . $activity->getType()->name);
    }
}
