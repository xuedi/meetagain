<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\NotFoundLog;
use App\Service\CmsService;
use App\Service\SitemapService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

readonly class NotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CmsService $cms,
        private SitemapService $sitemapService,
        private RouterInterface $router,
        private EntityManagerInterface $em,
    ) {
    }

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
            $path = $event->getRequest()->getPathInfo();
            $ip = $event->getRequest()->getClientIp() ?? '';

            $notFoundLog = new NotFoundLog();
            $notFoundLog->setCreatedAt(new DateTimeImmutable());
            $notFoundLog->setIp($ip);
            $notFoundLog->setUrl($path);

            $this->em->persist($notFoundLog);
            $this->em->flush();

            $context = new RequestContext();
            $context->setParameter('_locale', 'en');
            $this->router->setContext($context); // language not set on event subscriber yet

            // Special pages
            $content = match (trim($path, '/')) {
                'sitemap.xml' => $this->sitemapService->getContent('www.dragon-descendants.de'),
                default => $this->cms->createNotFoundPage(),
            };
            $event->setResponse($content);
            $event->stopPropagation();
        }
    }
}
