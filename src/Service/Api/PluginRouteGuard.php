<?php declare(strict_types=1);

namespace App\Service\Api;

use App\Service\Config\ActivePluginListInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class PluginRouteGuard
{
    public function __construct(
        private ActivePluginListInterface $pluginService,
    ) {}

    public function requireActive(string $pluginKey): void
    {
        if (!in_array($pluginKey, $this->pluginService->getActiveList(), true)) {
            throw new NotFoundHttpException();
        }
    }

    public function isActive(string $pluginKey): bool
    {
        return in_array($pluginKey, $this->pluginService->getActiveList(), true);
    }
}
