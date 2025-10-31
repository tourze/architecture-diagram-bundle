<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Model\Relation;

/**
 * @internal
 */
#[CoversClass(Architecture::class)]
class ArchitectureTest extends TestCase
{
    private Architecture $architecture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->architecture = new Architecture();
    }

    public function testConstructorWithDefaults(): void
    {
        $architecture = new Architecture();

        $this->assertSame('System Architecture', $architecture->getName());
        $this->assertSame('', $architecture->getDescription());
        $this->assertEmpty($architecture->getComponents());
        $this->assertEmpty($architecture->getRelations());
        $this->assertFalse($architecture->hasComponents());
        $this->assertFalse($architecture->hasRelations());
    }

    public function testConstructorWithParameters(): void
    {
        $name = 'Custom Architecture';
        $description = 'A test architecture';
        $architecture = new Architecture($name, $description);

        $this->assertSame($name, $architecture->getName());
        $this->assertSame($description, $architecture->getDescription());
    }

    public function testSetName(): void
    {
        $name = 'New Architecture Name';
        $this->architecture->setName($name);

        $this->assertSame($name, $this->architecture->getName());
    }

    public function testSetDescription(): void
    {
        $description = 'New description';
        $this->architecture->setDescription($description);

        $this->assertSame($description, $this->architecture->getDescription());
    }

    public function testInitializeLayers(): void
    {
        $layers = $this->architecture->getLayers();

        $this->assertIsArray($layers);
        $this->assertCount(4, $layers);
        $this->assertArrayHasKey('presentation', $layers);
        $this->assertArrayHasKey('application', $layers);
        $this->assertArrayHasKey('domain', $layers);
        $this->assertArrayHasKey('infrastructure', $layers);

        $this->assertSame(['controller', 'command', 'form'], $layers['presentation']);
        $this->assertSame(['service', 'handler', 'manager'], $layers['application']);
        $this->assertSame(['entity', 'model', 'valueobject'], $layers['domain']);
        $this->assertSame(['repository', 'gateway', 'adapter'], $layers['infrastructure']);
    }

    public function testAddComponent(): void
    {
        $component = new Component('test-id', 'Test Component', 'service');
        $result = $this->architecture->addComponent($component);

        $this->assertSame($this->architecture, $result);
        $this->assertTrue($this->architecture->hasComponents());
        $this->assertCount(1, $this->architecture->getComponents());
        $this->assertSame($component, $this->architecture->getComponent('test-id'));
    }

    public function testAddMultipleComponents(): void
    {
        $component1 = new Component('id1', 'Component 1', 'service');
        $component2 = new Component('id2', 'Component 2', 'controller');

        $this->architecture->addComponent($component1);
        $this->architecture->addComponent($component2);

        $components = $this->architecture->getComponents();
        $this->assertCount(2, $components);
        $this->assertSame($component1, $components['id1']);
        $this->assertSame($component2, $components['id2']);
    }

    public function testGetComponent(): void
    {
        $component = new Component('test-id', 'Test Component', 'service');
        $this->architecture->addComponent($component);

        $retrieved = $this->architecture->getComponent('test-id');
        $this->assertSame($component, $retrieved);
    }

    public function testGetComponentNotFound(): void
    {
        $result = $this->architecture->getComponent('non-existent');
        $this->assertNull($result);
    }

    public function testGetComponentsByType(): void
    {
        $service1 = new Component('service1', 'Service 1', 'service');
        $service2 = new Component('service2', 'Service 2', 'service');
        $controller = new Component('controller1', 'Controller 1', 'controller');

        $this->architecture->addComponent($service1);
        $this->architecture->addComponent($service2);
        $this->architecture->addComponent($controller);

        $services = $this->architecture->getComponentsByType('service');
        $this->assertCount(2, $services);
        $this->assertArrayHasKey('service1', $services);
        $this->assertArrayHasKey('service2', $services);

        $controllers = $this->architecture->getComponentsByType('controller');
        $this->assertCount(1, $controllers);
        $this->assertArrayHasKey('controller1', $controllers);

        $repositories = $this->architecture->getComponentsByType('repository');
        $this->assertEmpty($repositories);
    }

    public function testAddRelation(): void
    {
        $relation = new Relation('from-id', 'to-id', 'uses');
        $result = $this->architecture->addRelation($relation);

        $this->assertSame($this->architecture, $result);
        $this->assertTrue($this->architecture->hasRelations());
        $this->assertCount(1, $this->architecture->getRelations());
    }

    public function testAddDuplicateRelation(): void
    {
        $relation1 = new Relation('from-id', 'to-id', 'uses');
        $relation2 = new Relation('from-id', 'to-id', 'uses');

        $this->architecture->addRelation($relation1);
        $this->architecture->addRelation($relation2);

        $relations = $this->architecture->getRelations();
        $this->assertCount(1, $relations);
    }

    public function testAddDifferentRelations(): void
    {
        $relation1 = new Relation('from-id', 'to-id', 'uses');
        $relation2 = new Relation('from-id', 'to-id', 'extends');
        $relation3 = new Relation('other-id', 'to-id', 'uses');

        $this->architecture->addRelation($relation1);
        $this->architecture->addRelation($relation2);
        $this->architecture->addRelation($relation3);

        $relations = $this->architecture->getRelations();
        $this->assertCount(3, $relations);
    }

    public function testGetRelationsFrom(): void
    {
        $relation1 = new Relation('from-id', 'to-id1', 'uses');
        $relation2 = new Relation('from-id', 'to-id2', 'extends');
        $relation3 = new Relation('other-id', 'to-id1', 'uses');

        $this->architecture->addRelation($relation1);
        $this->architecture->addRelation($relation2);
        $this->architecture->addRelation($relation3);

        $relations = $this->architecture->getRelationsFrom('from-id');
        $this->assertCount(2, $relations);

        $otherRelations = $this->architecture->getRelationsFrom('other-id');
        $this->assertCount(1, $otherRelations);

        $emptyRelations = $this->architecture->getRelationsFrom('non-existent');
        $this->assertEmpty($emptyRelations);
    }

    public function testGetRelationsTo(): void
    {
        $relation1 = new Relation('from-id1', 'to-id', 'uses');
        $relation2 = new Relation('from-id2', 'to-id', 'extends');
        $relation3 = new Relation('from-id1', 'other-id', 'uses');

        $this->architecture->addRelation($relation1);
        $this->architecture->addRelation($relation2);
        $this->architecture->addRelation($relation3);

        $relations = $this->architecture->getRelationsTo('to-id');
        $this->assertCount(2, $relations);

        $otherRelations = $this->architecture->getRelationsTo('other-id');
        $this->assertCount(1, $otherRelations);

        $emptyRelations = $this->architecture->getRelationsTo('non-existent');
        $this->assertEmpty($emptyRelations);
    }

    public function testGetLayerForType(): void
    {
        $this->assertSame('presentation', $this->architecture->getLayerForType('controller'));
        $this->assertSame('presentation', $this->architecture->getLayerForType('command'));
        $this->assertSame('presentation', $this->architecture->getLayerForType('form'));

        $this->assertSame('application', $this->architecture->getLayerForType('service'));
        $this->assertSame('application', $this->architecture->getLayerForType('handler'));
        $this->assertSame('application', $this->architecture->getLayerForType('manager'));

        $this->assertSame('domain', $this->architecture->getLayerForType('entity'));
        $this->assertSame('domain', $this->architecture->getLayerForType('model'));
        $this->assertSame('domain', $this->architecture->getLayerForType('valueobject'));

        $this->assertSame('infrastructure', $this->architecture->getLayerForType('repository'));
        $this->assertSame('infrastructure', $this->architecture->getLayerForType('gateway'));
        $this->assertSame('infrastructure', $this->architecture->getLayerForType('adapter'));
    }

    public function testGetLayerForTypeCaseInsensitive(): void
    {
        $this->assertSame('presentation', $this->architecture->getLayerForType('CONTROLLER'));
        $this->assertSame('application', $this->architecture->getLayerForType('Service'));
        $this->assertSame('domain', $this->architecture->getLayerForType('Entity'));
        $this->assertSame('infrastructure', $this->architecture->getLayerForType('Repository'));
    }

    public function testGetLayerForTypeNotFound(): void
    {
        $this->assertNull($this->architecture->getLayerForType('unknown'));
        $this->assertNull($this->architecture->getLayerForType(''));
    }

    public function testHasComponents(): void
    {
        $this->assertFalse($this->architecture->hasComponents());

        $component = new Component('test-id', 'Test Component', 'service');
        $this->architecture->addComponent($component);

        $this->assertTrue($this->architecture->hasComponents());
    }

    public function testHasRelations(): void
    {
        $this->assertFalse($this->architecture->hasRelations());

        $relation = new Relation('from-id', 'to-id', 'uses');
        $this->architecture->addRelation($relation);

        $this->assertTrue($this->architecture->hasRelations());
    }

    public function testGetStatisticsEmpty(): void
    {
        $stats = $this->architecture->getStatistics();

        $this->assertIsArray($stats);
        $this->assertSame(0, $stats['total_components']);
        $this->assertSame(0, $stats['total_relations']);
        $this->assertEmpty($stats['components_by_type']);
        $this->assertEmpty($stats['components_by_layer']);
    }

    public function testGetStatisticsWithComponents(): void
    {
        $service1 = new Component('service1', 'Service 1', 'service');
        $service2 = new Component('service2', 'Service 2', 'service');
        $controller = new Component('controller1', 'Controller 1', 'controller');
        $entity = new Component('entity1', 'Entity 1', 'entity');

        $this->architecture->addComponent($service1);
        $this->architecture->addComponent($service2);
        $this->architecture->addComponent($controller);
        $this->architecture->addComponent($entity);

        $relation1 = new Relation('service1', 'service2', 'uses');
        $relation2 = new Relation('controller1', 'service1', 'uses');
        $this->architecture->addRelation($relation1);
        $this->architecture->addRelation($relation2);

        $stats = $this->architecture->getStatistics();

        $this->assertSame(4, $stats['total_components']);
        $this->assertSame(2, $stats['total_relations']);

        $this->assertSame(2, $stats['components_by_type']['service']);
        $this->assertSame(1, $stats['components_by_type']['controller']);
        $this->assertSame(1, $stats['components_by_type']['entity']);

        $this->assertSame(2, $stats['components_by_layer']['application']);
        $this->assertSame(1, $stats['components_by_layer']['presentation']);
        $this->assertSame(1, $stats['components_by_layer']['domain']);
    }

    public function testGetStatisticsWithUnknownType(): void
    {
        $unknownComponent = new Component('unknown1', 'Unknown Component', 'unknown');
        $this->architecture->addComponent($unknownComponent);

        $stats = $this->architecture->getStatistics();

        $this->assertSame(1, $stats['total_components']);
        $this->assertSame(1, $stats['components_by_type']['unknown']);
        $this->assertEmpty($stats['components_by_layer']);
    }

    public function testAddComponentObject(): void
    {
        $component = new Component('TestService', 'TestService', 'service');

        $result = $this->architecture->addComponentObject($component);

        self::assertSame($this->architecture, $result);
        self::assertSame($component, $this->architecture->getComponent('TestService'));
    }

    public function testCreateAndAddComponent(): void
    {
        $result = $this->architecture->createAndAddComponent('controller', 'TestController', 'presentation');

        self::assertSame($this->architecture, $result);
        $component = $this->architecture->getComponent('TestController');
        self::assertNotNull($component);
        self::assertSame('controller', $component->getType());
        self::assertSame('TestController', $component->getName());
        self::assertSame('presentation', $component->getLayer());
    }

    public function testCreateAndAddRelation(): void
    {
        $result = $this->architecture->createAndAddRelation('ComponentA', 'ComponentB', 'uses', 'HTTP');

        self::assertSame($this->architecture, $result);
        $relations = $this->architecture->getRelations();
        self::assertCount(1, $relations);
        $relation = $relations[0];
        self::assertSame('ComponentA', $relation->getFrom());
        self::assertSame('ComponentB', $relation->getTo());
        self::assertSame('uses', $relation->getType());
        self::assertSame('HTTP', $relation->getTechnology());
    }

    public function testAddDataFlow(): void
    {
        $result = $this->architecture->addDataFlow('source', 'target', 'User data', 'Real-time', 'HTTPS');

        self::assertSame($this->architecture, $result);
        $dataFlows = $this->architecture->getDataFlows();
        self::assertCount(1, $dataFlows);
        self::assertSame('source', $dataFlows[0]['from']);
        self::assertSame('target', $dataFlows[0]['to']);
        self::assertSame('User data', $dataFlows[0]['data']);
        self::assertSame('Real-time', $dataFlows[0]['frequency']);
        self::assertSame('HTTPS', $dataFlows[0]['protocol']);
    }

    public function testAddExternalSystem(): void
    {
        $result = $this->architecture->addExternalSystem('payment_gateway', 'Payment Gateway', 'API', 'HTTPS');

        self::assertSame($this->architecture, $result);
        $externalSystems = $this->architecture->getExternalSystems();
        self::assertCount(1, $externalSystems);
        self::assertArrayHasKey('payment_gateway', $externalSystems);
        self::assertSame('Payment Gateway', $externalSystems['payment_gateway']['name']);
        self::assertSame('API', $externalSystems['payment_gateway']['type']);
        self::assertSame('HTTPS', $externalSystems['payment_gateway']['technology']);
    }

    public function testAddInfrastructure(): void
    {
        $result = $this->architecture->addInfrastructure('web_server', 'Web Server', 'server', ['technology' => 'Apache HTTP Server']);

        self::assertSame($this->architecture, $result);
        $infrastructures = $this->architecture->getInfrastructures();
        self::assertCount(1, $infrastructures);
        self::assertArrayHasKey('web_server', $infrastructures);
        self::assertSame('Web Server', $infrastructures['web_server']['name']);
        self::assertSame('server', $infrastructures['web_server']['type']);
        self::assertSame('Apache HTTP Server', $infrastructures['web_server']['properties']['technology']);
    }

    public function testAddSecurityMeasure(): void
    {
        $result = $this->architecture->addSecurityMeasure('firewall', 'Firewall', 'network', 'All traffic');

        self::assertSame($this->architecture, $result);
        $securityMeasures = $this->architecture->getSecurityMeasures();
        self::assertCount(1, $securityMeasures);
        self::assertArrayHasKey('firewall', $securityMeasures);
        self::assertSame('Firewall', $securityMeasures['firewall']['name']);
        self::assertSame('network', $securityMeasures['firewall']['type']);
        self::assertSame('All traffic', $securityMeasures['firewall']['scope']);
    }
}
