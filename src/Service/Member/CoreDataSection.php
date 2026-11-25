<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\Activity;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\ImageReport;
use App\Entity\ItemReport;
use App\Entity\Location;
use App\Entity\Message;
use App\Entity\ModerationReport;
use App\Entity\RsvpGuest;
use App\Entity\SupportRequest;
use App\Entity\Topic;
use App\Entity\User;
use App\Entity\UserBlock;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SortDirection;

readonly class CoreDataSection implements DataSectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserService $userService,
    ) {}

    #[Override]
    public function export(User $user): array
    {
        $locale = $user->getLocale();

        return [
            'profile' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'locale' => $locale,
                'bio' => $user->getBio(),
                'city_id' => $user->getCityId(),
                'status' => $user->getStatus()->name,
                'role' => $user->getRole()->value,
                'verified' => $user->isVerified(),
                'created_at' => $this->date($user->getCreatedAt()),
                'last_login' => $this->date($user->getLastLogin()),
                'avatar' => $user->getImage() instanceof Image ? $this->imageFileName($user->getImage()) : null,
            ],
            'settings' => [
                'public_profile' => $user->isPublic(),
                'email_notification' => (bool) $user->isNotification(),
                'notification_settings' => $user->getNotificationSettings(),
                'consent_tagging' => (bool) $user->isTagging(),
                'consent_openstreetmap' => (bool) $user->isOsmConsent(),
            ],
            'rsvps' => array_values(array_map(fn(Event $event): array => [
                'event_id' => $event->getId(),
                'title' => $event->getTitle($locale),
                'start' => $this->date($event->getStart()),
            ], $user->getRsvpEvents()->toArray())),
            'rsvp_guests' => array_map(
                static fn(RsvpGuest $guest): array => ['event_id' => $guest->getEvent()->getId(), 'guests' => $guest->getGuests()],
                $this->em->getRepository(RsvpGuest::class)->findBy(['user' => $user]),
            ),
            'comments' => array_map(
                fn(Comment $comment): array => [
                    'target_type' => $comment->getTargetType(),
                    'target_id' => $comment->getTargetId(),
                    'created_at' => $this->date($comment->getCreatedAt()),
                    'content' => $comment->getContent(),
                ],
                $this->em->getRepository(Comment::class)->findBy(['user' => $user], ['id' => 'ASC']),
            ),
            'topics' => array_map(
                fn(Topic $topic): array => ['id' => $topic->getId(), 'title' => $topic->getTitle(), 'created_at' => $this->date($topic->getCreatedAt())],
                $this->em->getRepository(Topic::class)->findBy(['author' => $user], ['id' => 'ASC']),
            ),
            'messages' => $this->messages($user),
            'following' => array_values(array_map($this->member(...), $user->getFollowing()->toArray())),
            'followers' => array_values(array_map($this->member(...), $user->getFollowers()->toArray())),
            'blocks' => array_map(
                fn(UserBlock $block): array => $this->member($block->getBlocked()) + ['created_at' => $this->date($block->getCreatedAt())],
                $this->em->getRepository(UserBlock::class)->findBy(['blocker' => $user]),
            ),
            'activity' => array_map(
                fn(Activity $activity): array => [
                    'type' => $activity->getType(),
                    'created_at' => $this->date($activity->getCreatedAt()),
                    'meta' => $activity->getMeta(),
                ],
                $this->em->getRepository(Activity::class)->findBy(['user' => $user], ['id' => 'ASC']),
            ),
            'support_requests' => array_map(
                fn(SupportRequest $request): array => [
                    'id' => $request->getId(),
                    'created_at' => $this->date($request->getCreatedAt()),
                    'status' => $request->getStatus()->value,
                    'message' => $request->getMessage(),
                ],
                $this->em->getRepository(SupportRequest::class)->findBy(['requester' => $user], ['id' => 'ASC']),
            ),
            'reports_filed' => [
                'members_and_content' => array_map(
                    fn(ModerationReport $report): array => [
                        'subject_type' => $report->getSubjectType(),
                        'subject_id' => $report->getSubjectId(),
                        'reason' => $report->getReason()->value,
                        'remarks' => $report->getRemarks(),
                        'status' => $report->getStatus()->value,
                        'created_at' => $this->date($report->getCreatedAt()),
                    ],
                    $this->em->getRepository(ModerationReport::class)->findBy(['reporter' => $user], ['id' => 'ASC']),
                ),
                'items' => array_map(
                    fn(ItemReport $report): array => [
                        'item_type' => $report->getItemType(),
                        'item_id' => $report->getItemId(),
                        'reason' => $report->getReason()->value,
                        'explanation' => $report->getExplanation(),
                        'status' => $report->getStatus()->value,
                        'created_at' => $this->date($report->getCreatedAt()),
                    ],
                    $this->em->getRepository(ItemReport::class)->findBy(['reporter' => $user], ['id' => 'ASC']),
                ),
                'images' => array_map(
                    fn(ImageReport $report): array => [
                        'image_id' => $report->getImage()?->getId(),
                        'reason' => $report->getReason()->value,
                        'remarks' => $report->getRemarks(),
                        'status' => $report->getStatus()->value,
                        'created_at' => $this->date($report->getCreatedAt()),
                    ],
                    $this->em->getRepository(ImageReport::class)->findBy(['reporter' => $user], ['id' => 'ASC']),
                ),
            ],
            'events_created' => array_map(
                fn(Event $event): array => ['id' => $event->getId(), 'title' => $event->getTitle($locale), 'start' => $this->date($event->getStart())],
                $this->em->getRepository(Event::class)->findBy(['user' => $user], ['id' => 'ASC']),
            ),
            'locations_created' => array_map(
                fn(Location $location): array => [
                    'id' => $location->getId(),
                    'name' => $location->getName(),
                    'created_at' => $this->date($location->getCreatedAt()),
                ],
                $this->em->getRepository(Location::class)->findBy(['user' => $user], ['id' => 'ASC']),
            ),
            'images_uploaded' => array_map(
                fn(Image $image): array => [
                    'id' => $image->getId(),
                    'type' => $image->getType()?->name,
                    'file' => $this->imageFileName($image),
                    'created_at' => $this->date($image->getCreatedAt()),
                ],
                $this->em->getRepository(Image::class)->findBy(['uploader' => $user], ['id' => 'ASC']),
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messages(User $user): array
    {
        $messages = $this->em
            ->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user)
            ->orderBy('m.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return array_values(array_map(function (Message $message) use ($user): array {
            $sent = $message->getSender()?->getId() === $user->getId();
            $partner = $sent ? $message->getReceiver() : $message->getSender();

            return [
                'direction' => $sent ? 'sent' : 'received',
                'partner' => $this->member($partner),
                'created_at' => $this->date($message->getCreatedAt()),
                'content' => $message->getContent(),
                'read' => (bool) $message->isWasRead(),
            ];
        }, $messages));
    }

    /**
     * @return array{id: ?int, name: string}
     */
    private function member(?User $user): array
    {
        return ['id' => $user?->getId(), 'name' => $this->userService->displayName($user)];
    }

    private function imageFileName(Image $image): string
    {
        return $image->getHash() . '.' . $image->getExtension();
    }

    private function date(?DateTimeInterface $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }
}
