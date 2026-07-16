<?php declare(strict_types=1);

namespace Plugin\Films\Command;

use App\Enum\ImageType;
use App\Service\Media\ImageService;
use Plugin\Films\Repository\FilmRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'films:posters:rebuild-thumbnails', description: 'Regenerate poster thumbnails for every film. Used after introducing the size provider.')]
final class RebuildPosterThumbnailsCommand extends Command
{
    public function __construct(
        private readonly FilmRepository $filmRepo,
        private readonly ImageService $imageService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $films = $this->filmRepo->findAll();
        $built = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($films as $film) {
            $image = $film->getPosterImage();
            if ($image === null) {
                $skipped++;
                continue;
            }

            try {
                $this->imageService->createThumbnails($image, ImageType::PluginFilmsPoster);
                $built++;
                $io->writeln(sprintf('  built %s (#%d)', $film->getTitle(), $film->getId()));
            } catch (Throwable $e) {
                $failed++;
                $io->warning(sprintf('film #%d: %s', $film->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf('Built thumbnails for %d film(s); skipped %d (no poster); failed %d.', $built, $skipped, $failed));

        return Command::SUCCESS;
    }
}
