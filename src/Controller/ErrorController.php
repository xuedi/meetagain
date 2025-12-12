<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Twig\Environment;

class ErrorController extends AbstractController
{
    public function __construct(private readonly \Twig\Environment $twig)
    {
    }
    public function show(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof NotFoundHttpException) {
            return new Response($this->twig->render('cms/404.html.twig', [
                '_locale' => $request->getLocale(),
                'message' => "These aren't the droids you're looking for!",
            ]), Response::HTTP_NOT_FOUND);
        }

        return new Response($this->twig->render('cms/error.html.twig', [
            'CorrelationID' => md5($exception->getTraceAsString()),
        ]), Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
