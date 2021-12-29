<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

use function Psl\Fun\pipe;

function debug(mixed $x): mixed
{
    return $x;
}


/**
 * @psalm-suppress UnusedClosureParam, ForbiddenCode, UnusedVariable
 */
function test(): void
{
    // TODO : https://github.com/azjezz/psl/issues/329
    $anonymous = new class () {
        public function __invoke(string $x): int
        {
            return 12;
        }
    };

    // This crashes: Uncaught AssertionError: assert(!$this->isFirstClassCallable()) in vendor/nikic/php-parser/lib/PhpParser/Node/Expr/CallLike.php:36
    $stages = pipe(
        $anonymous(...),
        debug(...) //:
    );
    $res = $stages('hello');

    /** @psalm-trace $res, $stages */

    var_dump($res);
}

test();