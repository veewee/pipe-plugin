<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

use function Psl\Fun\pipe;

/**
 * @template T
 * @param T $x
 * @return T
 */
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

    $stages = pipe(
        $x = $anonymous(...),
        $z = debug(...)
        //fn (int $i): int => $i
    );
    $res = $stages('hello');

    /** @psalm-trace $res, $stages */

    var_dump($res);
}

test();