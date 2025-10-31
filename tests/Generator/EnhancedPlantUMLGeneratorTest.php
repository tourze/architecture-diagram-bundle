<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Generator\EnhancedPlantUMLGenerator;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Model\Relation;

/**
 * @internal
 */
#[CoversClass(EnhancedPlantUMLGenerator::class)]
class EnhancedPlantUMLGeneratorTest extends TestCase
{
    private EnhancedPlantUMLGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new EnhancedPlantUMLGenerator();
    }

    public function testGenerateSystemOverview(): void
    {
        $architecture = new Architecture('Test System', 'Test Description');
        $architecture->addComponent(new Component('web', 'Web App', 'controller', 'Web interface'));
        $architecture->addComponent(new Component('api', 'API Server', 'service', 'REST API'));
        $architecture->addRelation(new Relation('web', 'api', 'uses', 'HTTP calls'));

        $result = $this->generator->generateSystemOverview($architecture);

        $this->assertStringContainsString('@startuml', $result);
        $this->assertStringContainsString('@enduml', $result);
        $this->assertStringContainsString('C4_Context.puml', $result);
        $this->assertStringContainsString('Test System', $result);
    }

    public function testGenerateLayeredArchitecture(): void
    {
        $architecture = new Architecture('Test System', 'Component view');
        $controller = new Component('controller1', 'UserController', 'controller');
        $service = new Component('service1', 'UserService', 'service');
        $repository = new Component('repo1', 'UserRepository', 'repository');

        $architecture->addComponent($controller);
        $architecture->addComponent($service);
        $architecture->addComponent($repository);

        $architecture->addRelation(new Relation('controller1', 'service1', 'uses'));
        $architecture->addRelation(new Relation('service1', 'repo1', 'uses'));

        $result = $this->generator->generateLayeredArchitecture($architecture);

        $this->assertStringContainsString('@startuml', $result);
        $this->assertStringContainsString('@enduml', $result);
        $this->assertStringContainsString('C4_Component.puml', $result);
        $this->assertStringContainsString('UserController', $result);
        $this->assertStringContainsString('UserService', $result);
        $this->assertStringContainsString('UserRepository', $result);
    }

    public function testGenerateDeploymentDiagram(): void
    {
        $architecture = new Architecture('Test System', 'Deployment view');
        $architecture->addInfrastructure('server1', 'Web Server', 'server', ['cpu' => '4 cores', 'memory' => '8GB']);
        $architecture->addInfrastructure('db1', 'Database Server', 'database', ['type' => 'MySQL', 'storage' => '100GB']);

        $result = $this->generator->generateDeploymentDiagram($architecture);

        $this->assertStringContainsString('@startuml', $result);
        $this->assertStringContainsString('@enduml', $result);
        $this->assertStringContainsString('C4_Deployment.puml', $result);
        $this->assertStringContainsString('Web服务器集群', $result);
        $this->assertStringContainsString('数据库集群', $result);
    }

    public function testGenerateWithExternalSystems(): void
    {
        $architecture = new Architecture('Test System');
        $architecture->addExternalSystem('payment', 'Payment Gateway', 'payment', 'REST API');
        $architecture->addDataFlow('system', 'payment', 'Payment request', 'Real-time', 'HTTPS');

        $result = $this->generator->generateSystemOverview($architecture);

        $this->assertStringContainsString('Payment Gateway', $result);
        $this->assertStringContainsString('REST API', $result);
    }

    public function testGenerateWithSecurityMeasures(): void
    {
        $architecture = new Architecture('Secure System');
        $architecture->addSecurityMeasure('firewall', 'Web Firewall', 'WAF', 'All traffic');
        $architecture->addSecurityMeasure('ssl', 'SSL/TLS', 'encryption', 'HTTPS');

        $result = $this->generator->generateSystemOverview($architecture);

        $this->assertStringContainsString('@startuml', $result);
        $this->assertStringContainsString('@enduml', $result);
    }

    public function testGenerateDataFlowDiagram(): void
    {
        $architecture = new Architecture('Test System');
        $architecture->addComponent(new Component('user', 'User', 'actor'));
        $architecture->addComponent(new Component('web', 'Web App', 'controller'));
        $architecture->addComponent(new Component('api', 'API', 'service'));
        $architecture->addComponent(new Component('db', 'Database', 'repository'));

        $architecture->addDataFlow('user', 'web', 'Login request', 'Once', 'HTTP');
        $architecture->addDataFlow('web', 'api', 'Validate user', 'Once', 'REST');
        $architecture->addDataFlow('api', 'db', 'Query user', 'Once', 'SQL');

        $result = $this->generator->generateDataFlowDiagram($architecture);

        $this->assertStringContainsString('@startuml', $result);
        $this->assertStringContainsString('@enduml', $result);
        $this->assertStringContainsString('Login request', $result);
    }

    public function testSanitizeId(): void
    {
        $architecture = new Architecture();
        $component = new Component('test-component.name', 'Test Component', 'service');
        $architecture->addComponent($component);

        $result = $this->generator->generateLayeredArchitecture($architecture);

        $this->assertStringContainsString('test_component_name', $result);
        $this->assertStringNotContainsString('test-component.name', $result);
    }
}
