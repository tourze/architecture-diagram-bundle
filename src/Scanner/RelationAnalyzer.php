<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Model\Relation;

class RelationAnalyzer
{
    private Parser $parser;

    /** @var array<string, array<string>> */
    private array $componentDependencies = [];

    /** @var array<string, string> */
    private array $classToComponentMap = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function analyze(Architecture $architecture): void
    {
        $this->buildClassToComponentMap($architecture);

        foreach ($architecture->getComponents() as $component) {
            if (null !== $component->getFilePath() && file_exists($component->getFilePath())) {
                $this->analyzeComponent($component, $architecture);
            }
        }

        $this->createRelationsFromDependencies($architecture);
    }

    private function buildClassToComponentMap(Architecture $architecture): void
    {
        foreach ($architecture->getComponents() as $component) {
            $namespace = $component->getNamespace();
            $name = $component->getName();
            $fullName = null !== $namespace ? $namespace . '\\' . $name : $name;
            $this->classToComponentMap[$fullName] = $component->getId();
            $this->classToComponentMap[$name] = $component->getId();
        }
    }

    private function analyzeComponent(Component $component, Architecture $architecture): void
    {
        $filePath = $component->getFilePath();
        if (null === $filePath) {
            return;
        }

        $code = file_get_contents($filePath);
        if (false === $code) {
            return;
        }

        try {
            $stmts = $this->parser->parse($code);
            if (null === $stmts) {
                return;
            }

            $visitor = new DependencyVisitor($this->classToComponentMap);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);

            $dependencies = $visitor->getDependencies();
            if ([] !== $dependencies) {
                $this->componentDependencies[$component->getId()] = $dependencies;
            }
        } catch (\Exception $e) {
        }
    }

    private function createRelationsFromDependencies(Architecture $architecture): void
    {
        foreach ($this->componentDependencies as $fromId => $toIds) {
            $fromComponent = $architecture->getComponent($fromId);
            if (null === $fromComponent) {
                continue;
            }

            foreach ($toIds as $toId) {
                $toComponent = $architecture->getComponent($toId);
                if (null === $toComponent || $fromId === $toId) {
                    continue;
                }

                $relation = $this->createRelation($fromComponent, $toComponent);
                $architecture->addRelation($relation);
            }
        }
    }

    private function createRelation(Component $from, Component $to): Relation
    {
        $fromType = $from->getType();
        $toType = $to->getType();

        $type = 'uses';
        $description = '';
        $technology = '';

        if ('controller' === $fromType && 'service' === $toType) {
            $description = 'Calls service methods';
            $technology = 'Dependency Injection';
        } elseif ('controller' === $fromType && 'repository' === $toType) {
            $description = 'Data access';
            $technology = 'Dependency Injection';
        } elseif ('service' === $fromType && 'repository' === $toType) {
            $description = 'Performs data operations';
            $technology = 'Method Call';
        } elseif ('repository' === $fromType && 'entity' === $toType) {
            $type = 'manages';
            $description = 'CRUD operations';
            $technology = 'Doctrine ORM';
        } elseif ('entity' === $toType) {
            $type = 'depends';
            $description = 'Uses entity';
            $technology = 'Object Reference';
        } else {
            $description = ucfirst($fromType) . ' uses ' . $toType;
            $technology = 'Dependency';
        }

        return new Relation($from->getId(), $to->getId(), $type, $description, $technology);
    }
}
