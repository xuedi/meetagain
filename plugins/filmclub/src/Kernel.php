<?php declare(strict_types=1);

namespace Plugin\Filmclub;

use App\Entity\Link;
use App\Plugin;
use Plugin\Filmclub\Repository\VoteRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly VoteRepository $voteRepository,
        private readonly Environment $twig,
    ) {
    }

    public function getPluginKey(): string
    {
        return 'filmclub';
    }

    public function getMenuLinks(): array
    {
        return [
            new Link(
                slug: $this->urlGenerator->generate('app_filmclub_filmlist'),
                name: 'Filme',
            ),
            new Link(
                slug: $this->urlGenerator->generate('app_filmclub_vote'),
                name: 'Vote',
            )
        ];
    }

    public function getEventTile(int $eventId): ?string
    {
        $vote = $this->voteRepository->findByEventId($eventId);
        if ($vote === null) {
            return null;
        }

        return $this->twig->render('@Filmclub/tile/event.html.twig', [
            'vote' => $vote,
        ]);
    }
}
