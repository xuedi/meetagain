<?php declare(strict_types=1);

namespace Module\Trust\Tests\Functional;

use Module\Trust\Contract\TrustConfig;
use Module\Trust\Contract\TrustInterface;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\ConfigStore;
use Module\Trust\Internal\ScoreProvider;
use Module\Trust\Tests\Stub\ActionSource;
use Module\Trust\Tests\Stub\ContextDescriber;
use Module\Trust\Tests\Stub\UserLocator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StubConsumerTest extends WebTestCase
{
    private const string PASSWORD = '1234';
    private const string CONTEXT = ContextDescriber::CONTEXT;

    public function testAVouchLiftsAMemberOverTheParticipationMinimum(): void
    {
        // Arrange
        static::createClient();
        $locator = static::getContainer()->get(UserLocator::class);
        $trust = static::getContainer()->get(TrustInterface::class);
        $rootId = (int) $locator->idFor(UserLocator::ROOT_EMAIL);
        $newcomerId = (int) $locator->idFor(UserLocator::NEWCOMER_EMAIL);
        static::getContainer()->get(ConfigStore::class)->save(self::CONTEXT, new TrustConfig(minimumToParticipate: 200));
        static::getContainer()->get(ScoreProvider::class)->invalidate(self::CONTEXT);

        // Act
        $before = $trust->meetsMinimum(self::CONTEXT, $newcomerId);
        $trust->grant(self::CONTEXT, $rootId, $newcomerId, TrustLevel::Absolute);
        $after = $trust->meetsMinimum(self::CONTEXT, $newcomerId);

        // Assert
        static::assertFalse($before);
        static::assertTrue($after);
        static::assertSame(500, $trust->getScore(self::CONTEXT, $newcomerId));
    }

    public function testAQuantityCapKeepsTenureFromRunningAway(): void
    {
        // Arrange
        static::createClient();
        $locator = static::getContainer()->get(UserLocator::class);
        $trust = static::getContainer()->get(TrustInterface::class);
        $configStore = static::getContainer()->get(ConfigStore::class);
        $earnerId = (int) $locator->idFor(UserLocator::EARNER_EMAIL);
        $capped = $trust->getScore(self::CONTEXT, $earnerId);

        // Act
        $configStore->save(self::CONTEXT, new TrustConfig(capsPerAction: [ActionSource::TENURE => ActionSource::TENURE_MONTHS]));
        static::getContainer()->get(ScoreProvider::class)->invalidate(self::CONTEXT);
        $uncapped = $trust->getScore(self::CONTEXT, $earnerId);

        // Assert
        static::assertSame(ActionSource::HANDOVERS * ActionSource::DEFAULT_POINTS + ActionSource::TENURE_CAP, $capped);
        static::assertSame(ActionSource::HANDOVERS * ActionSource::DEFAULT_POINTS + ActionSource::TENURE_MONTHS, $uncapped);
    }

    public function testActionPointsAloneProduceAScore(): void
    {
        // Arrange
        static::createClient();
        $locator = static::getContainer()->get(UserLocator::class);
        $trust = static::getContainer()->get(TrustInterface::class);
        $earnerId = (int) $locator->idFor(UserLocator::EARNER_EMAIL);

        // Act
        $score = $trust->getScore(self::CONTEXT, $earnerId);

        // Assert
        $expected = ActionSource::HANDOVERS * ActionSource::DEFAULT_POINTS + ActionSource::TENURE_CAP;
        static::assertSame($expected, $score);
    }

    public function testAnUndeclaredActionIsIgnoredAndReported(): void
    {
        // Arrange
        static::createClient();
        $provider = static::getContainer()->get(ScoreProvider::class);

        // Act
        $undeclared = $provider->findUndeclaredActions(self::CONTEXT);

        // Assert
        static::assertSame(['stub_never_declared'], $undeclared);
    }

    public function testRaisingThePointsPerHandoverMovesEverybodyWhoEverEarned(): void
    {
        // Arrange
        static::createClient();
        $locator = static::getContainer()->get(UserLocator::class);
        $trust = static::getContainer()->get(TrustInterface::class);
        $configStore = static::getContainer()->get(ConfigStore::class);
        $earnerId = (int) $locator->idFor(UserLocator::EARNER_EMAIL);
        $before = $trust->getScore(self::CONTEXT, $earnerId);

        // Act
        $configStore->save(self::CONTEXT, new TrustConfig(pointsPerAction: [ActionSource::HANDOVER => 50]));
        static::getContainer()->get(ScoreProvider::class)->invalidate(self::CONTEXT);
        $after = $trust->getScore(self::CONTEXT, $earnerId);

        // Assert
        $tenure = ActionSource::TENURE_CAP;
        static::assertSame(ActionSource::HANDOVERS * ActionSource::DEFAULT_POINTS + $tenure, $before);
        static::assertSame(ActionSource::HANDOVERS * 50 + $tenure, $after);
    }

    public function testTwoContextsScoreTheSameMembersIndependently(): void
    {
        // Arrange
        static::createClient();
        $locator = static::getContainer()->get(UserLocator::class);
        $trust = static::getContainer()->get(TrustInterface::class);
        $earnerId = (int) $locator->idFor(UserLocator::EARNER_EMAIL);

        // Act
        $described = $trust->getScore(self::CONTEXT, $earnerId);
        $undescribed = $trust->getScore('nobody-describes-this', $earnerId);

        // Assert
        static::assertGreaterThan(0, $described);
        static::assertSame(0, $undescribed);
    }

    public function testAMemberNeverSeesAnotherMembersOutgoingVouches(): void
    {
        // Arrange
        static::createClient();
        $locator = static::getContainer()->get(UserLocator::class);
        $trust = static::getContainer()->get(TrustInterface::class);
        $rootId = (int) $locator->idFor(UserLocator::ROOT_EMAIL);
        $earnerId = (int) $locator->idFor(UserLocator::EARNER_EMAIL);
        $newcomerId = (int) $locator->idFor(UserLocator::NEWCOMER_EMAIL);
        $trust->grant(self::CONTEXT, $rootId, $newcomerId, TrustLevel::Trusted);

        // Act
        $ownEdges = $trust->getOutgoing(self::CONTEXT, $earnerId);

        // Assert
        static::assertSame([], $ownEdges);
        static::assertSame(1, $trust->getVouchCount(self::CONTEXT, $newcomerId));
    }

    public function testTheContextPageOffersAVouchControlForEveryOtherMember(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, UserLocator::ROOT_EMAIL);
        $locator = static::getContainer()->get(UserLocator::class);
        $earnerId = (int) $locator->idFor(UserLocator::EARNER_EMAIL);
        $rootId = (int) $locator->idFor(UserLocator::ROOT_EMAIL);

        // Act
        $crawler = $client->request('GET', '/en/admin/trust/context?context=' . self::CONTEXT);

        // Assert
        $this->assertResponseIsSuccessful();
        $vouchTargets = $crawler->filter('form.trust-vouch input[name="user"]')->extract(['value']);
        static::assertContains((string) $earnerId, $vouchTargets);
        static::assertNotContains((string) $rootId, $vouchTargets);
    }

    public function testAnAdministratorReachesTheOperatorPageAndSeesTheStubContext(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, UserLocator::ROOT_EMAIL);

        // Act
        $crawler = $client->request('GET', '/en/admin/trust');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('Stub context', $crawler->filter('body')->text());
    }

    public function testAPlainMemberCannotReachTheOperatorPage(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, UserLocator::EARNER_EMAIL);

        // Act
        $client->request('GET', '/en/admin/trust');

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => $email,
                '_password' => self::PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
