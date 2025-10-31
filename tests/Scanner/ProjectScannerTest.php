<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Exception\InvalidProjectPathException;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Scanner\ProjectScanner;

/**
 * @internal
 */
#[CoversClass(ProjectScanner::class)]
final class ProjectScannerTest extends TestCase
{
    private ProjectScanner $projectScanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectScanner = new ProjectScanner();
    }

    public function testScanThrowsExceptionForInvalidPath(): void
    {
        $this->expectException(InvalidProjectPathException::class);
        $this->expectExceptionMessage('Project path does not exist: /non/existent/path');

        $this->projectScanner->scan('/non/existent/path');
    }

    public function testScanReturnsEmptyArchitectureWhenNoSrcDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertInstanceOf(Architecture::class, $architecture);
            $this->assertEquals(basename($tempDir), $architecture->getName());
            $this->assertEquals('Architecture diagram for ' . basename($tempDir), $architecture->getDescription());
            $this->assertFalse($architecture->hasComponents());
        } finally {
            rmdir($tempDir);
        }
    }

    public function testScanWithEmptySrcDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertInstanceOf(Architecture::class, $architecture);
            $this->assertFalse($architecture->hasComponents());
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testScanWithRealEntityFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Entity');

        $entityFile = $tempDir . '/src/Entity/User.php';
        $entityCode = '<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    private int $id;
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertInstanceOf(Architecture::class, $architecture);
            $this->assertTrue($architecture->hasComponents());
            $this->assertGreaterThanOrEqual(1, count($architecture->getComponents()));

            $entities = $architecture->getComponentsByType('entity');
            $this->assertGreaterThanOrEqual(1, count($entities));
            $entity = array_values($entities)[0];
            $this->assertEquals('User', $entity->getName());
        } finally {
            unlink($entityFile);
            rmdir($tempDir . '/src/Entity');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testScanWithRealControllerFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Controller');

        $controllerFile = $tempDir . '/src/Controller/TestController.php';
        $controllerCode = '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TestController extends AbstractController
{
    public function index() {}
}';
        file_put_contents($controllerFile, $controllerCode);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertTrue($architecture->hasComponents());

            $this->assertCount(1, $architecture->getComponents());

            $controllers = $architecture->getComponentsByType('controller');
            $this->assertCount(1, $controllers);
            $controller = array_values($controllers)[0];
            $this->assertEquals('TestController', $controller->getName());
        } finally {
            unlink($controllerFile);
            rmdir($tempDir . '/src/Controller');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testScanWithRealRepositoryFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Repository');

        $repositoryFile = $tempDir . '/src/Repository/UserRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class UserRepository extends ServiceEntityRepository
{
    public function findByEmail(string $email) {}
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertTrue($architecture->hasComponents());
            $this->assertCount(1, $architecture->getComponents());

            $repositories = $architecture->getComponentsByType('repository');
            $this->assertCount(1, $repositories);
            $repository = array_values($repositories)[0];
            $this->assertEquals('UserRepository', $repository->getName());
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir . '/src/Repository');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testScanWithRealServiceFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Service');

        $serviceFile = $tempDir . '/src/Service/UserService.php';
        $serviceCode = '<?php
namespace App\Service;

class UserService
{
    public function createUser(string $name): void {}
}';
        file_put_contents($serviceFile, $serviceCode);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertTrue($architecture->hasComponents());
            $this->assertCount(1, $architecture->getComponents());

            $services = $architecture->getComponentsByType('service');
            $this->assertCount(1, $services);
            $service = array_values($services)[0];
            $this->assertEquals('UserService', $service->getName());
        } finally {
            unlink($serviceFile);
            rmdir($tempDir . '/src/Service');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testScanWithServicesDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Services');

        $serviceFile = $tempDir . '/src/Services/TestService.php';
        $serviceCode = '<?php
namespace App\Services;

class TestService
{
    public function test(): void {}
}';
        file_put_contents($serviceFile, $serviceCode);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertTrue($architecture->hasComponents());
            $this->assertCount(1, $architecture->getComponents());

            $services = $architecture->getComponentsByType('service');
            $this->assertCount(1, $services);
            $service = array_values($services)[0];
            $this->assertEquals('TestService', $service->getName());
        } finally {
            unlink($serviceFile);
            rmdir($tempDir . '/src/Services');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testScanWithMultipleComponentTypes(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Entity');
        mkdir($tempDir . '/src/Controller');
        mkdir($tempDir . '/src/Repository');
        mkdir($tempDir . '/src/Service');

        $entityFile = $tempDir . '/src/Entity/User.php';
        $entityCode = '<?php
namespace App\Entity;


#[ORM\Entity]
class User
{
    #[ORM\Id]
    private int $id;
}';
        file_put_contents($entityFile, $entityCode);

        $controllerFile = $tempDir . '/src/Controller/UserController.php';
        $controllerCode = '<?php
namespace App\Controller;

use App\Service\UserService;

class UserController extends AbstractController
{
    public function __construct(private UserService $userService) {}
    
    public function index() {}
}';
        file_put_contents($controllerFile, $controllerCode);

        $repositoryFile = $tempDir . '/src/Repository/UserRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;

use App\Entity\User;

class UserRepository extends ServiceEntityRepository
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function findByEmail(string $email) {}
}';
        file_put_contents($repositoryFile, $repositoryCode);

        $serviceFile = $tempDir . '/src/Service/UserService.php';
        $serviceCode = '<?php
namespace App\Service;

use App\Repository\UserRepository;

class UserService
{
    public function __construct(private UserRepository $userRepository) {}
    
    public function createUser(string $name): void {}
}';
        file_put_contents($serviceFile, $serviceCode);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertTrue($architecture->hasComponents());
            $this->assertCount(4, $architecture->getComponents());
            $this->assertTrue($architecture->hasRelations());

            $this->assertCount(1, $architecture->getComponentsByType('entity'));
            $this->assertCount(1, $architecture->getComponentsByType('controller'));
            $this->assertCount(1, $architecture->getComponentsByType('repository'));
            $this->assertCount(1, $architecture->getComponentsByType('service'));

            // Test that relations are inferred
            $relations = $architecture->getRelations();
            $this->assertGreaterThan(0, count($relations));
        } finally {
            unlink($entityFile);
            unlink($controllerFile);
            unlink($repositoryFile);
            unlink($serviceFile);
            rmdir($tempDir . '/src/Entity');
            rmdir($tempDir . '/src/Controller');
            rmdir($tempDir . '/src/Repository');
            rmdir($tempDir . '/src/Service');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testRelationInferenceBetweenMatchingComponents(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Entity');
        mkdir($tempDir . '/src/Repository');

        $entityFile = $tempDir . '/src/Entity/User.php';
        $entityCode = '<?php
namespace App\Entity;


#[ORM\Entity]
class User
{
    #[ORM\Id]
    private int $id;
}';
        file_put_contents($entityFile, $entityCode);

        $repositoryFile = $tempDir . '/src/Repository/UserRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;


class UserRepository extends ServiceEntityRepository
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function findByEmail(string $email): ?User 
    {
        // Create a new User instance to establish dependency
        return new User();
    }
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $architecture = $this->projectScanner->scan($tempDir);

            $this->assertTrue($architecture->hasComponents());
            $this->assertCount(2, $architecture->getComponents());
            $this->assertTrue($architecture->hasRelations());

            $relations = $architecture->getRelations();
            $this->assertCount(1, $relations);

            $relation = $relations[0];
            $this->assertEquals('manages', $relation->getType());

            $repository = array_values($architecture->getComponentsByType('repository'))[0];
            $entity = array_values($architecture->getComponentsByType('entity'))[0];

            $this->assertEquals($repository->getId(), $relation->getFrom());
            $this->assertEquals($entity->getId(), $relation->getTo());
        } finally {
            unlink($entityFile);
            unlink($repositoryFile);
            rmdir($tempDir . '/src/Entity');
            rmdir($tempDir . '/src/Repository');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }
}
