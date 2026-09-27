<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use App\Emails\CoreTemplateProvider;
use App\Enum\EmailType;
use App\ExtendedFilesystem;
use Module\Email\Contract\TemplateDefinition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CoreTemplateProviderTest extends TestCase
{
    private const string PROJECT_DIR = '/app';

    public function testEveryShippedTypeHasADefinition(): void
    {
        // Arrange
        $provider = $this->provider(static fn(string $path): bool => true);

        // Act
        $identifiers = array_map(static fn(TemplateDefinition $d): string => $d->identifier, $provider->getDefinitions('en'));

        // Assert
        static::assertEqualsCanonicalizing(array_map(static fn(EmailType $t): string => $t->value, EmailType::cases()), $identifiers);
    }

    public function testTheLanguageSpecificBodyWinsWhenItExists(): void
    {
        // Arrange
        $provider = $this->provider(static fn(string $path): bool => true);

        // Act
        $welcome = $this->definition($provider, 'de', EmailType::Welcome);

        // Assert
        static::assertSame('/app/templates/email/defaults/de/welcome.html', $welcome->body);
        static::assertSame('Willkommen!', $welcome->subject);
    }

    public function testTheDefaultBodyStandsInForAMissingLanguageFile(): void
    {
        // Arrange
        $provider = $this->provider(static fn(string $path): bool => !str_contains($path, '/de/'));

        // Act
        $welcome = $this->definition($provider, 'de', EmailType::Welcome);

        // Assert
        static::assertSame('/app/templates/email/defaults/welcome.html', $welcome->body);
    }

    public function testAMissingBodyFileThrows(): void
    {
        // Arrange
        $provider = $this->provider(static fn(string $path): bool => false);

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $provider->getDefinitions('de');
    }

    public function testOnlyTheFragmentVariablesAreDeclaredRaw(): void
    {
        // Arrange
        $provider = $this->provider(static fn(string $path): bool => true);

        // Act
        $raw = [];
        foreach ($provider->getDefinitions('en') as $definition) {
            if ($definition->htmlVariables !== []) {
                $raw[$definition->identifier] = $definition->htmlVariables;
            }
        }

        // Assert
        static::assertSame(
            [
                EmailType::Announcement->value => ['content'],
                EmailType::AdminNotification->value => ['sections'],
                EmailType::UpcomingEvents->value => ['eventsHtml'],
                EmailType::EventUpdateNotification->value => ['changesHtml'],
                EmailType::SeriesRescheduled->value => ['removedDatesHtml'],
            ],
            $raw,
        );
    }

    private function provider(callable $exists): CoreTemplateProvider
    {
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturnCallback($exists);
        $fs->method('getFileContents')->willReturnArgument(0);

        return new CoreTemplateProvider($fs, self::PROJECT_DIR);
    }

    private function definition(CoreTemplateProvider $provider, string $language, EmailType $type): TemplateDefinition
    {
        foreach ($provider->getDefinitions($language) as $definition) {
            if ($definition->identifier === $type->value) {
                return $definition;
            }
        }

        static::fail(sprintf('No definition for "%s"', $type->value));
    }
}
