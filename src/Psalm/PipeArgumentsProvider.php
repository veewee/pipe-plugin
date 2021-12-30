<?php
declare(strict_types=1);

namespace Psl\Psalm;

use Closure;
use PhpParser\Node\Arg;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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

/**
 * @psalm-type Stage = array{0: Type\Union, 1: Type\Union, 2: string}
 * @psalm-type StagesOrEmpty = list<Stage>|null
 * @psalm-type Stages = non-empty-list<Stage>
 */
class PipeArgumentsProvider implements FunctionParamsProviderInterface, FunctionReturnTypeProviderInterface
{
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
        if (null === $stages) {
            return null;
        }

        if (!$stages) {
            //
            // @see https://github.com/vimeo/psalm/issues/7244
            // Currently, templated arguments are not being resolved in closures / callables
            // For now, we fall back to the built-in types.

            // $templated = self::createTemplatedType('T', Type::getMixed(), 'fn-'.$event->getFunctionId());
            // return self::createClosureStage($templated, $templated, 'input');

            return null;
        }

        $in = self::pipeInputType($stages);
        $out = self::pipeOutputType($stages);

        return self::createClosureStage($in, $out, 'input');
    }

    /**
     * @param list<Arg> $args
     * @return StagesOrEmpty
     */
    private static function parseStages(StatementsSource $source, array $args): ?array
    {
        if (!count($args)) {
            // No pipeline stages is possible: It will work like an identity function.
            // Let's return an empty array instead of null, so that we can set the return type in a more accurate way.
            return [];
        }


        $stages = [];
        foreach ($args as $arg) {
            $stage = $arg->value;

            if (!$stage instanceof FunctionLike) {
                // The stage could also be an expression instead of a function-like.
                // This plugin currently only supports function-like statements.
                // All other input is considered to result in a mixed -> mixed stage
                // This way we can still recover if types are known in later stages.

                // Note : `x(...)` results in FuncCall(args: {0: VariadicPlaceholder})
                // We are currently not able to parse the input and output types for these structures.

                $stages[] = [Type::getMixed(), Type::getMixed(), 'input'];
                continue;
            }

            $params = $stage->getParams();
            $paramName = $params[0]?->var?->name ?? 'input';
            $in = self::determineValidatedStageInputParam($source, $stage);
            $out = self::parseTypeFromASTNode($source, $stage->getReturnType());

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
    private static function determineValidatedStageInputParam(StatementsSource $source, FunctionLike $stage): Type\Union
    {
        $params = $stage->getParams();

        if (count($params) === 0) {
            IssueBuffer::maybeAdd(
                new TooFewArguments(
                    'Pipe stage functions require exactly one input parameter, none given. This will ignore the input value.',
                    new CodeLocation($source, $stage)
                )
            );
        }

        // The pipe function will crash during runtime when there are more than 1 function parameters required.
        // We can still determine the stages Input / Output types at this point.
        if (count($params) > 1 && !($params[1] ?? null)?->default) {
            IssueBuffer::maybeAdd(
                new TooManyArguments(
                    'Pipe stage functions can only deal with one input parameter.',
                    new CodeLocation($source, $params[1])
                )
            );
        }

        $type = $params ? $params[0]?->type : null;

        return self::parseTypeFromASTNode($source, $type);
    }

    /**
     * This function tries parsing the node type based on psalm's NodeTypeProvider.
     * If that one is not able to determine the type, this function will fall back on parsing the AST's node type.
     * In case we are not able to determine the type, this function falls back to the $default type.
     */
    private static function parseTypeFromASTNode(StatementsSource $source, null|Expr|ComplexType|Identifier|Name $node, string $default = 'mixed'): Type\Union
    {
        if (!$node) {
            return self::createSimpleType($default);
        }

        $nodeTypeProvider = $source->getNodeTypeProvider();
        $nodeType = $nodeTypeProvider->getType($node);
        $nodeType ??= self::createSimpleType($node->toString() ?: $default);

        return $nodeType;
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

    private static function createSimpleType(string $type): Type\Union
    {
        return new Type\Union([Type\Atomic::create($type)]);
    }

    private static function createTemplatedType(string $name, Type\Union $baseType, string $definingClass): Type\Union
    {
        return new Type\Union([
            new Type\Atomic\TTemplateParam($name, $baseType, $definingClass)
        ]);
    }

}
