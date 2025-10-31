<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Model;

class Component
{
    private string $id;

    private string $name;

    private string $type;

    private string $description;

    private string $technology;

    /** @var array<string, mixed> */
    private array $metadata = [];

    private ?string $namespace = null;

    private ?string $filePath = null;

    private string $layer = '';

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $id,
        string $name,
        string $type,
        string $description = '',
        string $technology = 'PHP',
        array $metadata = [],
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->technology = $technology;
        $this->metadata = $metadata;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getTechnology(): string
    {
        return $this->technology;
    }

    public function setTechnology(string $technology): void
    {
        $this->technology = $technology;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function setNamespace(?string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getLayer(): string
    {
        return $this->layer;
    }

    public function setLayer(string $layer): void
    {
        $this->layer = $layer;
    }

    public function getShortName(): string
    {
        if (null !== $this->namespace) {
            $parts = explode('\\', $this->name);

            return end($parts);
        }

        return $this->name;
    }
}
