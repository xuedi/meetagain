<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Suggestion;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\Suggestion\GlossaryTarget;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryTargetTest extends TestCase
{
    public function testAPayloadSurvivesTheRoundTrip(): void
    {
        // Arrange
        $target = $this->target();
        $draft = new Glossary()
            ->setPhrase('半路出家')
            ->setSecondary('bàn lù chū jiā')
            ->submitDefinitions(['en' => 'A latecomer to a craft.', 'de' => 'Ein Quereinsteiger.', 'fr' => '']);

        // Act
        $payload = $target->toPayload($draft);
        $restored = $target->fromPayload($payload);

        // Assert
        self::assertSame(
            ['phrase' => '半路出家', 'secondary' => 'bàn lù chū jiā', 'definition_en' => 'A latecomer to a craft.', 'definition_de' => 'Ein Quereinsteiger.'],
            $payload,
        );
        self::assertInstanceOf(Glossary::class, $restored);
        self::assertSame('bàn lù chū jiā', $restored->getSecondary());
        self::assertSame(['en' => 'A latecomer to a craft.', 'de' => 'Ein Quereinsteiger.'], $restored->getSubmittedDefinitions());
    }

    public function testAnAbsentSecondaryFieldComesBackAsNullRatherThanAnEmptyString(): void
    {
        // Arrange
        $target = $this->target();

        // Act
        $restored = $target->fromPayload(['phrase' => '加油', 'definition_en' => 'Keep going.']);

        // Assert
        self::assertInstanceOf(Glossary::class, $restored);
        self::assertNull($restored->getSecondary());
    }

    public function testAPhraseAlreadyInTheGlossaryIsRefused(): void
    {
        // Arrange
        $target = $this->target(duplicates: ['你好']);

        // Act
        $duplicate = $target->validate(new Glossary()
            ->setPhrase('你好')
            ->submitDefinitions(['en' => 'Hello.']));
        $fresh = $target->validate(new Glossary()
            ->setPhrase('您好')
            ->submitDefinitions(['en' => 'Hello, politely.']));

        // Assert
        self::assertSame('glossary.validator_duplicate', $duplicate);
        self::assertNull($fresh);
    }

    public function testAnEntryWithoutAnyDefinitionIsRefused(): void
    {
        // Arrange
        $target = $this->target();

        // Act & Assert
        self::assertSame('glossary.validator_incomplete', $target->validate(new Glossary()->setPhrase('加油')));
        self::assertSame('glossary.validator_incomplete', $target->validate(new Glossary()
            ->setPhrase('加油')
            ->submitDefinitions(['en' => ' '])));
        self::assertSame('glossary.validator_incomplete', $target->validate(new Glossary()
            ->setPhrase(' ')
            ->submitDefinitions(['en' => 'Keep going.'])));
    }

    public function testTheSummaryShowsTheSecondaryOnlyWhereEnabledAndOneRowPerDefinition(): void
    {
        // Arrange
        $withSecondary = $this->target(secondaryEnabled: true);
        $withoutSecondary = $this->target(secondaryEnabled: false);
        $payload = ['phrase' => '你好', 'secondary' => 'nǐ hǎo', 'definition_en' => 'Hello.', 'definition_de' => 'Hallo.'];

        // Act
        $labelled = array_column($withSecondary->summaryRows($payload), 'value');
        $plain = array_column($withoutSecondary->summaryRows($payload), 'value');

        // Assert
        self::assertSame(['你好', 'nǐ hǎo', 'Hello.', 'Hallo.'], $labelled);
        self::assertSame(['你好', 'Hello.', 'Hallo.'], $plain);
    }

    /**
     * @param list<string> $duplicates
     */
    private function target(array $duplicates = [], bool $secondaryEnabled = true): GlossaryTarget
    {
        $service = $this->createStub(GlossaryService::class);
        $service->method('isDuplicatePhrase')->willReturnCallback(static fn(string $phrase): bool => in_array($phrase, $duplicates, true));
        $service->method('definitionLanguageOf')->willReturnCallback(static fn(string $field): ?string => preg_match(
            '/^definition_([a-z]{2})$/',
            $field,
            $match,
        ) === 1
                ? $match[1]
                : null);

        $config = new Config()->setSecondaryEnabled($secondaryEnabled);
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new GlossaryTarget($service, $configService, $security, $translator);
    }
}
