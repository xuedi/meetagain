<?php declare(strict_types=1);

namespace ModuleEmailMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email module - the four tables take the module prefix; rows and keys move with them, the generated index names follow the new table names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'RENAME TABLE email_queue TO mod_email_queue, email_template TO mod_email_template, '
            . 'email_template_translation TO mod_email_template_translation, email_blocklist TO mod_email_blocklist',
        );
        $this->addSql('ALTER TABLE mod_email_template RENAME INDEX uniq_9c0600ca772e836a TO UNIQ_BCC0338F772E836A');
        $this->addSql('ALTER TABLE mod_email_blocklist RENAME INDEX idx_6423b7f355b127a4 TO IDX_62B5C4DF55B127A4');
        $this->addSql('ALTER TABLE mod_email_template_translation RENAME INDEX uniq_71f8068dd4db71b5131a730f TO UNIQ_9FE01735D4DB71B5131A730F');
        $this->addSql('ALTER TABLE mod_email_template_translation RENAME INDEX idx_71f8068d131a730f TO IDX_9FE01735131A730F');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_email_template RENAME INDEX UNIQ_BCC0338F772E836A TO uniq_9c0600ca772e836a');
        $this->addSql('ALTER TABLE mod_email_blocklist RENAME INDEX IDX_62B5C4DF55B127A4 TO idx_6423b7f355b127a4');
        $this->addSql('ALTER TABLE mod_email_template_translation RENAME INDEX UNIQ_9FE01735D4DB71B5131A730F TO uniq_71f8068dd4db71b5131a730f');
        $this->addSql('ALTER TABLE mod_email_template_translation RENAME INDEX IDX_9FE01735131A730F TO idx_71f8068d131a730f');
        $this->addSql(
            'RENAME TABLE mod_email_queue TO email_queue, mod_email_template TO email_template, '
            . 'mod_email_template_translation TO email_template_translation, mod_email_blocklist TO email_blocklist',
        );
    }
}
