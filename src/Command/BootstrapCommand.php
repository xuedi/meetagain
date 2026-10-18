<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Cms;
use App\Entity\CmsTitle;
use App\Entity\User;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\CmsRepository;
use App\Repository\UserRepository;
use App\Service\Cms\CmsBlockService;
use App\Service\Cms\CmsService;
use App\Service\Config\ConfigService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(name: 'app:bootstrap', description: 'Seed an empty database, create the admin and print a one-time login link')]
class BootstrapCommand extends Command
{
    private const string LINK_LIFETIME = '+24 hours';
    private const string STARTER_SLUG = 'index';

    private const array STARTER_PAGE = [
        'en' => [
            'title' => 'Welcome',
            'text' => '<p>This is your new MeetAgain site. Sign in as the admin to replace this page under Admin, CMS, plan your first event and switch on the plugins you want under Admin, Plugins.</p>',
            'events' => 'Upcoming events',
            'eventsText' => 'Come along to one of our next meetups.',
        ],
        'de' => [
            'title' => 'Willkommen',
            'text' => '<p>Das ist deine neue MeetAgain-Seite. Melde dich als Admin an, um diese Seite unter Admin, CMS zu ersetzen, plane deine erste Veranstaltung und schalte unter Admin, Plugins die Plugins ein, die du brauchst.</p>',
            'events' => 'Kommende Veranstaltungen',
            'eventsText' => 'Komm zu einem unserer nächsten Treffen.',
        ],
        'zh' => [
            'title' => '欢迎',
            'text' => '<p>这是你的新 MeetAgain 网站。以管理员身份登录，在“管理 - CMS”中替换此页面，安排你的第一个活动，并在“管理 - 插件”中启用需要的插件。</p>',
            'events' => '即将举行的活动',
            'eventsText' => '欢迎参加我们的下一次聚会。',
        ],
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ConfigService $configService,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly CmsRepository $cmsRepository,
        private readonly CmsBlockService $blockService,
        private readonly CmsService $cmsService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email of the admin to create');
        $this->addOption('site-url', null, InputOption::VALUE_REQUIRED, 'Public address of the site, e.g. https://events.example.org');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name of the admin', 'Admin');
        $this->addOption('no-starter-page', null, InputOption::VALUE_NONE, 'Leave the home page to a plugin instead of creating a starter page');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getOption('email')));
        $site = parse_url(rtrim(trim((string) $input->getOption('site-url')), '/'));
        $scheme = $site['scheme'] ?? '';
        $host = ($site['host'] ?? '') . (isset($site['port']) ? ':' . $site['port'] : '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !in_array($scheme, ['http', 'https'], true) || $host === '' || isset($site['path'])) {
            $io->error('--email needs a valid address and --site-url an address like https://events.example.org.');

            return Command::INVALID;
        }
        if ($this->userRepository->count(['role' => UserRole::Admin]) > 0) {
            $io->error('This database already has an admin - nothing to do.');

            return Command::FAILURE;
        }

        $seeded = $this->getApplication()?->find('app:install:seed')->run(new ArrayInput([]), $output) ?? Command::FAILURE;
        if ($seeded !== Command::SUCCESS) {
            return $seeded;
        }

        $siteUrl = $scheme . '://' . $host;
        $this->configService->setString('website_url', $host);
        $this->configService->setString('website_host', $siteUrl);
        $this->configService->setString('email_sender_mail', 'noreply@' . ($site['host'] ?? $host));

        $admin = $this->createAdmin($email, (string) $input->getOption('name'));
        $path = $this->urlGenerator->generate('app_reset_password', ['_locale' => 'en', 'code' => $admin->getRegcode()]);
        if (!$input->getOption('no-starter-page') && $this->cmsRepository->findOneBy(['slug' => self::STARTER_SLUG]) === null) {
            $this->createStarterPage($admin);
        }

        $baseline = $this->getApplication()?->find('app:data-hotfix:baseline')->run(new ArrayInput([]), $output) ?? Command::FAILURE;

        $io->success(sprintf('Created admin %s. Open this link within 24 hours to set the password:', $email));
        $output->writeln($siteUrl . $path);

        return $baseline;
    }

    private function createAdmin(string $email, string $name): User
    {
        $code = bin2hex(random_bytes(32));
        $now = $this->clock->now();

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setLocale('en');
        $user->setRole(UserRole::Admin);
        $user->setStatus(UserStatus::Active);
        $user->setPublic(true);
        $user->setVerified(true);
        $user->setRestricted(false);
        $user->setOsmConsent(false);
        $user->setTagging(true);
        $user->setNotification(false);
        $user->setCreatedAt($now);
        $user->setLastLogin(DateTime::createFromImmutable($now));
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setRegcode($code);
        $user->setRegcodeExpiresAt($now->modify(self::LINK_LIFETIME));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createStarterPage(User $author): void
    {
        $page = new Cms();
        $page->setSlug(self::STARTER_SLUG);
        $page->setPublished(true);
        $page->setLocked(false);
        $page->setCreatedBy($author);
        $page->setCreatedAt($this->clock->now());
        foreach (self::STARTER_PAGE as $locale => $copy) {
            $page->addTitle(
                new CmsTitle()
                    ->setLanguage($locale)
                    ->setTitle($copy['title']),
            );
        }
        $this->em->persist($page);
        $this->em->flush();

        foreach (self::STARTER_PAGE as $locale => $copy) {
            $this->blockService->createBlock($page, $locale, CmsBlockType::Headline, ['title' => $copy['title']]);
            $this->blockService->createBlock($page, $locale, CmsBlockType::Text, ['content' => $copy['text']]);
            $this->blockService->createBlock($page, $locale, CmsBlockType::EventTeaser, ['headline' => $copy['events'], 'text' => $copy['eventsText']]);
        }
        $this->cmsService->invalidateAll();
    }
}
