<?php declare(strict_types=1);

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UserExtension extends AbstractExtension
{
    public function __construct(
        private readonly UserRepository $userRepo,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_user_name', $this->getUserName(...)),
        ];
    }

    public function getUserName(int $id): string
    {
        return $this->userRepo->findOneBy(['id' => $id])?->getName() ?? 'Unknown';
    }
}
