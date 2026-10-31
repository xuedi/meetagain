<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031161640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A location and a member can name their city from the geo module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location ADD city_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_location_city ON location (city_id)');
        $this->addSql('ALTER TABLE `user` ADD city_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_location_city ON location');
        $this->addSql('ALTER TABLE location DROP city_id');
        $this->addSql('ALTER TABLE `user` DROP city_id');
    }
}
