<?php declare(strict_types=1);

namespace Tests\Functional\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetRoutesContractTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string USER_EMAIL = 'Adem.Lane@example.org';
    private const string USER_PASSWORD = '1234';

    private const array SKIPPED_ROUTES = [
        'app_admin_cms_block_image_remove' => 'entity lookup before CSRF',
        'app_admin_cms_block_image_toggle_side' => 'entity lookup before CSRF',
        'app_admin_cms_card_image_remove' => 'entity lookup before CSRF',
        'app_admin_cms_gallery_remove' => 'entity lookup before CSRF',
        'app_admin_meta_integrity_fix_all' => 'key validation before CSRF',
        'app_admin_meta_integrity_fix_one' => 'key validation before CSRF',
        'app_admin_meta_subscriptions_reload' => 'needs active subscription',
        'app_multisite_my_groups_leave' => 'entity lookup before CSRF',
        'app_admin_group_languages_remove' => 'needs configured language',
        'app_admin_group_languages_set_default' => 'needs configured language',
        'app_multisite_admin_member_group_verify' => 'entity lookup before CSRF',
        'app_multisite_admin_member_group_unverify' => 'entity lookup before CSRF',
        'app_multisite_admin_member_group_restrict' => 'entity lookup before CSRF',
        'app_multisite_admin_member_group_unrestrict' => 'entity lookup before CSRF',
        'app_admin_meta_cms_pages_toggle_locked' => 'entity lookup before CSRF',
        'app_admin_meta_cms_pages_unassign' => 'entity lookup before CSRF',
        'app_admin_group_social_link_delete' => 'entity lookup before CSRF',
        'app_admin_email_sendlog_clear_cap' => 'entity lookup before CSRF',
        'app_replace_image_select' => 'entity resolver before CSRF',
        'app_image_rotate' => 'entity resolver before CSRF',
        'app_event_delete_comment' => 'entity lookup before CSRF',
        'plugin_dinnerclub_lists_delete' => 'entity lookup before CSRF',
        'plugin_dinnerclub_image_delete' => 'entity lookup before CSRF',
        'app_plugin_glossary_suggestion_apply' => 'signed-token exception',
        'app_plugin_glossary_suggestion_delete' => 'signed-token exception',
        'app_admin_email_announcements_from_cms' => 'entity lookup before CSRF',
        'app_admin_email_announcements_delete' => 'entity lookup before CSRF',
        'app_admin_email_announcements_send' => 'entity lookup before CSRF',
    ];

    public static function provideAdminRoutes(): iterable
    {
        yield 'cms delete' => ['/en/admin/cms/delete'];
        yield 'cms block delete' => ['/en/admin/cms/block/delete'];
        yield 'host delete' => ['/en/admin/hosts/1/delete'];
        yield 'location delete' => ['/en/admin/locations/1/delete'];
        yield 'email blocklist delete' => ['/en/admin/email/blocklist/1/delete'];
        yield 'language toggle' => ['/en/admin/language/1/toggle'];
        yield 'cms block move down' => ['/en/admin/cms/block/down'];
        yield 'cms block move up' => ['/en/admin/cms/block/up'];
        yield 'access denied log clear' => ['/en/admin/logs/access-denied/clear'];
        yield 'cron log clear' => ['/en/admin/logs/cron/clear'];
        yield 'not found log clear' => ['/en/admin/logs/404/clear'];
        yield 'system log cleanup' => ['/en/admin/logs/system/cleanup'];
        yield 'security blocked clear' => ['/en/admin/security/blocked/clear'];
        yield 'security incidents clear' => ['/en/admin/security/incidents/clear'];
        yield 'security incidents unblock' => ['/en/admin/security/incidents/1/unblock'];
        yield 'security rate limiting clear' => ['/en/admin/security/rate-limiting/clear'];
        yield 'redis cache clear' => ['/en/admin/system/cache/clear'];
        yield 'seo indexnow submit' => ['/en/admin/system/seo/indexnow-submit'];
        yield 'regenerate thumbnails' => ['/en/admin/system/images/regenerate_thumbnails'];
        yield 'cleanup thumbnails' => ['/en/admin/system/images/cleanup_thumbnails'];
        yield 'sync image locations' => ['/en/admin/system/images/sync_locations'];
        yield 'support report resolve' => ['/en/admin/support/reports/resolve/1'];
        yield 'support mark read' => ['/en/admin/support/mark-read/1'];
    }

    public static function provideAdminMultisiteRoutes(): iterable
    {
        yield 'cms pages unassign' => ['/en/admin/meta/cms-pages/1/unassign'];
        yield 'cms pages toggle locked' => ['/en/admin/meta/cms-pages/1/toggle-locked'];
        yield 'subscriptions reload' => ['/en/admin/meta/subscriptions/1/reload'];
    }

    public static function provideUserRoutes(): iterable
    {
        yield 'default language' => ['/en/language/de'];
        yield 'event toggle rsvp' => ['/en/event/toggleRsvp/1/'];
        yield 'profile toggle rsvp' => ['/en/profile/toggleRsvp/1/'];
        yield 'profile config toggle' => ['/en/profile/config/toggle/notification'];
        yield 'profile config toggle notification' => ['/en/profile/config/toggleNotification/notification'];
        yield 'profile social toggle follow' => ['/en/profile/social/toggleFollow/1/'];
        yield 'member toggle follow' => ['/en/members/toggleFollow/1'];
        yield 'member rotate avatar' => ['/en/members/rotate-avatar/1'];
        yield 'member remove avatar' => ['/en/members/remove-image/1'];
        yield 'member restrict' => ['/en/members/restrict/1'];
        yield 'member verify' => ['/en/members/verify/1'];
        yield 'bookclub withdraw' => ['/en/bookclub/withdraw/1'];
        yield 'glossary delete' => ['/en/glossary/delete/1'];
        yield 'glossary approval approve' => ['/en/glossary/approval/approve/1'];
        yield 'glossary approval deny' => ['/en/glossary/approval/deny/1'];
    }

    public static function provideNonLocaleRoutes(): iterable
    {
        yield 'ajax cookie accept' => ['/ajax/cookie/accept'];
        yield 'ajax cookie deny' => ['/ajax/cookie/deny'];
    }

    public static function provideMultisiteLocaleRoutes(): iterable
    {
        yield 'multisite context set redirect' => ['/en/api/multisite/context/set-and-redirect/1'];
    }

    #[DataProvider('provideAdminRoutes')]
    public function testAdminRouteRejectsGet(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', $path);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    #[DataProvider('provideAdminRoutes')]
    public function testAdminRouteRejectsInvalidCsrf(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('POST', $path, ['_token' => 'invalid-csrf-token']);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [400, 302, 404]);
    }

    #[DataProvider('provideAdminMultisiteRoutes')]
    public function testAdminMultisiteRouteRejectsGet(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('GET', $path);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    #[DataProvider('provideAdminMultisiteRoutes')]
    public function testAdminMultisiteRouteRejectsInvalidCsrf(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('POST', $path, ['_token' => 'invalid-csrf-token']);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [400, 302, 403, 404]);
    }

    #[DataProvider('provideUserRoutes')]
    public function testUserRouteRejectsGet(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsUser($client);

        // Act
        $client->request('GET', $path);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    #[DataProvider('provideUserRoutes')]
    public function testUserRouteRejectsInvalidCsrf(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsUser($client);

        // Act
        $client->request('POST', $path, ['_token' => 'invalid-csrf-token']);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [400, 302, 403, 404]);
    }

    #[DataProvider('provideNonLocaleRoutes')]
    public function testNonLocaleRouteRejectsGet(string $path): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', $path);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    #[DataProvider('provideMultisiteLocaleRoutes')]
    public function testMultisiteLocaleRouteRejectsGet(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsUser($client);

        // Act
        $client->request('GET', $path);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [404, 405]);
    }

    #[DataProvider('provideMultisiteLocaleRoutes')]
    public function testMultisiteLocaleRouteRejectsInvalidCsrf(string $path): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsUser($client);

        // Act
        $client->request('POST', $path, ['_token' => 'invalid-csrf-token']);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [400, 302, 403, 404]);
    }

    #[DataProvider('provideNonLocaleRoutes')]
    public function testNonLocaleRouteRejectsInvalidCsrf(string $path): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', $path, ['_token' => 'invalid-csrf-token']);

        // Assert
        static::assertContains($client->getResponse()->getStatusCode(), [400, 302, 403, 404]);
    }

    public function testSkippedRoutesConstantIsNonEmpty(): void
    {
        // Arrange
        $skipped = self::SKIPPED_ROUTES;

        // Assert
        static::assertNotEmpty($skipped);
        foreach ($skipped as $route => $reason) {
            static::assertNotEmpty($route);
            static::assertNotEmpty($reason);
        }
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }

    private function loginAsUser(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::USER_EMAIL,
                '_password' => self::USER_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
