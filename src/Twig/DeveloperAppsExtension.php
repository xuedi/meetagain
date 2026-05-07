<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Enum\DeveloperAppStatus;
use App\Repository\DeveloperAppApplicationRepository;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class DeveloperAppsExtension extends AbstractExtension
{
    public function __construct(
        private readonly DeveloperAppApplicationRepository $repo,
        private readonly Security $security,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_developer_app_count', $this->pendingDeveloperAppCount(...)),
            new TwigFunction('unread_developer_app_outcome_count', $this->unreadDeveloperAppOutcomeCount(...)),
        ];
    }

    public function pendingDeveloperAppCount(): int
    {
        return $this->repo->countByStatus(DeveloperAppStatus::Pending);
    }

    public function unreadDeveloperAppOutcomeCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->repo->countUnreadOutcomeByUser($user);
    }
}
