<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate email_queue.template from legacy Twig path to EmailType enum backing value';
    }

    public function up(Schema $schema): void
    {
        // Old records stored the full template path (e.g. "_emails/password_reset_request.html.twig").
        // The EmailType enum now uses short backing values (e.g. "password_reset_request").
        // Strip the "_emails/" prefix and ".html.twig" suffix for all affected rows.
        $this->addSql(
            "UPDATE email_queue SET template = REPLACE(REPLACE(template, '_emails/', ''), '.html.twig', '') WHERE template LIKE '\\_emails/%.html.twig'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE email_queue SET template = CONCAT('_emails/', template, '.html.twig') WHERE template IS NOT NULL AND template NOT LIKE '\\_emails/%'"
        );
    }
}
