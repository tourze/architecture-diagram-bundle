<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ControllerVisitor extends NodeVisitorAbstract
{
    private ?string $namespace = null;

    private ?string $className = null;

    private ?string $parentClass = null;

    private bool $isController = false;

    /** @var array<string> */
    private array $actions = [];

    /** @var array<array{path: string, methods: array<string>}> */
    private array $routes = [];

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
        $this->extractParentClass($node);
        $this->detectControllerByParent();
        $this->analyzeClassAnnotations($node);
    }

    private function extractParentClass(Node\Stmt\Class_ $node): void
    {
        if (null !== $node->extends) {
            $this->parentClass = (string) $node->extends;
        }
    }

    private function detectControllerByParent(): void
    {
        if (null !== $this->parentClass && str_contains($this->parentClass, 'Controller')) {
            $this->isController = true;
        }
    }

    private function analyzeClassAnnotations(Node\Stmt\Class_ $node): void
    {
        $this->analyzeClassAttributes($node);
        $this->analyzeClassDocComment($node);
    }

    private function analyzeClassAttributes(Node\Stmt\Class_ $node): void
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $this->processClassAttribute($attr);
            }
        }
    }

    private function processClassAttribute(Node\Attribute $attr): void
    {
        $attrName = (string) $attr->name;
        if ($this->isControllerOrRouteAttribute($attrName)) {
            $this->isController = true;
        }
    }

    private function isControllerOrRouteAttribute(string $attrName): bool
    {
        return str_contains($attrName, 'Controller') || str_contains($attrName, 'Route');
    }

    private function analyzeClassDocComment(Node\Stmt\Class_ $node): void
    {
        $docComment = $node->getDocComment();
        if (null === $docComment) {
            return;
        }

        $comment = $docComment->getText();
        if (str_contains($comment, '@Controller')) {
            $this->isController = true;
        }
    }

    private function processMethod(Node\Stmt\ClassMethod $node): void
    {
        if (!$node->isPublic()) {
            return;
        }

        $this->actions[] = (string) $node->name;
        $this->analyzeMethodRoutes($node);
    }

    private function analyzeMethodRoutes(Node\Stmt\ClassMethod $node): void
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $this->processMethodAttribute($attr, $node);
            }
        }
    }

    private function processMethodAttribute(Node\Attribute $attr, Node\Stmt\ClassMethod $method): void
    {
        $attrName = (string) $attr->name;
        if (!str_contains($attrName, 'Route')) {
            return;
        }

        $this->isController = true;
        $route = $this->extractRouteFromAttribute($attr, $method);
        $this->routes[] = $route;
    }

    /** @return array{path: string, methods: array<string>} */
    private function extractRouteFromAttribute(Node\Attribute $attr, Node\Stmt\ClassMethod $method): array
    {
        $routePath = '';
        $routeMethods = ['GET'];

        foreach ($attr->args as $index => $arg) {
            [$routePath, $routeMethods] = $this->processRouteArgument($arg, $index, $routePath, $routeMethods);
        }

        if ('' === $routePath) {
            $routePath = $this->generateDefaultPath($method);
        }

        return [
            'path' => $routePath,
            'methods' => $routeMethods,
        ];
    }

    /**
     * @param array<string> $routeMethods
     * @return array{string, array<string>}
     */
    private function processRouteArgument(Node\Arg $arg, int $index, string $routePath, array $routeMethods): array
    {
        if ($this->isPathArgument($arg, $index)) {
            $routePath = $this->extractPathValue($arg);
        }

        if ($this->isMethodsArgument($arg)) {
            $routeMethods = $this->extractMethodsValue($arg);
        }

        return [$routePath, $routeMethods];
    }

    private function isPathArgument(Node\Arg $arg, int $index): bool
    {
        return (null !== $arg->name && 'path' === $arg->name->name)
            || (null === $arg->name && 0 === $index);
    }

    private function extractPathValue(Node\Arg $arg): string
    {
        if ($arg->value instanceof Node\Scalar\String_) {
            return $arg->value->value;
        }

        return '';
    }

    private function isMethodsArgument(Node\Arg $arg): bool
    {
        return null !== $arg->name && 'methods' === $arg->name->name;
    }

    /** @return array<string> */
    private function extractMethodsValue(Node\Arg $arg): array
    {
        if (!$arg->value instanceof Node\Expr\Array_) {
            return ['GET'];
        }

        $methods = [];
        foreach ($arg->value->items as $item) {
            if (null !== $item && $item->value instanceof Node\Scalar\String_) {
                $methods[] = $item->value->value;
            }
        }

        return [] === $methods ? ['GET'] : $methods;
    }

    private function generateDefaultPath(Node\Stmt\ClassMethod $method): string
    {
        return '/' . strtolower((string) $method->name);
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

    public function isController(): bool
    {
        return $this->isController;
    }

    /** @return array<string> */
    public function getActions(): array
    {
        return $this->actions;
    }

    /** @return array<array{path: string, methods: array<string>}> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
