#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Fails when a GET-reachable controller method contains an unguarded mutation call
 */

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

$allowlistFile = $root . '/tests/config/get-routes-allowlist.php';
$allowlist     = file_exists($allowlistFile) ? (require $allowlistFile) : [];

$mutationMethodNames = ['flush', 'persist', 'remove', 'dispatch', 'send'];

if (isset($argv[1])) {
    $scanDirs = [$argv[1]];
} else {
    $scanDirs = array_filter([
        $root . '/src/Controller',
        ...glob($root . '/plugins/*/src/Controller') ?: [],
    ], 'is_dir');
}

/** @return iterable<SplFileInfo> */
function phpFilesIn(array $dirs): iterable
{
    foreach ($dirs as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}

function routeIsGetReachable(Node\AttributeGroup $attrGroup): bool
{
    foreach ($attrGroup->attrs as $attr) {
        $attrName = $attr->name->toString();
        if (!in_array($attrName, ['Route', 'Symfony\\Component\\Routing\\Attribute\\Route'], true)) {
            continue;
        }
        foreach ($attr->args as $arg) {
            if ($arg->name?->toString() === 'methods') {
                if ($arg->value instanceof Node\Expr\Array_) {
                    foreach ($arg->value->items as $item) {
                        if ($item?->value instanceof Node\Scalar\String_) {
                            if (strtoupper($item->value->value) === 'GET') {
                                return true;
                            }
                        }
                    }
                    return false;
                }
                return false;
            }
        }
        // Symfony default-all matches GET when methods: is omitted.
        return true;
    }
    return false;
}

function isAncestorOf(Node $ancestor, Node $target, NodeFinder $finder): bool
{
    $descendants = $finder->find([$ancestor], fn (Node $n) => $n === $target);
    return $descendants !== [];
}

function ifBlockIsPostGuard(Node\Stmt\If_ $if): bool
{
    return containsPostGuardCall($if->cond);
}

function containsPostGuardCall(Node $node): bool
{
    if ($node instanceof MethodCall) {
        if ($node->name instanceof Node\Identifier) {
            $name = $node->name->toString();
            if ($name === 'isSubmitted') {
                return true;
            }
            if ($name === 'isMethod') {
                $firstArg = $node->args[0] ?? null;
                if ($firstArg instanceof Node\Arg && $firstArg->value instanceof Node\Scalar\String_) {
                    return strtoupper($firstArg->value->value) === 'POST';
                }
            }
        }
    }
    if ($node instanceof Node\Expr\BinaryOp\BooleanAnd) {
        return containsPostGuardCall($node->left) || containsPostGuardCall($node->right);
    }
    if ($node instanceof Node\Expr\BinaryOp\BooleanOr) {
        return false;
    }
    if ($node instanceof Node\Expr\BooleanNot) {
        return false;
    }
    return false;
}

function findUnguardedMutationCalls(array $stmts, array $mutationMethodNames, NodeFinder $finder): array
{
    $hits = [];

    $postGuardedIfs = $finder->find($stmts, fn (Node $n) => $n instanceof Node\Stmt\If_ && ifBlockIsPostGuard($n));

    $allMutationCalls = $finder->find($stmts, function (Node $node) use ($mutationMethodNames): bool {
        if (!($node instanceof MethodCall)) {
            return false;
        }
        if (!($node->name instanceof Node\Identifier)) {
            return false;
        }
        $name = $node->name->toString();
        if (!in_array($name, $mutationMethodNames, true)) {
            return false;
        }
        // $form->remove() is Form field removal, not EntityManager.
        if ($name === 'remove'
            && $node->var instanceof Node\Expr\Variable
            && $node->var->name === 'form'
        ) {
            return false;
        }
        return true;
    });

    foreach ($allMutationCalls as $call) {
        $isGuarded = false;
        foreach ($postGuardedIfs as $guardedIf) {
            if (isAncestorOf($guardedIf, $call, $finder)) {
                $isGuarded = true;
                break;
            }
        }
        if (!$isGuarded) {
            $hits[] = [
                'call' => '->' . $call->name->toString() . '(',
                'line' => $call->getStartLine(),
            ];
        }
    }

    return $hits;
}

$parser     = (new ParserFactory())->createForNewestSupportedVersion();
$finder     = new NodeFinder();
$violations = [];

foreach (phpFilesIn($scanDirs) as $file) {
    $code = file_get_contents($file->getPathname());
    try {
        $ast = $parser->parse($code);
    } catch (Throwable) {
        continue;
    }
    if ($ast === null) {
        continue;
    }

    $namespaceName = '';
    foreach ($ast as $node) {
        if ($node instanceof Node\Stmt\Namespace_) {
            $namespaceName = $node->name?->toString() ?? '';
            break;
        }
    }

    $classes = (new NodeFinder())->findInstanceOf($ast, Node\Stmt\Class_::class);

    foreach ($classes as $class) {
        $className = $class->name?->toString() ?? '';
        $fqcn      = $namespaceName !== '' ? $namespaceName . '\\' . $className : $className;

        foreach ($class->getMethods() as $method) {
            $methodName = $method->name->toString();
            $key        = $fqcn . '::' . $methodName;

            if (isset($allowlist[$key])) {
                continue;
            }

            $isGetReachable = false;
            foreach ($method->attrGroups as $attrGroup) {
                if (routeIsGetReachable($attrGroup)) {
                    $isGetReachable = true;
                    break;
                }
            }
            if (!$isGetReachable) {
                continue;
            }

            $bodyStmts = $method->stmts ?? [];
            $hits      = findUnguardedMutationCalls($bodyStmts, $mutationMethodNames, $finder);
            foreach ($hits as $hit) {
                $violations[] = sprintf(
                    '%s:%d [%s] %s',
                    $file->getPathname(),
                    $hit['line'],
                    $key,
                    $hit['call'],
                );
            }
        }
    }
}

if ($violations === []) {
    echo 'GET-route purity check passed - no unguarded mutation calls found in GET-reachable methods.' . PHP_EOL;
    exit(0);
}

foreach ($violations as $violation) {
    echo $violation . PHP_EOL;
}
echo PHP_EOL;
echo 'See architecture/security/get-routes.md for the rule and allowed exceptions.' . PHP_EOL;
echo 'Add allowlist entries to tests/config/get-routes-allowlist.php only for documented exceptions.' . PHP_EOL;
exit(1);
