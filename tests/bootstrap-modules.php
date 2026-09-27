<?php declare(strict_types=1);

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\Persistence\ManagerRegistry;
use Tests\Module\ModuleKernel;

require __DIR__ . '/bootstrap.php';

$kernel = new ModuleKernel('test', false);
$kernel->boot();
$doctrine = $kernel->getContainer()->get('doctrine');
assert($doctrine instanceof ManagerRegistry);
try {
    $tables = $doctrine->getConnection()->createSchemaManager()->listTableNames();
} catch (DbalException) {
    $tables = [];
}
$kernel->shutdown();

if ($tables === []) {
    fwrite(STDERR, "The module test database is missing or empty. Run `just testModules`, which builds it.\n");
    exit(1);
}
