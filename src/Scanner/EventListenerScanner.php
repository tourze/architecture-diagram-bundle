<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Tourze\ArchitectureDiagramBundle\Model\Component;

class EventListenerScanner
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return array<Component>
     */
    public function scan(string $path): array
    {
        $components = [];

        $paths = [$path];
        if (!is_dir($path)) {
            $parentPath = dirname($path);
            $paths = [
                $parentPath . '/EventListener',
                $parentPath . '/EventSubscriber',
                $parentPath . '/Listener',
            ];
        }

        foreach ($paths as $scanPath) {
            if (!is_dir($scanPath)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($scanPath)->name('*.php');

            foreach ($finder as $file) {
                $component = $this->scanFile($file->getRealPath());
                if (null !== $component) {
                    $components[] = $component;
                }
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

            $visitor = new EventListenerVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);

            if (null !== $visitor->getClassName() && ($visitor->isSubscriber() || [] !== $visitor->getEvents())) {
                $className = $visitor->getClassName();
                $namespace = $visitor->getNamespace();
                $fullName = null !== $namespace ? $namespace . '\\' . $className : $className;

                $type = $visitor->isSubscriber() ? 'event_subscriber' : 'event_listener';

                $component = new Component(
                    $type . '_' . strtolower(str_replace('\\', '_', $fullName)),
                    $className,
                    $type,
                    $this->extractDescription($visitor),
                    'Event System'
                );

                $component->setNamespace($namespace);
                $component->setFilePath($filePath);

                $metadata = [
                    'events' => $visitor->getEvents(),
                    'methods' => $visitor->getMethods(),
                    'isSubscriber' => $visitor->isSubscriber(),
                ];
                $component->setMetadata($metadata);

                return $component;
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    private function extractDescription(EventListenerVisitor $visitor): string
    {
        $events = $visitor->getEvents();
        $eventCount = count($events);
        $type = $visitor->isSubscriber() ? 'Event Subscriber' : 'Event Listener';

        $description = "{$type} handling {$eventCount} events";

        if ([] !== $events) {
            $eventList = implode(', ', array_slice($events, 0, 3));
            if (count($events) > 3) {
                $eventList .= '...';
            }
            $description .= " ({$eventList})";
        }

        return $description;
    }
}
