<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminCmsPageCreated;
use App\Activity\Messages\AdminCmsPageDeleted;
use App\Activity\Messages\AdminCmsPageUpdated;
use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\Announcement;
use App\Entity\BlockType\EventTeaser;
use App\Entity\BlockType\FactsRow;
use App\Entity\BlockType\Gallery;
use App\Entity\BlockType\Headline;
use App\Entity\BlockType\Hero;
use App\Entity\BlockType\Text;
use App\Entity\BlockType\TrioCards;
use App\Entity\Cms;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Cms\AdminCmsListFilterService;
use App\Form\CmsType;
use App\Repository\AnnouncementRepository;
use App\Repository\CmsBlockRepository;
use App\Repository\CmsRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_STEWARD'), Route('/admin/cms')]
final class CmsController extends AbstractController implements AdminNavigationInterface
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_content',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_cms',
                    route: 'app_admin_cms',
                    active: 'cms',
                    role: 'ROLE_STEWARD',
                ),
            ],
            sectionPriority: 50,
        );
    }

    public function __construct(
        private readonly CmsRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly CmsBlockRepository $blockRepo,
        private readonly AnnouncementRepository $announcementRepo,
        private readonly AdminCmsListFilterService $adminCmsListFilterService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly LoggerInterface $logger,
        private readonly LanguageService $languageService,
        private readonly ActivityService $activityService,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_cms')]
    public function cmsList(): Response
    {
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        $newForm = $this->createForm(CmsType::class, null, [
            'action' => $this->generateUrl('app_admin_cms_add'),
            'is_admin' => $isAdmin,
        ]);

        $filterResult = $this->adminCmsListFilterService->getCmsIdFilter();
        $cmsPages = $this->repo->findByIds($filterResult->getCmsIds());

        $cmsIdsWithAnnouncements = [];
        $publishedCount = 0;
        foreach ($cmsPages as $page) {
            $announcement = $this->announcementRepo->findByCmsPage($page->getId());
            if ($announcement !== null) {
                $cmsIdsWithAnnouncements[] = $page->getId();
            }
            if ($page->isPublished()) {
                $publishedCount++;
            }
        }

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($cmsPages),
                $this->translator->trans('admin_cms.summary_total'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $publishedCount,
                $this->translator->trans('admin_cms.summary_published'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($cmsIdsWithAnnouncements),
                $this->translator->trans('admin_cms.summary_with_announcement'),
            )),
        ];

        $adminTop = new AdminTop(info: $info, actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('admin_cms.button_add_page'),
                target: '#',
                icon: 'plus',
                toggleId: 'cms-add',
            ),
        ]);

        return $this->render('admin/cms/cms_list.html.twig', [
            'active' => 'cms',
            'form' => $newForm,
            'cms' => $cmsPages,
            'is_admin' => $isAdmin,
            'cms_with_announcements' => $cmsIdsWithAnnouncements,
            'adminTop' => $adminTop,
        ]);
    }

    #[Route(
        '/{id}/edit/{locale}',
        name: 'app_admin_cms_edit',
        requirements: ['locale' => '[^/]+'],
        defaults: ['locale' => null],
        methods: ['GET', 'POST'],
    )]
    public function cmsEdit(Request $request, Cms $cms, ?string $locale = null): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::CMS_PAGE_UPDATE, $cms);

        if (!$this->adminCmsListFilterService->isCmsAccessible($cms->getId())) {
            $this->logAccessDenied($cms, $request, 'edit');
            throw $this->createAccessDeniedException('This CMS page is not accessible in the current context');
        }

        $locale = $this->getLastEditLocale($locale, $request->getSession());

        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        $form = $this->createForm(CmsType::class, $cms, [
            'is_admin' => $isAdmin,
            'edit_locale' => $locale,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $user = $this->getAuthedUser();
            $this->activityService->log(AdminCmsPageUpdated::TYPE, $user, [
                'cms_id' => $cms->getId(),
                'cms_slug' => $cms->getSlug(),
            ]);
            $this->entityActionDispatcher->dispatch(EntityAction::UpdateCms, $cms->getId());

            return $this->redirectToRoute('app_admin_cms_edit', [
                'id' => $cms->getId(),
                'locale' => $locale,
            ]);
        }

        $newBlocks = [
            Headline::getType(),
            Text::getType(),
            Gallery::getType(),
            Hero::getType(),
            EventTeaser::getType(),
            TrioCards::getType(),
            FactsRow::getType(),
        ];

        $linkedAnnouncement = $this->announcementRepo->findByCmsPage($cms->getId());

        return $this->render('admin/cms/cms_edit.html.twig', [
            'active' => 'cms',
            'newBlocks' => $newBlocks,
            'editLocale' => $locale,
            'blocks' => $this->blockRepo->getBlocks($cms->getId(), $locale),
            'form' => $form,
            'cms' => $cms,
            'linkedAnnouncement' => $linkedAnnouncement,
            'is_admin' => $isAdmin,
            'adminTop' => $this->buildEditTop($cms, $linkedAnnouncement, $isAdmin),
        ]);
    }

    private function buildEditTop(Cms $cms, ?Announcement $linkedAnnouncement, bool $isAdmin): AdminTop
    {
        $statusTag = $cms->isPublished()
            ? sprintf('<span class="tag is-success is-medium">%s</span>', htmlspecialchars(
                $this->translator->trans('admin_cms.published'),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ))
            : sprintf('<span class="tag is-light is-medium">%s</span>', htmlspecialchars(
                $this->translator->trans('admin_cms.draft'),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ));

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>/%s</strong>', htmlspecialchars(
                (string) $cms->getSlug(),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ))),
            new AdminTopInfoHtml($statusTag),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($cms->getBlocks()),
                $this->translator->trans('admin_cms.summary_blocks'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($cms->getLanguages()),
                $this->translator->trans('admin_cms.summary_languages'),
            )),
        ];

        $actions = [];
        if ($isAdmin) {
            if ($linkedAnnouncement !== null) {
                $actions[] = new AdminTopActionButton(
                    label: $this->translator->trans('admin_cms.button_open_announcement'),
                    target: $this->generateUrl('app_admin_email_announcements_view', [
                        'id' => $linkedAnnouncement->getId(),
                    ]),
                    icon: 'bullhorn',
                );
            } else {
                $actions[] = new AdminTopActionForm(
                    label: $this->translator->trans('admin_cms.button_create_announcement'),
                    target: $this->generateUrl('app_admin_email_announcements_from_cms', ['id' => $cms->getId()]),
                    csrfTokenId: 'admin_email_announcements_from_cms' . $cms->getId(),
                    icon: 'bullhorn',
                );
            }
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_cms'),
            icon: 'arrow-left',
        );

        return new AdminTop(info: $info, actions: $actions);
    }

    private function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException(
                'Should never happen, see: config/packages/security.yaml',
            );
        }

        return $user;
    }

    #[Route('/delete', name: 'app_admin_cms_delete', methods: ['POST'])]
    public function cmsDelete(Request $request): Response
    {
        $id = (int) $request->request->get('id');

        if (!$this->isCsrfTokenValid('admin_cms_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $cmsPage = $this->repo->find($id);
        if ($cmsPage !== null) {
            $this->denyAccessUnlessGranted(PermissionAttribute::CMS_PAGE_DELETE, $cmsPage);

            if (!$this->adminCmsListFilterService->isCmsAccessible($cmsPage->getId())) {
                $this->logAccessDenied($cmsPage, $request, 'delete');
                throw $this->createAccessDeniedException('This CMS page is not accessible in the current context');
            }

            $cmsId = $cmsPage->getId();
            $cmsSlug = $cmsPage->getSlug();
            $this->em->remove($cmsPage);
            $this->em->flush();

            $user = $this->getAuthedUser();
            $this->activityService->log(AdminCmsPageDeleted::TYPE, $user, ['cms_id' => $cmsId, 'cms_slug' => $cmsSlug]);
            $this->entityActionDispatcher->dispatch(EntityAction::DeleteCms, $cmsId);
        }

        return $this->redirectToRoute('app_admin_cms');
    }

    #[Route('/add', name: 'app_admin_cms_add', methods: ['POST'])]
    public function cmsAdd(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::CMS_PAGE_CREATE);

        $user = $this->getAuthedUser();

        $newPage = new Cms();
        $newPage->setSlug($request->request->all('cms')['slug']);
        $newPage->setPublished(false);
        $newPage->setLocked(false);
        $newPage->setCreatedBy($user);
        $newPage->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($newPage);
        $this->em->flush();

        $this->activityService->log(AdminCmsPageCreated::TYPE, $user, [
            'cms_id' => $newPage->getId(),
            'cms_slug' => $newPage->getSlug(),
        ]);
        $this->entityActionDispatcher->dispatch(EntityAction::CreateCms, $newPage->getId());

        return $this->redirectToRoute('app_admin_cms_edit', [
            'id' => $newPage->getId(),
            'locale' => $request->getLocale(),
        ]);
    }

    private function getLastEditLocale(?string $locale, SessionInterface $session): string
    {
        $lastEditLocaleKey = 'lastEditLocale';
        if ($locale === null) {
            $locale = $session->get($lastEditLocaleKey, $this->languageService->getAdminFilteredEnabledCodes()[0]);
        }
        $session->set($lastEditLocaleKey, $locale);

        return $locale;
    }

    private function logAccessDenied(Cms $cms, Request $request, string $action): void
    {
        $filterResult = $this->adminCmsListFilterService->getCmsIdFilter();
        $allowedIds = $filterResult->hasActiveFilter() ? $filterResult->getCmsIds() : null;

        $context = [
            'action' => $action,
            'cms_id' => $cms->getId(),
            'cms_slug' => $cms->getSlug(),
            'request_uri' => $request->getUri(),
            'request_host' => $request->getHost(),
            'user_id' => $this->getAuthedUser()->getId(),
            'allowed_cms_ids' => $allowedIds !== null ? array_slice($allowedIds, 0, 20) : 'all',
            'total_allowed' => $allowedIds !== null ? count($allowedIds) : null,
        ];

        $filterContext = $this->adminCmsListFilterService->getDebugContext($cms->getId());
        if ($filterContext !== []) {
            $context['filter_context'] = $filterContext;
        }

        $this->logger->warning('CMS page access denied', $context);
    }
}
