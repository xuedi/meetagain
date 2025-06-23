<?php declare(strict_types=1);

namespace App\Factory;

use App\Entity\Activity;
use App\Entity\Activity\MessageInterface;
use App\Entity\Activity\Messages\ChangedUsername;
use App\Entity\Activity\Messages\EventImageUploaded;
use App\Entity\Activity\Messages\FollowedUser;
use App\Entity\Activity\Messages\Login;
use App\Entity\Activity\Messages\PasswordReset;
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
use Symfony\Component\Routing\RouterInterface;

readonly class ActivityMessageFactory
{
    public function __construct(
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

        return match ($activity->getType()->value) {
            ActivityType::EventImageUploaded->value => new EventImageUploaded(...$params),
            ActivityType::ReportedImage->value => new ReportedImage(...$params),
            ActivityType::ChangedUsername->value => new ChangedUsername(...$params),
            ActivityType::RsvpYes->value => new RsvpYes(...$params),
            ActivityType::RsvpNo->value => new RsvpNo(...$params),
            ActivityType::SendMessage->value => new SendMessage(...$params),
            ActivityType::FollowedUser->value => new FollowedUser(...$params),
            ActivityType::Login->value => new Login(...$params),
            ActivityType::Registered->value => new Registered(...$params),
            ActivityType::RegistrationEmailConfirmed->value => new RegistrationEmailConfirmed(...$params),
            ActivityType::PasswordReset->value => new PasswordReset(...$params),
            default => throw new Exception('To be implemented: ' . $activity->getType()->name),
        };
    }
}
