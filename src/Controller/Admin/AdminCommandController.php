<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CommandEnum;
use App\Service\CommandService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Stopwatch\Stopwatch;

#[Route('/admin/command/')]
class AdminCommandController extends AbstractController
{
    #[Route('', name: 'app_admin_command')]
    public function index(): Response
    {
        return $this->render('admin/command/index.html.twig', [
            'commands' => CommandEnum::getCommands(),
            'active' => 'command',
        ]);
    }

    #[Route('/execute/{command}', name: 'app_admin_command_execute')]
    public function execute(CommandService $service, string $command): Response
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('executeCommand');

        $output = match ($command) {
            CommandEnum::clearCache->name => $service->clearCache(),
            CommandEnum::executeMigrations->name => $service->executeMigrations(),
            CommandEnum::extractTranslations->name => $service->extractTranslations(),
            default => throw new InvalidArgumentException('Unknown command: ' . $command),
        };

        return $this->render('admin/command/execute.html.twig', [
            'active' => 'command',
            'executionTime' => (string)$stopwatch->stop('executeCommand'),
            'command' => $command,
            'output' => $output,
        ]);
    }
}
