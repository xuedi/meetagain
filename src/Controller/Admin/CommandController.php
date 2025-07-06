<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CommandEnum;
use App\Service\CommandService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommandController extends AbstractController
{
    #[Route('/admin/command/', name: 'app_admin_command')]
    public function index(): Response
    {
        return $this->render('admin/command/index.html.twig', [
            'commands' => CommandEnum::getCommands(),
            'active' => 'command',
        ]);
    }

    #[Route('/admin/command/execute/{command}', name: 'app_admin_command_execute')]
    public function execute(CommandService $service, string $command): Response
    {
        $output = match ($command) {
            CommandEnum::clearCache->name => $service->clearCache(),
            CommandEnum::executeMigrations->name => $service->executeMigrations(),
            CommandEnum::extractTranslations->name => $service->extractTranslations(),
            default => throw new InvalidArgumentException('Unknown command: ' . $command),
        };

        return $this->render('admin/command/execute.html.twig', [
            'active' => 'command',
            'command' => $command,
            'output' => $output,
        ]);
    }
}
