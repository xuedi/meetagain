<?php declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use DateTimeZone;
use Sentry\EventId;
use Sentry\SentrySdk;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
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
            return new Response($this->twig->render('error/404.html.twig', [
                '_locale' => $request->getLocale(),
                'message' => 'error.404_default_message',
            ]), Response::HTTP_NOT_FOUND);
        }

        if ($exception instanceof AccessDeniedHttpException || $exception instanceof AccessDeniedException) {
            return new Response($this->twig->render('error/403.html.twig', [
                '_locale' => $request->getLocale(),
            ]), Response::HTTP_FORBIDDEN);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            if (str_starts_with($request->getPathInfo(), '/api/')) {
                return new JsonResponse(['error' => $exception->getMessage()], $status);
            }

            $eventId = SentrySdk::getCurrentHub()->getLastEventId() ?? EventId::generate();

            return new Response($this->twig->render('error/500.html.twig', [
                'errorId' => (string) $eventId,
                'occurredAt' => (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC')),
            ]), $status);
        }

        $eventId = SentrySdk::getCurrentHub()->getLastEventId() ?? EventId::generate();
        $errorId = (string) $eventId;
        $occurredAt = (new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));

        return new Response($this->twig->render('error/500.html.twig', [
            'errorId' => $errorId,
            'occurredAt' => $occurredAt,
        ]), Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
