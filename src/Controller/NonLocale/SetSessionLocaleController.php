<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Service\Config\LanguageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sets the preferred locale in the session and redirects to `/`.
 *
 * Used by the session-mode language selector on the frontpage where the
 * path-rewrite selector (which swaps the `/{_locale}/` segment) does not
 * apply because the request URL has no locale segment to swap.
 *
 * Independent from `IndexController::setLanguage` / `app_default_language`,
 * which is left untouched.
 */
final class SetSessionLocaleController extends AbstractController
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[Route('/_locale/{locale}', name: 'app_set_session_locale', requirements: ['locale' => '[a-z]{2}'])]
    public function index(Request $request, string $locale): Response
    {
        if (!in_array($locale, $this->languageService->getEnabledCodes(), true)) {
            throw new NotFoundHttpException();
        }

        $request->getSession()->set('_locale', $locale);

        return new RedirectResponse('/');
    }
}
