<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ServiceVisitor extends NodeVisitorAbstract
{
    private ?string $namespace = null;

    private ?string $className = null;

    /** @var array<string> */
    private array $publicMethods = [];

    /** @var array<string> */
    private array $dependencies = [];

    /** @var array<string> */
    private array $interfaces = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->processNamespace($node);

            return null;
        }

        if ($node instanceof Node\Stmt\Class_) {
            $this->processClass($node);

            return null;
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->processMethod($node);
        }

        return null;
    }

    private function processNamespace(Node\Stmt\Namespace_ $node): void
    {
        $this->namespace = (string) $node->name;
    }

    private function processClass(Node\Stmt\Class_ $node): void
    {
        $this->className = (string) $node->name;
        $this->extractInterfaces($node);
    }

    private function extractInterfaces(Node\Stmt\Class_ $node): void
    {
        if ([] === $node->implements || !is_array($node->implements)) {
            return;
        }

        foreach ($node->implements as $interface) {
            $this->interfaces[] = (string) $interface;
        }
    }

    private function processMethod(Node\Stmt\ClassMethod $node): void
    {
        if ($node->isPublic()) {
            $this->publicMethods[] = (string) $node->name;
        }

        $this->extractDependenciesFromMethod($node);
    }

    private function extractDependenciesFromMethod(Node\Stmt\ClassMethod $method): void
    {
        if (null === $method->stmts) {
            return;
        }

        foreach ($method->stmts as $stmt) {
            $this->processStatement($stmt);
        }
    }

    private function processStatement(Node\Stmt $stmt): void
    {
        if (!$this->isMethodCallExpression($stmt)) {
            return;
        }

        /** @var Node\Stmt\Expression $stmt */
        $call = $stmt->expr;
        /** @var Node\Expr\MethodCall $call */
        $dependency = $this->extractDependencyFromCall($call);

        if (null !== $dependency) {
            $this->addDependency($dependency);
        }
    }

    private function isMethodCallExpression(Node\Stmt $stmt): bool
    {
        return $stmt instanceof Node\Stmt\Expression
            && $stmt->expr instanceof Node\Expr\MethodCall;
    }

    private function extractDependencyFromCall(Node\Expr\MethodCall $call): ?string
    {
        if (!$this->isThisPropertyCall($call)) {
            return null;
        }

        /** @var Node\Expr\PropertyFetch $propertyFetch */
        $propertyFetch = $call->var;
        /** @var Node\Identifier $name */
        $name = $propertyFetch->name;

        return $name->toString();
    }

    private function isThisPropertyCall(Node\Expr\MethodCall $call): bool
    {
        return $call->var instanceof Node\Expr\PropertyFetch
            && $call->var->var instanceof Node\Expr\Variable
            && 'this' === $call->var->var->name
            && $call->var->name instanceof Node\Identifier;
    }

    private function addDependency(string $dependency): void
    {
        if (!in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
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

    /** @return array<string> */
    public function getPublicMethods(): array
    {
        return $this->publicMethods;
    }

    /** @return array<string> */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /** @return array<string> */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }
}
