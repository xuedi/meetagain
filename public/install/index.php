<?php

declare(strict_types=1);

/**
 * MeetAgain Web Installer - Entry Point
 */

require_once __DIR__ . '/TemplateRenderer.php';
require_once __DIR__ . '/SystemRequirements.php';
require_once __DIR__ . '/Installer.php';

$installer = new Installer();

// Check if already installed
if ($installer->isInstalled()) {
    header('Location: /');
    exit;
}

// Handle routing
$step = $_GET['step'] ?? null;
$action = $_POST['action'] ?? null;

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$installer->validateCsrfToken($csrfToken)) {
        $installer->addError('Invalid security token. Please try again.');
        echo $installer->render('error', ['message' => 'Invalid security token']);
        exit;
    }
}

// Route handling
switch ($action) {
    case 'step1':
        handleStep1($installer);
        break;

    case 'step2':
        handleStep2($installer);
        break;

    case 'step3':
        handleStep3($installer);
        break;

    default:
        showCurrentStep($installer);
        break;
}

function showCurrentStep(Installer $installer): void
{
    $step = $installer->getCurrentStep();

    switch ($step) {
        case 1:
            showStep1($installer);
            break;
        case 2:
            showStep2($installer);
            break;
        case 3:
            showStep3($installer);
            break;
        default:
            showStep1($installer);
    }
}

function showStep1(Installer $installer): void
{
    $requirements = $installer->checkRequirements();
    $canProceed = $installer->allRequirementsPassed();

    echo $installer->render('step1', [
        'requirements' => $requirements,
        'can_proceed' => $canProceed,
        'db_host' => $installer->getSessionData('db_host', 'localhost'),
        'db_port' => $installer->getSessionData('db_port', '3306'),
        'db_name' => $installer->getSessionData('db_name', 'meetAgain'),
        'db_user' => $installer->getSessionData('db_user', 'meetAgain'),
        'db_password' => $installer->getSessionData('db_password', ''),
    ]);
}

function handleStep1(Installer $installer): void
{
    // Collect and sanitize database settings
    $data = [
        'db_host' => $installer->sanitize($_POST['db_host'] ?? 'localhost'),
        'db_port' => $installer->sanitizeInt($_POST['db_port'] ?? 3306),
        'db_name' => $installer->sanitize($_POST['db_name'] ?? 'meetAgain'),
        'db_user' => $installer->sanitize($_POST['db_user'] ?? ''),
    ];
    $dbPassword = $_POST['db_password'] ?? '';

    // Validation
    if (empty($data['db_user'])) {
        $installer->addError('Database user is required');
    }

    if (empty($dbPassword)) {
        $installer->addError('Database password is required');
    }

    // Test connection if no validation errors
    if (!$installer->hasErrors()) {
        $installer->testDatabaseConnection(
            $data['db_host'],
            $data['db_port'],
            $data['db_name'],
            $data['db_user'],
            $dbPassword
        );
    }

    // Store password only on success
    if (!$installer->hasErrors()) {
        $data['db_password'] = $dbPassword;
    }

    $installer->handleFormResult($data, 2, fn() => showStep1($installer));
}

function showStep2(Installer $installer): void
{
    echo $installer->render('step2', [
        'provider' => $installer->getSessionData('mail_provider', 'null'),
        'smtp_host' => $installer->getSessionData('smtp_host', ''),
        'smtp_port' => $installer->getSessionData('smtp_port', '587'),
        'smtp_user' => $installer->getSessionData('smtp_user', ''),
        'smtp_password' => $installer->getSessionData('smtp_password', ''),
        'smtp_encryption' => $installer->getSessionData('smtp_encryption', 'tls'),
        'sendgrid_api_key' => $installer->getSessionData('sendgrid_api_key', ''),
        'mailgun_api_key' => $installer->getSessionData('mailgun_api_key', ''),
        'mailgun_domain' => $installer->getSessionData('mailgun_domain', ''),
        'mailgun_region' => $installer->getSessionData('mailgun_region', 'us'),
        'ses_region' => $installer->getSessionData('ses_region', 'eu-west-1'),
        'ses_access_key' => $installer->getSessionData('ses_access_key', ''),
        'ses_secret_key' => $installer->getSessionData('ses_secret_key', ''),
    ]);
}

function handleStep2(Installer $installer): void
{
    $provider = $installer->sanitize($_POST['mail_provider'] ?? 'null');
    $mailConfig = ['provider' => $provider];

    // Collect and validate provider-specific configuration
    switch ($provider) {
        case 'mailhog':
            // No configuration needed for MailHog
            break;

        case 'smtp':
            $mailConfig['smtp_host'] = $installer->sanitize($_POST['smtp_host'] ?? '');
            $mailConfig['smtp_port'] = $installer->sanitizeInt($_POST['smtp_port'] ?? 587);
            $mailConfig['smtp_user'] = $installer->sanitize($_POST['smtp_user'] ?? '');
            $mailConfig['smtp_password'] = $_POST['smtp_password'] ?? '';
            $mailConfig['encryption'] = $installer->sanitize($_POST['smtp_encryption'] ?? 'tls');

            if (empty($mailConfig['smtp_host'])) {
                $installer->addError('SMTP host is required');
            }

            // Optionally test SMTP connection
            if (!$installer->hasErrors() && !empty($_POST['test_smtp'])) {
                $installer->testSmtpConnection(
                    $mailConfig['smtp_host'],
                    $mailConfig['smtp_port'],
                    $mailConfig['smtp_user'],
                    $mailConfig['smtp_password'],
                    $mailConfig['encryption']
                );
            }
            break;

        case 'sendgrid':
            $mailConfig['api_key'] = $_POST['sendgrid_api_key'] ?? '';
            if (empty($mailConfig['api_key'])) {
                $installer->addError('SendGrid API key is required');
            }
            break;

        case 'mailgun':
            $mailConfig['api_key'] = $_POST['mailgun_api_key'] ?? '';
            $mailConfig['domain'] = $installer->sanitize($_POST['mailgun_domain'] ?? '');
            $mailConfig['region'] = $installer->sanitize($_POST['mailgun_region'] ?? 'us');

            if (empty($mailConfig['api_key'])) {
                $installer->addError('Mailgun API key is required');
            }
            if (empty($mailConfig['domain'])) {
                $installer->addError('Mailgun domain is required');
            }
            break;

        case 'ses':
            $mailConfig['region'] = $installer->sanitize($_POST['ses_region'] ?? 'eu-west-1');
            $mailConfig['access_key'] = $_POST['ses_access_key'] ?? '';
            $mailConfig['secret_key'] = $_POST['ses_secret_key'] ?? '';

            if (empty($mailConfig['access_key'])) {
                $installer->addError('AWS Access Key is required');
            }
            if (empty($mailConfig['secret_key'])) {
                $installer->addError('AWS Secret Key is required');
            }
            break;

        case 'null':
        default:
            // No validation needed
            break;
    }

    // Build and store MAILER_DSN on success
    $data = array_merge(['mail_provider' => $provider], $mailConfig);
    if (!$installer->hasErrors()) {
        $data['mailer_dsn'] = $installer->buildMailerDsn($mailConfig);
    }

    $installer->handleFormResult($data, 3, fn() => showStep2($installer));
}

function showStep3(Installer $installer): void
{
    // Auto-detect site URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $defaultUrl = $protocol . '://' . $host;

    echo $installer->render('step3', [
        'site_url' => $installer->getSessionData('site_url', $defaultUrl),
        'site_name' => $installer->getSessionData('site_name', 'MeetAgain'),
        'admin_email' => $installer->getSessionData('admin_email', ''),
        'admin_name' => $installer->getSessionData('admin_name', 'Admin'),
    ]);
}

function handleStep3(Installer $installer): void
{
    // Collect and sanitize site settings
    $data = [
        'site_url' => $installer->sanitize($_POST['site_url'] ?? ''),
        'site_name' => $installer->sanitize($_POST['site_name'] ?? 'MeetAgain'),
        'admin_email' => $installer->sanitize($_POST['admin_email'] ?? ''),
        'admin_name' => $installer->sanitize($_POST['admin_name'] ?? 'Admin'),
    ];
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';

    // Validation
    if (empty($data['site_url'])) {
        $installer->addError('Site URL is required');
    }

    if (empty($data['admin_email']) || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $installer->addError('Valid admin email is required');
    }

    if (empty($adminPassword)) {
        $installer->addError('Admin password is required');
    } elseif (strlen($adminPassword) < 8) {
        $installer->addError('Admin password must be at least 8 characters');
    } elseif (strlen($adminPassword) > 72) {
        $installer->addError('Admin password must not exceed 72 characters');
    } elseif ($adminPassword !== $adminPasswordConfirm) {
        $installer->addError('Passwords do not match');
    }

    // Store password only on success
    if (!$installer->hasErrors()) {
        $data['admin_password'] = $adminPassword;
    }

    $installer->handleFormResult($data, null, fn() => showStep3($installer));

    // Run installation (only reached if no errors)
    if ($installer->runInstallation()) {
        echo $installer->render('success', [
            'site_url' => $data['site_url'],
            'admin_email' => $data['admin_email'],
        ]);
    } else {
        echo $installer->render('error', [
            'message' => 'Installation failed',
        ]);
    }
}
