<?php declare(strict_types=1);

namespace App\Anonymization\Anonymizer;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use Override;

readonly class ReferenceDataAnonymizer implements AnonymizerInterface
{
    private const array ADDRESS_SETTINGS = [
        'security_contact' => 'security',
        'email_sender_mail' => 'noreply',
    ];

    public function __construct(
        private Scrubber $scrubber,
    ) {}

    #[Override]
    public function getOrder(): int
    {
        return 0;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            Claim::columns('config', rewritten: ['value'], kept: ['name']),
            Claim::reference('app_state'),
            Claim::reference('language'),
            Claim::reference('pronunciation_system'),
            Claim::reference('plugin_activation'),
            Claim::reference('plugin_settings'),
            Claim::reference('doctrine_migration_versions'),
            Claim::reference('mod_email_template'),
            Claim::reference('mod_email_template_translation'),
            Claim::reference('mod_geo_country'),
            Claim::reference('mod_geo_country_translation'),
            Claim::reference('mod_geo_state'),
            Claim::reference('mod_geo_state_translation'),
            Claim::reference('mod_geo_district'),
            Claim::reference('mod_geo_district_translation'),
            Claim::reference('mod_geo_city'),
            Claim::reference('mod_geo_city_translation'),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void
    {
        foreach (self::ADDRESS_SETTINGS as $name => $localPart) {
            $this->scrubber->rewrite('config', ['value' => ':address'], "name = :name AND value <> ''", [
                'name' => $name,
                'address' => sprintf('%s@%s', $localPart, Scrubber::ADDRESS_DOMAIN),
            ]);
        }
    }
}
