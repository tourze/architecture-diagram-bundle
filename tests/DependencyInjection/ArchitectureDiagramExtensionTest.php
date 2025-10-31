<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\ArchitectureDiagramBundle\DependencyInjection\ArchitectureDiagramExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(ArchitectureDiagramExtension::class)]
final class ArchitectureDiagramExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private ArchitectureDiagramExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new ArchitectureDiagramExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testLoadWithDefaultConfig(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition('Tourze\ArchitectureDiagramBundle\Scanner\ProjectScanner'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ArchitectureDiagramBundle\Generator\PlantUMLGenerator'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ArchitectureDiagramBundle\Command\GenerateArchitectureDiagramCommand'));
    }

    public function testLoadRegistersServices(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition('Tourze\ArchitectureDiagramBundle\Scanner\EnhancedArchitectureScanner'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ArchitectureDiagramBundle\Generator\EnhancedPlantUMLGenerator'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ArchitectureDiagramBundle\Command\GenerateEnhancedArchitectureCommand'));
    }

    public function testExtensionAlias(): void
    {
        $this->assertEquals('architecture_diagram', $this->extension->getAlias());
    }
}
