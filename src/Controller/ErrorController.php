<?php declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use DateTimeZone;
use Sentry\EventId;
use Sentry\SentrySdk;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Twig\Environment;

final class ErrorController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function show(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof NotFoundHttpException) {
            return new Response($this->twig->render('cms/404.html.twig', [
                '_locale' => $request->getLocale(),
                'message' => 'cms.error_404_default_message',
            ]), Response::HTTP_NOT_FOUND);
        }

        $eventId = SentrySdk::getCurrentHub()->getLastEventId() ?? EventId::generate();
        $errorId = (string) $eventId;
        $occurredAt = (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));

        return new Response($this->twig->render('cms/error.html.twig', [
            'errorId' => $errorId,
            'occurredAt' => $occurredAt,
        ]), Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
