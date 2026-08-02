<?php declare(strict_types=1);

namespace App\Twig;

use App\Comment\CommentService;
use App\Comment\TargetRegistry;
use App\Entity\Comment;
use Override;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CommentExtension extends AbstractExtension
{
    public const string SECTION_TEMPLATE = '_components/comment/section.html.twig';

    public function __construct(
        private readonly CommentService $commentService,
        private readonly TargetRegistry $registry,
        private readonly Environment $twig,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('comment_section', $this->section(...), ['is_safe' => ['html']]),
        ];
    }

    public function section(string $targetType, int $targetId, bool $showHeading = true): string
    {
        $provider = $this->registry->providerFor($targetType);
        if ($provider === null) {
            return '';
        }

        return $this->twig->render(self::SECTION_TEMPLATE, [
            'targetType' => $targetType,
            'targetId' => $targetId,
            'comments' => $this->commentService->getFor($targetType, $targetId),
            'canComment' => $provider->canComment($targetId),
            'maxLength' => Comment::MAX_CONTENT_LENGTH,
            'showHeading' => $showHeading,
        ]);
    }
}
