<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CmsBlock;
use App\Entity\CmsBlockTypes;
use App\Entity\ImageType;
use App\Service\ImageService;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CmsBlockFixture extends AbstractFixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->start();
        $priority = 1; // it is OK to increase the priority of the blocks
        $importUser = $this->getRefUser(UserFixture::ADMIN);
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
                $this->imageService->createThumbnails($image, ImageType::CmsBlock);

                // associate image with a user
                $block->setImage($image);
                $manager->persist($block);
            }

            ++$priority;
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

    private function getData(): array
    {
        return [
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Imprint',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'title' => '1. Paragraph',
                    'content' => 'Some text p1',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'title' => '2. Paragraph',
                    'content' => 'Some text p2',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Impressum',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Text,
                [
                    'title' => '1. Paragraf',
                    'content' => 'Etwas text p1',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Headline,
                [
                    'title' => '版本说明',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Text,
                [
                    'title' => '第 1 段',
                    'content' => '第一段的一些文字',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Headline,
                [
                    'title' => 'About',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'content' => $this->getText('about_en'),
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Über Uns',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Text,
                [
                    'content' => $this->getText('about_de'),
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Headline,
                [
                    'title' => '关于我们',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Text,
                [
                    'content' => $this->getText('about_cn'),
                ],
                null,
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
            // ========== Rules Page ==========
            [
                CmsFixture::RULES,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Game Rules',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'title' => 'Introduction to Go',
                    'content' => 'Go (Weiqi in Chinese, Baduk in Korean) is an ancient board game for two players that originated in China over 2,500 years ago. The game is played on a 19×19 grid, though beginners often start with smaller 9×9 or 13×13 boards.',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'title' => 'Basic Rules',
                    'content' => '1. Players alternate placing stones on empty intersections\n2. Stones are captured when surrounded (no liberties)\n3. The game ends when both players pass\n4. Winner is determined by controlled territory plus captures\n\nFor detailed rules and strategy guides, join our beginner workshops!',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Headline,
                [
                    'title' => 'Spielregeln',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Text,
                [
                    'title' => 'Einführung in Go',
                    'content' => 'Go (Weiqi auf Chinesisch, Baduk auf Koreanisch) ist ein altes Brettspiel für zwei Spieler, das vor über 2.500 Jahren in China entstand. Das Spiel wird auf einem 19×19-Gitter gespielt, obwohl Anfänger oft mit kleineren 9×9- oder 13×13-Brettern beginnen.',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Text,
                [
                    'title' => 'Grundregeln',
                    'content' => '1. Spieler platzieren abwechselnd Steine auf leeren Schnittpunkten\n2. Steine werden gefangen, wenn sie umzingelt sind (keine Freiheiten)\n3. Das Spiel endet, wenn beide Spieler passen\n4. Der Gewinner wird durch kontrolliertes Gebiet plus Gefangene bestimmt\n\nFür detaillierte Regeln und Strategieanleitungen besuchen Sie unsere Anfänger-Workshops!',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Headline,
                [
                    'title' => '游戏规则',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Text,
                [
                    'title' => '围棋简介',
                    'content' => '围棋（中文称围棋，韩文称바둑）是一种起源于中国2500多年前的古老双人棋盘游戏。游戏在19×19的棋盘上进行，虽然初学者通常从较小的9×9或13×13棋盘开始。',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Text,
                [
                    'title' => '基本规则',
                    'content' => '1. 玩家轮流在空交叉点上放置棋子\n2. 当棋子被包围时（无气）会被吃掉\n3. 当双方都选择弃权时游戏结束\n4. 赢家由控制的地盘加上吃掉的棋子数决定\n\n详细规则和策略指南，请参加我们的初学者工作坊！',
                ],
                null,
            ],

            // ========== Announcement Page ==========
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Text,
                [
                    'content' => 'We are excited to announce the launch of our new website version. Enjoy a better experience and new features!',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::ENGLISH,
                CmsBlockTypes::Image,
                [
                    'id' => 'announcement-en',
                ],
                'screenshot-en.png',
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Text,
                [
                    'content' => 'Wir freuen uns, den Start unserer neuen Website-Version bekannt zu geben. Genießen Sie eine bessere Benutzererfahrung und neue Funktionen!',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::GERMAN,
                CmsBlockTypes::Image,
                [
                    'id' => 'announcement-de',
                ],
                'screenshot-de.png',
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Text,
                [
                    'content' => '我们很高兴地宣布新版本网站正式上线。享受更好的体验和更多新功能！',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::CHINESE,
                CmsBlockTypes::Image,
                [
                    'id' => 'announcement-cn',
                ],
                'screenshot-cn.png',
            ],
        ];
    }

    public static function getGroups(): array
    {
        return ['base'];
    }
}
