<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Functional\Change;

use Doctrine\ORM\EntityManagerInterface;
use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Tests\Stub\Change\Target;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Module\Members;

final class DiffPageTest extends WebTestCase
{
    public function testTheProposerSeesTheirPendingChange(): void
    {
        // Arrange
        $client = static::createClient();
        $proposer = $this->members($client)->member('Proposer');
        $this->changes($client)->propose(Target::TYPE, 7, (int) $proposer->getId(), [new FieldChange('name', 'Cafe', 'Cafe Central')]);
        $client->loginUser($proposer);

        // Act
        $crawler = $client->request('GET', '/en/review/proposals/' . Target::TYPE . '/7');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Cafe Central', $crawler->text());
    }

    public function testAStrangerWhoMayNotReviewGetsA404(): void
    {
        // Arrange
        $client = static::createClient();
        $proposer = $this->members($client)->member('Proposer');
        $this->changes($client)->propose(Target::TYPE, 7, (int) $proposer->getId(), [new FieldChange('name', 'Cafe', 'Cafe Central')]);
        $client->loginUser($this->members($client)->member('Stranger'));

        // Act
        $client->request('GET', '/en/review/proposals/' . Target::TYPE . '/7');

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    public function testATargetWithoutALabelIsA404(): void
    {
        // Arrange
        $client = static::createClient();
        $client->loginUser($this->members($client)->member('Proposer'));

        // Act
        $client->request('GET', '/en/review/proposals/' . Target::TYPE . '/' . Target::MISSING);

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    private function changes(KernelBrowser $client): ChangeInterface
    {
        return $client->getContainer()->get(ChangeInterface::class);
    }

    private function members(KernelBrowser $client): Members
    {
        return new Members($client->getContainer()->get(EntityManagerInterface::class));
    }
}
