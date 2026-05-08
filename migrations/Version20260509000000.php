<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add edited_at column to message for sender-edit-within-10-minutes feature.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE message ADD edited_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP edited_at');
    }
}
