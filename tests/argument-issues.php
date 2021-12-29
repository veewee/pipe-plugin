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
        fn (string $x, string $y): int => 2,
        fn (): int => 2,
        fn (int $y, string $x = 'hello'): float => 1.2,
        fn (float ... $items): int => 23
    );
    $res = $stages('hello');

    /** @psalm-trace $res, $stages */

    var_dump($res);
}

test();