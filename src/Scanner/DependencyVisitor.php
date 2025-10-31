<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class DependencyVisitor extends NodeVisitorAbstract
{
    /** @var array<string> */
    private array $dependencies = [];

    /** @var array<string, string> */
    private array $classMap;

    /**
     * @param array<string, string> $classMap
     */
    public function __construct(array $classMap)
    {
        $this->classMap = $classMap;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->analyzeClassNode($node);
        }

        if ($node instanceof Node\Stmt\ClassMethod && '__construct' === $node->name->toString()) {
            $this->analyzeConstructor($node);
        }

        if ($node instanceof Node\Expr\New_) {
            $this->analyzeNewExpression($node);
        }

        if ($node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\MethodCall) {
            $this->analyzeMethodCall($node);
        }

        return null;
    }

    private function analyzeClassNode(Node\Stmt\Class_ $node): void
    {
        if (null !== $node->extends) {
            $parentClass = $node->extends->toString();
            $this->addDependency($parentClass);
        }

        if ([] !== $node->implements) {
            foreach ($node->implements as $interface) {
                $this->addDependency($interface->toString());
            }
        }
    }

    private function analyzeConstructor(Node\Stmt\ClassMethod $node): void
    {
        foreach ($node->params as $param) {
            if ($param->type instanceof Node\Name) {
                $typeName = $param->type->toString();
                $this->addDependency($typeName);
            } elseif ($param->type instanceof Node\NullableType && $param->type->type instanceof Node\Name) {
                $typeName = $param->type->type->toString();
                $this->addDependency($typeName);
            }
        }
    }

    private function analyzeNewExpression(Node\Expr\New_ $node): void
    {
        if ($node->class instanceof Node\Name) {
            $className = $node->class->toString();
            $this->addDependency($className);
        }
    }

    private function analyzeMethodCall(Node $node): void
    {
        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name) {
            $className = $node->class->toString();
            $this->addDependency($className);
        }
    }

    private function addDependency(string $className): void
    {
        if (isset($this->classMap[$className])) {
            $componentId = $this->classMap[$className];
            if (!in_array($componentId, $this->dependencies, true)) {
                $this->dependencies[] = $componentId;
            }
        }
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
