<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CmsBlock;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\ImageType;
use App\Service\Media\ImageService;
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
        $priority = 1;
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
                $imageFile = __DIR__ . "/CmsBlock/{$imageName}";
                $uploadedImage = new UploadedFile($imageFile, $block->getId() . '.jpg');

                if ($type === CmsBlockType::Gallery) {
                    $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::CmsGallery);
                    $manager->persist($image);
                    $manager->flush();
                    $this->imageService->createThumbnails($image, ImageType::CmsGallery);

                    $json['images'][] = ['id' => $image->getId(), 'hash' => $image->getHash()];
                    $block->setJson($json);
                    $manager->persist($block);
                }

                if ($type !== CmsBlockType::Gallery) {
                    $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::CmsBlock);
                    $manager->flush();
                    $this->imageService->createThumbnails($image, ImageType::CmsBlock);
                    $block->setImage($image);
                    $manager->persist($block);
                }
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
                CmsBlockType::Headline,
                [
                    'title' => 'Imprint',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockType::Text,
                [
                    'title' => '1. Paragraph',
                    'content' => 'Some text p1',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::ENGLISH,
                CmsBlockType::Text,
                [
                    'title' => '2. Paragraph',
                    'content' => 'Some text p2',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::GERMAN,
                CmsBlockType::Headline,
                [
                    'title' => 'Impressum',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::GERMAN,
                CmsBlockType::Text,
                [
                    'title' => '1. Paragraf',
                    'content' => 'Etwas text p1',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::CHINESE,
                CmsBlockType::Headline,
                [
                    'title' => '版本说明',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::CHINESE,
                CmsBlockType::Text,
                [
                    'title' => '第 1 段',
                    'content' => '第一段的一些文字',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::FRENCH,
                CmsBlockType::Headline,
                [
                    'title' => 'Mentions légales',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::FRENCH,
                CmsBlockType::Text,
                [
                    'title' => '1. Paragraphe',
                    'content' => 'Un peu de texte p1',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::SPANISH,
                CmsBlockType::Headline,
                [
                    'title' => 'Aviso legal',
                ],
                null,
            ],
            [
                CmsFixture::IMPRINT,
                LanguageFixture::SPANISH,
                CmsBlockType::Text,
                [
                    'title' => '1. Párrafo',
                    'content' => 'Un poco de texto p1',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::ENGLISH,
                CmsBlockType::Headline,
                [
                    'title' => 'About',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::ENGLISH,
                CmsBlockType::Text,
                [
                    'content' => $this->getText('about_en'),
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::GERMAN,
                CmsBlockType::Headline,
                [
                    'title' => 'Über Uns',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::GERMAN,
                CmsBlockType::Text,
                [
                    'content' => $this->getText('about_de'),
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::CHINESE,
                CmsBlockType::Headline,
                [
                    'title' => '关于我们',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::CHINESE,
                CmsBlockType::Text,
                [
                    'content' => $this->getText('about_zh'),
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::FRENCH,
                CmsBlockType::Headline,
                [
                    'title' => 'À propos',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::FRENCH,
                CmsBlockType::Text,
                [
                    'content' => $this->getText('about_fr'),
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::SPANISH,
                CmsBlockType::Headline,
                [
                    'title' => 'Sobre nosotros',
                ],
                null,
            ],
            [
                CmsFixture::ABOUT,
                LanguageFixture::SPANISH,
                CmsBlockType::Text,
                [
                    'content' => $this->getText('about_es'),
                ],
                null,
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::ENGLISH,
                CmsBlockType::Hero,
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
                CmsBlockType::EventTeaser,
                [
                    'headline' => 'Welcome',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::GERMAN,
                CmsBlockType::Hero,
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
                CmsBlockType::EventTeaser,
                [
                    'headline' => 'Willkommen',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-de.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::CHINESE,
                CmsBlockType::Hero,
                [
                    'headline' => '国际围棋大会',
                    'subHeadline' => '游戏、娱乐和学习',
                    'text' => $this->getText('index_hero_zh'),
                    'buttonLink' => '/register',
                    'buttonText' => '加入我们',
                    'color' => '#0700da',
                ],
                'hero-zh.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::CHINESE,
                CmsBlockType::EventTeaser,
                [
                    'headline' => '欢迎光临',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-zh.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::FRENCH,
                CmsBlockType::Hero,
                [
                    'headline' => 'Club de weiqi international',
                    'subHeadline' => 'apprendre, jouer et s\'amuser',
                    'text' => $this->getText('index_hero_fr'),
                    'buttonLink' => '/register',
                    'buttonText' => 'Rejoins-nous',
                    'color' => '#0700da',
                ],
                'hero-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::FRENCH,
                CmsBlockType::EventTeaser,
                [
                    'headline' => 'Bienvenue',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::SPANISH,
                CmsBlockType::Hero,
                [
                    'headline' => 'Club de weiqi internacional',
                    'subHeadline' => 'aprender, jugar y divertirse',
                    'text' => $this->getText('index_hero_es'),
                    'buttonLink' => '/register',
                    'buttonText' => 'Únete',
                    'color' => '#0700da',
                ],
                'hero-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::SPANISH,
                CmsBlockType::EventTeaser,
                [
                    'headline' => 'Bienvenido',
                    'text' => $this->getText('index_events_lorem'),
                ],
                'group-en.jpg',
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::ENGLISH,
                CmsBlockType::FactsRow,
                [
                    'headline' => 'Why play with us',
                    'facts' => [
                        ['icon' => 'fa fa-users', 'label' => 'Active community'],
                        ['icon' => 'fa fa-globe', 'label' => 'Players worldwide'],
                        ['icon' => 'fa fa-graduation-cap', 'label' => 'Beginner-friendly'],
                        ['icon' => 'fa fa-trophy', 'label' => 'Regular tournaments'],
                    ],
                ],
                null,
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::GERMAN,
                CmsBlockType::FactsRow,
                [
                    'headline' => 'Warum mit uns spielen',
                    'facts' => [
                        ['icon' => 'fa fa-users', 'label' => 'Aktive Community'],
                        ['icon' => 'fa fa-globe', 'label' => 'Spieler weltweit'],
                        ['icon' => 'fa fa-graduation-cap', 'label' => 'Anfaengerfreundlich'],
                        ['icon' => 'fa fa-trophy', 'label' => 'Regelmaessige Turniere'],
                    ],
                ],
                null,
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::CHINESE,
                CmsBlockType::FactsRow,
                [
                    'headline' => '为什么和我们一起下棋',
                    'facts' => [
                        ['icon' => 'fa fa-users', 'label' => '活跃的社区'],
                        ['icon' => 'fa fa-globe', 'label' => '全球棋手'],
                        ['icon' => 'fa fa-graduation-cap', 'label' => '适合初学者'],
                        ['icon' => 'fa fa-trophy', 'label' => '定期比赛'],
                    ],
                ],
                null,
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::FRENCH,
                CmsBlockType::FactsRow,
                [
                    'headline' => 'Pourquoi jouer avec nous',
                    'facts' => [
                        ['icon' => 'fa fa-users', 'label' => 'Communauté active'],
                        ['icon' => 'fa fa-globe', 'label' => 'Joueurs dans le monde entier'],
                        ['icon' => 'fa fa-graduation-cap', 'label' => 'Adapté aux débutants'],
                        ['icon' => 'fa fa-trophy', 'label' => 'Tournois réguliers'],
                    ],
                ],
                null,
            ],
            [
                CmsFixture::INDEX,
                LanguageFixture::SPANISH,
                CmsBlockType::FactsRow,
                [
                    'headline' => 'Por qué jugar con nosotros',
                    'facts' => [
                        ['icon' => 'fa fa-users', 'label' => 'Comunidad activa'],
                        ['icon' => 'fa fa-globe', 'label' => 'Jugadores de todo el mundo'],
                        ['icon' => 'fa fa-graduation-cap', 'label' => 'Apto para principiantes'],
                        ['icon' => 'fa fa-trophy', 'label' => 'Torneos regulares'],
                    ],
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::ENGLISH,
                CmsBlockType::Headline,
                [
                    'title' => 'Game Rules',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::ENGLISH,
                CmsBlockType::Text,
                [
                    'title' => 'Introduction to Go',
                    'content' => 'Go (Weiqi in Chinese, Baduk in Korean) is an ancient board game for two players that originated in China over 2,500 years ago. The game is played on a 19×19 grid, though beginners often start with smaller 9×9 or 13×13 boards.',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::ENGLISH,
                CmsBlockType::Text,
                [
                    'title' => 'Basic Rules',
                    'content' => '1. Players alternate placing stones on empty intersections\n2. Stones are captured when surrounded (no liberties)\n3. The game ends when both players pass\n4. Winner is determined by controlled territory plus captures\n\nFor detailed rules and strategy guides, join our beginner workshops!',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::GERMAN,
                CmsBlockType::Headline,
                [
                    'title' => 'Spielregeln',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::GERMAN,
                CmsBlockType::Text,
                [
                    'title' => 'Einführung in Go',
                    'content' => 'Go (Weiqi auf Chinesisch, Baduk auf Koreanisch) ist ein altes Brettspiel für zwei Spieler, das vor über 2.500 Jahren in China entstand. Das Spiel wird auf einem 19×19-Gitter gespielt, obwohl Anfänger oft mit kleineren 9×9- oder 13×13-Brettern beginnen.',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::GERMAN,
                CmsBlockType::Text,
                [
                    'title' => 'Grundregeln',
                    'content' => '1. Spieler platzieren abwechselnd Steine auf leeren Schnittpunkten\n2. Steine werden gefangen, wenn sie umzingelt sind (keine Freiheiten)\n3. Das Spiel endet, wenn beide Spieler passen\n4. Der Gewinner wird durch kontrolliertes Gebiet plus Gefangene bestimmt\n\nFür detaillierte Regeln und Strategieanleitungen besuchen Sie unsere Anfänger-Workshops!',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::CHINESE,
                CmsBlockType::Headline,
                [
                    'title' => '游戏规则',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::CHINESE,
                CmsBlockType::Text,
                [
                    'title' => '围棋简介',
                    'content' => '围棋（中文称围棋，韩文称바둑）是一种起源于中国2500多年前的古老双人棋盘游戏。游戏在19×19的棋盘上进行，虽然初学者通常从较小的9×9或13×13棋盘开始。',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::CHINESE,
                CmsBlockType::Text,
                [
                    'title' => '基本规则',
                    'content' => '1. 玩家轮流在空交叉点上放置棋子\n2. 当棋子被包围时（无气）会被吃掉\n3. 当双方都选择弃权时游戏结束\n4. 赢家由控制的地盘加上吃掉的棋子数决定\n\n详细规则和策略指南，请参加我们的初学者工作坊！',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::FRENCH,
                CmsBlockType::Headline,
                [
                    'title' => 'Règles du jeu',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::FRENCH,
                CmsBlockType::Text,
                [
                    'title' => 'Introduction au Go',
                    'content' => 'Le Go (Weiqi en chinois, Baduk en coréen) est un ancien jeu de plateau pour deux joueurs, originaire de Chine il y a plus de 2 500 ans. Le jeu se joue sur une grille de 19×19, bien que les débutants commencent souvent avec des plateaux plus petits de 9×9 ou 13×13.',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::FRENCH,
                CmsBlockType::Text,
                [
                    'title' => 'Règles de base',
                    'content' => '1. Les joueurs placent à tour de rôle des pierres sur les intersections vides\n2. Les pierres sont capturées lorsqu\'elles sont encerclées (plus de libertés)\n3. La partie se termine lorsque les deux joueurs passent\n4. Le gagnant est déterminé par le territoire contrôlé plus les captures\n\nPour des règles détaillées et des guides de stratégie, rejoins nos ateliers pour débutants !',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::SPANISH,
                CmsBlockType::Headline,
                [
                    'title' => 'Reglas del juego',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::SPANISH,
                CmsBlockType::Text,
                [
                    'title' => 'Introducción al Go',
                    'content' => 'El Go (Weiqi en chino, Baduk en coreano) es un antiguo juego de tablero para dos jugadores originado en China hace más de 2.500 años. El juego se juega en un tablero de 19×19, aunque los principiantes a menudo empiezan con tableros más pequeños de 9×9 o 13×13.',
                ],
                null,
            ],
            [
                CmsFixture::RULES,
                LanguageFixture::SPANISH,
                CmsBlockType::Text,
                [
                    'title' => 'Reglas básicas',
                    'content' => '1. Los jugadores colocan piedras por turnos en las intersecciones vacías\n2. Las piedras se capturan cuando están rodeadas (sin libertades)\n3. La partida termina cuando ambos jugadores pasan\n4. El ganador se determina por el territorio controlado más las capturas\n\n¡Para reglas detalladas y guías de estrategia, únete a nuestros talleres para principiantes!',
                ],
                null,
            ],

            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::ENGLISH,
                CmsBlockType::Text,
                [
                    'content' => 'We are excited to announce the launch of our new website version. Enjoy a better experience and new features!',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::ENGLISH,
                CmsBlockType::Gallery,
                ['title' => '', 'images' => []],
                'screenshot-en.png',
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::GERMAN,
                CmsBlockType::Text,
                [
                    'content' => 'Wir freuen uns, den Start unserer neuen Website-Version bekannt zu geben. Genießen Sie eine bessere Benutzererfahrung und neue Funktionen!',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::GERMAN,
                CmsBlockType::Gallery,
                ['title' => '', 'images' => []],
                'screenshot-de.png',
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::CHINESE,
                CmsBlockType::Text,
                [
                    'content' => '我们很高兴地宣布新版本网站正式上线。享受更好的体验和更多新功能！',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::CHINESE,
                CmsBlockType::Gallery,
                ['title' => '', 'images' => []],
                'screenshot-zh.png',
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::FRENCH,
                CmsBlockType::Text,
                [
                    'content' => 'Nous sommes ravis d\'annoncer le lancement de la nouvelle version de notre site web. Profite d\'une meilleure expérience et de nouvelles fonctionnalités !',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::FRENCH,
                CmsBlockType::Gallery,
                ['title' => '', 'images' => []],
                'screenshot-en.png',
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::SPANISH,
                CmsBlockType::Text,
                [
                    'content' => '¡Estamos encantados de anunciar el lanzamiento de la nueva versión de nuestro sitio web! ¡Disfruta de una mejor experiencia y de nuevas funciones!',
                ],
                null,
            ],
            [
                CmsFixture::ANNOUNCEMENT,
                LanguageFixture::SPANISH,
                CmsBlockType::Gallery,
                ['title' => '', 'images' => []],
                'screenshot-en.png',
            ],
        ];
    }

    public static function getGroups(): array
    {
        return ['base'];
    }
}
