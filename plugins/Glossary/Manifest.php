<?php declare(strict_types=1);

namespace Plugin\Glossary;

use App\Plugin;
use Plugin\Glossary\Controller\EditController;
use Plugin\Glossary\Controller\IndexController;
use Symfony\Component\HttpFoundation\Request;

class Manifest implements Plugin
{
    public function getIdent(): string
    {
        return 'glossary';
    }

    public function getName(): string
    {
        return 'Glossary';
    }

    public function getVersion(): string
    {
        return '0.1';
    }

    public function getDescription(): string
    {
        return 'This allows users to add and maintain a multilingual glossary.';
    }

    public function install(): void
    {
        // TODO: Implement install() method.
    }

    public function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }

    public function handleRoute(Request $request): ?string
    {
        // just for fun

        $app = new App();
        $app->addController(IndexController::class);
        $app->addController(EditController::class);

        return $app->handleRoute($request);
    }
}
