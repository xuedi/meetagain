<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Custom recurrence rules for event series, stored as an RFC-5545 rule string
 * (e.g. FREQ=MONTHLY;BYDAY=1SU). Authoritative only when rule = EventInterval::Custom;
 * the fixed presets keep using the rule column alone.
 */
final class Version20260725120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_series.rule_spec for custom RFC-5545 recurrence rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_series ADD rule_spec VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_series DROP rule_spec');
    }
}
