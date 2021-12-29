<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

use function Psl\Fun\pipe;

/**
 * @psalm-suppress ForbiddenCode
 */
function test(): void
{
    $res = pipe()('hello');

    /** @psalm-trace $res */

    var_dump($res);
}


test();