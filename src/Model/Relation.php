<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Model;

class Relation
{
    private string $from;

    private string $to;

    private string $type;

    private string $description;

    private string $technology;

    public function __construct(
        string $from,
        string $to,
        string $type = 'uses',
        string $description = '',
        string $technology = '',
    ) {
        $this->from = $from;
        $this->to = $to;
        $this->type = $type;
        $this->description = $description;
        $this->technology = $technology;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
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

    public function equals(Relation $other): bool
    {
        return $this->from === $other->from
            && $this->to === $other->to
            && $this->type === $other->type;
    }
}
