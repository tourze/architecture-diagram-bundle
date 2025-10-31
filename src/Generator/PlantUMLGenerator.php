<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Generator;

use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Model\Relation;

class PlantUMLGenerator
{
    /** @var array<string, string> */
    private array $colors = [
        'controller' => '#ff9999',
        'entity' => '#99ccff',
        'repository' => '#99ff99',
        'service' => '#ffcc99',
        'command' => '#ff99cc',
        'form' => '#ccff99',
        'event_listener' => '#ffb366',
        'event_subscriber' => '#ff9966',
        'voter' => '#b3d9ff',
        'handler' => '#ffd966',
        'manager' => '#ff99ff',
    ];

    /** @var array<string, string> */
    private array $icons = [
        'controller' => 'component',
        'entity' => 'entity',
        'repository' => 'database',
        'service' => 'collections',
        'command' => 'rectangle',
        'form' => 'card',
        'event_listener' => 'queue',
        'event_subscriber' => 'queue',
        'voter' => 'actor',
        'handler' => 'process',
        'manager' => 'package',
    ];

    /** @param array{level?: string, include_c4?: bool, group_by_layer?: bool} $options */
    public function generate(Architecture $architecture, array $options = []): string
    {
        $level = $options['level'] ?? 'component';
        $includeC4 = $options['include_c4'] ?? true;
        $groupByLayer = $options['group_by_layer'] ?? true;

        $output = [];
        $output[] = '@startuml';

        if ($includeC4) {
            $output[] = '!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML/master/C4_Component.puml';
            $output[] = '';
            $output[] = 'LAYOUT_WITH_LEGEND()';
            $output[] = '';
        } else {
            $output[] = 'skinparam componentStyle rectangle';
            $output[] = '';
        }

        $output[] = 'title ' . $architecture->getName();
        if ('' !== $architecture->getDescription()) {
            $output[] = 'caption ' . $architecture->getDescription();
        }
        $output[] = '';

        if ($includeC4) {
            $output[] = 'Container_Boundary(app, "Application") {';
            $output = array_merge($output, $this->generateC4Components($architecture, $groupByLayer));
            $output[] = '}';
        } else {
            $output = array_merge($output, $this->generateSimpleComponents($architecture, $groupByLayer));
        }

        $output[] = '';
        $output = array_merge($output, $this->generateRelations($architecture, $includeC4));

        $output[] = '';
        $output[] = '@enduml';

        return implode(PHP_EOL, $output);
    }

    /** @return array<string> */
    private function generateC4Components(Architecture $architecture, bool $groupByLayer): array
    {
        if ($groupByLayer) {
            return $this->generateGroupedC4Components($architecture);
        }

        return $this->generateFlatC4Components($architecture);
    }

    /** @return array<string> */
    private function generateGroupedC4Components(Architecture $architecture): array
    {
        $componentsByLayer = $this->groupComponentsByLayer($architecture);
        $output = $this->renderLayeredComponents($componentsByLayer, $architecture->getLayers());

        return array_merge($output, $this->renderOtherComponents($componentsByLayer));
    }

    /** @return array<string> */
    private function generateFlatC4Components(Architecture $architecture): array
    {
        $output = [];
        foreach ($architecture->getComponents() as $component) {
            $output = array_merge($output, $this->generateC4Component($component, '    '));
        }

        return $output;
    }

    /** @return array<string, array<Component>> */
    private function groupComponentsByLayer(Architecture $architecture): array
    {
        // Initialize all layers with empty arrays
        $componentsByLayer = [];
        foreach ($architecture->getLayers() as $layerName => $types) {
            $componentsByLayer[$layerName] = [];
        }
        $componentsByLayer['other'] = [];

        foreach ($architecture->getComponents() as $component) {
            $layer = $architecture->getLayerForType($component->getType()) ?? 'other';
            $componentsByLayer[$layer][] = $component;
        }

        return $componentsByLayer;
    }

    /**
     * @param array<string, array<Component>> $componentsByLayer
     * @param array<string, array<string>> $layers
     * @return array<string>
     */
    private function renderLayeredComponents(array $componentsByLayer, array $layers): array
    {
        $output = [];
        foreach ($layers as $layerName => $types) {
            if ([] === $componentsByLayer[$layerName]) {
                continue;
            }

            $output = array_merge($output, $this->renderLayerBoundary($layerName, $componentsByLayer[$layerName]));
        }

        return $output;
    }

    /**
     * @param array<Component> $components
     * @return array<string>
     */
    private function renderLayerBoundary(string $layerName, array $components): array
    {
        $output = [];
        $output[] = '    Container_Boundary(' . $layerName . ', "' . ucfirst($layerName) . ' Layer") {';
        foreach ($components as $component) {
            $output = array_merge($output, $this->generateC4Component($component, '        '));
        }
        $output[] = '    }';
        $output[] = '';

        return $output;
    }

    /**
     * @param array<string, array<Component>> $componentsByLayer
     * @return array<string>
     */
    private function renderOtherComponents(array $componentsByLayer): array
    {
        if ([] === $componentsByLayer['other']) {
            return [];
        }

        $output = [];
        $output[] = '    Container_Boundary(other, "Other Components") {';
        foreach ($componentsByLayer['other'] as $component) {
            $output = array_merge($output, $this->generateC4Component($component, '        '));
        }
        $output[] = '    }';

        return $output;
    }

    /** @return array<string> */
    private function generateC4Component(Component $component, string $indent): array
    {
        $id = $this->sanitizeId($component->getId());
        $name = $component->getName();
        $technology = $component->getTechnology();
        $description = $this->truncateDescription($component->getDescription());

        return [sprintf(
            '%sComponent(%s, "%s", "%s", "%s")',
            $indent,
            $id,
            $name,
            $technology,
            $description
        )];
    }

    /** @return array<string> */
    private function generateSimpleComponents(Architecture $architecture, bool $groupByLayer): array
    {
        if ($groupByLayer) {
            return $this->generateGroupedSimpleComponents($architecture);
        }

        return $this->generateFlatSimpleComponents($architecture);
    }

    /** @return array<string> */
    private function generateGroupedSimpleComponents(Architecture $architecture): array
    {
        $componentsByLayer = $this->groupComponentsByLayer($architecture);
        $output = $this->renderSimpleLayeredComponents($componentsByLayer, $architecture->getLayers());

        return array_merge($output, $this->renderSimpleOtherComponents($componentsByLayer));
    }

    /** @return array<string> */
    private function generateFlatSimpleComponents(Architecture $architecture): array
    {
        $output = [];
        foreach ($architecture->getComponents() as $component) {
            $output = array_merge($output, $this->generateSimpleComponent($component, ''));
        }

        return $output;
    }

    /**
     * @param array<string, array<Component>> $componentsByLayer
     * @param array<string, array<string>> $layers
     * @return array<string>
     */
    private function renderSimpleLayeredComponents(array $componentsByLayer, array $layers): array
    {
        $output = [];
        foreach ($layers as $layerName => $types) {
            if ([] === $componentsByLayer[$layerName]) {
                continue;
            }

            $output = array_merge($output, $this->renderSimpleLayerPackage($layerName, $componentsByLayer[$layerName]));
        }

        return $output;
    }

    /**
     * @param array<Component> $components
     * @return array<string>
     */
    private function renderSimpleLayerPackage(string $layerName, array $components): array
    {
        $output = [];
        $output[] = 'package "' . ucfirst($layerName) . ' Layer" {';
        foreach ($components as $component) {
            $output = array_merge($output, $this->generateSimpleComponent($component, '    '));
        }
        $output[] = '}';
        $output[] = '';

        return $output;
    }

    /**
     * @param array<string, array<Component>> $componentsByLayer
     * @return array<string>
     */
    private function renderSimpleOtherComponents(array $componentsByLayer): array
    {
        if ([] === $componentsByLayer['other']) {
            return [];
        }

        $output = [];
        $output[] = 'package "Other Components" {';
        foreach ($componentsByLayer['other'] as $component) {
            $output = array_merge($output, $this->generateSimpleComponent($component, '    '));
        }
        $output[] = '}';

        return $output;
    }

    /** @return array<string> */
    private function generateSimpleComponent(Component $component, string $indent): array
    {
        $id = $this->sanitizeId($component->getId());
        $name = $component->getName();
        $type = $component->getType();
        $color = $this->colors[$type] ?? '#cccccc';
        $icon = $this->icons[$type] ?? 'component';

        return [sprintf(
            '%s[%s] as %s <<$%s>> %s',
            $indent,
            $name,
            $id,
            $icon,
            $color
        )];
    }

    /** @return array<string> */
    private function generateRelations(Architecture $architecture, bool $includeC4): array
    {
        $output = [];
        foreach ($architecture->getRelations() as $relation) {
            $output = array_merge($output, $this->generateRelation($relation, $includeC4));
        }

        return $output;
    }

    /** @return array<string> */
    private function generateRelation(Relation $relation, bool $includeC4): array
    {
        $from = $this->sanitizeId($relation->getFrom());
        $to = $this->sanitizeId($relation->getTo());
        $description = $relation->getDescription();
        $technology = $relation->getTechnology();

        if ($includeC4) {
            if ('' !== $technology) {
                return [sprintf('Rel(%s, %s, "%s", "%s")', $from, $to, $description, $technology)];
            }

            return [sprintf('Rel(%s, %s, "%s")', $from, $to, $description)];
        }
        $arrow = match ($relation->getType()) {
            'implements' => '..|>',
            'extends' => '--|>',
            'depends' => '..>',
            'manages' => '--*',
            default => '-->',
        };

        if ('' !== $description) {
            return [sprintf('%s %s %s : %s', $from, $arrow, $to, $description)];
        }

        return [sprintf('%s %s %s', $from, $arrow, $to)];
    }

    private function sanitizeId(string $id): string
    {
        $result = preg_replace('/[^a-zA-Z0-9_]/', '_', $id);

        return null !== $result ? $result : $id;
    }

    private function truncateDescription(string $description, int $maxLength = 50): string
    {
        if (strlen($description) <= $maxLength) {
            return $description;
        }

        return substr($description, 0, $maxLength - 3) . '...';
    }
}
