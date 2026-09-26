<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\Security\CaptchaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;

final class CaptchaController extends AbstractController
{
    private const array REFRESHABLE_FORMS = ['app_register', 'app_reset', 'app_contact', 'app_login', 'app_report_item'];

    public function __construct(
        private readonly CaptchaService $captchaService,
    ) {}

    #[Route('/captcha/refresh/{context}', name: 'app_captcha_refresh', methods: ['POST'])]
    public function refresh(Request $request, string $context): Response
    {
        if (!in_array($context, self::REFRESHABLE_FORMS, true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('captcha_refresh' . $context, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->captchaService->reset($context);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'image' => $this->captchaService->generate($context),
                'count' => $this->captchaService->getRefreshCount(),
                'next' => $this->captchaService->getRefreshTime(),
                'expiries' => $this->captchaService->getRefreshExpiries(),
            ]);
        }

        $routeParams = array_filter($request->request->all('route'), is_string(...));
        try {
            return $this->redirectToRoute($context, $routeParams);
        } catch (RoutingException) {
            throw new BadRequestHttpException('Invalid route parameters.');
        }
    }
}
