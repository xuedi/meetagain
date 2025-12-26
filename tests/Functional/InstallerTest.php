<?php declare(strict_types=1);

namespace Tests\Functional;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

// Include the Installer classes (they're not autoloaded)
require_once __DIR__ . '/../../public/install/TemplateRenderer.php';
require_once __DIR__ . '/../../public/install/SystemRequirements.php';
require_once __DIR__ . '/../../public/install/Installer.php';

/**
 * Functional tests for the Installer class.
 *
 * These tests verify the installer's core functionality:
 * - Mail DSN generation for all providers
 * - Database connection validation
 * - Input sanitization
 * - Lock file detection
 * - Environment management
 *
 * Note: These are unit tests that test the Installer class directly,
 * not HTTP-based functional tests. For full installation testing,
 * use manual testing via: just devInstallerTest
 */
class InstallerTest extends TestCase
{
    private const DB_HOST = 'ma-db';
    private const DB_PORT = 3306;
    private const DB_NAME = 'meetAgain';
    private const DB_USER = 'meetAgain';
    private const DB_PASSWORD = 'UserPassW0rd';

    private const LOCK_FILE_PATH = __DIR__ . '/../../installed.lock';
    private const ENV_FILE_PATH = __DIR__ . '/../../.env';
    private const ENV_BACKUP_PATH = __DIR__ . '/../../.env.backup-test';

    private ?\Installer $installer = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Backup existing .env if it exists
        if (file_exists(self::ENV_FILE_PATH)) {
            copy(self::ENV_FILE_PATH, self::ENV_BACKUP_PATH);
        }

        // Remove lock file for testing
        if (file_exists(self::LOCK_FILE_PATH)) {
            unlink(self::LOCK_FILE_PATH);
        }

        // Create installer instance
        $this->installer = new \Installer();
    }

    protected function tearDown(): void
    {
        // Restore original .env
        if (file_exists(self::ENV_BACKUP_PATH)) {
            if (file_exists(self::ENV_FILE_PATH)) {
                unlink(self::ENV_FILE_PATH);
            }
            rename(self::ENV_BACKUP_PATH, self::ENV_FILE_PATH);
        }

        // Remove test lock file
        if (file_exists(self::LOCK_FILE_PATH)) {
            unlink(self::LOCK_FILE_PATH);
        }

        // Destroy session if active
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        parent::tearDown();
    }

    // ========== MAILER DSN TESTS ==========

    public function testBuildMailerDsnForMailhog(): void
    {
        $dsn = $this->installer->buildMailerDsn(['provider' => 'mailhog']);

        $this->assertEquals('smtp://mailhog:1025', $dsn);
    }

    public function testBuildMailerDsnForNull(): void
    {
        $dsn = $this->installer->buildMailerDsn(['provider' => 'null']);

        $this->assertEquals('null://null', $dsn);
    }

    public function testBuildMailerDsnForSendGrid(): void
    {
        $dsn = $this->installer->buildMailerDsn([
            'provider' => 'sendgrid',
            'api_key' => 'SG.test_key_12345'
        ]);

        $this->assertEquals('sendgrid+api://SG.test_key_12345@default', $dsn);
    }

    public function testBuildMailerDsnForMailgun(): void
    {
        $dsn = $this->installer->buildMailerDsn([
            'provider' => 'mailgun',
            'api_key' => 'key-12345',
            'domain' => 'mg.example.com',
            'region' => 'eu'
        ]);

        $this->assertEquals('mailgun+api://key-12345:mg.example.com@default?region=eu', $dsn);
    }

    public function testBuildMailerDsnForSes(): void
    {
        $dsn = $this->installer->buildMailerDsn([
            'provider' => 'ses',
            'access_key' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'region' => 'us-west-2'
        ]);

        $this->assertEquals(
            'ses+api://AKIAIOSFODNN7EXAMPLE:wJalrXUtnFEMI%2FK7MDENG%2FbPxRfiCYEXAMPLEKEY@default?region=us-west-2',
            $dsn
        );
    }

    public function testBuildMailerDsnForSmtpWithTls(): void
    {
        $dsn = $this->installer->buildMailerDsn([
            'provider' => 'smtp',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_user' => 'user@example.com',
            'smtp_password' => 'password123',
            'encryption' => 'tls'
        ]);

        $this->assertEquals('smtp://user%40example.com:password123@smtp.example.com:587?encryption=tls', $dsn);
    }

    public function testBuildMailerDsnForSmtpWithSsl(): void
    {
        $dsn = $this->installer->buildMailerDsn([
            'provider' => 'smtp',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465,
            'smtp_user' => 'user',
            'smtp_password' => 'pass',
            'encryption' => 'ssl'
        ]);

        $this->assertEquals('smtps://user:pass@smtp.example.com:465', $dsn);
    }

    public function testBuildMailerDsnForSmtpWithoutAuth(): void
    {
        $dsn = $this->installer->buildMailerDsn([
            'provider' => 'smtp',
            'smtp_host' => 'localhost',
            'smtp_port' => 25,
            'encryption' => 'none'
        ]);

        $this->assertEquals('smtp://localhost:25', $dsn);
    }

    // ========== DATABASE CONNECTION TESTS ==========

    public function testDatabaseConnectionWithValidCredentials(): void
    {
        $result = $this->installer->testDatabaseConnection(
            self::DB_HOST,
            self::DB_PORT,
            self::DB_NAME,
            self::DB_USER,
            self::DB_PASSWORD
        );

        $this->assertTrue($result, 'Should connect to database with valid credentials');
        $this->assertEmpty($this->installer->getErrors(), 'Should not have errors with valid credentials');
    }

    public function testDatabaseConnectionWithInvalidCredentials(): void
    {
        $result = $this->installer->testDatabaseConnection(
            self::DB_HOST,
            self::DB_PORT,
            self::DB_NAME,
            'invalid_user',
            'invalid_password'
        );

        $this->assertFalse($result, 'Should fail to connect with invalid credentials');
        $this->assertNotEmpty($this->installer->getErrors(), 'Should have error messages');
        $this->assertStringContainsString('connection failed', $this->installer->getErrors()[0]);
    }

    public function testDatabaseConnectionWithInvalidHost(): void
    {
        $result = $this->installer->testDatabaseConnection(
            'invalid-host',
            self::DB_PORT,
            self::DB_NAME,
            self::DB_USER,
            self::DB_PASSWORD
        );

        $this->assertFalse($result, 'Should fail to connect with invalid host');
        $this->assertNotEmpty($this->installer->getErrors(), 'Should have error messages');
    }

    // ========== INPUT SANITIZATION TESTS ==========

    public function testSanitizeRemovesHtmlTags(): void
    {
        $input = '<script>alert("xss")</script>Hello';
        $output = $this->installer->sanitize($input);

        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;Hello', $output);
    }

    public function testSanitizeTrimsWhitespace(): void
    {
        $input = '  Hello World  ';
        $output = $this->installer->sanitize($input);

        $this->assertEquals('Hello World', $output);
    }

    public function testSanitizeIntConvertsToInteger(): void
    {
        $this->assertEquals(123, $this->installer->sanitizeInt('123'));
        $this->assertEquals(123, $this->installer->sanitizeInt('123abc'));
        $this->assertEquals(0, $this->installer->sanitizeInt('abc'));
        $this->assertEquals(-456, $this->installer->sanitizeInt('-456'));
    }

    // ========== LOCK FILE TESTS ==========

    public function testIsInstalledReturnsFalseWhenNoLockFile(): void
    {
        // Lock file removed in setUp()
        $this->assertFalse($this->installer->isInstalled());
    }

    public function testIsInstalledReturnsTrueWhenLockFileExists(): void
    {
        // Create lock file
        file_put_contents(self::LOCK_FILE_PATH, "Installed: 2025-01-01 12:00:00\n");

        $this->assertTrue($this->installer->isInstalled());
    }

    public function testCreateLockFileCreatesFile(): void
    {
        $result = $this->installer->createLockFile();

        $this->assertTrue($result, 'createLockFile should return true');
        $this->assertFileExists(self::LOCK_FILE_PATH, 'Lock file should exist');

        $content = file_get_contents(self::LOCK_FILE_PATH);
        $this->assertStringContainsString('Installed:', $content);
    }

    // ========== SESSION MANAGEMENT TESTS ==========

    public function testSetAndGetSessionData(): void
    {
        $this->installer->setSessionData('test_key', 'test_value');
        $value = $this->installer->getSessionData('test_key');

        $this->assertEquals('test_value', $value);
    }

    public function testGetSessionDataReturnsDefaultWhenKeyNotSet(): void
    {
        $value = $this->installer->getSessionData('nonexistent_key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function testGetSessionDataReturnsNullWhenNoDefault(): void
    {
        $value = $this->installer->getSessionData('nonexistent_key');

        $this->assertNull($value);
    }

    public function testGetAllSessionDataReturnsArray(): void
    {
        $this->installer->setSessionData('key1', 'value1');
        $this->installer->setSessionData('key2', 'value2');

        $data = $this->installer->getAllSessionData();

        $this->assertIsArray($data);
        $this->assertEquals('value1', $data['key1']);
        $this->assertEquals('value2', $data['key2']);
    }

    // ========== STEP MANAGEMENT TESTS ==========

    public function testGetCurrentStepReturns1ByDefault(): void
    {
        $step = $this->installer->getCurrentStep();

        $this->assertEquals(1, $step);
    }

    public function testSetStepUpdatesCurrentStep(): void
    {
        $this->installer->setStep(2);
        $step = $this->installer->getCurrentStep();

        $this->assertEquals(2, $step);
    }

    public function testGetCurrentStepReturns0WhenInstalled(): void
    {
        // Create lock file to simulate installed state
        file_put_contents(self::LOCK_FILE_PATH, "Installed\n");

        $step = $this->installer->getCurrentStep();

        $this->assertEquals(0, $step);
    }

    // ========== ERROR HANDLING TESTS ==========

    public function testAddErrorAddsToErrorsList(): void
    {
        $this->installer->addError('Test error 1');
        $this->installer->addError('Test error 2');

        $errors = $this->installer->getErrors();

        $this->assertCount(2, $errors);
        $this->assertEquals('Test error 1', $errors[0]);
        $this->assertEquals('Test error 2', $errors[1]);
    }

    public function testHasErrorsReturnsTrueWhenErrorsExist(): void
    {
        $this->installer->addError('Test error');

        $this->assertTrue($this->installer->hasErrors());
    }

    public function testHasErrorsReturnsFalseWhenNoErrors(): void
    {
        $this->assertFalse($this->installer->hasErrors());
    }

    // ========== CSRF TOKEN TESTS ==========

    public function testGenerateCsrfTokenCreatesToken(): void
    {
        $token = $this->installer->generateCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testGenerateCsrfTokenReturnsSameTokenOnSecondCall(): void
    {
        $token1 = $this->installer->generateCsrfToken();
        $token2 = $this->installer->generateCsrfToken();

        $this->assertEquals($token1, $token2);
    }

    public function testValidateCsrfTokenReturnsTrueForValidToken(): void
    {
        $token = $this->installer->generateCsrfToken();
        $result = $this->installer->validateCsrfToken($token);

        $this->assertTrue($result);
    }

    public function testValidateCsrfTokenReturnsFalseForInvalidToken(): void
    {
        $this->installer->generateCsrfToken();
        $result = $this->installer->validateCsrfToken('invalid_token');

        $this->assertFalse($result);
    }

    // ========== REQUIREMENTS CHECK TESTS ==========

    public function testCheckRequirementsReturnsArray(): void
    {
        $requirements = $this->installer->checkRequirements();

        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('php_version', $requirements);
        $this->assertArrayHasKey('ext_pdo', $requirements);
    }

    public function testAllRequirementsPassedWithGoodEnvironment(): void
    {
        $result = $this->installer->allRequirementsPassed();

        // This should pass in Docker environment with PHP 8.4 and all extensions
        $this->assertTrue($result);
    }
}
