<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CmsBlock;
use App\Entity\CmsBlockTypes;
use App\Entity\ImageType;
use App\Service\ImageService;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CmsBlockFixture extends AbstractFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        private readonly ImageService $imageService,
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->start();
        $priority = 1; // it is OK to increase the priority of the blocks
        $importUser = $this->getRefUser(UserFixture::IMPORT);
        foreach ($this->getData() as [$page, $lang, $type, $json, $imageName]) {
            $block = new CmsBlock();
            $block->setPage($this->getRefCms($page));
            $block->setLanguage($lang);
            $block->setPriority($priority);
            $block->setType($type);
            $block->setJson($json);

            $manager->persist($block);
            if ($imageName !== null) {

                // upload file and create thumbnails
                $imageFile = __DIR__ . "/CmsBlock/$imageName";
                $uploadedImage = new UploadedFile($imageFile, $block->getId() . '.jpg');
                $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::CmsBlock);
                $this->imageService->createThumbnails($image);

                // associate image with a user
                $block->setImage($image);
                $manager->persist($block);
            }

            $priority++;
        }
        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            CmsFixture::class,
            UserFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['base'];
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
                    'content' => $this->getText('about_en')
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
                    'content' => $this->getText('about_de')
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
                    'content' => $this->getText('about_cn')
                ],
                null
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Hero,
                [
                    'headline' => 'International weiqi Club',
                    'subHeadline' => 'learn, play and have fun',
                    'text' => $this->getText('index_hero_en'),
                    'buttonLink' => '/register',
                    'buttonText' => 'Join us',
                    'color' => '#0700da',
                ],
                'hero-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::EventTeaser,
                [
                    'headline' => 'Welcome',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Hero,
                [
                    'headline' => 'Internationales weiqi Treffen',
                    'subHeadline' => 'Spiel, Spass und lernen',
                    'text' => $this->getText('index_hero_de'),
                    'buttonLink' => '/register',
                    'buttonText' => 'Mach mit',
                    'color' => '#0700da',
                ],
                'hero-de.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::GERMAN,
                CmsBlockTypes::EventTeaser,
                [
                    'headline' => 'Willkommen',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-de.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Hero,
                [
                    'headline' => '国际围棋大会',
                    'subHeadline' => '游戏、娱乐和学习',
                    'text' => $this->getText('index_hero_cn'),
                    'buttonLink' => '/register',
                    'buttonText' => '加入我们',
                    'color' => '#0700da',
                ],
                'hero-cn.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::CHINESE,
                CmsBlockTypes::EventTeaser,
                [
                    'headline' => '欢迎光临',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-cn.jpg',
            ],
        ];
    }
}
