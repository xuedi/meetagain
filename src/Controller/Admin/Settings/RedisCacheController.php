<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Cache\RedisCacheService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/cache')]
final class RedisCacheController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly RedisCacheService $redisCacheService,
        #[Autowire(service: 'cache.global_clearer')]
        private readonly Psr6CacheClearer $cacheClearer,
    ) {
        parent::__construct($translator, 'config');
    }

    #[Route('', name: 'app_admin_system_redis_cache', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_READ);

        $prefix = $request->query->getString('prefix');
        $prefix = $prefix === '' ? null : $prefix;

        $prefixes = $this->redisCacheService->listPrefixes();
        if ($prefix !== null && !array_key_exists($prefix, $prefixes)) {
            $prefix = null;
        }

        $result = $this->redisCacheService->listKeys($prefix);
        $totalKeys = array_sum($prefixes);

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalKeys,
                $this->translator->trans('admin_system_cache.summary_total'),
            )),
        ];
        if ($prefix !== null) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($result['keys']),
                $this->translator->trans('admin_system_cache.summary_in_filter'),
            ));
        }
        if ($result['truncated']) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium">%s</span>',
                $this->translator->trans('admin_system_cache.summary_truncated'),
            ));
        }

        $actions = [];
        if ($totalKeys > 0) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('global.button_clear'),
                target: $this->generateUrl('app_admin_system_redis_cache_clear'),
                icon: 'trash',
            );
        }
        $actions[] = $this->buildPrefixDropdown($prefix, $prefixes);
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_system_config'),
            icon: 'arrow-left',
        );

        $adminTop = new AdminTop(info: $info, actions: $actions);

        return $this->render('admin/system/cache/list.html.twig', [
            'active' => 'system',
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
            'keys' => $result['keys'],
            'activePrefix' => $prefix,
        ]);
    }

    #[Route('/clear', name: 'app_admin_system_redis_cache_clear', methods: ['GET'])]
    public function clear(): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_UPDATE);

        $this->cacheClearer->clear('');
        $this->addFlash('success', $this->translator->trans('admin_system_cache.flash_cleared'));

        return $this->redirectToRoute('app_admin_system_redis_cache');
    }

    #[Route('/show', name: 'app_admin_system_redis_cache_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_READ);

        $key = $request->query->getString('key');
        $prefix = $request->query->getString('prefix');

        $entry = $key !== '' ? $this->redisCacheService->inspect($key) : null;

        $backParams = $prefix !== '' ? ['prefix' => $prefix] : [];

        $adminTop = new AdminTop(
            info: $entry !== null
                ? [
                    new AdminTopInfoHtml(sprintf(
                        '<strong>%s</strong>',
                        htmlspecialchars($entry['key'], ENT_QUOTES, 'UTF-8'),
                    )),
                    new AdminTopInfoHtml(sprintf(
                        '<span class="tag is-light is-medium">%s</span>',
                        htmlspecialchars($entry['type'], ENT_QUOTES, 'UTF-8'),
                    )),
                    new AdminTopInfoHtml(sprintf(
                        '<span class="has-text-grey">%s</span>',
                        $this->formatTtl($entry['ttl']),
                    )),
                    new AdminTopInfoHtml(sprintf(
                        '<span class="has-text-grey">%s %s</span>',
                        $this->formatBytes($entry['size']),
                        $this->translator->trans('admin_system_cache.label_size'),
                    )),
                ]
                : [new AdminTopInfoHtml(sprintf(
                    '<span class="has-text-danger">%s</span>',
                    $this->translator->trans('admin_system_cache.flash_key_missing'),
                ))],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_system_redis_cache', $backParams),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/system/cache/show.html.twig', [
            'active' => 'system',
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
            'entry' => $entry,
        ]);
    }

    /**
     * @param array<string, int> $prefixes
     */
    private function buildPrefixDropdown(?string $current, array $prefixes): AdminTopActionDropdown
    {
        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_cache.prefix_filter_all'),
                target: $this->generateUrl('app_admin_system_redis_cache'),
                isActive: $current === null,
                count: null,
            ),
        ];
        foreach ($prefixes as $prefix => $count) {
            $options[] = new AdminTopActionDropdownOption(
                label: $prefix,
                target: $this->generateUrl('app_admin_system_redis_cache', ['prefix' => $prefix]),
                isActive: $current === $prefix,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_system_cache.prefix_filter_label'),
                $current ?? $this->translator->trans('admin_system_cache.prefix_filter_all'),
            ),
            options: $options,
            icon: 'filter',
        );
    }

    private function formatTtl(int $ttl): string
    {
        if ($ttl === -1) {
            return $this->translator->trans('admin_system_cache.ttl_none');
        }
        if ($ttl === -2) {
            return $this->translator->trans('admin_system_cache.ttl_expired');
        }
        if ($ttl < 60) {
            return $ttl . 's';
        }
        if ($ttl < 3600) {
            return floor($ttl / 60) . 'm';
        }
        if ($ttl < 86400) {
            return floor($ttl / 3600) . 'h';
        }

        return floor($ttl / 86400) . 'd';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return sprintf('%.1f %s', $bytes / (1024 ** $power), $units[$power]);
    }
}
