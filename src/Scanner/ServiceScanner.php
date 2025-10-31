<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Tourze\ArchitectureDiagramBundle\Model\Component;

class ServiceScanner
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /** @return array<int, Component> */
    public function scan(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $components = [];
        $finder = new Finder();
        $finder->files()->in($path)->name('*Service.php')->name('*Manager.php')->name('*Handler.php');

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

            $visitor = new ServiceVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);

            $className = $visitor->getClassName();
            if (null === $className) {
                return null;
            }

            $namespace = $visitor->getNamespace();
            $fullName = null !== $namespace ? $namespace . '\\' . $className : $className;

            $component = new Component(
                'service_' . strtolower(str_replace('\\', '_', $fullName)),
                $className,
                'service',
                $this->extractDescription($visitor),
                'Business Logic'
            );

            $component->setNamespace($namespace);
            $component->setFilePath($filePath);

            $metadata = [
                'methods' => $visitor->getPublicMethods(),
                'dependencies' => $visitor->getDependencies(),
                'implements' => $visitor->getInterfaces(),
            ];
            $component->setMetadata($metadata);

            return $component;
        } catch (\Exception $e) {
        }

        return null;
    }

    private function extractDescription(ServiceVisitor $visitor): string
    {
        $methods = $visitor->getPublicMethods();
        $methodCount = count($methods);
        $dependencies = $visitor->getDependencies();
        $depCount = count($dependencies);

        $description = "Service with {$methodCount} public methods";

        if ($depCount > 0) {
            $description .= " and {$depCount} dependencies";
        }

        return $description;
    }
}
