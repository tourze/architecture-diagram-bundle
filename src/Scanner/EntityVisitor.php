<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class EntityVisitor extends NodeVisitorAbstract
{
    private ?string $namespace = null;

    private ?string $className = null;

    private bool $isEntity = false;

    /** @var array<string> */
    private array $properties = [];

    /** @var array<string> */
    private array $methods = [];

    private ?string $tableName = null;

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

        if ($node instanceof Node\Stmt\Property) {
            $this->processProperty($node);

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
        $this->analyzeClassAttributes($node);
        $this->analyzeDocComments($node);
    }

    private function analyzeClassAttributes(Node\Stmt\Class_ $node): void
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $this->processAttribute($attr);
            }
        }
    }

    private function processAttribute(Node\Attribute $attr): void
    {
        $attrName = (string) $attr->name;

        if ($this->isEntityAttribute($attrName)) {
            $this->isEntity = true;
        }

        if ($this->isTableAttribute($attrName)) {
            $this->extractTableName($attr);
        }
    }

    private function isEntityAttribute(string $attrName): bool
    {
        return str_contains($attrName, 'Entity') || str_contains($attrName, 'ORM\Entity');
    }

    private function isTableAttribute(string $attrName): bool
    {
        return str_contains($attrName, 'Table') || str_contains($attrName, 'ORM\Table');
    }

    private function extractTableName(Node\Attribute $attr): void
    {
        foreach ($attr->args as $arg) {
            if ($this->isNameArgument($arg) && $arg->value instanceof Node\Scalar\String_) {
                $this->tableName = $arg->value->value;
                break;
            }
        }
    }

    private function isNameArgument(Node\Arg $arg): bool
    {
        return null !== $arg->name && 'name' === $arg->name->name;
    }

    private function analyzeDocComments(Node\Stmt\Class_ $node): void
    {
        $docComment = $node->getDocComment();
        if (null === $docComment) {
            return;
        }

        $comment = $docComment->getText();
        $this->extractEntityFromComment($comment);
        $this->extractTableFromComment($comment);
    }

    private function extractEntityFromComment(string $comment): void
    {
        if (str_contains($comment, '@Entity') || str_contains($comment, '@ORM\Entity')) {
            $this->isEntity = true;
        }
    }

    private function extractTableFromComment(string $comment): void
    {
        if (1 === preg_match('/@(?:ORM\\\)?Table\(name="([^"]+)"\)/', $comment, $matches)) {
            $this->tableName = $matches[1];
        }
    }

    private function processProperty(Node\Stmt\Property $node): void
    {
        foreach ($node->props as $prop) {
            $this->properties[] = (string) $prop->name;
        }
    }

    private function processMethod(Node\Stmt\ClassMethod $node): void
    {
        $this->methods[] = (string) $node->name;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function isEntity(): bool
    {
        return $this->isEntity;
    }

    /** @return array<string> */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /** @return array<string> */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }
}
