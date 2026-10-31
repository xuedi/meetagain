<?php declare(strict_types=1);

namespace Module\Task\Internal\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Internal\Action\RequestState;
use Module\Task\Internal\Repository\ActionRequestRepository;

#[ORM\Entity(repositoryClass: ActionRequestRepository::class)]
#[ORM\Table(name: 'mod_task_action_request')]
#[ORM\Index(name: 'idx_task_action_request_identifier', columns: ['identifier', 'id'])]
#[ORM\Index(name: 'idx_task_action_request_state', columns: ['state', 'created_at'])]
class ActionRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, enumType: RequestState::class)]
    private RequestState $state = RequestState::Pending;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $leaseUntil = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    #[ORM\Column(length: 16, nullable: true, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    /**
     * @param array<string, string|int|bool|null> $values
     */
    public function __construct(
        #[ORM\Column(length: 120)]
        private string $identifier,
        #[ORM\Column(name: 'action_values', type: Types::JSON)]
        private array $values,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $requestedBy,
        #[ORM\Column]
        private DateTimeImmutable $createdAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function getRequestedBy(): ?string
    {
        return $this->requestedBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getState(): RequestState
    {
        return $this->state;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getLeaseUntil(): ?DateTimeImmutable
    {
        return $this->leaseUntil;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function start(DateTimeImmutable $now, DateTimeImmutable $leaseUntil): void
    {
        $this->state = RequestState::Running;
        $this->startedAt = $now;
        $this->leaseUntil = $leaseUntil;
    }

    public function finish(Result $result, DateTimeImmutable $now): void
    {
        $this->state = RequestState::Finished;
        $this->finishedAt = $now;
        $this->leaseUntil = null;
        $this->status = $result->status;
        $this->message = $result->message;
    }
}
