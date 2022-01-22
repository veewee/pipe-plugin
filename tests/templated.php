<?php

/**
 * @template A
 * @template B
 *
 * @param Closure(A): B $_ab
 * @return Closure(list<A>): list<B>
 */
function map(Closure $_ab): Closure
{
    throw new RuntimeException('???');
}

/**
 * @return list<int>
 */
function getList(): array
{
    return [];
}

/**
 * @template T
 * @psalm-immutable
 */
final class Box
{
    /** @var T */
    public $prop;

    /**
     * @param T $a
     */
    public function __construct($a)
    {
        $this->prop = $a;
    }
}


// Inferred type is list<Box<mixed>>
// Without double run ArgumentsAnalyzer is list<Box<int>>
$pipe = \Psl\Fun\pipe(
    map(function (int $a) {
        return new Box($a + 1);
    }),
    map(function (Box $a) {
        return new Box($a->prop + 1);
    })
);
$result = $pipe(getList());

/** @psalm-trace $pipe */;
/** @psalm-trace $result */;
