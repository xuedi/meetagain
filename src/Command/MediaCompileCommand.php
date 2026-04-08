<?php declare(strict_types=1);

namespace App\Command;

use App\AssetMapper\OpaqueMediaPathResolver;
use Override;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:media:compile', description: 'Flatten compiled assets into public/media/')]
final class MediaCompileCommand extends Command
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly string $kernelProjectDir,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $this->kernelProjectDir . '/public/media';
        $filesystem = new Filesystem();
        $filesystem->remove($target);
        $filesystem->mkdir($target);

        $count = 0;
        $seen = [];

        foreach ($this->assetMapper->allAssets() as $asset) {
            $ext = pathinfo($asset->logicalPath, PATHINFO_EXTENSION) ?: 'bin';
            if (in_array($ext, ['js', 'mjs', 'map'], true)) {
                continue;
            }

            $hash = OpaqueMediaPathResolver::hashLogicalPath($asset->logicalPath);
            $filename = "$hash.$ext";

            if (isset($seen[$filename])) {
                throw new \RuntimeException("Hash collision detected: $filename");
            }
            $seen[$filename] = true;

            $content = $asset->content ?? file_get_contents($asset->sourcePath);
            file_put_contents("$target/$filename", $content);
            $count++;
        }

        $output->writeln("Wrote $count media files to $target");
        return Command::SUCCESS;
    }
}
