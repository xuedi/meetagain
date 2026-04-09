<?php declare(strict_types=1);

namespace App\Command;

use App\AssetMapper\OpaqueMediaPathResolver;
use MatthiasMullie\Minify\JS;
use Override;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:media:compile', description: 'Flatten compiled assets into public/media/ and bundle global JS')]
final class MediaCompileCommand extends Command
{
    /**
     * Global JS bundle — loaded on every page via base.html.twig in prod.
     * Order is significant: ma-fetch must be first (others depend on maFetch).
     * Matches the per-file load order used in dev.
     */
    private const array GLOBAL_JS_BUNDLE = [
        'js/ma-fetch.js',
        'js/notifications.js',
        'js/navbar.js',
        'js/toggles.js',
        'js/cookie-consent.js',
        'js/modal.js',
        'js/block-user.js',
    ];

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
            $rawExt = pathinfo($asset->logicalPath, PATHINFO_EXTENSION) ?: 'bin';
            $ext = match ($rawExt) {
                'scss', 'sass' => 'css',
                'mjs'          => 'js',
                default        => $rawExt,
            };
            $hash = OpaqueMediaPathResolver::hashLogicalPath($asset->logicalPath);
            $filename = "$hash.$ext";

            if (isset($seen[$filename])) {
                throw new \RuntimeException("Hash collision detected: $filename");
            }
            $seen[$filename] = true;

            $content = $asset->content ?? file_get_contents($asset->sourcePath);
            $content = $this->minify($content, $ext);
            file_put_contents("$target/$filename", $content);
            $count++;
        }

        $output->writeln("Wrote $count media files to $target");

        $this->bundleGlobalJs($target, $output);

        return Command::SUCCESS;
    }

    private function bundleGlobalJs(string $target, OutputInterface $output): void
    {
        $minifier = new JS();

        foreach (self::GLOBAL_JS_BUNDLE as $logicalPath) {
            $asset = $this->assetMapper->getAsset($logicalPath);
            if ($asset === null) {
                throw new \RuntimeException("Global JS bundle: asset '$logicalPath' not found in AssetMapper.");
            }
            $content = $asset->content ?? file_get_contents($asset->sourcePath);
            $minifier->add($content);
        }

        file_put_contents("$target/app.js", $minifier->minify());
        $output->writeln('Wrote global JS bundle to public/media/app.js');
    }

    private function minify(string $content, string $ext): string
    {
        return match ($ext) {
            'js'    => (new JS())->add($content)->minify(),
            default => $content,
        };
    }
}
