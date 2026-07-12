<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Service\GlossaryService;
use ReflectionProperty;
use RuntimeException;

class GlossaryServiceTest extends TestCase
{
    public function testCreateAutoApprovesAndStampsOwner(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $service = new GlossaryService($em, $this->createStub(GlossaryRepository::class));
        $glossary = (new Glossary())->setPhrase('你好');

        // Act
        $service->create($glossary, userId: 9, autoApprove: true);

        // Assert
        self::assertTrue($glossary->getApproved());
        self::assertSame(9, $glossary->getCreatedBy());
        self::assertNotNull($glossary->getCreatedAt());
    }

    public function testCreateWithoutAutoApproveLeavesEntryPending(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $service = new GlossaryService($em, $this->createStub(GlossaryRepository::class));
        $glossary = (new Glossary())->setPhrase('你好');

        // Act
        $service->create($glossary, userId: 9, autoApprove: false);

        // Assert
        self::assertFalse($glossary->getApproved());
    }

    public function testApproveNewMarksEntryApproved(): void
    {
        // Arrange
        $item = (new Glossary())->setApproved(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($item);
        $em->expects(self::once())->method('flush');
        $service = new GlossaryService($em, $this->repoReturning($item));

        // Act
        $service->approveNew(1);

        // Assert
        self::assertTrue($item->getApproved());
    }

    public function testDeleteNewRejectsApprovedEntry(): void
    {
        // Arrange
        $item = (new Glossary())->setApproved(true);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $service = new GlossaryService($em, $this->repoReturning($item));

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->deleteNew(1);
    }

    public function testDeleteNewRemovesPendingEntry(): void
    {
        // Arrange
        $item = (new Glossary())->setApproved(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($item);
        $em->expects(self::once())->method('flush');
        $service = new GlossaryService($em, $this->repoReturning($item));

        // Act
        $service->deleteNew(1);

        // Assert — mock verifies remove()/flush() were called
    }

    public function testDeleteRemovesEntry(): void
    {
        // Arrange
        $item = new Glossary();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($item);
        $em->expects(self::once())->method('flush');
        $service = new GlossaryService($em, $this->repoReturning($item));

        // Act
        $service->delete(1);

        // Assert — mock verifies remove()/flush() were called
    }

    public function testGenerateSuggestionsRecordsOnlyChangedFields(): void
    {
        // Arrange
        $current = (new Glossary())
            ->setPhrase('old')
            ->setPinyin('lǎo')
            ->setExplanation('same')
            ->setCategory(Category::Greeting);
        $em = $this->createStub(EntityManagerInterface::class);
        $service = new GlossaryService($em, $this->repoReturning($current));

        $submitted = (new Glossary())
            ->setPhrase('new')
            ->setPinyin('lǎo')
            ->setExplanation('same')
            ->setCategory(Category::Greeting);

        // Act
        $service->generateSuggestions($submitted, id: 1, userId: 5);

        // Assert: only the phrase changed, so exactly one suggestion is queued
        $stored = $this->readStoredSuggestions($current);
        self::assertCount(1, $stored);
        self::assertSame('phrase', $stored[0]['field']);
        self::assertSame('new', $stored[0]['value']);
    }

    public function testGenerateSuggestionsRecordsNothingWhenUnchanged(): void
    {
        // Arrange
        $current = (new Glossary())
            ->setPhrase('same')
            ->setPinyin('same')
            ->setExplanation('same')
            ->setCategory(Category::Greeting);
        $em = $this->createStub(EntityManagerInterface::class);
        $service = new GlossaryService($em, $this->repoReturning($current));

        $submitted = (new Glossary())
            ->setPhrase('same')
            ->setPinyin('same')
            ->setExplanation('same')
            ->setCategory(Category::Greeting);

        // Act
        $service->generateSuggestions($submitted, id: 1, userId: 5);

        // Assert
        self::assertSame([], $current->getSuggestions());
    }

    public function testApplySuggestionUpdatesFieldAndReturnsRemainingCount(): void
    {
        // Arrange
        $item = (new Glossary())->setPhrase('old');
        $this->injectStoredSuggestions($item, [
            $this->storedSuggestion(1, 'phrase', 'brandnew'),
            $this->storedSuggestion(2, 'pinyin', 'xīn'),
        ]);
        $phraseHash = $item->getSuggestions()[0]->getHash();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($item);
        $em->expects(self::once())->method('flush');
        $service = new GlossaryService($em, $this->repoReturning($item));

        // Act
        $remaining = $service->applySuggestion(1, $phraseHash);

        // Assert
        self::assertSame('brandnew', $item->getPhrase());
        self::assertSame(1, $remaining);
    }

    private function repoReturning(?Glossary $item): GlossaryRepository
    {
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneBy')->willReturn($item);

        return $repo;
    }

    /**
     * @return array{createdBy: int, createdAt: array{date: string, timezone_type: int, timezone: string}, field: string, value: string}
     */
    private function storedSuggestion(int $createdBy, string $field, string $value): array
    {
        return [
            'createdBy' => $createdBy,
            'createdAt' => ['date' => '2026-07-12 10:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
            'field' => $field,
            'value' => $value,
        ];
    }

    /**
     * @param list<array<string, mixed>> $suggestions
     */
    private function injectStoredSuggestions(Glossary $glossary, array $suggestions): void
    {
        $property = new ReflectionProperty(Glossary::class, 'suggestion');
        $property->setValue($glossary, $suggestions);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readStoredSuggestions(Glossary $glossary): array
    {
        $property = new ReflectionProperty(Glossary::class, 'suggestion');

        return $property->getValue($glossary) ?? [];
    }
}
