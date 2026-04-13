<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default footer column titles into app_state';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->addSql("INSERT INTO app_state (key_name, value, updated_at) VALUES ('footer_col1_title', 'Help', '{$now}')");
        $this->addSql("INSERT INTO app_state (key_name, value, updated_at) VALUES ('footer_col2_title', 'Platform', '{$now}')");
        $this->addSql("INSERT INTO app_state (key_name, value, updated_at) VALUES ('footer_col3_title', 'Social', '{$now}')");
        $this->addSql("INSERT INTO app_state (key_name, value, updated_at) VALUES ('footer_col4_title', 'Legal', '{$now}')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_state WHERE key_name IN ('footer_col1_title', 'footer_col2_title', 'footer_col3_title', 'footer_col4_title')");
    }
}
