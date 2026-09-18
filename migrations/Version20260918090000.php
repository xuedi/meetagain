<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the security measure usage log: one row per block, one counter row per day and measure for passes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE logs_security_measure (
            id INT AUTO_INCREMENT NOT NULL,
            day DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            measure VARCHAR(32) NOT NULL,
            outcome VARCHAR(16) NOT NULL,
            count INT NOT NULL,
            context VARCHAR(64) DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(512) DEFAULT NULL,
            detail JSON DEFAULT NULL,
            INDEX idx_security_measure_day (day, measure),
            INDEX idx_security_measure_created_at (created_at),
            INDEX idx_security_measure_outcome (measure, outcome, day),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE logs_security_measure');
    }
}
