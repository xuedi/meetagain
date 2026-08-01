<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Review;

use App\Entity\User;
use App\Entity\ItemTag;
use App\Item\Tag\TagService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryChangeTargetTest extends TestCase
{
    #[DataProvider('validationCases')]
    public function testValidate(?Glossary $entry, string $field, ?string $value, ?string $expected): void
    {
        // Arrange
        $target = $this->makeTarget(entry: $entry);

        // Act
        $error = $target->validate(1, $field, $value);

        // Assert
        self::assertSame($expected, $error);
    }

    public static function validationCases(): iterable
    {
        $entry = new Glossary();

        yield 'missing entry fails every field' => [null, GlossaryChangeTarget::FIELD_PHRASE, 'x', 'glossary.validation_entry_missing'];
        yield 'blank phrase is rejected' => [$entry, GlossaryChangeTarget::FIELD_PHRASE, '  ', 'glossary.validation_phrase_blank'];
        yield 'non-blank phrase is fine' => [$entry, GlossaryChangeTarget::FIELD_PHRASE, '你好', null];
        yield 'unknown tag id is rejected' => [$entry, GlossaryChangeTarget::FIELD_TAG, '99', 'glossary.validation_tag_unknown'];
        yield 'known tag id is fine' => [$entry, GlossaryChangeTarget::FIELD_TAG, '3', null];
        yield 'one unknown id in a set rejects the set' => [$entry, GlossaryChangeTarget::FIELD_TAG, '3,99', 'glossary.validation_tag_unknown'];
        yield 'clearing the tags is fine' => [$entry, GlossaryChangeTarget::FIELD_TAG, null, null];
        yield 'explanation has no constraints' => [$entry, GlossaryChangeTarget::FIELD_EXPLANATION, '', null];
    }

    public function testFormatValueResolvesTagLabels(): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act & Assert
        self::assertSame('Slang', $target->formatValue(GlossaryChangeTarget::FIELD_TAG, '3'));
        self::assertSame('Slang, 99', $target->formatValue(GlossaryChangeTarget::FIELD_TAG, '3,99'));
        self::assertSame('', $target->formatValue(GlossaryChangeTarget::FIELD_TAG, null));
        self::assertSame('plain', $target->formatValue(GlossaryChangeTarget::FIELD_PHRASE, 'plain'));
    }

    public function testFieldLabelsPreferConfiguredLabels(): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act & Assert
        self::assertSame('Term', $target->getFieldLabel(GlossaryChangeTarget::FIELD_PHRASE));
        self::assertSame('glossary.label_explanation', $target->getFieldLabel(GlossaryChangeTarget::FIELD_EXPLANATION));
    }

    public function testCanProposeRequiresAnApprovedEntry(): void
    {
        // Arrange
        $user = new User();

        // Act & Assert
        self::assertTrue($this->makeTarget(entry: (new Glossary())->setApproved(true))->canPropose($user, 1));
        self::assertFalse($this->makeTarget(entry: (new Glossary())->setApproved(false))->canPropose($user, 1));
        self::assertFalse($this->makeTarget(entry: null)->canPropose($user, 1));
        self::assertFalse($this->makeTarget(entry: (new Glossary())->setApproved(true), granted: false)->canPropose($user, 1));
    }

    public function testCanReviewRequiresRoleAndExistingEntry(): void
    {
        // Arrange
        $user = new User();

        // Act & Assert
        self::assertTrue($this->makeTarget(entry: new Glossary())->canReview($user, 1));
        self::assertFalse($this->makeTarget(entry: null)->canReview($user, 1));
        self::assertFalse($this->makeTarget(entry: new Glossary(), granted: false)->canReview($user, 1));
    }

    public function testTargetLabelIsThePhraseOrNull(): void
    {
        // Act & Assert
        self::assertSame('你好', $this->makeTarget(entry: (new Glossary())->setPhrase('你好'))->getTargetLabel(1));
        self::assertNull($this->makeTarget(entry: null)->getTargetLabel(1));
    }

    public function testApplyDelegatesToTheService(): void
    {
        // Arrange
        $service = $this->createMock(GlossaryService::class);
        $service->expects(self::once())
            ->method('applyChange')
            ->with(1, GlossaryChangeTarget::FIELD_PHRASE, 'new');
        $target = $this->makeTarget(service: $service);

        // Act
        $target->apply(1, GlossaryChangeTarget::FIELD_PHRASE, 'new');
    }

    private function makeTarget(
        ?Glossary $entry = null,
        bool $granted = true,
        ?TagService $tagService = null,
        ?GlossaryService $service = null,
    ): GlossaryChangeTarget {
        if ($service === null) {
            $service = $this->createStub(GlossaryService::class);
            $service->method('get')->willReturn($entry);
        }
        $service->method('decodeTagIds')->willReturnCallback(
            static fn(?string $value): array => $value === null || $value === ''
                ? []
                : array_map(intval(...), explode(',', $value)),
        );

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn(Config::fromArray(['primaryLabel' => 'Term']));

        $slang = new ItemTag();
        $slang->setItemType('glossary');
        $slang->setLabels(['en' => 'Slang']);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new GlossaryChangeTarget(
            $service,
            $configService,
            $tagService ?? $this->tagServiceWith($slang),
            $security,
            $this->createStub(RouterInterface::class),
            new RequestStack(),
            $translator,
        );
    }

    private function tagServiceWith(ItemTag $tag): TagService
    {
        $tagService = $this->createStub(TagService::class);
        $tagService->method('getManagedTag')->willReturnCallback(static fn(string $type, int $id): ?ItemTag => $id === 3 ? $tag : null);
        $tagService->method('labelFor')->willReturn('Slang');

        return $tagService;
    }
}
