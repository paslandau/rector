<?php

declare(strict_types=1);

namespace Rector\Privatization\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\TypeWithClassName;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\NodeCollector\NodeFinder\MethodCallParsedNodesFinder;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * @see \Rector\Privatization\Tests\Rector\ClassMethod\PrivatizeLocalOnlyMethodRector\PrivatizeLocalOnlyMethodRectorTest
 */
final class PrivatizeLocalOnlyMethodRector extends AbstractRector
{
    /**
     * @var MethodCallParsedNodesFinder
     */
    private $methodCallParsedNodesFinder;

    public function __construct(MethodCallParsedNodesFinder $methodCallParsedNodesFinder)
    {
        $this->methodCallParsedNodesFinder = $methodCallParsedNodesFinder;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Privatize local-only use methods', [
            new CodeSample(
                <<<'PHP'
class SomeClass
{
    /**
     * @api
     */
    public function run()
    {
        return $this->useMe();
    }

    public function useMe()
    {
    }
}
PHP
,
                <<<'PHP'
class SomeClass
{
    /**
     * @api
     */
    public function run()
    {
        return $this->useMe();
    }

    private function useMe()
    {
    }
}
PHP

            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->shouldSkip($node)) {
            return null;
        }

        $methodCallerClasses = $this->resolveMethodCallerClasses($node);
        if ($methodCallerClasses === []) {
            return null;
        }

        // is method called only here?
        $currentClassName = $node->getAttribute(AttributeKey::CLASS_NAME);
        if ([$currentClassName] !== $methodCallerClasses) {
            return null;
        }

        $this->changeNodeVisibility($node, 'private');

        return $node;
    }

    private function shouldSkip(ClassMethod $classMethod): bool
    {
        if ($classMethod->isPrivate()) {
            return true;
        }

        /** @var PhpDocInfo|null $phpDocInfo */
        $phpDocInfo = $classMethod->getAttribute(AttributeKey::PHP_DOC_INFO);
        if ($phpDocInfo === null) {
            return false;
        }

        return $phpDocInfo->hasByName('api');
    }

    /**
     * @return string[]
     */
    private function resolveMethodCallerClasses(ClassMethod $classMethod): array
    {
        $methodCalls = $this->methodCallParsedNodesFinder->findClassMethodCalls($classMethod);

        // remove static calls and [$this, 'call']
        $methodCalls = array_filter($methodCalls, function (Node $node) {
            return $node instanceof MethodCall;
        });

        $methodCallerClasses = [];
        foreach ($methodCalls as $methodCall) {
            $callerType = $this->getStaticType($methodCall->var);
            if (! $callerType instanceof TypeWithClassName) {
                // unable to handle reliably
                return [];
            }

            $methodCallerClasses[] = $callerType->getClassName();
        }

        return array_unique($methodCallerClasses);
    }
}
