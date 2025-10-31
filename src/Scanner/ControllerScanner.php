<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Tourze\ArchitectureDiagramBundle\Model\Component;

class ControllerScanner
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
        $finder->files()->in($path)->name('*Controller.php');

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

            $visitor = new ControllerVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);

            if (null !== $visitor->getClassName() && $visitor->isController()) {
                $className = $visitor->getClassName();
                $namespace = $visitor->getNamespace();
                $fullName = null !== $namespace ? $namespace . '\\' . $className : $className;

                $component = new Component(
                    'controller_' . strtolower(str_replace('\\', '_', $fullName)),
                    $className,
                    'controller',
                    $this->extractDescription($visitor),
                    'Symfony Controller'
                );

                $component->setNamespace($namespace);
                $component->setFilePath($filePath);

                $metadata = [
                    'actions' => $visitor->getActions(),
                    'routes' => $visitor->getRoutes(),
                    'extends' => $visitor->getParentClass(),
                ];
                $component->setMetadata($metadata);

                return $component;
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    private function extractDescription(ControllerVisitor $visitor): string
    {
        $actions = $visitor->getActions();
        $count = count($actions);
        $description = "Controller with {$count} actions";

        $routes = $visitor->getRoutes();
        if ([] !== $routes) {
            $routeCount = count($routes);
            $description .= " ({$routeCount} routes)";
        }

        return $description;
    }
}
