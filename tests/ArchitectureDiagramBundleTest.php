<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\ArchitectureDiagramBundle\ArchitectureDiagramBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(ArchitectureDiagramBundle::class)]
#[RunTestsInSeparateProcesses]
final class ArchitectureDiagramBundleTest extends AbstractBundleTestCase
{
}
