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
        return $this->concatProviderOutput(
            $this->memberViewActionProviders,
            static fn(ViewActionProviderInterface $provider): ?string => $provider->renderActions($viewer, $target),
        );
    }

    public function getMemberViewSections(User $viewer, User $target): string
    {
        return $this->concatProviderOutput(
            $this->memberViewSectionProviders,
            static fn(ViewSectionProviderInterface $provider): ?string => $provider->renderSection($viewer, $target),
        );
    }

    /**
     * @template T of object
     * @param iterable<T> $providers
     * @param callable(T): ?string $render
     */
    private function concatProviderOutput(iterable $providers, callable $render): string
    {
        $html = '';
        foreach ($providers as $provider) {
            try {
                $fragment = $render($provider);
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
