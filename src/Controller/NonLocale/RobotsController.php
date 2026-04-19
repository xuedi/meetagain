<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RobotsController extends AbstractController
{
    private const string CONTENT_SIGNAL = 'search=yes, ai-train=no, ai-input=no';

    /**
     * Named AI-crawler user-agents that should see their own block in robots.txt.
     * List curated April 2026 - excludes deprecated names like Claude-Web and anthropic-ai.
     */
    private const array AI_CRAWLERS = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'Google-Extended',
        'Applebot-Extended',
        'Amazonbot',
        'Bytespider',
        'CCBot',
        'PerplexityBot',
        'Perplexity-User',
    ];

    #[Route('/robots.txt', name: 'app_robots')]
    public function index(Request $request): Response
    {
        $sitemapUrl = $request->getSchemeAndHttpHost() . '/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Disallow: /api/',
            'Content-Signal: ' . self::CONTENT_SIGNAL,
            '',
        ];

        foreach (self::AI_CRAWLERS as $agent) {
            $lines[] = 'User-agent: ' . $agent;
            $lines[] = 'Allow: /';
            $lines[] = 'Content-Signal: ' . self::CONTENT_SIGNAL;
            $lines[] = '';
        }

        $lines[] = 'Sitemap: ' . $sitemapUrl;

        return new Response(
            implode("\n", $lines) . "\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain'],
        );
    }
}
