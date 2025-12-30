<?php declare(strict_types=1);

/**
 * MeetAgain Web Installer.
 *
 * Standalone installer that runs before composer install.
 * Handles system requirements checking, database setup, mail configuration,
 * and initial application installation.
 *
 * @author MeetAgain Team
 */
class Installer
{
    private const string LOCK_FILE = '../../installed.lock';
    private const string ENV_FILE = '../../.env';
    private const string ENV_DIST = '../../.env.dist';

    private array $errors = [];
    private array $session = [];
    private TemplateRenderer $renderer;
    private SystemRequirements $systemRequirements;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('MEETAGAIN_INSTALLER');
            session_start();
        }
        $this->session = &$_SESSION;
        $this->renderer = new TemplateRenderer(__DIR__ . '/templates');
        $this->systemRequirements = new SystemRequirements();
    }

    public function isInstalled(): bool
    {
        return file_exists(__DIR__ . '/' . self::LOCK_FILE);
    }

    public function hasEnvFile(): bool
    {
        return file_exists(__DIR__ . '/' . self::ENV_FILE);
    }

    public function getCurrentStep(): int
    {
        if ($this->isInstalled()) {
            return 0;
        }

        return (int) ($this->session['install_step'] ?? 1);
    }

    public function setStep(int $step): void
    {
        $this->session['install_step'] = $step;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    // CSRF Protection
    public function generateCsrfToken(): string
    {
        if (!isset($this->session['csrf_token'])) {
            $this->session['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $this->session['csrf_token'];
    }

    public function validateCsrfToken(string $token): bool
    {
        return isset($this->session['csrf_token']) && hash_equals($this->session['csrf_token'], $token);
    }

    // Session data management
    public function setSessionData(string $key, mixed $value): void
    {
        $this->session['install_data'][$key] = $value;
    }

    public function getSessionData(string $key, mixed $default = null): mixed
    {
        return $this->session['install_data'][$key] ?? $default;
    }

    public function getAllSessionData(): array
    {
        return $this->session['install_data'] ?? [];
    }

    /**
     * Store multiple values in session data.
     *
     * @param array<string, mixed> $data Key-value pairs to store
     */
    public function storeSessionData(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->setSessionData($key, $value);
        }
    }

    /**
     * Handle form validation and conditional redirect.
     *
     * If there are errors, stores the provided data and calls the error handler.
     * If successful, stores the data and redirects to the next step.
     *
     * @param array<string, mixed> $data
     */
    public function handleFormResult(array $data, ?int $nextStep = null, ?callable $onError = null): void
    {
        $this->storeSessionData($data);

        if ($this->hasErrors()) {
            if ($onError !== null) {
                $onError();
            }

            return;
        }

        if ($nextStep !== null) {
            $this->setStep($nextStep);
            header("Location: ?step={$nextStep}");
            exit;
        }
    }

    /**
     * Check system requirements for MeetAgain installation.
     *
     * @return array<string, array{name: string, passed: bool, current: string, optional?: bool}>
     */
    public function checkRequirements(): array
    {
        return $this->systemRequirements->check();
    }

    /**
     * Check if all non-optional requirements pass.
     */
    public function allRequirementsPassed(): bool
    {
        return $this->systemRequirements->allRequirementsPassed($this->checkRequirements());
    }

    /**
     * Test database connection with provided credentials.
     */
    public function testDatabaseConnection(string $host, int $port, string $name, string $user, string $password): bool
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1');

            return true;
        } catch (PDOException $e) {
            $this->addError('Database connection failed: ' . $e->getMessage());

            return false;
        }
    }

    // Generate .env file
    public function generateEnvFile(): bool
    {
        $data = $this->getAllSessionData();

        $envContent = <<<ENV
# Generated by MeetAgain Installer
# {$this->getTimestamp()}

APP_ENV=prod
APP_HOST="{$data['site_url']}"
APP_SECRET={$this->generateSecret()}

# Docker settings (auto-detected)
HOST_USERNAME={$this->getCurrentUser()}
HOST_UID={$this->getCurrentUid()}
HOST_GID={$this->getCurrentGid()}

# Database
DATABASE_URL="mysql://{$data['db_user']}:{$data['db_password']}@{$data['db_host']}:{$data['db_port']}/{$data['db_name']}?charset=utf8mb4"

# MariaDB components (to avoid warnings)
MARIADB_ROOT_PASSWORD={$data['db_password']}
MARIADB_DATABASE={$data['db_name']}
MARIADB_HOST={$data['db_host']}
MARIADB_USER={$data['db_user']}
MARIADB_PASSWORD={$data['db_password']}

# Mail
MAILER_DSN={$data['mailer_dsn']}

# Optional services
SENTRY_DSN=

ENV;

        $envPath = __DIR__ . '/' . self::ENV_FILE;
        $result = file_put_contents($envPath, $envContent);

        if ($result === false) {
            $this->addError('Failed to write .env file');

            return false;
        }

        // Restrict .env file permissions to owner-only (0600)
        chmod($envPath, 0600);

        return true;
    }

    // Run composer install
    public function runComposerInstall(): bool
    {
        $projectRoot = realpath(__DIR__ . '/../../');
        $output = [];
        $returnCode = 0;

        // Change to project root and run composer
        $command = sprintf(
            'cd %s && composer install --no-dev --optimize-autoloader 2>&1',
            escapeshellarg($projectRoot)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->addError('Composer install failed: ' . implode("\n", $output));

            return false;
        }

        return true;
    }

    // Run database migrations
    public function runMigrations(): bool
    {
        $projectRoot = realpath(__DIR__ . '/../../');
        $output = [];
        $returnCode = 0;

        $command = sprintf(
            'cd %s && php bin/console doctrine:migrations:migrate --no-interaction 2>&1',
            escapeshellarg($projectRoot)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->addError('Migrations failed: ' . implode("\n", $output));

            return false;
        }

        return true;
    }

    /**
     * Generic user creation method.
     *
     * Creates a user with provided data, merging with sensible defaults.
     * Automatically hashes passwords using bcrypt.
     *
     * @param array<string, mixed> $userData
     */
    private function createUser(PDO $pdo, array $userData): int
    {
        $now = date('Y-m-d H:i:s');

        $defaults = [
            'name' => 'User',
            'email' => 'user@localhost',
            'roles' => ['ROLE_USER'],
            'password' => '',
            'locale' => 'en',
            'status' => 2, // Active
            'public' => 0,
            'verified' => 1,
            'restricted' => 0,
            'osm_consent' => 0,
            'tagging' => 0,
            'notification' => 0,
        ];

        $data = array_merge($defaults, $userData);

        // Hash password if provided and not already hashed
        if (!empty($data['password']) && !str_starts_with($data['password'], '$2y$')) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 13]);
        }

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO `user` (name, email, roles, password, created_at, last_login, locale, status, public, verified, restricted, osm_consent, tagging, notification)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
            $data['name'],
            $data['email'],
            json_encode($data['roles']),
            $data['password'],
            $now,
            $now,
            $data['locale'],
            $data['status'],
            $data['public'],
            $data['verified'],
            $data['restricted'],
            $data['osm_consent'],
            $data['tagging'],
            $data['notification'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Create system user for automated operations.
     */
    public function createSystemUser(PDO $pdo): int
    {
        return $this->createUser($pdo, [
            'name' => 'System',
            'email' => 'system@localhost',
            'roles' => ['ROLE_SYSTEM'],
            'password' => '', // No password for system user
            'public' => 0,
            'tagging' => 0,
        ]);
    }

    /**
     * Create admin user with full privileges.
     */
    public function createAdminUser(PDO $pdo, string $email, string $password, string $name): int
    {
        return $this->createUser($pdo, [
            'name' => $name,
            'email' => $email,
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'password' => $password,
            'public' => 1,
            'tagging' => 1,
        ]);
    }

    // Create default config entries
    public function createDefaultConfig(PDO $pdo, int $systemUserId): void
    {
        $data = $this->getAllSessionData();

        $configs = [
            ['automatic_registration', 'false', 'boolean'],
            ['show_frontpage', 'false', 'boolean'],
            ['email_sender_mail', $data['admin_email'] ?? 'email@localhost', 'string'],
            ['email_sender_name', $data['site_name'] ?? 'MeetAgain', 'string'],
            ['website_url', $data['site_url'] ?? 'localhost', 'string'],
            ['website_host', $data['site_url'] ?? 'https://localhost', 'string'],
            ['system_user_id', (string) $systemUserId, 'integer'],
        ];

        $stmt = $pdo->prepare('INSERT INTO config (name, value, type) VALUES (?, ?, ?)');

        foreach ($configs as $config) {
            $stmt->execute($config);
        }
    }

    // Create lock file
    public function createLockFile(): bool
    {
        $lockPath = __DIR__ . '/' . self::LOCK_FILE;
        $lockDir = dirname($lockPath);

        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        $content = sprintf("Installed: %s\n", $this->getTimestamp());

        return file_put_contents($lockPath, $content) !== false;
    }

    // Get database connection from session data
    public function getDatabaseConnection(): ?PDO
    {
        $data = $this->getAllSessionData();

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $data['db_host'],
                $data['db_port'],
                $data['db_name']
            );

            return new PDO($dsn, $data['db_user'], $data['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $this->addError('Database connection failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Run the complete installation process.
     *
     * Executes all installation steps:
     * 1. Generate .env file
     * 2. Run composer install
     * 3. Run database migrations
     * 4. Create system and admin users
     * 5. Create default configuration
     * 6. Create installation lock file
     *
     * @return bool True if installation successful, false otherwise
     */
    public function runInstallation(): bool
    {
        $data = $this->getAllSessionData();

        // Step 1: Generate .env file
        if (!$this->generateEnvFile()) {
            return false;
        }

        // Step 2: Run composer install
        if (!$this->runComposerInstall()) {
            return false;
        }

        // Step 3: Run migrations
        if (!$this->runMigrations()) {
            return false;
        }

        // Step 4: Create users and config
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            return false;
        }

        try {
            $pdo->beginTransaction();

            $systemUserId = $this->createSystemUser($pdo);
            $this->createAdminUser($pdo, $data['admin_email'], $data['admin_password'], $data['admin_name']);
            $this->createDefaultConfig($pdo, $systemUserId);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $this->addError('Failed to create users/config: ' . $e->getMessage());

            return false;
        }

        // Step 5: Create lock file
        if (!$this->createLockFile()) {
            $this->addError('Failed to create lock file');

            return false;
        }

        // Clear session
        $this->session = [];
        session_destroy();

        return true;
    }

    // Helper methods
    private function generateSecret(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function getTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function getCurrentUser(): string
    {
        return get_current_user() ?: 'www-data';
    }

    private function getCurrentUid(): int
    {
        return function_exists('posix_getuid') ? posix_getuid() : 1000;
    }

    private function getCurrentGid(): int
    {
        return function_exists('posix_getgid') ? posix_getgid() : 1000;
    }

    /**
     * Render a template with default installer variables.
     *
     * @param array<string, mixed> $vars
     */
    public function render(string $template, array $vars = []): string
    {
        // Add default vars
        $vars['csrf_token'] = $this->generateCsrfToken();
        $vars['errors'] = $this->errors;
        $vars['current_step'] = $this->getCurrentStep();

        return $this->renderer->render($template, $vars);
    }

    // Input sanitization
    public function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    public function sanitizeInt(mixed $value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
}
