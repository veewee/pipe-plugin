<?php
declare(strict_types=1);

namespace Psl\Psalm;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\FunctionLike;
use Psalm\CodeLocation;
use Psalm\Issue\TooFewArguments;
use Psalm\Issue\TooManyArguments;
use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\Event\FunctionParamsProviderEvent;
use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\FunctionParamsProviderInterface;
use Psalm\Plugin\EventHandler\FunctionReturnTypeProviderInterface;
use Psalm\StatementsSource;
use Psalm\Storage\FunctionLikeParameter;
use Psalm\Type;
use Psalm\Type\Atomic\TClosure;

/**
 * @psalm-type Stage = array{0: Type\Union, 1: Type\Union, 2: string}
 * @psalm-type StagesOrEmpty = list<Stage>
 * @psalm-type Stages = non-empty-list<Stage>
 */
class PipeArgumentsProvider implements FunctionParamsProviderInterface, FunctionReturnTypeProviderInterface
{
    /**
     * @return array<lowercase-string>
     */
    public static function getFunctionIds(): array
    {
        return [
            'psl\fun\pipe'
        ];
    }

    /**
     * @return list<FunctionLikeParameter>|null
     */
    public static function getFunctionParams(FunctionParamsProviderEvent $event): ?array
    {
        $stages = self::parseStages($event->getStatementsSource(), $event->getCallArgs());

        // When stages are falsy, return the result directly.
        // This will either result in falling back to the initial declaration on the pipe function when it is NULL.
        // On empty array, it will tell that there are no params available.
        if (!$stages) {
            return $stages;
        }

        $params = [];
        $previousOut = self::pipeInputType($stages);

        foreach ($stages as $stage) {
            [$_, $currentOut, $paramName] = $stage;

            $params[] = self::createFunctionParameter(
                'stages',
                self::createClosureStage($previousOut, $currentOut, $paramName)
            );

            $previousOut = $currentOut;
        }

        return $params;
    }

    public static function getFunctionReturnType(FunctionReturnTypeProviderEvent $event): ?Type\Union
    {
        $stages = self::parseStages($event->getStatementsSource(), $event->getCallArgs());
        if (!$stages) {
            //
            // @see https://github.com/vimeo/psalm/issues/7244
            // Currently, templated arguments are not being resolved in closures / callables
            // For now, we fall back to the built-in types.

//            $templated = self::createTemplatedType('T', Type::getMixed(), 'fn-'.$event->getFunctionId());
//            return self::createClosureStage($templated, $templated, 'input');

            return null;
        }

        $in = self::pipeInputType($stages);
        $out = self::pipeOutputType($stages);

        return self::createClosureStage($in, $out, 'input');
    }

    /**
     * @param array<array-key, Arg> $args
     * @return StagesOrEmpty
     */
    private static function parseStages(StatementsSource $source, array $args): array
    {
        $stages = [];
        foreach ($args as $arg) {
            $stage = $arg->value;

            $nodeTypeProvider = $source->getNodeTypeProvider();
            $stageType = $nodeTypeProvider->getType($stage)?->getSingleAtomic();
            if (!$stageType instanceof TClosure) {
                $stages[] = [Type::getMixed(), Type::getMixed(), 'input'];
                continue;
            }

            $params = $stageType->params;
            $firstParam = reset($params);

            $paramName = $firstParam->name ?? 'input';
            $in = self::determineValidatedStageInputParam($source, $stage, $stageType);
            $out = $stageType->return_type ?? Type::getMixed();

            $stages[] = [$in, $out, $paramName];
        }

        return $stages;
    }

    /**
     * This function first validates the parameters of the stage.
     * A stage should have exactly one required input parameter.
     *
     * - If there are no parameters, the input parameter is ignored.
     * - If there are too many required parameters, this will result in a runtime exception.
     *
     * In both situations, we can continue building up the stages so that the user has as much analyzer info as possible.
     */
    private static function determineValidatedStageInputParam(StatementsSource $source, Node $stage, TClosure $stageType): Type\Union
    {
        $params = $stageType->params ?? [];

        if (count($params) === 0) {
            IssueBuffer::maybeAdd(
                new TooFewArguments(
                    'Pipe stage functions require exactly one input parameter, none given. This will ignore the input value.',
                    new CodeLocation($source, $stage)
                ),
                $source->getSuppressedIssues()
            );
        }

        // The pipe function will crash during runtime when there are more than 1 function parameters required.
        // We can still determine the stages Input / Output types at this point.
        if (count($params) > 1 && !$params[1]->is_optional) {
            IssueBuffer::maybeAdd(
                new TooManyArguments(
                    'Pipe stage functions can only deal with one input parameter.',
                    new CodeLocation($source, $stage instanceof FunctionLike ? $stage->getParams()[0] : $stage)
                ),
                $source->getSuppressedIssues()
            );
        }

        return $params[0]->type ?? Type::getMixed();
    }

    /**
     * @param Stages $stages
     */
    private static function pipeInputType(array $stages): Type\Union
    {
        $firstStage = array_shift($stages);
        [$in, $_, $_] = $firstStage;

        return $in;
    }

    /**
     * @param Stages $stages
     */
    private static function pipeOutputType(array $stages): Type\Union
    {
        $lastStage = array_pop($stages);
        [$_, $out, $_] = $lastStage;

        return $out;
    }

    private static function createClosureStage(Type\Union $in, Type\Union $out, string $paramName): Type\Union
    {
        return new Type\Union([
            new Type\Atomic\TClosure(
                value: Closure::class,
                params: [
                    self::createFunctionParameter($paramName, $in),
                ],
                return_type: $out,
            )
        ]);
    }

    private static function createFunctionParameter(string $name, Type\Union $type): FunctionLikeParameter
    {
        return new FunctionLikeParameter(
            $name,
            false,
            $type,
            is_optional: false,
            is_nullable: false,
            is_variadic: false,
        );
    }

    private static function createTemplatedType(string $name, Type\Union $baseType, string $definingClass): Type\Union
    {
        return new Type\Union([
            new Type\Atomic\TTemplateParam($name, $baseType, $definingClass)
        ]);
    }
}
