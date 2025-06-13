<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\CmsBlockTypes;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Filesystem\Filesystem;

class CmsBlockFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly Filesystem $fs)
    {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating cms blocks ... ';
        foreach ($this->getData() as [$page, $lang, $prio, $type, $json, $imageName]) {
            $block = new CmsBlock();
            $block->setPage($this->getReference('cms_' . md5((string)$page), Cms::class));
            $block->setLanguage($lang);
            $block->setPriority($prio);
            $block->setType($type);
            $block->setJson($json);

            $manager->persist($block);
            if ($imageName !== null) {
                $this->addReference('cmsBlock_' . md5($imageName), $block);
            }
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            CmsFixture::class,
            UserFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            ['imprint', 'en', 1, CmsBlockTypes::Headline, ['title' => 'Imprint'], null],
            ['imprint', 'en', 2, CmsBlockTypes::Text, ['title' => '1. Paragraph', 'content' => 'Some text p1'], null],
            ['imprint', 'en', 3, CmsBlockTypes::Text, ['title' => '2. Paragraph', 'content' => 'Some text p2'], null],
            ['imprint', 'de', 1, CmsBlockTypes::Headline, ['title' => 'Impressum'], null],
            ['imprint', 'de', 2, CmsBlockTypes::Text, ['title' => '1. Paragraf', 'content' => 'Etwas text p1'], null],
            ['imprint', 'cn', 1, CmsBlockTypes::Headline, ['title' => '版本说明'], null],
            ['imprint', 'cn', 2, CmsBlockTypes::Text, ['title' => '第 1 段', 'content' => '第一段的一些文字'], null],
            ['about', 'en', 1, CmsBlockTypes::Headline, ['title' => 'About'], null],
            ['about', 'en', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('about_en')], null],
            ['about', 'de', 1, CmsBlockTypes::Headline, ['title' => 'Über Uns'], null],
            ['about', 'de', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('about_de')], null],
            ['about', 'cn', 1, CmsBlockTypes::Headline, ['title' => '关于我们'], null],
            ['about', 'cn', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('about_cn')], null],
            ['index', 'en', 1, CmsBlockTypes::Hero, ['headline' => 'headline-en', 'subHeadline' => 'subHeadline-en', 'text' => 'text-en', 'buttonLink' => 'buttonLink-en', 'buttonText' => 'buttonText-en'], 'hero-en.jpg'],
            ['index', 'en', 2, CmsBlockTypes::EventTeaser, ['headline' => 'welcome_en', 'text' => 'text-en'], 'event-teaser-en.webp'],
            ['index', 'de', 1, CmsBlockTypes::Hero, ['headline' => 'headline-de', 'subHeadline' => 'subHeadline-de', 'text' => 'text-de', 'buttonLink' => 'buttonLink-de', 'buttonText' => 'buttonText-de'], 'hero-de.jpg'],
            ['index', 'de', 2, CmsBlockTypes::EventTeaser, ['headline' => 'welcome_de', 'text' => 'text-de'], 'event-teaser-de.webp'],
            ['index', 'cn', 1, CmsBlockTypes::Hero, ['headline' => 'headline-cn', 'subHeadline' => 'subHeadline-cn', 'text' => 'text-cn', 'buttonLink' => 'buttonLink-cn', 'buttonText' => 'buttonText-cn'], 'hero-cn.jpg'],
            ['index', 'cn', 2, CmsBlockTypes::EventTeaser, ['headline' => 'welcome_cn', 'text' => 'text-cn'], 'event-teaser-cn.webp'],
        ];
    }


    private function getBlob(string $string): string
    {
        return $this->fs->readFile(__DIR__ . "/blobs/$string.txt");
    }

    public function getBlockReferenceForImages(): array
    {
        return [
            'event-teaser-en.webp',
            'event-teaser-de.webp',
            'event-teaser-cn.webp',
            'hero-en.jpg',
            'hero-de.jpg',
            'hero-cn.jpg',
        ];
    }
}
