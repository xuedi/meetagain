<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\Host;
use App\Entity\User;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\HostsSection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class HostsSectionTest extends SectionTestCase
{
    public function testAHostSurvivesTheRoundTripWithItsMember(): void
    {
        // Arrange
        $source = $this->host(9, 'Anna', $this->user(5, 'anna@example.org'));
        $exported = $this->section([$this->eventWith($source)])->export(new Scope(users: [5 => 'user'], eventIds: [1]), $this->images());
        $context = $this->context();
        $member = new User();
        $context->mapRef(User::class, 'anna@example.org', $member);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $host = $this->onlyPersisted(Host::class);
        static::assertSame('Anna', $host->getName());
        static::assertSame($member, $host->getUser());
        static::assertSame($host, $context->resolveRef(Host::class, 9));
        static::assertSame(1, $context->toSummary()->get('hosts', Outcome::Created));
    }

    public function testAHostOfAMemberOutsideTheScopeTravelsWithoutTheUserLink(): void
    {
        // Arrange
        $source = $this->host(9, 'Anna', $this->user(5, 'anna@example.org'));

        // Act
        $rows = $this->section([$this->eventWith($source)])->export(new Scope(eventIds: [1]), $this->images());

        // Assert
        static::assertSame([['ref' => 9, 'name' => 'Anna', 'email' => null]], $rows);
    }

    public function testHostsSharedByEventsAreExportedOnceInIdOrder(): void
    {
        // Arrange
        $first = $this->host(3, 'Bo', null);
        $second = $this->host(9, 'Anna', null);
        $both = $this->eventWith($second);
        $both->addHost($first);

        // Act
        $rows = $this->section([$this->eventWith($second), $both])->export(new Scope(eventIds: [1, 2]), $this->images());

        // Assert
        static::assertSame([3, 9], array_column($rows, 'ref'));
    }

    public function testTheHostOfAKnownMemberIsMatched(): void
    {
        // Arrange
        $existing = new Host();
        $member = new User();
        $context = $this->context();
        $context->mapRef(User::class, 'anna@example.org', $member);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('findOneBy')->with(['user' => $member])->willReturn($existing);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        // Act
        new HostsSection($em)->import([['ref' => 9, 'name' => 'Anna', 'email' => 'anna@example.org']], $context);

        // Assert
        static::assertSame($existing, $context->resolveRef(Host::class, 9));
        static::assertSame(1, $context->toSummary()->get('hosts', Outcome::Matched));
    }

    public function testAHostWithoutAMemberIsMatchedByItsName(): void
    {
        // Arrange
        $existing = new Host();
        $context = $this->context();

        // Act
        $this->section([], $existing)->import([['ref' => 9, 'name' => 'Anna', 'email' => null]], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame($existing, $context->resolveRef(Host::class, 9));
        static::assertSame(1, $context->toSummary()->get('hosts', Outcome::Matched));
    }

    public function testTwoUnlinkedRowsOfOneNameShareTheHostCreatedForTheFirst(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section()->import([['ref' => 3, 'name' => 'Anna'], ['ref' => 9, 'name' => 'Anna']], $context);

        // Assert
        $host = $this->onlyPersisted(Host::class);
        static::assertSame($host, $context->resolveRef(Host::class, 9));
        static::assertSame(1, $context->toSummary()->get('hosts', Outcome::Created));
        static::assertSame(1, $context->toSummary()->get('hosts', Outcome::Matched));
    }

    public function testALongNameIsCutToTheColumnLength(): void
    {
        // Act
        $this->section()->import([['ref' => 1, 'name' => 'Grandmaster Wolfgang']], $this->context());

        // Assert
        static::assertSame('Grandmaster Wolf', $this->onlyPersisted(Host::class)->getName());
    }

    /**
     * @param list<Event> $events
     */
    private function section(array $events = [], ?Host $existing = null): HostsSection
    {
        return new HostsSection($this->entityManager($events, $existing));
    }

    private function host(int $id, string $name, ?User $user): Host
    {
        $host = $this->withId(new Host(), $id);
        $host->setName($name);
        $host->setUser($user);

        return $host;
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }

    private function eventWith(Host $host): Event
    {
        $event = new Event();
        $event->addHost($host);

        return $event;
    }
}
