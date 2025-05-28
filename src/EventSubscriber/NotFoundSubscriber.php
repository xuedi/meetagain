<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\NotFoundLog;
use App\Service\CmsService;
use App\Service\SitemapService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

readonly class NotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CmsService $cms,
        private SitemapService $sitemapService,
        private RouterInterface $router,
        private EntityManagerInterface $em,
        private Environment $twig
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onKernelException', 32],
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof NotFoundHttpException) {
            $originalPath = $event->getRequest()->getPathInfo();
            $ip = $event->getRequest()->getClientIp() ?? '';
            $path = str_ends_with($originalPath, '.env') ? '.env' : $originalPath;

            $context = new RequestContext();
            $context->setParameter('_locale', 'en');
            $this->router->setContext($context); // language isn't set on event subscriber yet

            // try stuff and special cases before actual 404
            $content = match (trim($path, '/')) {
                '' => $this->redirectToFrontpage(),
                '.env' => $this->createFunEnvResponse(),
                'sitemap.xml' => $this->sitemapService->getContent('www.dragon-descendants.de'),
                default => $this->cms->createNotFoundPage(),
            };

            if ($content->getStatusCode() !== Response::HTTP_OK || $path === '.env') {
                $notFoundLog = new NotFoundLog();
                $notFoundLog->setCreatedAt(new DateTimeImmutable());
                $notFoundLog->setIp($ip);
                $notFoundLog->setUrl($originalPath);

                $this->em->persist($notFoundLog);
                $this->em->flush();
            }

            $event->allowCustomResponseCode();
            $event->setResponse($content);
            $event->stopPropagation();
        }
    }

    private function redirectToFrontpage(): RedirectResponse
    {
        $url = $this->router->generate('app_default');
        return new RedirectResponse($url)->setStatusCode(Response::HTTP_OK);
    }

    private function createFunEnvResponse(): Response
    {
        return new Response($this->twig->render('env_fun_file.txt.twig'), Response::HTTP_OK);
    }
}
