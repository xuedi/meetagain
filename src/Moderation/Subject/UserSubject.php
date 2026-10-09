<?php declare(strict_types=1);

namespace App\Moderation\Subject;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Filter\Member\MemberFilterService;
use App\Moderation\SubjectProviderInterface;
use App\Moderation\SubjectSnapshot;
use App\Repository\UserRepository;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class UserSubject implements SubjectProviderInterface
{
    public const string TYPE = 'user';
    private const int EXCERPT_LENGTH = 1000;

    public function __construct(
        private UserRepository $users,
        private MemberFilterService $memberFilterService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function describe(int $id, User $reporter): ?SubjectSnapshot
    {
        $user = $this->users->find($id);
        if ($user === null || $user->getId() === $reporter->getId()) {
            return null;
        }

        $isGone = $user->getStatus() === UserStatus::Deleted || $user->getRole() === UserRole::System;
        if ($isGone || !$this->memberFilterService->isMemberAccessible($id)) {
            return null;
        }

        $bio = trim((string) $user->getBio());

        return new SubjectSnapshot(label: (string) $user->getName(), excerpt: $bio === '' ? null : mb_substr($bio, 0, self::EXCERPT_LENGTH), author: $user);
    }

    #[Override]
    public function getAdminPath(int $id): ?string
    {
        return $this->urlGenerator->generate('app_member_view', ['id' => $id]);
    }

    #[Override]
    public function getRemoveLabelKey(): ?string
    {
        return null;
    }

    #[Override]
    public function remove(int $id): void {}
}
