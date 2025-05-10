<?php declare(strict_types=1);

namespace App\DataFixtures;

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
        foreach ($this->getData() as [$page, $lang, $prio, $type, $json]) {
            $block = new CmsBlock();
            $block->setPage($this->getReference('cms_' . md5((string)$page)));
            $block->setLanguage($lang);
            $block->setPriority($prio);
            $block->setType($type);
            $block->setJson($json);

            $manager->persist($block);
        }
        $manager->flush();
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
            ['imprint', 'en', 1, CmsBlockTypes::Headline, ['title' => 'Imprint']],
            ['imprint', 'en', 2, CmsBlockTypes::Text, ['title' => '1. Paragraph', 'content' => 'Some text p1']],
            ['imprint', 'en', 3, CmsBlockTypes::Text, ['title' => '2. Paragraph', 'content' => 'Some text p2']],
            ['imprint', 'de', 1, CmsBlockTypes::Headline, ['title' => 'Impressum']],
            ['imprint', 'de', 2, CmsBlockTypes::Text, ['title' => '1. Paragraf', 'content' => 'Etwas text p1']],
            ['imprint', 'cn', 1, CmsBlockTypes::Headline, ['title' => '版本说明']],
            ['imprint', 'cn', 2, CmsBlockTypes::Text, ['title' => '第 1 段', 'content' => '第一段的一些文字']],
            ['about', 'en', 1, CmsBlockTypes::Headline, ['title' => 'About']],
            ['about', 'en', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('about_en')]],
            ['about', 'de', 1, CmsBlockTypes::Headline, ['title' => 'Über Uns']],
            ['about', 'de', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('about_de')]],
            ['about', 'cn', 1, CmsBlockTypes::Headline, ['title' => '关于我们']],
            ['about', 'cn', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('about_cn')]],
            ['index', 'en', 1, CmsBlockTypes::Headline, ['title' => 'Welcome']],
            ['index', 'en', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('welcome_en')]],
            ['index', 'de', 1, CmsBlockTypes::Headline, ['title' => 'Willkommen']],
            ['index', 'de', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('welcome_de')]],
            ['index', 'cn', 1, CmsBlockTypes::Headline, ['title' => '你好']],
            ['index', 'cn', 2, CmsBlockTypes::Text, ['content' => $this->getBlob('welcome_cn')]],
        ];
    }

    private function getBlob(string $string): string
    {
        return $this->fs->readFile(__DIR__ . "/blobs/$string.txt");
    }
}
