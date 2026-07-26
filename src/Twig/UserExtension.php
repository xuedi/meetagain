<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\Member\ViewActionProviderInterface;
use App\Service\Member\ViewSectionProviderInterface;
use App\Service\Member\UserService;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UserExtension extends AbstractExtension
{
    /**
     * @param iterable<ViewActionProviderInterface>  $memberViewActionProviders
     * @param iterable<ViewSectionProviderInterface> $memberViewSectionProviders
     */
    public function __construct(
        private readonly UserService $userService,
        #[AutowireIterator(ViewActionProviderInterface::class)]
        private readonly iterable $memberViewActionProviders,
        #[AutowireIterator(ViewSectionProviderInterface::class)]
        private readonly iterable $memberViewSectionProviders,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_user_name', $this->getUserName(...)),
            new TwigFunction('get_member_view_actions', $this->getMemberViewActions(...), ['is_safe' => ['html']]),
            new TwigFunction('get_member_view_sections', $this->getMemberViewSections(...), ['is_safe' => ['html']]),
        ];
    }

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
