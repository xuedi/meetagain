<?php declare(strict_types=1);

namespace App\Controller;

use App\AssetMapper\MediaMap;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends AbstractController
{
    #[Route(
        '/media/{hash}.{ext}',
        name: 'media_serve',
        requirements: ['hash' => '[a-f0-9]{16}', 'ext' => '[a-z0-9]+'],
        methods: ['GET'],
    )]
    public function serve(
        string $hash,
        string $ext,
        MediaMap $mediaMap,
        AssetMapperInterface $assetMapper,
    ): Response {
        $map = $mediaMap->build();
        $logicalPath = $map[$hash] ?? throw new NotFoundHttpException("Unknown media hash: $hash");

        $asset = $assetMapper->getAsset($logicalPath)
            ?? throw new NotFoundHttpException("Asset disappeared: $logicalPath");

        // Use $asset->content when available: it contains AssetMapper-processed output
        // (e.g. CSS with url() references rewritten to /media/ hashes). Falling back to
        // BinaryFileResponse($asset->sourcePath) would serve raw SCSS or unprocessed content.
        if ($asset->content !== null) {
            return new Response(
                $asset->content,
                200,
                [
                    'Content-Type'  => $this->guessMime($ext),
                    'Cache-Control' => 'public, max-age=5',
                ],
            );
        }

        $response = new BinaryFileResponse($asset->sourcePath);
        $response->headers->set('Content-Type', $this->guessMime($ext));
        $response->setMaxAge(5);
        $response->setPublic();
        return $response;
    }

    private function guessMime(string $ext): string
    {
        return match ($ext) {
            'png'   => 'image/png',
            'jpg',
            'jpeg'  => 'image/jpeg',
            'webp'  => 'image/webp',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            default => 'application/octet-stream',
        };
    }
}
