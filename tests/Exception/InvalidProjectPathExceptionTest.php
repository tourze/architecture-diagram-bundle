<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\ArchitectureDiagramBundle\Exception\InvalidProjectPathException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidProjectPathException::class)]
class InvalidProjectPathExceptionTest extends AbstractExceptionTestCase
{
    public function testFromPath(): void
    {
        $path = '/non/existent/path';
        $exception = InvalidProjectPathException::fromPath($path);

        self::assertSame("Project path does not exist: {$path}", $exception->getMessage());
    }

    public function testExceptionIsThrowable(): void
    {
        $this->expectException(InvalidProjectPathException::class);
        $this->expectExceptionMessage('Project path does not exist: /test/path');

        throw InvalidProjectPathException::fromPath('/test/path');
    }

    public function testCanCreateWithCustomMessage(): void
    {
        $exception = new InvalidProjectPathException('Custom error message');

        self::assertSame('Custom error message', $exception->getMessage());
    }
}
