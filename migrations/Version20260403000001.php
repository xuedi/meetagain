<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate activity.type from int enum column to VARCHAR(80) string identifier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity ADD type_string VARCHAR(80) NOT NULL DEFAULT ''");

        $this->addSql("UPDATE activity SET type_string = CASE type
            WHEN 0  THEN 'core.changed_username'
            WHEN 1  THEN 'core.login'
            WHEN 2  THEN 'core.rsvp_yes'
            WHEN 3  THEN 'core.rsvp_no'
            WHEN 4  THEN 'core.registered'
            WHEN 5  THEN 'core.followed_user'
            WHEN 6  THEN 'core.unfollowed_user'
            WHEN 7  THEN 'core.password_reset_request'
            WHEN 8  THEN 'core.password_reset'
            WHEN 9  THEN 'core.event_image_uploaded'
            WHEN 10 THEN 'core.reported_image'
            WHEN 11 THEN 'core.send_message'
            WHEN 12 THEN 'core.registration_email_confirmed'
            WHEN 13 THEN 'core.updated_profile_picture'
            WHEN 14 THEN 'core.blocked_user'
            WHEN 15 THEN 'core.unblocked_user'
            WHEN 16 THEN 'core.password_changed'
            WHEN 17 THEN 'core.commented_on_event'
            WHEN 18 THEN 'core.registration_email_resent'
            WHEN 19 THEN 'core.admin_event_created'
            WHEN 20 THEN 'core.admin_event_edited'
            WHEN 21 THEN 'core.admin_event_deleted'
            WHEN 22 THEN 'core.admin_event_cancelled'
            WHEN 23 THEN 'core.admin_cms_page_created'
            WHEN 24 THEN 'core.admin_cms_page_updated'
            WHEN 25 THEN 'core.admin_cms_page_deleted'
            WHEN 26 THEN 'core.admin_member_approved'
            WHEN 27 THEN 'core.admin_member_denied'
            WHEN 28 THEN 'core.admin_member_promoted'
            ELSE CONCAT('core.unknown_', type)
        END");

        $this->addSql('ALTER TABLE activity DROP COLUMN type');
        $this->addSql('ALTER TABLE activity CHANGE type_string type VARCHAR(80) NOT NULL');
        $this->addSql('CREATE INDEX IDX_AC74095A8CDE5729 ON activity (type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_AC74095A8CDE5729 ON activity');
        $this->addSql('ALTER TABLE activity ADD type_int INT NOT NULL DEFAULT 0');

        $this->addSql("UPDATE activity SET type_int = CASE type
            WHEN 'core.changed_username'             THEN 0
            WHEN 'core.login'                        THEN 1
            WHEN 'core.rsvp_yes'                     THEN 2
            WHEN 'core.rsvp_no'                      THEN 3
            WHEN 'core.registered'                   THEN 4
            WHEN 'core.followed_user'                THEN 5
            WHEN 'core.unfollowed_user'              THEN 6
            WHEN 'core.password_reset_request'       THEN 7
            WHEN 'core.password_reset'               THEN 8
            WHEN 'core.event_image_uploaded'         THEN 9
            WHEN 'core.reported_image'               THEN 10
            WHEN 'core.send_message'                 THEN 11
            WHEN 'core.registration_email_confirmed' THEN 12
            WHEN 'core.updated_profile_picture'      THEN 13
            WHEN 'core.blocked_user'                 THEN 14
            WHEN 'core.unblocked_user'               THEN 15
            WHEN 'core.password_changed'             THEN 16
            WHEN 'core.commented_on_event'           THEN 17
            WHEN 'core.registration_email_resent'    THEN 18
            WHEN 'core.admin_event_created'          THEN 19
            WHEN 'core.admin_event_edited'           THEN 20
            WHEN 'core.admin_event_deleted'          THEN 21
            WHEN 'core.admin_event_cancelled'        THEN 22
            WHEN 'core.admin_cms_page_created'       THEN 23
            WHEN 'core.admin_cms_page_updated'       THEN 24
            WHEN 'core.admin_cms_page_deleted'       THEN 25
            WHEN 'core.admin_member_approved'        THEN 26
            WHEN 'core.admin_member_denied'          THEN 27
            WHEN 'core.admin_member_promoted'        THEN 28
            ELSE 0
        END");

        $this->addSql('ALTER TABLE activity DROP COLUMN type');
        $this->addSql('ALTER TABLE activity CHANGE type_int type INT NOT NULL');
    }
}
