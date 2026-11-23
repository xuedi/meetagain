<?php declare(strict_types=1);

namespace App\Anonymization\Anonymizer;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use Override;

readonly class PublishedContentAnonymizer implements AnonymizerInterface
{
    #[Override]
    public function getOrder(): int
    {
        return 0;
    }

    #[Override]
    public function getClaims(): array
    {
        return [
            Claim::columns('event'),
            Claim::columns('event_translation', kept: ['language', 'title', 'description', 'teaser']),
            Claim::columns('event_series', kept: ['name', 'rule_spec']),
            Claim::columns('event_canonical_root', kept: ['locale']),
            Claim::columns('event_item_association', kept: ['item_type', 'section_label']),
            Claim::columns('announcement', kept: ['link_hash']),
            Claim::columns('cms', kept: ['slug']),
            Claim::columns('cms_block', kept: ['language', 'json']),
            Claim::columns('cms_link_name', kept: ['language', 'name']),
            Claim::columns('cms_menu_location'),
            Claim::columns('cms_title', kept: ['language', 'title']),
            Claim::columns('location', kept: ['name', 'description', 'street', 'city', 'postcode', 'longitude', 'latitude']),
            Claim::columns('item_tag', kept: ['item_type', 'labels']),
            Claim::columns('item_tag_assignment', kept: ['item_type']),
            Claim::columns('image_location'),
            Claim::columns('mod_ballot', kept: ['purpose', 'title', 'subject_type', 'winning_key', 'tied_keys']),
            Claim::columns('mod_ballot_option', kept: ['option_key', 'label']),
            Claim::columns('mod_ballot_vote', kept: ['option_key']),
            Claim::columns('mod_circulation_copy', kept: ['item_type', 'context', 'label']),
            Claim::columns('mod_circulation_handover'),
            Claim::columns('mod_circulation_ledger', kept: ['context', 'item_type', 'payload']),
            Claim::columns('mod_circulation_request', kept: ['context', 'item_type']),
            Claim::columns('mod_proposal_change', kept: ['target_type', 'changes']),
            Claim::columns('mod_proposal_suggestion', kept: ['target_type', 'payload', 'scope']),
            Claim::columns('mod_trust_grant', kept: ['context']),
            Claim::columns('mod_trust_context_config', kept: ['context', 'payload']),
        ];
    }

    #[Override]
    public function anonymize(Context $context): void {}
}
