<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Model;

class Architecture
{
    /** @var array<string, Component> */
    private array $components = [];

    /** @var array<int, Relation> */
    private array $relations = [];

    /** @var array<string, array<string>> */
    private array $layers = [];

    /** @var array<string, mixed> */
    private array $metadata = [];

    /** @var array<string, array{name: string, type: string, properties: array<string, mixed>}> */
    private array $infrastructures = [];

    /** @var array<string, array{name: string, type: string, technology: string}> */
    private array $externalSystems = [];

    /** @var array<int, array{from: string, to: string, data: string, frequency: string, protocol: string}> */
    private array $dataFlows = [];

    /** @var array<string, array{name: string, type: string, scope: string}> */
    private array $securityMeasures = [];

    private string $name;

    private string $description;

    public function __construct(string $name = 'System Architecture', string $description = '')
    {
        $this->name = $name;
        $this->description = $description;
        $this->initializeLayers();
    }

    private function initializeLayers(): void
    {
        $this->layers = [
            'presentation' => ['controller', 'command', 'form'],
            'application' => ['service', 'handler', 'manager'],
            'domain' => ['entity', 'model', 'valueobject'],
            'infrastructure' => ['repository', 'gateway', 'adapter'],
        ];
    }

    public function addComponent(Component $component): self
    {
        $this->components[$component->getId()] = $component;

        return $this;
    }

    public function addComponentObject(Component $component): self
    {
        return $this->addComponent($component);
    }

    public function createAndAddComponent(string $type, string $name, string $layer = ''): self
    {
        $component = new Component($name, $name, $type);
        if ('' !== $layer) {
            $component->setLayer($layer);
        }

        return $this->addComponent($component);
    }

    public function getComponent(string $id): ?Component
    {
        return $this->components[$id] ?? null;
    }

    /** @return array<string, Component> */
    public function getComponents(): array
    {
        return $this->components;
    }

    /** @return array<string, Component> */
    public function getComponentsByType(string $type): array
    {
        return array_filter($this->components, fn (Component $c) => $c->getType() === $type);
    }

    public function addRelation(Relation $relation): self
    {
        foreach ($this->relations as $existingRelation) {
            if ($existingRelation->equals($relation)) {
                return $this;
            }
        }
        $this->relations[] = $relation;

        return $this;
    }

    public function createAndAddRelation(string $from, string $to, string $type = 'uses', string $technology = ''): self
    {
        $relation = new Relation($from, $to, $type, '', $technology);

        return $this->addRelation($relation);
    }

    /** @return array<int, Relation> */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /** @return array<int, Relation> */
    public function getRelationsFrom(string $componentId): array
    {
        return array_filter($this->relations, fn (Relation $r) => $r->getFrom() === $componentId);
    }

    /** @return array<int, Relation> */
    public function getRelationsTo(string $componentId): array
    {
        return array_filter($this->relations, fn (Relation $r) => $r->getTo() === $componentId);
    }

    /** @return array<string, array<string>> */
    public function getLayers(): array
    {
        return $this->layers;
    }

    public function getLayerForType(string $type): ?string
    {
        $typeLower = strtolower($type);
        foreach ($this->layers as $layer => $types) {
            if (in_array($typeLower, $types, true)) {
                return $layer;
            }
        }

        return null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function hasComponents(): bool
    {
        return [] !== $this->components;
    }

    public function hasRelations(): bool
    {
        return [] !== $this->relations;
    }

    /** @return array{total_components: int, total_relations: int, total_data_flows: int, total_external_systems: int, total_infrastructures: int, total_security_measures: int, components_by_type: array<string, int>, components_by_layer: array<string, int>} */
    public function getStatistics(): array
    {
        $stats = [
            'total_components' => count($this->components),
            'total_relations' => count($this->relations),
            'total_data_flows' => count($this->dataFlows),
            'total_external_systems' => count($this->externalSystems),
            'total_infrastructures' => count($this->infrastructures),
            'total_security_measures' => count($this->securityMeasures),
            'components_by_type' => [],
            'components_by_layer' => [],
        ];

        foreach ($this->components as $component) {
            $type = $component->getType();
            $stats['components_by_type'][$type] = ($stats['components_by_type'][$type] ?? 0) + 1;

            $layer = $this->getLayerForType($type);
            if (null !== $layer) {
                $stats['components_by_layer'][$layer] = ($stats['components_by_layer'][$layer] ?? 0) + 1;
            }
        }

        return $stats;
    }

    /** @param mixed $value */
    public function setMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $properties */
    public function addInfrastructure(string $id, string $name, string $type, array $properties = []): self
    {
        $this->infrastructures[$id] = [
            'name' => $name,
            'type' => $type,
            'properties' => $properties,
        ];

        return $this;
    }

    /** @return array<string, array{name: string, type: string, properties: array<string, mixed>}> */
    public function getInfrastructures(): array
    {
        return $this->infrastructures;
    }

    public function addExternalSystem(string $id, string $name, string $type, string $technology): self
    {
        $this->externalSystems[$id] = [
            'name' => $name,
            'type' => $type,
            'technology' => $technology,
        ];

        return $this;
    }

    /** @return array<string, array{name: string, type: string, technology: string}> */
    public function getExternalSystems(): array
    {
        return $this->externalSystems;
    }

    public function addDataFlow(string $from, string $to, string $data, string $frequency = '', string $protocol = ''): self
    {
        $this->dataFlows[] = [
            'from' => $from,
            'to' => $to,
            'data' => $data,
            'frequency' => $frequency,
            'protocol' => $protocol,
        ];

        return $this;
    }

    /** @return array<int, array{from: string, to: string, data: string, frequency: string, protocol: string}> */
    public function getDataFlows(): array
    {
        return $this->dataFlows;
    }

    public function addSecurityMeasure(string $id, string $name, string $type, string $scope): self
    {
        $this->securityMeasures[$id] = [
            'name' => $name,
            'type' => $type,
            'scope' => $scope,
        ];

        return $this;
    }

    /** @return array<string, array{name: string, type: string, scope: string}> */
    public function getSecurityMeasures(): array
    {
        return $this->securityMeasures;
    }
}
