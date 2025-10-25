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
    public function __construct(
        private readonly Filesystem $fs,
    )
    {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating cms blocks ... ';
        $priority = 1; // it is OK to increase the priority of the blocks
        foreach ($this->getData() as [$page, $lang, $type, $json, $imageName]) {
            $block = new CmsBlock();
            $block->setPage($this->getReference('CmsFixture::' . md5((string)$page), Cms::class));
            $block->setLanguage($lang);
            $block->setPriority($priority);
            $block->setType($type);
            $block->setJson($json);

            $manager->persist($block);
            if ($imageName !== null) {
                $this->addReference('CmsBlockFixture::' . md5($imageName), $block);
            }
            $priority++;
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
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Imprint'
                ],
                null
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'title' => '1. Paragraph',
                    'content' => 'Some text p1'
                ],
                null
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'title' => '2. Paragraph',
                    'content' => 'Some text p2'
                ],
                null
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Impressum'
                ],
                null
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Text,
                [
                    'title' => '1. Paragraf',
                    'content' => 'Etwas text p1'
                ],
                null
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Headline,
                [
                    'title' => '版本说明'
                ],
                null
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Text,
                [
                    'title' => '第 1 段',
                    'content' => '第一段的一些文字'
                ],
                null
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Headline,
                [
                    'title' => 'About'
                ],
                null
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'content' => $this->getBlob('about_en')
                ],
                null
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Über Uns'
                ],
                null
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Text,
                [
                    'content' => $this->getBlob('about_de')
                ],
                null
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Headline,
                [
                    'title' => '关于我们'
                ],
                null
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Text,
                [
                    'content' => $this->getBlob('about_cn')
                ],
                null
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Hero,
                [
                    'headline' => 'headline-en',
                    'subHeadline' => 'subHeadline-en',
                    'text' => 'text-en',
                    'buttonLink' => 'buttonLink-en',
                    'buttonText' => 'buttonText-en',
                ],
                'hero-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::EventTeaser,
                [
                    'headline' => 'welcome_en',
                    'text' => 'text-en'
                ],
                'event-teaser-en.webp',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Hero,
                [
                    'headline' => 'headline-de',
                    'subHeadline' => 'subHeadline-de',
                    'text' => 'text-de',
                    'buttonLink' => 'buttonLink-de',
                    'buttonText' => 'buttonText-de',
                ],
                'hero-de.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::GERMAN,
                CmsBlockTypes::EventTeaser,
                [
                    'headline' => 'welcome_de',
                    'text' => 'text-de'
                ],
                'event-teaser-de.webp',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Hero,
                [
                    'headline' => 'headline-cn',
                    'subHeadline' => 'subHeadline-cn',
                    'text' => 'text-cn',
                    'buttonLink' => 'buttonLink-cn',
                    'buttonText' => 'buttonText-cn',
                ],
                'hero-cn.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::CHINESE,
                CmsBlockTypes::EventTeaser,
                [
                    'headline' => 'welcome_cn',
                    'text' => 'text-cn'
                ],
                'event-teaser-cn.webp',
            ],
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
