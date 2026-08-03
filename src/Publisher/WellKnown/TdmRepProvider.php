<?php declare(strict_types=1);

namespace App\Publisher\WellKnown;

use App\Service\Config\ConfigService;
use Override;
use Symfony\Component\HttpFoundation\Request;

final readonly class TdmRepProvider implements WellKnownProviderInterface
{
    public const string SUFFIX = 'tdmrep.json';

    private const int MAX_AGE = 86400;

    public function __construct(
        private ConfigService $configService,
    ) {}

    #[Override]
    public function getSuffix(): string
    {
        return self::SUFFIX;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function provide(Request $request): ?WellKnownDocument
    {
        $rule = [
            'location' => '/',
            'tdm-reservation' => $this->configService->isTdmReservationEnabled() ? 1 : 0,
        ];

        $policy = $this->configService->getTdmPolicyUrl();
        if ($policy !== '') {
            $rule['tdm-policy'] = $policy;
        }

        return WellKnownDocument::json([$rule], 'application/json', self::MAX_AGE);
    }
}
