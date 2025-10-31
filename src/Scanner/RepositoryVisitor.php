<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class RepositoryVisitor extends NodeVisitorAbstract
{
    private ?string $namespace = null;

    private ?string $className = null;

    private ?string $parentClass = null;

    private ?string $entityClass = null;

    /** @var array<string> */
    private array $publicMethods = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = (string) $node->name;
        }

        if ($node instanceof Node\Stmt\Class_) {
            $this->className = (string) $node->name;

            if (null !== $node->extends) {
                $this->parentClass = (string) $node->extends;
            }

            $this->extractEntityFromConstructor($node);
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->isPublic()) {
                $this->publicMethods[] = (string) $node->name;
            }
        }

        return null;
    }

    private function extractEntityFromConstructor(Node\Stmt\Class_ $class): void
    {
        $constructor = $this->findConstructor($class);
        if (null !== $constructor) {
            $this->extractEntityFromConstructorBody($constructor);
        }

        $this->fallbackToNamingConvention();
    }

    private function findConstructor(Node\Stmt\Class_ $class): ?Node\Stmt\ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($this->isConstructorMethod($stmt)) {
                /** @var Node\Stmt\ClassMethod $stmt */
                return $stmt;
            }
        }

        return null;
    }

    private function isConstructorMethod(Node\Stmt $stmt): bool
    {
        return $stmt instanceof Node\Stmt\ClassMethod
            && '__construct' === (string) $stmt->name;
    }

    private function extractEntityFromConstructorBody(Node\Stmt\ClassMethod $constructor): void
    {
        foreach ($constructor->stmts ?? [] as $stmt) {
            $this->processConstructorStatement($stmt);
        }
    }

    private function processConstructorStatement(Node\Stmt $stmt): void
    {
        if (!$this->isParentConstructorCall($stmt)) {
            return;
        }

        /** @var Node\Stmt\Expression $stmt */
        $call = $stmt->expr;
        /** @var Node\Expr\StaticCall $call */
        $this->extractEntityFromParentCall($call);
    }

    private function isParentConstructorCall(Node\Stmt $stmt): bool
    {
        return $stmt instanceof Node\Stmt\Expression
            && $stmt->expr instanceof Node\Expr\StaticCall
            && $this->isConstructorCallExpression($stmt->expr);
    }

    private function isConstructorCallExpression(Node\Expr\StaticCall $call): bool
    {
        return $call->name instanceof Node\Identifier
            && '__construct' === (string) $call->name
            && [] !== $call->args;
    }

    private function extractEntityFromParentCall(Node\Expr\StaticCall $call): void
    {
        $firstArg = $call->args[0] ?? null;
        if (!$this->isValidEntityArgument($firstArg)) {
            return;
        }

        /** @var Node\Arg $firstArg */
        /** @var Node\Expr\ClassConstFetch $value */
        $value = $firstArg->value;
        /** @var Node\Name $class */
        $class = $value->class;

        $this->entityClass = $class->toString();
    }

    /**
     * @param Node\Arg|Node\VariadicPlaceholder|null $arg
     */
    private function isValidEntityArgument($arg): bool
    {
        return null !== $arg
            && $arg instanceof Node\Arg
            && $arg->value instanceof Node\Expr\ClassConstFetch
            && $arg->value->class instanceof Node\Name;
    }

    private function fallbackToNamingConvention(): void
    {
        if (null !== $this->entityClass || null === $this->className) {
            return;
        }

        $entityName = str_replace('Repository', '', $this->className);
        if ($entityName !== $this->className) {
            $this->entityClass = $entityName;
        }
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getParentClass(): ?string
    {
        return $this->parentClass;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    /** @return array<string> */
    public function getPublicMethods(): array
    {
        return $this->publicMethods;
    }
}
