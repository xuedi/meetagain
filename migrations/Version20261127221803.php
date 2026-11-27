<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127221803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds account_deletion_feedback: the optional, unlinked reason and comment a member leaves when deleting their account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE account_deletion_feedback (id INT AUTO_INCREMENT NOT NULL, reason VARCHAR(32) NOT NULL, comment LONGTEXT DEFAULT NULL, locale VARCHAR(5) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE account_deletion_feedback');
    }
}
