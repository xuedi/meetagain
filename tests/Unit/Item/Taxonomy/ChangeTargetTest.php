<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Entity\User;
use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\ChangeTarget;
use App\Item\Taxonomy\Config;
use App\Item\Taxonomy\ScopeCodec;
use App\Item\Taxonomy\ChangeFieldCodec;
use App\Item\Taxonomy\ScopedSettings;
use App\Publisher\PluginSettings\Data;
use App\Publisher\PluginSettings\DescriptorInterface;
use App\Publisher\PluginSettings\Resolver;
use App\Publisher\PluginSettings\ScopeProviderInterface;
use App\Publisher\PluginSettings\StoreInterface;
use App\Service\Admin\PluginSettingsService;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangeTargetTest extends TestCase
{
    private const string SETTINGS_KEY = 'test_settings';

    private TaxonomyTestStore $store;

    public function testTargetTypeIsPrefixedWithTheItemType(): void
    {
        // Arrange + Act
        $target = $this->target();

        // Assert
        static::assertSame('taxonomy_test', $target->getTargetType());
    }

    public function testProposingNeedsTheRequestToResolveToTheTargetScope(): void
    {
        // Arrange
        $target = $this->target(scopeId: '5');

        // Act + Assert
        static::assertTrue($target->canPropose(new User(), 5));
        static::assertFalse($target->canPropose(new User(), 0), 'Another scope is another vocabulary');
    }

    public function testReviewingTheGlobalScopeNeedsAdmin(): void
    {
        // Arrange
        $adminTarget = $this->target(roles: ['ROLE_ADMIN', 'ROLE_STEWARD']);
        $stewardTarget = $this->target(roles: ['ROLE_STEWARD']);

        // Act + Assert
        static::assertTrue($adminTarget->canReview(new User(), 0));
        static::assertFalse($stewardTarget->canReview(new User(), 0));
    }

    public function testReviewingAnOverrideScopeNeedsSteward(): void
    {
        // Arrange
        $steward = $this->target(scopeId: '5', roles: ['ROLE_STEWARD']);
        $member = $this->target(scopeId: '5', roles: ['ROLE_USER']);

        // Act + Assert
        static::assertTrue($steward->canReview(new User(), 5));
        static::assertFalse($member->canReview(new User(), 5));
        static::assertFalse($steward->canReview(new User(), 6), 'A steward of one scope is not a reviewer of another');
    }

    public function testValidateRejectsABlankOrDuplicateLabel(): void
    {
        // Arrange
        $target = $this->target();

        // Act + Assert
        static::assertSame('item.taxonomy_validation_blank', $target->validate(0, 'category_rename_1_en', ' '));
        static::assertSame('item.taxonomy_validation_duplicate', $target->validate(0, 'category_add_en_0', 'slang'));
        static::assertNull($target->validate(0, 'category_add_en_0', 'Idiom'));
    }

    public function testValidateRejectsAVanishedDefinition(): void
    {
        // Arrange
        $target = $this->target();

        // Act + Assert
        static::assertSame('item.taxonomy_validation_gone', $target->validate(0, 'category_remove_99', null));
        static::assertNull($target->validate(0, 'category_remove_1', null));
    }

    public function testValidateRejectsAnUnparsableField(): void
    {
        // Arrange
        $target = $this->target();

        // Act + Assert
        static::assertSame('item.taxonomy_validation_unavailable', $target->validate(0, 'explanation', 'x'));
    }

    public function testApplyWritesTheRenameBackToTheTargetScope(): void
    {
        // Arrange
        $target = $this->target(scopeId: '5');

        // Act
        $target->apply(5, 'category_rename_1_en', 'Salutation');

        // Assert
        static::assertSame(
            [['id' => 1, 'labels' => ['en' => 'Salutation'], 'group' => null, 'parent' => null], ['id' => 2, 'labels' => ['en' => 'Slang'], 'group' => null, 'parent' => null]],
            $this->taxonomyAt('5')->getCategories(),
        );
        static::assertSame(
            [['id' => 1, 'labels' => ['en' => 'Greeting'], 'group' => null, 'parent' => null], ['id' => 2, 'labels' => ['en' => 'Slang'], 'group' => null, 'parent' => null]],
            $this->taxonomyAt(null)->getCategories(),
            'The global vocabulary is untouched',
        );
    }

    public function testApplyAddsARowAndNormalizeGivesItTheNextId(): void
    {
        // Arrange
        $target = $this->target();

        // Act
        $target->apply(0, 'category_add_en_0', ' Idiom ');

        // Assert
        static::assertSame(
            [['id' => 1, 'labels' => ['en' => 'Greeting'], 'group' => null, 'parent' => null], ['id' => 2, 'labels' => ['en' => 'Slang'], 'group' => null, 'parent' => null], ['id' => 3, 'labels' => ['en' => 'Idiom'], 'group' => null, 'parent' => null]],
            $this->taxonomyAt(null)->getCategories(),
        );
    }

    public function testApplyRemovesADefinition(): void
    {
        // Arrange
        $target = $this->target();

        // Act
        $target->apply(0, 'category_remove_1', null);

        // Assert
        static::assertSame([['id' => 2, 'labels' => ['en' => 'Slang'], 'group' => null, 'parent' => null]], $this->taxonomyAt(null)->getCategories());
    }

    public function testValidateRejectsASubTagUnderAGoneOrTooDeepParent(): void
    {
        // Arrange
        $target = $this->target();

        // Act + Assert
        static::assertSame('item.taxonomy_validation_gone', $target->validate(0, 'tag_add_99_en_0', 'Chicken'));
        static::assertSame('item.taxonomy_validation_depth', $target->validate(0, 'tag_add_2_en_0', 'Wing'));
        static::assertNull($target->validate(0, 'tag_add_1_en_0', 'Chicken'));
    }

    public function testApplyHangsAnAddedSubTagUnderItsParent(): void
    {
        // Arrange
        $target = $this->target();

        // Act
        $target->apply(0, 'tag_add_1_en_0', 'Chicken');

        // Assert
        $tags = $this->taxonomyAt(null)->tagDefinitions();
        static::assertSame('Chicken', $tags[2]->labels['en']);
        static::assertSame(1, $tags[2]->parent);
    }

    public function testAnInactiveTypeHasNoLabelSoOrphansStayHidden(): void
    {
        // Arrange
        $target = $this->target(typeRegistered: false);

        // Act + Assert
        static::assertNull($target->getTargetLabel(0));
        static::assertFalse($target->canPropose(new User(), 0));
    }

    /** @param list<string> $roles */
    private function target(?string $scopeId = null, array $roles = ['ROLE_ADMIN', 'ROLE_STEWARD', 'ROLE_USER'], bool $typeRegistered = true): ChangeTarget
    {
        $this->store = new TaxonomyTestStore([
            '' => $this->settings(),
            '5' => $this->settings(),
        ]);

        $descriptors = new PluginSettingsService([new TaxonomyTestDescriptor()]);
        $resolver = new Resolver($descriptors, [$this->store], [new TaxonomyTestScopeProvider($scopeId)]);

        $provider = $this->createStub(CategorizableTypeProviderInterface::class);
        $provider->method('getSettingsKey')->willReturn(self::SETTINGS_KEY);
        $provider->method('getLabelKey')->willReturn('test.item_label');
        $provider->method('taxonomyOf')->willReturnCallback(
            static fn(object $settings): Config => $settings instanceof TaxonomyTestSettings ? $settings->taxonomy : new Config(),
        );

        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn($typeRegistered ? $provider : null);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn(mixed $role): bool => in_array($role, $roles, true));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new TaxonomyTestChangeTarget(
            $registry,
            new ScopedSettings($resolver, $descriptors),
            new ScopeCodec($resolver),
            new ChangeFieldCodec(),
            $security,
            $this->createStub(RouterInterface::class),
            $translator,
        );
    }

    private function settings(): TaxonomyTestSettings
    {
        $settings = new TaxonomyTestSettings();
        $settings->taxonomy
            ->setCategoriesEnabled(true)
            ->setCategories([
                ['id' => 1, 'labels' => ['en' => 'Greeting']],
                ['id' => 2, 'labels' => ['en' => 'Slang']],
            ])
            ->setTagsEnabled(true)
            ->setTagDepth(2)
            ->setTags([
                ['id' => 1, 'labels' => ['en' => 'Meat']],
                ['id' => 2, 'labels' => ['en' => 'Poultry'], 'parent' => 1],
            ]);

        return $settings;
    }

    private function taxonomyAt(?string $scopeId): Config
    {
        return $this->store->records[$scopeId ?? '']->taxonomy;
    }
}

final class TaxonomyTestChangeTarget extends ChangeTarget
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'test';
    }

    #[Override]
    protected function getTypeKey(): string
    {
        return 'test';
    }
}

final class TaxonomyTestSettings implements Data
{
    public Config $taxonomy;

    public function __construct()
    {
        $this->taxonomy = new Config();
    }

    #[Override]
    public function toArray(): array
    {
        return ['taxonomy' => $this->taxonomy->toArray()];
    }

    #[Override]
    public static function fromArray(array $raw): static
    {
        $settings = new self();
        $settings->taxonomy = Config::fromArray((array) ($raw['taxonomy'] ?? []));

        return $settings;
    }
}

final class TaxonomyTestStore implements StoreInterface
{
    /** @param array<string, TaxonomyTestSettings> $records */
    public function __construct(public array $records) {}

    #[Override]
    public function supports(string $key, ?string $scopeId): bool
    {
        return true;
    }

    #[Override]
    public function load(string $key, ?string $scopeId): ?object
    {
        return $this->records[$scopeId ?? ''] ?? null;
    }

    #[Override]
    public function save(string $key, object $data, ?string $scopeId): void
    {
        \assert($data instanceof TaxonomyTestSettings);

        $this->records[$scopeId ?? ''] = $data;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }
}

final class TaxonomyTestScopeProvider implements ScopeProviderInterface
{
    public function __construct(private readonly ?string $scopeId) {}

    #[Override]
    public function getScopeId(): ?string
    {
        return $this->scopeId;
    }
}

final class TaxonomyTestDescriptor implements DescriptorInterface
{
    #[Override]
    public function getKey(): string
    {
        return 'test_settings';
    }

    #[Override]
    public function getPluginKey(): string
    {
        return 'test';
    }

    #[Override]
    public function isScopable(): bool
    {
        return true;
    }

    #[Override]
    public function getTitleKey(): string
    {
        return 'test.title';
    }

    #[Override]
    public function getFormType(): string
    {
        return 'test';
    }

    #[Override]
    public function getFormOptions(object $data): array
    {
        return [];
    }

    #[Override]
    public function createDefault(): object
    {
        return new TaxonomyTestSettings();
    }

    #[Override]
    public function applyForm(object $data, FormInterface $form): void {}

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }
}
