<?php

declare(strict_types=1);

/**
 * MeetAgain Web Installer
 * Standalone installer that runs before composer install
 */
class Installer
{
    private const LOCK_FILE = '../../var/installed.lock';
    private const ENV_FILE = '../../.env';
    private const ENV_DIST = '../../.env.dist';

    private array $errors = [];
    private array $session = [];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Use a separate session name to avoid conflicts with Symfony sessions
            session_name('MEETAGAIN_INSTALLER');
            session_start();
        }
        $this->session = &$_SESSION;
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

    // PHP Requirements check
    public function checkRequirements(): array
    {
        $requirements = [];

        // PHP version
        $requirements['php_version'] = [
            'name' => 'PHP >= 8.4',
            'passed' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'current' => PHP_VERSION,
        ];

        // Required extensions
        $extensions = ['pdo', 'pdo_mysql', 'intl', 'iconv', 'ctype', 'json', 'mbstring'];
        foreach ($extensions as $ext) {
            $requirements['ext_' . $ext] = [
                'name' => 'Extension: ' . $ext,
                'passed' => extension_loaded($ext),
                'current' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        // Optional extensions
        $optionalExtensions = ['apcu', 'imagick', 'gd', 'opcache'];
        foreach ($optionalExtensions as $ext) {
            $requirements['ext_' . $ext . '_optional'] = [
                'name' => 'Extension: ' . $ext . ' (optional)',
                'passed' => true, // optional, always "passes"
                'current' => extension_loaded($ext) ? 'Loaded' : 'Not loaded',
                'optional' => true,
            ];
        }

        // Writable directories
        $writableDirs = ['../../var', '../../var/cache', '../../var/log'];
        foreach ($writableDirs as $dir) {
            $fullPath = __DIR__ . '/' . $dir;
            $exists = is_dir($fullPath);
            $writable = $exists && is_writable($fullPath);
            $requirements['writable_' . basename($dir)] = [
                'name' => 'Writable: ' . $dir,
                'passed' => $writable || !$exists, // Pass if doesn't exist yet
                'current' => $exists ? ($writable ? 'Writable' : 'Not writable') : 'Will be created',
            ];
        }

        return $requirements;
    }

    public function allRequirementsPassed(): bool
    {
        foreach ($this->checkRequirements() as $req) {
            if (!($req['optional'] ?? false) && !$req['passed']) {
                return false;
            }
        }
        return true;
    }

    // Database connection test
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

    // SMTP connection test
    public function testSmtpConnection(string $host, int $port, ?string $user, ?string $password, string $encryption): bool
    {
        try {
            $timeout = 5;
            $socket = @fsockopen(
                ($encryption === 'ssl' ? 'ssl://' : '') . $host,
                $port,
                $errno,
                $errstr,
                $timeout
            );

            if (!$socket) {
                $this->addError("SMTP connection failed: $errstr ($errno)");
                return false;
            }

            $response = fgets($socket, 512);
            if (strpos($response, '220') !== 0) {
                $this->addError('SMTP server did not respond correctly');
                fclose($socket);
                return false;
            }

            fclose($socket);
            return true;
        } catch (Exception $e) {
            $this->addError('SMTP connection failed: ' . $e->getMessage());
            return false;
        }
    }

    // Build MAILER_DSN from provider settings
    public function buildMailerDsn(array $mailConfig): string
    {
        $provider = $mailConfig['provider'] ?? 'null';

        return match ($provider) {
            'smtp' => $this->buildSmtpDsn($mailConfig),
            'sendgrid' => sprintf('sendgrid+api://%s@default', urlencode($mailConfig['api_key'] ?? '')),
            'mailgun' => sprintf(
                'mailgun+api://%s:%s@default?region=%s',
                urlencode($mailConfig['api_key'] ?? ''),
                urlencode($mailConfig['domain'] ?? ''),
                $mailConfig['region'] ?? 'us'
            ),
            'ses' => sprintf(
                'ses+api://%s:%s@default?region=%s',
                urlencode($mailConfig['access_key'] ?? ''),
                urlencode($mailConfig['secret_key'] ?? ''),
                $mailConfig['region'] ?? 'eu-west-1'
            ),
            default => 'null://null',
        };
    }

    private function buildSmtpDsn(array $config): string
    {
        $scheme = match ($config['encryption'] ?? 'none') {
            'tls' => 'smtp',
            'ssl' => 'smtps',
            default => 'smtp',
        };

        $auth = '';
        if (!empty($config['smtp_user'])) {
            $auth = urlencode($config['smtp_user']);
            if (!empty($config['smtp_password'])) {
                $auth .= ':' . urlencode($config['smtp_password']);
            }
            $auth .= '@';
        }

        $dsn = sprintf(
            '%s://%s%s:%d',
            $scheme,
            $auth,
            $config['smtp_host'] ?? 'localhost',
            (int) ($config['smtp_port'] ?? 25)
        );

        if (($config['encryption'] ?? 'none') === 'tls') {
            $dsn .= '?encryption=tls';
        }

        return $dsn;
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

    // Create system user
    public function createSystemUser(PDO $pdo): int
    {
        $now = date('Y-m-d H:i:s');
        $roles = json_encode(['ROLE_SYSTEM']);

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO `user` (name, email, roles, password, created_at, last_login, locale, status, public, verified, restricted, osm_consent, tagging, notification)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
            'System',
            'system@localhost',
            $roles,
            '', // No password for system user
            $now,
            $now,
            'en',
            2, // Active
            0, // Not public
            1, // Verified
            0,
            0,
            0,
            0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    // Create admin user
    public function createAdminUser(PDO $pdo, string $email, string $password, string $name): int
    {
        $now = date('Y-m-d H:i:s');
        $roles = json_encode(['ROLE_ADMIN', 'ROLE_USER']);
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]);

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO `user` (name, email, roles, password, created_at, last_login, locale, status, public, verified, restricted, osm_consent, tagging, notification)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);

        $stmt->execute([
            $name,
            $email,
            $roles,
            $hashedPassword,
            $now,
            $now,
            'en',
            2, // Active
            1, // Public
            1, // Verified
            0,
            0,
            1,
            0,
        ]);

        return (int) $pdo->lastInsertId();
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

    // Run the full installation
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

    // Template rendering
    public function render(string $template, array $vars = []): string
    {
        $templatePath = __DIR__ . '/templates/' . $template . '.html';

        if (!file_exists($templatePath)) {
            return "Template not found: $template";
        }

        $content = file_get_contents($templatePath);

        // Add default vars
        $vars['csrf_token'] = $this->generateCsrfToken();
        $vars['errors'] = $this->errors;
        $vars['current_step'] = $this->getCurrentStep();

        // Simple template variable replacement
        foreach ($vars as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{ ' . $key . ' }}', htmlspecialchars((string) $value), $content);
            }
        }

        // Handle arrays for loops (simple implementation)
        $content = $this->processLoops($content, $vars);
        $content = $this->processConditions($content, $vars);

        // Load into layout
        $layoutPath = __DIR__ . '/templates/layout.html';
        if (file_exists($layoutPath)) {
            $layout = file_get_contents($layoutPath);
            $layout = str_replace('{{ content }}', $content, $layout);
            $layout = str_replace('{{ current_step }}', (string) ($vars['current_step'] ?? 1), $layout);
            return $layout;
        }

        return $content;
    }

    private function processLoops(string $content, array $vars): string
    {
        // Simple loop: {% for item in items %}...{% endfor %}
        $pattern = '/{%\s*for\s+(\w+)\s+in\s+(\w+)\s*%}(.*?){%\s*endfor\s*%}/s';

        return preg_replace_callback($pattern, function ($matches) use ($vars) {
            $itemVar = $matches[1];
            $arrayVar = $matches[2];
            $loopContent = $matches[3];
            $output = '';

            if (isset($vars[$arrayVar]) && is_array($vars[$arrayVar])) {
                foreach ($vars[$arrayVar] as $item) {
                    $itemContent = $loopContent;
                    if (is_array($item)) {
                        foreach ($item as $key => $value) {
                            $itemContent = str_replace(
                                '{{ ' . $itemVar . '.' . $key . ' }}',
                                htmlspecialchars((string) $value),
                                $itemContent
                            );
                        }
                    } else {
                        $itemContent = str_replace('{{ ' . $itemVar . ' }}', htmlspecialchars((string) $item), $itemContent);
                    }
                    $output .= $itemContent;
                }
            }

            return $output;
        }, $content) ?? $content;
    }

    private function processConditions(string $content, array $vars): string
    {
        // Simple condition: {% if var %}...{% endif %}
        $pattern = '/{%\s*if\s+(\w+)\s*%}(.*?){%\s*endif\s*%}/s';

        return preg_replace_callback($pattern, function ($matches) use ($vars) {
            $varName = $matches[1];
            $ifContent = $matches[2];

            if (!empty($vars[$varName])) {
                return $ifContent;
            }

            return '';
        }, $content) ?? $content;
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
