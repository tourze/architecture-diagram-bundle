<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Tourze\ArchitectureDiagramBundle\Model\Component;

class RepositoryScanner
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
        $finder->files()->in($path)->name('*Repository.php');

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

            $visitor = new RepositoryVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);

            if (null !== $visitor->getClassName()) {
                $className = $visitor->getClassName();
                $namespace = $visitor->getNamespace();
                $fullName = null !== $namespace ? $namespace . '\\' . $className : $className;

                $component = new Component(
                    'repository_' . strtolower(str_replace('\\', '_', $fullName)),
                    $className,
                    'repository',
                    $this->extractDescription($visitor),
                    'Doctrine Repository'
                );

                $component->setNamespace($namespace);
                $component->setFilePath($filePath);

                $metadata = [
                    'methods' => $visitor->getPublicMethods(),
                    'entity' => $visitor->getEntityClass(),
                    'extends' => $visitor->getParentClass(),
                ];
                $component->setMetadata($metadata);

                return $component;
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    private function extractDescription(RepositoryVisitor $visitor): string
    {
        $methods = $visitor->getPublicMethods();
        $customMethods = array_filter($methods, function ($method) {
            return !in_array($method, ['__construct', 'find', 'findAll', 'findBy', 'findOneBy'], true);
        });

        $count = count($customMethods);
        $description = "Repository with {$count} custom methods";

        $entityClass = $visitor->getEntityClass();
        if (null !== $entityClass) {
            $entityName = basename(str_replace('\\', '/', $entityClass));
            $description .= " for {$entityName}";
        }

        return $description;
    }
}
