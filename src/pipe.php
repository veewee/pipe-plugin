<?php

declare(strict_types=1);

namespace Psl\Fun;

use Closure;

/**
 * Performs left-to-right function composition.
 *
 * @template T
 *
 * @param Closure(T): T ...$stages
 *
 * @return Closure(T): T
 *
 * @pure
 */
function pipe(Closure ...$stages): Closure
{
    return static fn ($input) => array_reduce(
        $stages,
        /**
         * @param T $input
         * @param (Closure(T): T) $next
         *
         * @return T
         */
        static fn (mixed $input, Closure $next): mixed => $next($input),
        $input
    );
}
