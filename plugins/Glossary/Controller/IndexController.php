<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Symfony\Component\HttpFoundation\Request;

class IndexController extends AbstractController
{
    public function list(Request $request): string
    {
        return $this->render('index.html.twig');
    }

    public function details(Request $request): string
    {
        return $this->render('index.html.twig', [
            'id' => 1223, // get from request
        ]);
    }

    public function getRoutes(): array
    {
        return [
            '/glossary' => 'list',
            '/glossary/details' => 'details',
        ];
    }
}
