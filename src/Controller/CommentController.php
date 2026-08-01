<?php declare(strict_types=1);

namespace App\Controller;

use App\Comment\CommentService;
use App\Comment\InvalidContentException;
use App\Comment\TargetProviderInterface;
use App\Comment\TargetRegistry;
use App\Repository\CommentRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentService $commentService,
        private readonly TargetRegistry $registry,
        private readonly CommentRepository $comments,
    ) {}

    #[Route(
        '/comment/{targetType}/{targetId}',
        name: 'app_comment_create',
        requirements: ['targetType' => '[a-z_]+', 'targetId' => '\d+'],
        methods: ['POST'],
    )]
    public function create(Request $request, string $targetType, int $targetId): Response
    {
        $this->guardCsrf($request, 'app_comment_create' . $targetType . $targetId);
        $provider = $this->resolveProvider($targetType);
        $returnUrl = $provider->getReturnUrl($targetId);
        if ($returnUrl === null) {
            throw $this->createNotFoundException();
        }

        if (!$provider->canComment($targetId)) {
            $this->addFlash('warning', 'comment.flash_not_allowed');

            return $this->redirect($returnUrl);
        }

        try {
            $this->commentService->create($targetType, $targetId, $this->getAuthedUser(), (string) $request->request->get('content', ''));
        } catch (InvalidContentException $exception) {
            $this->addFlash(
                'error',
                $exception->reason === InvalidContentException::REASON_TOO_LONG ? 'comment.flash_too_long' : 'comment.flash_empty',
            );

            return $this->redirect($returnUrl);
        }

        $this->addFlash('success', 'comment.flash_created');

        return $this->redirect($returnUrl);
    }

    #[Route(
        '/comment/{targetType}/{targetId}/delete/{id}',
        name: 'app_comment_delete',
        requirements: ['targetType' => '[a-z_]+', 'targetId' => '\d+', 'id' => '\d+'],
        methods: ['POST'],
    )]
    public function delete(Request $request, string $targetType, int $targetId, int $id): Response
    {
        $this->guardCsrf($request, 'app_comment_delete' . $id);
        $provider = $this->resolveProvider($targetType);
        $returnUrl = $provider->getReturnUrl($targetId);
        if ($returnUrl === null) {
            throw $this->createNotFoundException();
        }

        $comment = $this->comments->find($id);
        if ($comment === null || $comment->getTargetType() !== $targetType || $comment->getTargetId() !== $targetId) {
            throw $this->createNotFoundException();
        }

        if (!$this->commentService->canDelete($comment, $this->getAuthedUser())) {
            $this->addFlash('warning', 'comment.flash_not_allowed');

            return $this->redirect($returnUrl);
        }

        $this->commentService->delete($comment);
        $this->addFlash('success', 'comment.flash_deleted');

        return $this->redirect($returnUrl);
    }

    private function resolveProvider(string $targetType): TargetProviderInterface
    {
        $provider = $this->registry->providerFor($targetType);
        if ($provider === null) {
            throw $this->createNotFoundException();
        }

        return $provider;
    }

    private function guardCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }
}
