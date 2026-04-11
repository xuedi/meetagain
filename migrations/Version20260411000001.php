<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename locale code "cn" to "zh" across all core tables';
    }

    public function up(Schema $schema): void
    {
        // All statements in a single transaction so no window exists where
        // language.code='zh' but FK rows in other tables still say 'cn'.
        $this->addSql("UPDATE language SET code='zh' WHERE code='cn'");
        $this->addSql("UPDATE user SET locale='zh' WHERE locale='cn'");
        $this->addSql("UPDATE event_translation SET language='zh' WHERE language='cn'");
        $this->addSql("UPDATE cms_block SET language='zh' WHERE language='cn'");
        $this->addSql("UPDATE cms_title SET language='zh' WHERE language='cn'");
        $this->addSql("UPDATE cms_link_name SET language='zh' WHERE language='cn'");
        $this->addSql("UPDATE email_template_translation SET language='zh' WHERE language='cn'");
        $this->addSql("UPDATE email_queue SET lang='zh' WHERE lang='cn'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE email_queue SET lang='cn' WHERE lang='zh'");
        $this->addSql("UPDATE email_template_translation SET language='cn' WHERE language='zh'");
        $this->addSql("UPDATE cms_link_name SET language='cn' WHERE language='zh'");
        $this->addSql("UPDATE cms_title SET language='cn' WHERE language='zh'");
        $this->addSql("UPDATE cms_block SET language='cn' WHERE language='zh'");
        $this->addSql("UPDATE event_translation SET language='cn' WHERE language='zh'");
        $this->addSql("UPDATE user SET locale='cn' WHERE locale='zh'");
        $this->addSql("UPDATE language SET code='cn' WHERE code='zh'");
    }
}
