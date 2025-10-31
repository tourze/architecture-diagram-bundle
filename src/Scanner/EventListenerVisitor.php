<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class EventListenerVisitor extends NodeVisitorAbstract
{
    private ?string $namespace = null;

    private ?string $className = null;

    private bool $isSubscriber = false;

    /** @var array<string> */
    private array $events = [];

    /** @var array<string> */
    private array $methods = [];

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
        $this->namespace = $node->name?->toString() ?? '';
    }

    private function processClass(Node\Stmt\Class_ $node): void
    {
        $this->className = $node->name?->toString() ?? '';
        $this->checkForEventSubscriberInterface($node);
        $this->checkForListenerAttributes($node);
    }

    private function checkForEventSubscriberInterface(Node\Stmt\Class_ $node): void
    {
        if ([] === $node->implements) {
            return;
        }

        foreach ($node->implements as $interface) {
            $interfaceName = $interface->toString();
            if (str_contains($interfaceName, 'EventSubscriberInterface')) {
                $this->isSubscriber = true;
                break;
            }
        }
    }

    private function processMethod(Node\Stmt\ClassMethod $node): void
    {
        $this->methods[] = $node->name->toString();
        $this->processSubscribedEvents($node);
        $this->checkMethodAttributes($node);
    }

    private function processSubscribedEvents(Node\Stmt\ClassMethod $node): void
    {
        $methodName = $node->name->toString();
        if ('getSubscribedEvents' === $methodName && $this->isSubscriber) {
            $this->extractSubscribedEvents($node);
        }
    }

    private function checkForListenerAttributes(Node\Stmt\Class_ $node): void
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                if (str_contains($attrName, 'AsEventListener')) {
                    $this->extractEventFromAttribute($attr);
                }
            }
        }
    }

    private function checkMethodAttributes(Node\Stmt\ClassMethod $node): void
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                if (str_contains($attrName, 'AsEventListener')) {
                    $this->extractEventFromAttribute($attr);
                }
            }
        }
    }

    private function extractEventFromAttribute(Node\Attribute $attr): void
    {
        foreach ($attr->args as $arg) {
            if (null !== $arg->name && 'event' === $arg->name->name) {
                if ($arg->value instanceof Node\Scalar\String_) {
                    $this->events[] = $arg->value->value;
                }
            }
        }
    }

    private function extractSubscribedEvents(Node\Stmt\ClassMethod $node): void
    {
        foreach ($node->stmts ?? [] as $stmt) {
            $this->processReturnStatement($stmt);
        }
    }

    private function processReturnStatement(Node\Stmt $stmt): void
    {
        if (!$this->isArrayReturnStatement($stmt)) {
            return;
        }

        /** @var Node\Stmt\Return_ $stmt */
        $arrayExpr = $stmt->expr;
        /** @var Node\Expr\Array_ $arrayExpr */
        $this->extractEventsFromArray($arrayExpr);
    }

    private function isArrayReturnStatement(Node\Stmt $stmt): bool
    {
        return $stmt instanceof Node\Stmt\Return_
            && $stmt->expr instanceof Node\Expr\Array_;
    }

    private function extractEventsFromArray(Node\Expr\Array_ $arrayExpr): void
    {
        foreach ($arrayExpr->items as $item) {
            $this->processArrayItem($item);
        }
    }

    private function processArrayItem(?Node\Expr\ArrayItem $item): void
    {
        if (null === $item || !$item->key instanceof Node\Scalar\String_) {
            return;
        }

        $this->events[] = $item->key->value;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function isSubscriber(): bool
    {
        return $this->isSubscriber;
    }

    /**
     * @return array<string>
     */
    public function getEvents(): array
    {
        return array_unique($this->events);
    }

    /**
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
