<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Generator\PlantUMLGenerator;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;

/**
 * @internal
 */
#[CoversClass(PlantUMLGenerator::class)]
class PlantUMLGeneratorTest extends TestCase
{
    private PlantUMLGenerator $generator;

    private Architecture $architecture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new PlantUMLGenerator();
        $this->architecture = new Architecture('TestProject');
    }

    public function testGenerateEmptyArchitecture(): void
    {
        $result = $this->generator->generate($this->architecture);

        self::assertStringContainsString('@startuml', $result);
        self::assertStringContainsString('@enduml', $result);
        self::assertStringContainsString('title TestProject', $result);
    }

    public function testGenerateWithC4Model(): void
    {
        $this->architecture->createAndAddComponent('controller', 'UserController', 'presentation');

        $result = $this->generator->generate($this->architecture, ['include_c4' => true]);

        self::assertStringContainsString('!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML', $result);
        self::assertStringContainsString('Container_Boundary(app, "Application")', $result);
        self::assertStringContainsString('Component(', $result);
    }

    public function testGenerateWithoutC4Model(): void
    {
        $this->architecture->createAndAddComponent('controller', 'UserController', 'presentation');

        $result = $this->generator->generate($this->architecture, ['include_c4' => false]);

        self::assertStringNotContainsString('!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML', $result);
        self::assertStringContainsString('skinparam componentStyle rectangle', $result);
        self::assertStringContainsString('[UserController]', $result);
    }

    public function testGenerateWithLayerGrouping(): void
    {
        $this->architecture->createAndAddComponent('controller', 'UserController', 'presentation');
        $this->architecture->createAndAddComponent('service', 'UserService', 'business');
        $this->architecture->createAndAddComponent('repository', 'UserRepository', 'persistence');

        $result = $this->generator->generate($this->architecture, [
            'include_c4' => false,
            'group_by_layer' => true,
        ]);

        self::assertStringContainsString('package "Presentation Layer"', $result);
        self::assertStringContainsString('package "Application Layer"', $result);
        self::assertStringContainsString('package "Infrastructure Layer"', $result);
    }

    public function testGenerateWithoutLayerGrouping(): void
    {
        $this->architecture->createAndAddComponent('controller', 'UserController', 'presentation');
        $this->architecture->createAndAddComponent('service', 'UserService', 'business');

        $result = $this->generator->generate($this->architecture, [
            'include_c4' => false,
            'group_by_layer' => false,
        ]);

        self::assertStringNotContainsString('package "Presentation Layer"', $result);
        self::assertStringNotContainsString('package "Application Layer"', $result);
        self::assertStringContainsString('[UserController]', $result);
        self::assertStringContainsString('[UserService]', $result);
    }

    public function testGenerateWithRelations(): void
    {
        $controller = new Component('controller', 'UserController', 'presentation');
        $service = new Component('service', 'UserService', 'business');
        $this->architecture->addComponentObject($controller);
        $this->architecture->addComponentObject($service);

        $this->architecture->createAndAddRelation('UserController', 'UserService', 'uses', 'uses');

        $result = $this->generator->generate($this->architecture, ['include_c4' => false]);

        self::assertStringContainsString('UserController --> UserService', $result);
    }

    public function testGenerateWithC4Relations(): void
    {
        $controller = new Component('controller', 'UserController', 'presentation');
        $service = new Component('service', 'UserService', 'business');
        $this->architecture->addComponentObject($controller);
        $this->architecture->addComponentObject($service);

        $this->architecture->createAndAddRelation('UserController', 'UserService', 'uses', 'HTTP/REST');

        $result = $this->generator->generate($this->architecture, ['include_c4' => true]);

        self::assertStringContainsString('Rel(UserController, UserService,', $result);
    }

    public function testGenerateWithDifferentRelationTypes(): void
    {
        $parent = new Component('service', 'BaseService', 'business');
        $child = new Component('service', 'UserService', 'business');
        $this->architecture->addComponentObject($parent);
        $this->architecture->addComponentObject($child);

        $this->architecture->createAndAddRelation('UserService', 'BaseService', 'extends', 'extends');

        $result = $this->generator->generate($this->architecture, ['include_c4' => false]);

        self::assertStringContainsString('UserService --|> BaseService', $result);
    }

    public function testGenerateWithDescription(): void
    {
        $this->architecture->setDescription('Test project architecture');

        $result = $this->generator->generate($this->architecture);

        self::assertStringContainsString('caption Test project architecture', $result);
    }

    public function testSanitizeId(): void
    {
        $component = new Component('User-Controller.php', 'User-Controller.php', 'controller');
        $this->architecture->addComponentObject($component);

        $result = $this->generator->generate($this->architecture, ['include_c4' => false]);

        self::assertStringContainsString('User_Controller_php', $result);
    }

    public function testTruncateLongDescriptions(): void
    {
        $longDescription = str_repeat('Very long description ', 10);
        $component = new Component('service', 'TestService', 'business');
        $component->setDescription($longDescription);
        $this->architecture->addComponentObject($component);

        $result = $this->generator->generate($this->architecture, ['include_c4' => true]);

        self::assertStringContainsString('...', $result);
        self::assertStringNotContainsString($longDescription, $result);
    }

    public function testGenerateWithMultipleLayers(): void
    {
        $this->architecture->createAndAddComponent('controller', 'UserController', 'presentation');
        $this->architecture->createAndAddComponent('controller', 'AdminController', 'presentation');
        $this->architecture->createAndAddComponent('service', 'UserService', 'business');
        $this->architecture->createAndAddComponent('repository', 'UserRepository', 'persistence');
        $this->architecture->createAndAddComponent('entity', 'User', 'persistence');

        $result = $this->generator->generate($this->architecture, [
            'include_c4' => false,
            'group_by_layer' => true,
        ]);

        $presentationCount = substr_count($result, '[UserController]') + substr_count($result, '[AdminController]');
        $businessCount = substr_count($result, '[UserService]');
        $persistenceCount = substr_count($result, '[UserRepository]') + substr_count($result, '[User]');

        self::assertEquals(2, $presentationCount);
        self::assertEquals(1, $businessCount);
        self::assertEquals(2, $persistenceCount);
    }
}
