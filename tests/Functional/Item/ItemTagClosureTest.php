<?php declare(strict_types=1);

namespace Tests\Functional\Item;

use App\Entity\User;
use App\Repository\ItemTagAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Films\Entity\Film;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;

class ItemTagClosureTest extends WebTestCase
{
    private const string HOST = 'cinema.meetagain.local';
    private const string MANAGER_EMAIL = 'Admin@example.org';
    private const string UNTAGGED_FILM = 'Perfect Days';

    public function testTaggingAnItemWithASubTagStoresItsAncestorsToo(): void
    {
        // Arrange
        $client = static::createClient();
        $client->setServerParameter('HTTP_HOST', self::HOST);
        $client->loginUser($this->manager($client));
        $film = $this->film($client, self::UNTAGGED_FILM);
        $crawler = $client->request('GET', '/en/films/' . $film->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        // Act
        $this->submitTags($client, $crawler->filter('form[name="film_edit"]')->form(), ['1']);

        // Assert
        $this->assertResponseRedirects();
        static::assertSame([0, 1], $this->tagIds($client, (int) $film->getId()), 'The parent rides along');
    }

    public function testDroppingAParentTagLeavesTheSubTagAssignmentIntact(): void
    {
        // Arrange
        $client = static::createClient();
        $client->setServerParameter('HTTP_HOST', self::HOST);
        $client->loginUser($this->manager($client));
        $film = $this->film($client, self::UNTAGGED_FILM);
        $crawler = $client->request('GET', '/en/films/' . $film->getId() . '/edit');
        $this->submitTags($client, $crawler->filter('form[name="film_edit"]')->form(), ['0', '1']);

        // Act
        $crawler = $client->request('GET', '/en/films/' . $film->getId() . '/edit');
        $this->submitTags($client, $crawler->filter('form[name="film_edit"]')->form(), ['1']);

        // Assert
        static::assertSame([0, 1], $this->tagIds($client, (int) $film->getId()));
    }

    /** @param list<string> $tagIds */
    private function submitTags(KernelBrowser $client, Form $form, array $tagIds): void
    {
        $values = $form->getPhpValues();
        $values['film_edit']['taxonomyTags'] = $tagIds;
        $client->request('POST', $form->getUri(), $values, $form->getPhpFiles());
    }

    /** @return list<int> */
    private function tagIds(KernelBrowser $client, int $filmId): array
    {
        $this->em($client)->clear();
        $ids = $client->getContainer()->get(ItemTagAssignmentRepository::class)->tagIdsFor('film', $filmId);
        sort($ids);

        return $ids;
    }

    private function film(KernelBrowser $client, string $title): Film
    {
        $film = $this->em($client)->getRepository(Film::class)->findOneBy(['title' => $title]);
        if (!$film instanceof Film) {
            self::fail('Required fixture film missing: ' . $title);
        }

        return $film;
    }

    private function manager(KernelBrowser $client): User
    {
        $user = $this->em($client)->getRepository(User::class)->findOneBy(['email' => self::MANAGER_EMAIL]);
        if (!$user instanceof User) {
            self::fail('Required fixture user missing: ' . self::MANAGER_EMAIL);
        }

        return $user;
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
