<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\Member\UserService;
use App\Service\Member\ViewActionProviderInterface;
use App\Service\Member\ViewSectionProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class UserRuntime implements RuntimeExtensionInterface
{
    /**
     * @param iterable<ViewActionProviderInterface>  $memberViewActionProviders
     * @param iterable<ViewSectionProviderInterface> $memberViewSectionProviders
     */
    public function __construct(
        private UserService $userService,
        #[AutowireIterator(ViewActionProviderInterface::class)]
        private iterable $memberViewActionProviders,
        #[AutowireIterator(ViewSectionProviderInterface::class)]
        private iterable $memberViewSectionProviders,
    ) {}

    public function getUserName(int $id): string
    {
        return $this->userService->resolveUserName($id);
    }

    public function getMemberViewActions(User $viewer, User $target): string
    {
        return $this->concatProviderOutput($this->memberViewActionProviders, $viewer, $target, 'renderActions');
    }

    public function getMemberViewSections(User $viewer, User $target): string
    {
        return $this->concatProviderOutput($this->memberViewSectionProviders, $viewer, $target, 'renderSection');
    }

    /**
     * @param iterable<object> $providers
     */
    private function concatProviderOutput(iterable $providers, User $viewer, User $target, string $method): string
    {
        $html = '';
        foreach ($providers as $provider) {
            try {
                $fragment = $provider->{$method}($viewer, $target);
            } catch (Throwable) {
                continue;
            }
            if ($fragment !== null && $fragment !== '') {
                $html .= $fragment;
            }
        }

        return $html;
    }
}
