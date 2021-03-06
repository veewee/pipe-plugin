<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

use function Psl\Fun\pipe;

/**
 * @psalm-suppress ForbiddenCode
 */
function test(): void
{
    $stages = pipe();
    $res = $stages('hello');

    /** @psalm-trace $res, $stages */

    var_dump($res);

}


test();