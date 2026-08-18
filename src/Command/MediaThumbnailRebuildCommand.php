<?php declare(strict_types=1);

namespace App\Command;

use App\Enum\ImageType;
use App\Service\Media\ImageService;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:thumbnail-rebuild', description: 'Create missing thumbnails for every image, optionally limited to one image type')]
final class MediaThumbnailRebuildCommand extends Command
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Limit the run to one image type, by enum case name (e.g. PluginBooksCover)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $requestedType = $input->getOption('type');

        $only = null;
        if ($requestedType !== null) {
            $only = $this->resolveType((string) $requestedType);
            if ($only === null) {
                $io->error(sprintf('Unknown image type "%s".', $requestedType));
                $io->listing(array_map(static fn(ImageType $type): string => $type->name, ImageType::cases()));

                return Command::FAILURE;
            }
        }

        $created = $this->imageService->regenerateAllThumbnails($only);

        $io->success(
            $only === null
                ? sprintf('Created %d missing thumbnail(s) across all image types.', $created)
                : sprintf('Created %d missing thumbnail(s) for image type %s.', $created, $only->name),
        );

        return Command::SUCCESS;
    }

    private function resolveType(string $name): ?ImageType
    {
        foreach (ImageType::cases() as $type) {
            if (strcasecmp($type->name, $name) === 0) {
                return $type;
            }
        }

        return null;
    }
}
