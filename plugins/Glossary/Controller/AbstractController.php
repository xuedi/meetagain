<?php

namespace Plugin\Glossary\Controller;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

abstract class AbstractController
{
    protected Environment $twig;

    public function __construct()
    {
        $pluginPath = __DIR__ . '/../';
        $loader = new FilesystemLoader($pluginPath . 'Templates/');
        $this->twig = new Environment($loader, [
            'cache' => $pluginPath . 'Cache/',
        ]);
    }

    public function render(string $template, array $options = []): string
    {
        return $this->twig->render($template, $options);
    }
}