<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\AppState;
use App\Entity\Config;
use App\Entity\Language;
use App\Entity\PronunciationSystem;
use App\Entity\User;
use App\Enum\ConfigType;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Service\Config\LanguageService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:install:seed', description: 'Seeds the rows every installation needs; rows already present are kept')]
class InstallSeedCommand extends Command
{
    private const string IMPORT_USER = 'import';
    private const array SYSTEM_USERS = [self::IMPORT_USER, 'cron'];

    private const array LANGUAGES = [
        ['code' => 'en', 'name' => 'English', 'sortOrder' => 1, 'enabled' => true],
        ['code' => 'de', 'name' => 'German', 'sortOrder' => 2, 'enabled' => true],
        ['code' => 'zh', 'name' => 'Chinese', 'sortOrder' => 3, 'enabled' => true],

        ['code' => 'fr', 'name' => 'French', 'sortOrder' => 10],
        ['code' => 'es', 'name' => 'Spanish', 'sortOrder' => 11],
        ['code' => 'it', 'name' => 'Italian', 'sortOrder' => 12],
        ['code' => 'pt', 'name' => 'Portuguese', 'sortOrder' => 13],
        ['code' => 'nl', 'name' => 'Dutch', 'sortOrder' => 14],
        ['code' => 'pl', 'name' => 'Polish', 'sortOrder' => 15],
        ['code' => 'ru', 'name' => 'Russian', 'sortOrder' => 16],
        ['code' => 'ja', 'name' => 'Japanese', 'sortOrder' => 17],
        ['code' => 'ko', 'name' => 'Korean', 'sortOrder' => 18],
        ['code' => 'ar', 'name' => 'Arabic', 'sortOrder' => 19],
        ['code' => 'tr', 'name' => 'Turkish', 'sortOrder' => 20],
        ['code' => 'sv', 'name' => 'Swedish', 'sortOrder' => 21],
        ['code' => 'no', 'name' => 'Norwegian', 'sortOrder' => 22],
        ['code' => 'da', 'name' => 'Danish', 'sortOrder' => 23],
        ['code' => 'fi', 'name' => 'Finnish', 'sortOrder' => 24],
        ['code' => 'uk', 'name' => 'Ukrainian', 'sortOrder' => 25],
        ['code' => 'cs', 'name' => 'Czech', 'sortOrder' => 26],
        ['code' => 'hu', 'name' => 'Hungarian', 'sortOrder' => 27],
        ['code' => 'ro', 'name' => 'Romanian', 'sortOrder' => 28],
        ['code' => 'el', 'name' => 'Greek', 'sortOrder' => 29],
        ['code' => 'bg', 'name' => 'Bulgarian', 'sortOrder' => 30],
        ['code' => 'hr', 'name' => 'Croatian', 'sortOrder' => 31],
        ['code' => 'sk', 'name' => 'Slovak', 'sortOrder' => 32],
        ['code' => 'hi', 'name' => 'Hindi', 'sortOrder' => 33],
        ['code' => 'vi', 'name' => 'Vietnamese', 'sortOrder' => 34],
        ['code' => 'id', 'name' => 'Indonesian', 'sortOrder' => 35],
        ['code' => 'th', 'name' => 'Thai', 'sortOrder' => 36],
        ['code' => 'ms', 'name' => 'Malay', 'sortOrder' => 37],
        ['code' => 'he', 'name' => 'Hebrew', 'sortOrder' => 38],
        ['code' => 'fa', 'name' => 'Persian', 'sortOrder' => 39],
        ['code' => 'sr', 'name' => 'Serbian', 'sortOrder' => 40],
        ['code' => 'sl', 'name' => 'Slovenian', 'sortOrder' => 41],
        ['code' => 'lt', 'name' => 'Lithuanian', 'sortOrder' => 42],
        ['code' => 'lv', 'name' => 'Latvian', 'sortOrder' => 43],
        ['code' => 'et', 'name' => 'Estonian', 'sortOrder' => 44],
        ['code' => 'is', 'name' => 'Icelandic', 'sortOrder' => 45],
        ['code' => 'af', 'name' => 'Afrikaans', 'sortOrder' => 46],
        ['code' => 'sw', 'name' => 'Swahili', 'sortOrder' => 47],
        ['code' => 'bn', 'name' => 'Bengali', 'sortOrder' => 48],
        ['code' => 'ur', 'name' => 'Urdu', 'sortOrder' => 49],
        ['code' => 'ta', 'name' => 'Tamil', 'sortOrder' => 50],
        ['code' => 'my', 'name' => 'Burmese', 'sortOrder' => 51],
        ['code' => 'km', 'name' => 'Khmer', 'sortOrder' => 52],
        ['code' => 'ka', 'name' => 'Georgian', 'sortOrder' => 53],
        ['code' => 'hy', 'name' => 'Armenian', 'sortOrder' => 54],
        ['code' => 'az', 'name' => 'Azerbaijani', 'sortOrder' => 55],
        ['code' => 'be', 'name' => 'Belarusian', 'sortOrder' => 56],
        ['code' => 'mk', 'name' => 'Macedonian', 'sortOrder' => 57],
        ['code' => 'sq', 'name' => 'Albanian', 'sortOrder' => 58],
        ['code' => 'mn', 'name' => 'Mongolian', 'sortOrder' => 59],
        ['code' => 'si', 'name' => 'Sinhala', 'sortOrder' => 60],
        ['code' => 'lo', 'name' => 'Lao', 'sortOrder' => 61],
        ['code' => 'ga', 'name' => 'Irish', 'sortOrder' => 62],
        ['code' => 'cy', 'name' => 'Welsh', 'sortOrder' => 63],
        ['code' => 'mt', 'name' => 'Maltese', 'sortOrder' => 64],
        ['code' => 'ca', 'name' => 'Catalan', 'sortOrder' => 65],
        ['code' => 'eu', 'name' => 'Basque', 'sortOrder' => 66],
        ['code' => 'gl', 'name' => 'Galician', 'sortOrder' => 67],
        ['code' => 'lb', 'name' => 'Luxembourgish', 'sortOrder' => 68],
        ['code' => 'rm', 'name' => 'Romansh', 'sortOrder' => 69],
        ['code' => 'fo', 'name' => 'Faroese', 'sortOrder' => 70],
        ['code' => 'fy', 'name' => 'Frisian', 'sortOrder' => 71],
    ];

    private const array CONFIG = [
        ['automatic_registration',      'false',                   ConfigType::Boolean],
        ['show_town_hall',              'false',                   ConfigType::Boolean],
        ['send_rsvp_notifications',     'false',                   ConfigType::Boolean],
        ['send_admin_notification',     'true',                    ConfigType::Boolean],
        ['email_delivery_sync_enabled', 'false',                   ConfigType::Boolean],
        ['send_event_reminders',        'false',                   ConfigType::Boolean],
        ['send_upcoming_digest',        'false',                   ConfigType::Boolean],
        ['email_sender_mail',           'email@localhost',         ConfigType::String],
        ['email_sender_name',           'localhost',               ConfigType::String],
        ['website_url',                 'meetagain.local',         ConfigType::String],
        ['website_host',                'https://meetagain.local', ConfigType::String],
        ['date_format',                 'Y-m-d H:i',               ConfigType::String],
        ['tdm_reservation',             'true',                    ConfigType::Boolean],
    ];

    private const array PRONUNCIATION_SYSTEMS = [
        ['Chinese / Mandarin', 'Pinyin',               'má po dòu fu'],
        ['Japanese',           'Rōmaji',               'ra-men'],
        ['Arabic',             'Romanisation',         'kus-kus'],
        ['Korean',             'Revised Romanisation', 'bi-bim-bap'],
        ['Thai',               'RTGS',                 'phàt thai'],
        ['Hindi',              'IAST',                 'bi-ryā-nī'],
        ['Greek',              'Greeklish',            'mu-sa-kás'],
    ];

    private const array APP_STATE = [
        'footer_col1_title' => 'Help',
        'footer_col2_title' => 'Platform',
        'footer_col3_title' => 'Social',
        'footer_col4_title' => 'Legal',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly LanguageService $languageService,
        #[Autowire(service: 'cache.config')]
        private readonly CacheItemPoolInterface $configPool,
        #[Autowire(service: 'cache.app_state')]
        private readonly CacheItemPoolInterface $appStatePool,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->seedSystemUsers();
        $languages = $this->seedLanguages();
        $configRows = $this->seedConfig();
        $pronunciationSystems = $this->seedPronunciationSystems();
        $appStateRows = $this->seedAppState();
        $this->em->flush();

        $this->languageService->invalidateCache();
        $this->configPool->clear();
        $this->appStatePool->clear();

        $output->writeln(sprintf(
            'Created %d system users, %d languages, %d config rows, %d pronunciation systems and %d app state rows.',
            $users,
            $languages,
            $configRows,
            $pronunciationSystems,
            $appStateRows,
        ));

        return $this->getApplication()->find('app:email-templates:seed')->run(new ArrayInput([]), $output);
    }

    private function seedSystemUsers(): int
    {
        $created = 0;
        foreach (self::SYSTEM_USERS as $name) {
            if ($this->findSystemUser($name) instanceof User) {
                continue;
            }

            $user = new User();
            $user->setVerified(true);
            $user->setStatus(UserStatus::Active);
            $user->setName($name);
            $user->setEmail($name . '@example.com');
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));
            $user->setPublic(false);
            $user->setTagging(false);
            $user->setRestricted(false);
            $user->setNotification(false);
            $user->setBio(null);
            $user->setOsmConsent(false);
            $user->setLocale('en');
            $user->setRole(UserRole::System);
            $user->setCreatedAt(new DateTimeImmutable());
            $user->setLastLogin(new DateTime());

            $this->em->persist($user);
            ++$created;
        }
        $this->em->flush();

        return $created;
    }

    private function seedLanguages(): int
    {
        $existing = array_map(static fn(Language $language): ?string => $language->getCode(), $this->em->getRepository(Language::class)->findAll());

        $created = 0;
        foreach (self::LANGUAGES as $row) {
            if (in_array($row['code'], $existing, true)) {
                continue;
            }

            $language = new Language();
            $language->setCode($row['code']);
            $language->setName($row['name']);
            $language->setEnabled($row['enabled'] ?? false);
            $language->setSortOrder($row['sortOrder']);

            $this->em->persist($language);
            ++$created;
        }

        return $created;
    }

    private function seedConfig(): int
    {
        $existing = array_map(static fn(Config $config): ?string => $config->getName(), $this->em->getRepository(Config::class)->findAll());
        $systemUserId = (string) $this->findSystemUser(self::IMPORT_USER)?->getId();

        $created = 0;
        foreach ([...self::CONFIG, ['system_user_id', $systemUserId, ConfigType::Integer]] as [$name, $value, $type]) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $config = new Config();
            $config->setName($name);
            $config->setValue($value);
            $config->setType($type);

            $this->em->persist($config);
            ++$created;
        }

        return $created;
    }

    private function seedPronunciationSystems(): int
    {
        $existing = array_map(
            static fn(PronunciationSystem $system): string => $system->getLanguage() . '/' . $system->getName(),
            $this->em->getRepository(PronunciationSystem::class)->findAll(),
        );

        $created = 0;
        foreach (self::PRONUNCIATION_SYSTEMS as [$language, $name, $example]) {
            if (in_array($language . '/' . $name, $existing, true)) {
                continue;
            }

            $system = new PronunciationSystem();
            $system->setLanguage($language);
            $system->setName($name);
            $system->setExample($example);

            $this->em->persist($system);
            ++$created;
        }

        return $created;
    }

    private function seedAppState(): int
    {
        $existing = array_map(static fn(AppState $entry): string => $entry->getKeyName(), $this->em->getRepository(AppState::class)->findAll());

        $created = 0;
        foreach (self::APP_STATE as $key => $value) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            $this->em->persist(new AppState($key, $value, new DateTimeImmutable()));
            ++$created;
        }

        return $created;
    }

    private function findSystemUser(string $name): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $name . '@example.com']);
    }
}
