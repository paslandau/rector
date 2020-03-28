<?php

declare(strict_types=1);

namespace Rector\NodeCollector\NodeFinder;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\NodeCollector\NodeCollector\ParsedFunctionLikeNodeCollector;
use Rector\NodeTypeResolver\Node\AttributeKey;

final class MethodCallParsedNodesFinder
{
    /**
     * @var ParsedFunctionLikeNodeCollector
     */
    private $parsedFunctionLikeNodeCollector;

    public function __construct(ParsedFunctionLikeNodeCollector $parsedFunctionLikeNodeCollector)
    {
        $this->parsedFunctionLikeNodeCollector = $parsedFunctionLikeNodeCollector;
    }

    /**
     * @return MethodCall[]|StaticCall[]|Array_[]
     */
    public function findClassMethodCalls(ClassMethod $classMethod): array
    {
        /** @var string|null $className */
        $className = $classMethod->getAttribute(AttributeKey::CLASS_NAME);
        if ($className === null) { // anonymous
            return [];
        }

        /** @var string $methodName */
        $methodName = $classMethod->getAttribute(AttributeKey::METHOD_NAME);

        return $this->parsedFunctionLikeNodeCollector->findByClassAndMethod($className, $methodName);
    }
}
