<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

use function Psl\Fun\pipe;

function debug(mixed $x): mixed
{
    return $x;
}

/**
 * @psalm-suppress UnusedClosureParam, UnusedVariable
 */
function test(): void
{
    $stages = pipe(
        new class () {
            public function __invoke(string $x): int
            {
                return 12;
            }
        },
        // debug(...) : This crashes: Uncaught AssertionError: assert(!$this->isFirstClassCallable()) in vendor/nikic/php-parser/lib/PhpParser/Node/Expr/CallLike.php:36
    );
    $res = $stages('hello');

    /** @psalm-trace $res, $stages */
}
