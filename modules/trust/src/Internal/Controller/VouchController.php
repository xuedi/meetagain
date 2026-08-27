<?php declare(strict_types=1);

namespace Module\Trust\Internal\Controller;

use App\Controller\AbstractController;
use InvalidArgumentException;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\AccessResolver;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\VouchService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER'), Route('/trust')]
final class VouchController extends AbstractController
{
    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly AccessResolver $accessResolver,
        private readonly VouchService $vouchService,
    ) {}

    #[Route('/vouch', name: 'app_trust_vouch', methods: ['POST'])]
    public function vouch(Request $request): RedirectResponse
    {
        $targetId = $request->request->getInt('user');
        if (!$this->isCsrfTokenValid('trust_vouch' . $targetId, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $context = $request->request->getString('context');
        $descriptor = $this->registry->describe($context);
        if ($descriptor === null) {
            throw $this->createNotFoundException();
        }

        $viewerId = $this->getAuthedUser()->getId();
        if ($viewerId === null || !$this->accessResolver->canView($context, $viewerId)) {
            throw $this->createAccessDeniedException();
        }

        $level = TrustLevel::tryFrom($request->request->getString('level'));

        try {
            if ($level === null) {
                $this->vouchService->revoke($context, $viewerId, $targetId);
            } else {
                $this->vouchService->grant($context, $viewerId, $targetId, $level);
            }
        } catch (InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid vouch.');
        }

        return $this->redirect($descriptor->returnUrl ?? $request->headers->get('referer') ?? '/');
    }
}
