<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\Profile\ProfileConfigPrivacyToggle;
use App\Service\Profile\ProfileConfigPrivacyToggleProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProfileConfigExtension extends AbstractExtension
{
    /**
     * @param iterable<ProfileConfigPrivacyToggleProviderInterface> $privacyToggleProviders
     */
    public function __construct(
        #[AutowireIterator(ProfileConfigPrivacyToggleProviderInterface::class)]
        private readonly iterable $privacyToggleProviders,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_profile_config_privacy_toggles', $this->getPrivacyToggles(...)),
        ];
    }

    /**
     * @return list<ProfileConfigPrivacyToggle>
     */
    public function getPrivacyToggles(User $user): array
    {
        $toggles = [];
        foreach ($this->privacyToggleProviders as $provider) {
            $toggle = $provider->getToggle($user);
            if ($toggle !== null) {
                $toggles[] = $toggle;
            }
        }

        return $toggles;
    }
}
