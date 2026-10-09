<?php declare(strict_types=1);

namespace App\Moderation;

use App\CronTaskInterface;
use App\Entity\User;
use App\Enum\CronTaskStatus;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Member\UserService;
use App\ValueObject\CronTaskResult;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final readonly class SuspensionExpiry implements CronTaskInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private UserService $userService,
        private ConfigService $config,
        private ClockInterface $clock,
    ) {}

    public function getIdentifier(): string
    {
        return 'suspension-expiry';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $expired = $this->userRepository->findExpiredSuspensions($this->clock->now());
            if ($expired === []) {
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, '0 lifted');
            }

            $systemUser = $this->userRepository->find($this->config->getSystemUserId());
            if (!$systemUser instanceof User) {
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::error, 'system user missing');
            }

            foreach ($expired as $user) {
                $this->userService->transitionStatus($systemUser, $user, UserStatus::Active);
            }
            $message = sprintf('%d lifted', count($expired));
            $output->writeln('SuspensionExpiry: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (Throwable $e) {
            $output->writeln('SuspensionExpiry exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }
}
