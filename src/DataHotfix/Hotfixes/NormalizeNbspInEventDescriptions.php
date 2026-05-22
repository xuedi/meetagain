<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Repository\EventTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class NormalizeNbspInEventDescriptions implements DataHotfixInterface
{
    private const int BATCH_SIZE = 200;
    private const array SEARCH = ["\u{00A0}", '&nbsp;'];
    private const string REPLACE = ' ';

    public function __construct(
        private EventTranslationRepository $repository,
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_05_15_normalize_nbsp_in_event_translations';
    }

    #[Override]
    public function execute(): void
    {
        $i = 0;
        foreach ($this->repository->iterateAll() as $translation) {
            $title = (string) $translation->getTitle();
            $teaser = (string) $translation->getTeaser();
            $description = (string) $translation->getDescription();

            $newTitle = str_replace(self::SEARCH, self::REPLACE, $title);
            $newTeaser = str_replace(self::SEARCH, self::REPLACE, $teaser);
            $newDescription = str_replace(self::SEARCH, self::REPLACE, $description);

            if ($newTitle === $title && $newTeaser === $teaser && $newDescription === $description) {
                continue;
            }

            $translation->setTitle($newTitle);
            $translation->setTeaser($newTeaser);
            $translation->setDescription($newDescription);

            if ((++$i % self::BATCH_SIZE) === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }
        $this->em->flush();
    }
}
