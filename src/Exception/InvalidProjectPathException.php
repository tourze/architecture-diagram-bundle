<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Exception;

class InvalidProjectPathException extends \RuntimeException
{
    public static function fromPath(string $path): self
    {
        return new self("Project path does not exist: {$path}");
    }
}
