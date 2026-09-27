<?php declare(strict_types=1);

namespace Module\Trust\Tests\Functional;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\TrustAction;
use Module\Trust\Contract\TrustInterface;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Tests\Stub\AccessProvider;
use Module\Trust\Tests\Stub\ActionSource;
use Module\Trust\Tests\Stub\ContextDescriber;
use Module\Trust\Tests\Stub\RootProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Module\Members;

final class StubConsumerTest extends KernelTestCase
{
    private const string CONTEXT = ContextDescriber::CONTEXT;
    private const string TENURE = 'stub_tenure';
    private const string UNDECLARED = 'stub_never_declared';
    private const int HANDOVERS = 4;
    private const int TENURE_MONTHS = 40;
    private const int TENURE_CAP = 24;

    private User $root;

    private User $earner;

    private User $newcomer;

    private TrustInterface $trust;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $members = new Members($container->get(EntityManagerInterface::class));
        $this->root = $members->member('Root');
        $this->earner = $members->member('Earner');
        $this->newcomer = $members->member('Newcomer');

        $container->get(RootProvider::class)->rootUserId = $this->root->getId();
        $container->get(AccessProvider::class)->administratorId = $this->root->getId();
        $source = $container->get(ActionSource::class);
        $source->descriptors = [
            new ActionDescriptor(ActionSource::HANDOVER, 'trust_stub.action_handover', ActionSource::POINTS),
            new ActionDescriptor(self::TENURE, 'trust_stub.action_tenure', 1, self::TENURE_CAP),
        ];
        $earnerId = (int) $this->earner->getId();
        for ($i = 1; $i <= self::HANDOVERS; $i++) {
            $source->actions[] = new TrustAction($earnerId, ActionSource::HANDOVER, new DateTimeImmutable('2026-01-0' . $i));
        }
        $source->actions[] = new TrustAction($earnerId, self::TENURE, new DateTimeImmutable('2026-01-01'), self::TENURE_MONTHS);
        $source->actions[] = new TrustAction($earnerId, self::UNDECLARED, new DateTimeImmutable('2026-01-01'));

        $this->trust = $container->get(TrustInterface::class);
    }

    public function testAVouchLiftsAMemberOverTheParticipationMinimum(): void
    {
        // Arrange
        $this->configure(['minimumToParticipate' => 200]);
        $newcomerId = (int) $this->newcomer->getId();

        // Act
        $before = $this->trust->meetsMinimum(self::CONTEXT, $newcomerId);
        $this->trust->grant(self::CONTEXT, (int) $this->root->getId(), $newcomerId, TrustLevel::Absolute);
        $after = $this->trust->meetsMinimum(self::CONTEXT, $newcomerId);

        // Assert
        self::assertFalse($before);
        self::assertTrue($after);
        self::assertSame(500, $this->trust->getScore(self::CONTEXT, $newcomerId));
    }

    public function testAQuantityCapKeepsTenureFromRunningAway(): void
    {
        // Arrange
        $capped = $this->trust->getScore(self::CONTEXT, (int) $this->earner->getId());

        // Act
        $this->configure(['capsPerAction' => [self::TENURE => self::TENURE_MONTHS]]);
        $uncapped = $this->trust->getScore(self::CONTEXT, (int) $this->earner->getId());

        // Assert
        self::assertSame(self::handoverPoints() + self::TENURE_CAP, $capped);
        self::assertSame(self::handoverPoints() + self::TENURE_MONTHS, $uncapped);
    }

    public function testActionPointsAloneProduceAScore(): void
    {
        // Act
        $score = $this->trust->getScore(self::CONTEXT, (int) $this->earner->getId());

        // Assert
        self::assertSame(self::handoverPoints() + self::TENURE_CAP, $score);
    }

    public function testAnUndeclaredActionIsIgnoredAndReported(): void
    {
        // Arrange
        $tester = new CommandTester(new Application(self::$kernel)->find('app:trust:rebuild'));

        // Act
        $exitCode = $tester->execute(['--context' => self::CONTEXT]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(self::UNDECLARED, $tester->getDisplay());
    }

    public function testRaisingThePointsPerHandoverMovesEverybodyWhoEverEarned(): void
    {
        // Arrange
        $before = $this->trust->getScore(self::CONTEXT, (int) $this->earner->getId());

        // Act
        $this->configure(['pointsPerAction' => [ActionSource::HANDOVER => 50]]);
        $after = $this->trust->getScore(self::CONTEXT, (int) $this->earner->getId());

        // Assert
        self::assertSame(self::handoverPoints() + self::TENURE_CAP, $before);
        self::assertSame((self::HANDOVERS * 50) + self::TENURE_CAP, $after);
    }

    public function testTwoContextsScoreTheSameMembersIndependently(): void
    {
        // Act
        $described = $this->trust->getScore(self::CONTEXT, (int) $this->earner->getId());
        $undescribed = $this->trust->getScore('nobody-describes-this', (int) $this->earner->getId());

        // Assert
        self::assertGreaterThan(0, $described);
        self::assertSame(0, $undescribed);
    }

    public function testAMemberNeverSeesAnotherMembersOutgoingVouches(): void
    {
        // Arrange
        $newcomerId = (int) $this->newcomer->getId();
        $this->trust->grant(self::CONTEXT, (int) $this->root->getId(), $newcomerId, TrustLevel::Trusted);

        // Act
        $ownEdges = $this->trust->getOutgoing(self::CONTEXT, (int) $this->earner->getId());

        // Assert
        self::assertSame([], $ownEdges);
        self::assertSame(1, $this->trust->getVouchCount(self::CONTEXT, $newcomerId));
    }

    public function testTheTableOffersAVouchControlForEveryOtherMember(): void
    {
        // Arrange
        $container = self::getContainer();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get(RequestStack::class)->push($request);
        $container->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($this->root, 'main', $this->root->getRoles()));

        // Act
        $html = $container->get('twig')->createTemplate('{{ trust_table(context) }}')->render(['context' => self::CONTEXT]);

        // Assert
        $vouchTargets = new Crawler($html)
            ->filter('form.trust-vouch input[name="user"]')
            ->extract(['value']);
        self::assertContains((string) $this->earner->getId(), $vouchTargets);
        self::assertNotContains((string) $this->root->getId(), $vouchTargets);
    }

    private static function handoverPoints(): int
    {
        return self::HANDOVERS * ActionSource::POINTS;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function configure(array $payload): void
    {
        $container = self::getContainer();
        $container->get(Connection::class)->insert(
            'mod_trust_context_config',
            ['context' => self::CONTEXT, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => new DateTimeImmutable()],
            ['updated_at' => Types::DATETIME_IMMUTABLE],
        );
        $container->get('services_resetter')->reset();
    }
}
