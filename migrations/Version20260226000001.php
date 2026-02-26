<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate email_queue.template from legacy Twig paths and renamed enum values to current EmailType backing values';
    }

    public function up(Schema $schema): void
    {
        // Step 1: old records stored the full template path (e.g. "_emails/password_reset_request.html.twig").
        // Strip the "_emails/" prefix and ".html.twig" suffix for all affected rows.
        $this->addSql(
            "UPDATE email_queue SET template = REPLACE(REPLACE(template, '_emails/', ''), '.html.twig', '') WHERE template LIKE '\\_emails/%.html.twig'"
        );

        // Step 2: rename old backing value "notification_rsvp" → "notification_rsvp_aggregated"
        // (also covers rows that came from "_emails/notification_rsvp.html.twig" after step 1).
        $this->addSql(
            "UPDATE email_queue SET template = 'notification_rsvp_aggregated' WHERE template = 'notification_rsvp'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE email_queue SET template = 'notification_rsvp' WHERE template = 'notification_rsvp_aggregated'"
        );
        $this->addSql(
            "UPDATE email_queue SET template = CONCAT('_emails/', template, '.html.twig') WHERE template IS NOT NULL AND template NOT LIKE '\\_emails/%'"
        );
    }
}
