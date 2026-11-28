<?php declare(strict_types=1);

namespace App\Contribution;

use App\Entity\Event;
use App\Entity\User;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

readonly class EventSection implements TargetProviderInterface
{
    public const string TYPE = 'event';

    public function __construct(
        private EventRepository $eventRepo,
        private EventFilterService $eventFilter,
        private Security $security,
    ) {}

    #[Override]
    public function getType(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return '';
    }

    #[Override]
    public function mayTouch(User $user, int|string $id): bool
    {
        if (!$this->eventFilter->isEventAccessible((int) $id)) {
            return false;
        }

        $event = $this->eventRepo->find((int) $id);

        return $event instanceof Event && $this->security->isGranted(PermissionAttribute::EVENT_SUGGEST, $event);
    }
}
