<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Tourze\ArchitectureDiagramBundle\Model\Component;

class EntityScanner
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /** @return array<int, Component> */
    public function scan(string $path): array
    {
        $components = [];
        $finder = new Finder();
        $finder->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            $component = $this->scanFile($file->getRealPath());
            if (null !== $component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    private function scanFile(string $filePath): ?Component
    {
        $code = file_get_contents($filePath);
        if (false === $code) {
            return null;
        }

        try {
            $stmts = $this->parser->parse($code);
            if (null === $stmts) {
                return null;
            }

            $visitor = new EntityVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);

            if (null !== $visitor->getClassName() && $visitor->isEntity()) {
                $className = $visitor->getClassName();
                $namespace = $visitor->getNamespace();
                $fullName = null !== $namespace ? $namespace . '\\' . $className : $className;

                $component = new Component(
                    'entity_' . strtolower(str_replace('\\', '_', $fullName)),
                    $className,
                    'entity',
                    $this->extractDescription($visitor),
                    'Doctrine ORM'
                );

                $component->setNamespace($namespace);
                $component->setFilePath($filePath);

                $metadata = [
                    'properties' => $visitor->getProperties(),
                    'methods' => $visitor->getMethods(),
                    'table' => $visitor->getTableName(),
                ];
                $component->setMetadata($metadata);

                return $component;
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    private function extractDescription(EntityVisitor $visitor): string
    {
        $properties = $visitor->getProperties();
        $count = count($properties);
        $description = "Entity with {$count} properties";

        $tableName = $visitor->getTableName();
        if (null !== $tableName) {
            $description .= " (Table: {$tableName})";
        }

        return $description;
    }
}
