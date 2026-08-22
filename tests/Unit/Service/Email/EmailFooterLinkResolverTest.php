<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Entity\Cms;
use App\Entity\CmsLinkName;
use App\Repository\CmsRepository;
use App\Service\Email\EmailFooterLinkResolver;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EmailFooterLinkResolverTest extends TestCase
{
    public function testEveryFlaggedPageBecomesALocalisedLinkOnTheSendingHost(): void
    {
        // Arrange
        $resolver = $this->resolver([
            $this->cms(11, 'imprint', 'de', 'Impressum'),
            $this->cms(12, 'privacy', 'de', 'Datenschutz'),
        ]);

        // Act
        $links = $resolver->resolve('https://example.org/', 'de');

        // Assert
        static::assertSame([
            ['label' => 'Impressum', 'url' => 'https://example.org/de/imprint'],
            ['label' => 'Datenschutz', 'url' => 'https://example.org/de/privacy'],
        ], $links);
    }

    public function testANarrowingListDropsAPageOutsideItAndKeepsTheOnesInside(): void
    {
        // Arrange
        $resolver = $this->resolver([
            $this->cms(11, 'club-rules', 'de', 'Clubregeln'),
            $this->cms(12, 'imprint', 'de', 'Impressum'),
            $this->cms(13, 'other-terms', 'de', 'Fremde Bedingungen'),
        ]);

        // Act
        $links = $resolver->resolve('https://weiqi.meetagain.test', 'de', [11, 12]);

        // Assert
        static::assertSame([
            ['label' => 'Clubregeln', 'url' => 'https://weiqi.meetagain.test/de/club-rules'],
            ['label' => 'Impressum', 'url' => 'https://weiqi.meetagain.test/de/imprint'],
        ], $links);
    }

    public function testAnEmptyNarrowingListLeavesNoFooterLinksAtAll(): void
    {
        // Arrange
        $resolver = $this->resolver([$this->cms(11, 'imprint', 'de', 'Impressum')]);

        // Act
        $links = $resolver->resolve('https://example.org', 'de', []);

        // Assert
        static::assertSame([], $links);
    }

    public function testTheSlugStandsInWhenThePageHasNoLinkNameForTheLocale(): void
    {
        // Arrange
        $resolver = $this->resolver([$this->cms(11, 'privacy', 'de', 'Datenschutz')]);

        // Act
        $links = $resolver->resolve('https://example.org', 'fr');

        // Assert
        static::assertSame([['label' => 'privacy', 'url' => 'https://example.org/fr/privacy']], $links);
    }

    /**
     * @param array<Cms> $flagged
     */
    private function resolver(array $flagged): EmailFooterLinkResolver
    {
        $cmsRepo = $this->createStub(CmsRepository::class);
        $cmsRepo->method('findForEmailFooter')->willReturn($flagged);

        return new EmailFooterLinkResolver($cmsRepo);
    }

    private function cms(int $id, string $slug, string $language, string $linkName): Cms
    {
        $cms = new Cms();
        $cms->setSlug($slug);
        $cms->setEmailFooter(true);
        new ReflectionProperty(Cms::class, 'id')->setValue($cms, $id);
        $cms->addLinkName(new CmsLinkName()->setLanguage($language)->setName($linkName));

        return $cms;
    }
}
