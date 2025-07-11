<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;

abstract class AbstractGlossaryController extends AbstractSymfonyController
{
    public function __construct(protected GlossaryService $service)
    {
        //
    }

    protected function renderList(string $template, array $parameter = [])
    {
        return $this->render($template, [
            'list' => $this->service->getList(),
            ...$parameter
        ]);
    }
}
