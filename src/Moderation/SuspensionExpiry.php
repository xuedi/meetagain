<?php declare(strict_types=1);

namespace App\Moderation;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Member\UserService;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class SuspensionExpiry implements TaskInterface
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

    public function getCadence(): Cadence
    {
        return new Cadence('* * * * *');
    }

    public function getDescription(): string
    {
        return 'Lifts temporary suspensions that have run out. Every member whose suspension end date has passed is set back to active in the name of the system user, so the change is logged like any other status change. It runs every minute so a suspension ends close to the moment the moderator chose.';
    }

    public function run(): Result
    {
        try {
            $expired = $this->userRepository->findExpiredSuspensions($this->clock->now());
            if ($expired === []) {
                return new Result(Status::ok, '0 lifted');
            }

            $systemUser = $this->userRepository->find($this->config->getSystemUserId());
            if (!$systemUser instanceof User) {
                return new Result(Status::error, 'system user missing');
            }

            foreach ($expired as $user) {
                $this->userService->transitionStatus($systemUser, $user, UserStatus::Active);
            }
            $message = sprintf('%d lifted', count($expired));

            return new Result(Status::ok, $message);
        } catch (Throwable $e) {
            return new Result(Status::exception, $e->getMessage());
        }
    }
}
