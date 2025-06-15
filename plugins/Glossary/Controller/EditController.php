<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Symfony\Component\HttpFoundation\Request;

class EditController extends AbstractController
{
    public function edit(Request $request): string
    {
        return $this->render('edit.html.twig', [
            'id' => 2323,
        ]);
    }

    public function save(Request $request): string
    {
        return $this->render('save.html.twig');
    }

    public function getRoutes(): array
    {
        return [
            '/glossary/edit' => 'edit',
            '/glossary/save' => 'save',
        ];
    }
}
