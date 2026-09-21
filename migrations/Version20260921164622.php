<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921164622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add support_request.locale so the answer to a support request is sent in the language it was asked in';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE support_request ADD locale VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE support_request DROP locale');
    }
}
