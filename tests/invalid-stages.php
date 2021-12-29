<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

use function Psl\Fun\pipe;

/**
 * @psalm-suppress UnusedClosureParam, ForbiddenCode
 */
function test(): void
{
    $stages = pipe(
        fn (string $x): int => 2,
        fn (string $y): float => 1.2,
        fn (float $z): int => 23
    );
    $res = $stages('hello');

    /** @psalm-trace $res, $stages */

    var_dump($res);
}

test();
