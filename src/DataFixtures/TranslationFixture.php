<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Translation;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TranslationFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $languageCode = ['cn','de','en'];
        $languageSet = [0,1,2];
        $importUser = $this->getReference('user_' . md5('import'));
        foreach ($this->getData() as [$placeholder, $translation]) {
            foreach ($languageSet as $index) {
                $user = new Translation();
                $user->setLanguage($languageCode[$index]);
                $user->setUser($importUser);
                $user->setPlaceholder($placeholder);
                $user->setTranslation($translation[$index]);
                $user->setCreatedAt(new DateTimeImmutable());

                $manager->persist($user);
            }
        }
        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            ['page_title_default', ['欢迎','Willkommen','Welcome']],
            ['menu_about', ['关于','Über Uns','About']],
            ['menu_events', ['活动','Events','Events']],
            ['menu_members', ['成员','Mitglieder','Members']],
            ['menu_manage', ['管理','Verwaltung','Manage']],
            ['menu_admin', ['管理员','Admin','Admin']],
            ['menu_profile_events', ['我的活动','Meine Events','My events']],
            ['menu_profile_messages', ['我的留言','Meine Nachrichten','My messages']],
            ['menu_profile_view_profile', ['查看简介','Mein Profil','My profile']],
            ['menu_profile_config', ['设置和隐私','Einstellungen','Settings & Privacy']],
            ['menu_profile_logout', ['注销','Abmelden','Logout']],
            ['language_de', ['德语','Deutsch','German']],
            ['language_en', ['英语','English','English']],
            ['language_cn', ['中文','Chinesisch','Chinese']],
            ['button_save', ['节省','Speichern','Save']],
            ['menu_admin_cms', ['页面','Seiten','Pages']],
            ['menu_admin_config', ['配置','Konfiguration','Configuration']],
            ['menu_admin_event', ['活动','Events','Events']],
            ['menu_admin_host', ['主机','Gastgeber','Hosts']],
            ['menu_admin_location', ['会场','Veranstaltungsort','Venue']],
            ['menu_admin_translation_edit', ['编辑','Bearbeiten','Edit']],
            ['menu_admin_translation_extract', ['搜索','Suchen','Search']],
            ['menu_admin_translation_publish', ['发布','Veröffentlichen','Publish']],
            ['menu_admin_user', ['成员','Mitglieder','Members']],
            ['page_title_about', ['关于','Über uns','About']],
            ['page_title_admin', ['管理','Admin','Admin']],
            ['page_title_event', ['活动','Events','Events']],
            ['page_title_login', ['登录','Login','Login']],
            ['page_title_manage', ['管理','Verwalten','Manage']],
            ['page_title_member', ['成员','Mitglieder','Members']],
            ['page_title_profile', ['概况','Profil','Profile']],
            ['role_admin', ['管理','Administrator','Admin']],
            ['role_manager', ['经理','Manager','Manager']],
            ['role_system', ['系统','System','System']],
            ['role_user', ['用户','Benutzer','User']],
            ['menu_default', ['主页','Startseite','Homepage']],
            ['menu_login', ['登录','Anmelden','Login']],
            ['p: Please sign in', ['请登录','Anmeldung','Please sign in']],
            ['p: Please register', ['请注册','Registrierung','Please register']],
        ];
    }
}
