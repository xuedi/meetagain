<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\EmailTemplate;
use App\Service\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class EmailTemplateFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly EmailTemplateService $templateService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ($this->templateService->getDefaultTemplates() as $identifier => $data) {
            $template = new EmailTemplate();
            $template->setIdentifier($identifier);
            $template->setSubject($data['subject']);
            $template->setBody($data['body']);
            $template->setAvailableVariables($data['variables']);
            $template->setUpdatedAt(new DateTimeImmutable());

            $manager->persist($template);
        }
        $manager->flush();
        $this->stop();
    }

    public static function getGroups(): array
    {
        return ['install'];
    }
}
