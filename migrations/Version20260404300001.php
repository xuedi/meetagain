<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404300001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_location table for tracking where images are used';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE image_location (id INT AUTO_INCREMENT NOT NULL, image_id INT NOT NULL, location_type SMALLINT NOT NULL, location_id INT NOT NULL, UNIQUE INDEX uq_image_location (image_id, location_type, location_id), CONSTRAINT fk_image_location_image FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE CASCADE, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image_location DROP FOREIGN KEY fk_image_location_image');
        $this->addSql('DROP TABLE image_location');
    }
}
