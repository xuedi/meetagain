<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\NotificationSettings;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\ImageType;
use App\Repository\ActivityRepository;
use App\Repository\ImageRepository;
use App\Repository\ItemReportRepository;
use App\Repository\RateLimitLogRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\SendlogInterface;
use Module\Task\Contract\ActionQueueInterface;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class Erasure implements TaskInterface
{
    public const int ERASURE_WINDOW_DAYS = 30;

    public function __construct(
        private UserRepository $userRepo,
        private ActivityRepository $activityRepo,
        private ImageRepository $imageRepo,
        private ImageService $imageService,
        private ImageLocationService $imageLocationService,
        private SupportRequestRepository $supportRequestRepo,
        private ItemReportRepository $itemReportRepo,
        private RateLimitLogRepository $rateLimitLogRepo,
        private SendlogInterface $sendlog,
        private ActionQueueInterface $actionQueue,
        private EntityActionDispatcher $dispatcher,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return 'member-erasure';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('35 4 * * *', jitterSeconds: 1800, offloaded: true);
    }

    public function getDescription(): string
    {
        return sprintf(
            'Erases the members who deleted their account, or were deleted by an admin, more than %d days ago. It deletes their RSVPs, follows, blocks, own activity, profile pictures, push subscriptions, tokens and the other rows that only concern them, scrubs their address from support requests, reports, the send log and the security logs, and turns their account into an anonymous placeholder. What they wrote stays, shown as written by a deleted member. Until then an admin can restore the account.',
            self::ERASURE_WINDOW_DAYS,
        );
    }

    public function run(): Result
    {
        $cutoff = $this->clock->now()->modify(sprintf('-%d days', self::ERASURE_WINDOW_DAYS));

        $erased = 0;
        $failed = 0;
        foreach ($this->userRepo->findErasable($cutoff) as $user) {
            try {
                $this->erase($user);
                $erased++;
            } catch (Throwable $e) {
                $failed++;
                $this->logger->error('Member erasure failed', ['user_id' => $user->getId(), 'exception' => $e]);
            }
        }

        return new Result($failed === 0 ? Status::ok : Status::error, sprintf('erased: %d, failed: %d', $erased, $failed));
    }

    public function erase(User $user): void
    {
        $userId = (int) $user->getId();
        $email = (string) $user->getEmail();
        $placeholder = $this->placeholderEmail($userId);

        $this->userRepo->deleteRelations($user);
        $this->activityRepo->deleteForUser($userId);
        $this->removeProfileImages($user);
        $this->supportRequestRepo->anonymiseRequester($userId, $email);
        $this->itemReportRepo->anonymiseNotifier($userId, $email);
        $this->rateLimitLogRepo->forgetIdentifier($email);
        $this->sendlog->forgetRecipient($email, $placeholder);
        $this->actionQueue->forgetRequester($email, $placeholder);

        $this->dispatcher->dispatch(EntityAction::EraseUser, $userId);

        $user->setName(sprintf('deleted-%d', $userId));
        $user->setEmail($placeholder);
        $user->setPassword('!');
        $user->setBio(null);
        $user->setCityId(null);
        $user->setRegcode(null);
        $user->setRegcodeExpiresAt(null);
        $user->setNotificationSettings(new NotificationSettings([]));
        $user->setNotification(false);
        $user->setPublic(false);
        $user->setTagging(false);
        $user->setOsmConsent(false);
        $user->setDeletionRequestedAt(null);
        $this->em->flush();
    }

    private function removeProfileImages(User $user): void
    {
        $userId = (int) $user->getId();
        $user->setImage(null);
        $this->em->flush();

        $images = $this->imageRepo->findBy(['uploader' => $user, 'type' => ImageType::ProfilePicture]);
        foreach ($images as $image) {
            $this->imageLocationService->removeLocation((int) $image->getId(), ImageType::ProfilePicture, $userId);
            $this->imageService->removeIfOrphaned($image);
        }
    }

    private function placeholderEmail(int $userId): string
    {
        return sprintf('deleted-%d@%s', $userId, User::ERASED_EMAIL_DOMAIN);
    }
}
