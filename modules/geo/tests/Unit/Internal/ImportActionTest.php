<?php declare(strict_types=1);

namespace Module\Geo\Tests\Unit\Internal;

use Doctrine\ORM\EntityManagerInterface;
use Module\Geo\Contract\DirectoryInterface;
use Module\Geo\Internal\Import\Loader;
use Module\Geo\Internal\ImportAction;
use Module\Geo\Internal\Repository\CityRepository;
use Module\Task\Contract\ActionSettingsInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImportActionTest extends TestCase
{
    public function testSavedValuesBecomeTheDefaults(): void
    {
        // Arrange
        $action = $this->action([ImportAction::BASE_URL => 'https://mirror.example/geonames/', ImportAction::MIN_POPULATION => 5000]);

        // Act
        $options = $action->options();

        // Assert
        self::assertSame(['https://mirror.example/geonames/', 5000, null], [$options->baseUrl, $options->minPopulation, $options->sourceDir]);
    }

    public function testAnExplicitValueBeatsTheSavedOne(): void
    {
        // Arrange
        $action = $this->action([ImportAction::BASE_URL => 'https://mirror.example/geonames/', ImportAction::MIN_POPULATION => 5000]);

        // Act
        $options = $action->options(baseUrl: 'https://other.example/', minPopulation: 1000);

        // Assert
        self::assertSame(['https://other.example/', 1000], [$options->baseUrl, $options->minPopulation]);
    }

    /**
     * @param array<string, string|int> $saved
     */
    private function action(array $saved): ImportAction
    {
        $settings = $this->createStub(ActionSettingsInterface::class);
        $settings->method('get')->willReturnCallback(static fn(string $identifier, string $key): string|int|null => $identifier === ImportAction::IDENTIFIER
            ? $saved[$key] ?? null
            : null);

        return new ImportAction(
            $this->createStub(Loader::class),
            $this->createStub(DirectoryInterface::class),
            $this->createStub(CityRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $settings,
            $this->createStub(TranslatorInterface::class),
        );
    }
}
