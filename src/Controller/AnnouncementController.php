<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\Cms;
use App\Repository\AnnouncementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnnouncementController extends AbstractController
{
    public function __construct(private readonly AnnouncementRepository $announcementRepo)
    {
    }

    #[Route('/announcement/{hash}', name: 'app_announcement_redirect')]
    public function show(string $hash, Request $request): Response
    {
        $announcement = $this->announcementRepo->findByLinkHash($hash);
        $cmsPage = $announcement->getCmsPage();

        if (!$announcement instanceof Announcement || !$cmsPage instanceof Cms) {
            throw $this->createNotFoundException('Announcement not found');
        }

        $locale = $request->getLocale();
        $slug = $cmsPage->getSlug();

        return $this->redirect(sprintf('/%s/%s', $locale, $slug));
    }
}
