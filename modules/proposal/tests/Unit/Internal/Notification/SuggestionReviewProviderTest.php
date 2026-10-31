<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Unit\Internal\Notification;

use App\Entity\User;
use Module\Proposal\Internal\Entity\Suggestion;
use Module\Proposal\Internal\Notification\SuggestionReviewProvider;
use Module\Proposal\Internal\Suggestion\SuggestionService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SuggestionReviewProviderTest extends TestCase
{
    public function testReviewItemsCarryTheSuggestedFieldsWithoutABefore(): void
    {
        // Arrange
        $suggestion = new Suggestion();
        new ReflectionProperty(Suggestion::class, 'id')->setValue($suggestion, 3);
        $suggestion->setTargetType('location');
        $suggestion->setProposedBy(new User());
        $rows = [['label' => 'Name', 'value' => 'Café Linde'], ['label' => 'Street', 'value' => '']];
        $service = $this->createStub(SuggestionService::class);
        $service->method('pendingReviewableBy')->willReturn([$suggestion]);
        $service->method('summaryRows')->willReturn($rows);
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/review/suggestion/3');
        $provider = new SuggestionReviewProvider($service, $router, $this->createStub(TranslatorInterface::class));

        // Act
        $items = $provider->getReviewItems(new User());

        // Assert
        self::assertCount(1, $items);
        self::assertSame('3', $items[0]->id);
        self::assertNull($items[0]->longDescription);
        self::assertSame($rows, $items[0]->fields);
    }
}
