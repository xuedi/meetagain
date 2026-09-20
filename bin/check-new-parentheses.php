#!/usr/bin/env php
<?php declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

if (isset($argv[1])) {
    $scanDirs = [$argv[1]];
} else {
    $scanDirs = array_filter([
        $root . '/src',
        $root . '/tests',
        $root . '/migrations',
        $root . '/bin',
        $root . '/config',
        ...(glob($root . '/modules/*', GLOB_ONLYDIR) ?: []),
        ...(glob($root . '/plugins/*', GLOB_ONLYDIR) ?: []),
    ], 'is_dir');
}

/** @return iterable<string, string> path relative to the repository root => absolute path */
function phpFilesIn(array $dirs, string $root): iterable
{
    foreach ($dirs as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = $file->getPathname();
            $relative = str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;
            $isVendored = preg_match('~(^|/)(vendor|node_modules|var)/~', $relative) === 1;
            if ($file->isFile() && $file->getExtension() === 'php' && !$isVendored) {
                yield $relative => $path;
            }
        }
    }
}

function dereferencedNew(Node $node): ?Expr\New_
{
    $target = match (true) {
        $node instanceof Expr\MethodCall,
        $node instanceof Expr\NullsafeMethodCall,
        $node instanceof Expr\PropertyFetch,
        $node instanceof Expr\NullsafePropertyFetch,
        $node instanceof Expr\ArrayDimFetch,
            => $node->var,
        $node instanceof Expr\StaticCall, $node instanceof Expr\StaticPropertyFetch, $node instanceof Expr\ClassConstFetch => $node->class,
        default => null,
    };

    return $target instanceof Expr\New_ ? $target : null;
}

function isWrapped(string $code, Expr\New_ $new): bool
{
    $before = rtrim(substr($code, 0, $new->getStartFilePos()));
    $after = ltrim(substr($code, $new->getEndFilePos() + 1));

    return str_ends_with($before, '(') && str_starts_with($after, ')');
}

$parser = new ParserFactory()->createForNewestSupportedVersion();
$finder = new NodeFinder();
$violations = [];
$scanned = 0;

foreach (phpFilesIn($scanDirs, $root) as $relative => $path) {
    ++$scanned;
    $code = (string) file_get_contents($path);
    try {
        $ast = $parser->parse($code);
    } catch (Throwable) {
        continue;
    }

    foreach ($finder->find($ast ?? [], static fn(Node $node): bool => dereferencedNew($node) !== null) as $node) {
        $new = dereferencedNew($node);
        if ($new !== null && isWrapped($code, $new)) {
            $violations[] = sprintf('%s:%d', $relative, $new->getStartLine());
        }
    }
}

if ($violations === []) {
    echo sprintf('New-expression check passed - %d files, no (new Foo())->... left.', $scanned), PHP_EOL;
    exit(0);
}

foreach (array_unique($violations) as $violation) {
    echo $violation . PHP_EOL;
}
echo PHP_EOL;
echo 'PHP 8.4 dereferences a new expression directly: write new Foo()->bar(), not (new Foo())->bar().' . PHP_EOL;
echo 'See architecture/coding-standards.md, "PHP 8.4 features - use".' . PHP_EOL;
exit(1);
