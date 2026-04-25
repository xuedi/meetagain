<?php declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use DateTimeZone;
use Sentry\EventId;
use Sentry\SentrySdk;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            if (str_starts_with($request->getPathInfo(), '/api/')) {
                return new JsonResponse(['error' => $exception->getMessage()], $status);
            }

            $eventId = SentrySdk::getCurrentHub()->getLastEventId() ?? EventId::generate();

            return new Response($this->twig->render('cms/error.html.twig', [
                'errorId' => (string) $eventId,
                'occurredAt' => (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC')),
            ]), $status);
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
