<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Model\Architecture;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Scanner\RelationAnalyzer;

/**
 * @internal
 */
#[CoversClass(RelationAnalyzer::class)]
class RelationAnalyzerTest extends TestCase
{
    private RelationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->analyzer = new RelationAnalyzer();
    }

    public function testAnalyzeEmptyArchitecture(): void
    {
        $architecture = new Architecture();
        $this->analyzer->analyze($architecture);

        $this->assertEmpty($architecture->getRelations());
    }

    public function testAnalyzeWithComponents(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_relation_' . uniqid();
        mkdir($tempDir);

        $architecture = new Architecture();

        // Create controller component
        $controllerContent = '<?php
namespace App\Controller;

use App\Service\UserService;

class UserController
{
    public function __construct(private UserService $userService)
    {
    }

    public function index()
    {
        return $this->userService->getUsers();
    }
}';
        $controllerFile = $tempDir . '/UserController.php';
        file_put_contents($controllerFile, $controllerContent);

        $controller = new Component('controller1', 'UserController', 'controller');
        $controller->setNamespace('App\Controller');
        $controller->setFilePath($controllerFile);
        $architecture->addComponent($controller);

        // Create service component
        $serviceContent = '<?php
namespace App\Service;

use App\Repository\UserRepository;

class UserService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function getUsers()
    {
        return $this->userRepository->findAll();
    }
}';
        $serviceFile = $tempDir . '/UserService.php';
        file_put_contents($serviceFile, $serviceContent);

        $service = new Component('service1', 'UserService', 'service');
        $service->setNamespace('App\Service');
        $service->setFilePath($serviceFile);
        $architecture->addComponent($service);

        // Create repository component
        $repositoryContent = '<?php
namespace App\Repository;

use App\Entity\User;

class UserRepository
{
    public function findAll(): array
    {
        return [];
    }
}';
        $repositoryFile = $tempDir . '/UserRepository.php';
        file_put_contents($repositoryFile, $repositoryContent);

        $repository = new Component('repo1', 'UserRepository', 'repository');
        $repository->setNamespace('App\Repository');
        $repository->setFilePath($repositoryFile);
        $architecture->addComponent($repository);

        try {
            $this->analyzer->analyze($architecture);

            $relations = $architecture->getRelations();
            $this->assertNotEmpty($relations);

            // Check if controller -> service relation exists
            $controllerRelations = $architecture->getRelationsFrom('controller1');
            $this->assertNotEmpty($controllerRelations);

            // Check if service -> repository relation exists
            $serviceRelations = $architecture->getRelationsFrom('service1');
            $this->assertNotEmpty($serviceRelations);
        } finally {
            unlink($controllerFile);
            unlink($serviceFile);
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testAnalyzeWithInheritance(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_relation_' . uniqid();
        mkdir($tempDir);

        $architecture = new Architecture();

        // Create base controller
        $baseContent = '<?php
namespace App\Controller;

abstract class BaseController
{
}';
        $baseFile = $tempDir . '/BaseController.php';
        file_put_contents($baseFile, $baseContent);

        $base = new Component('base1', 'BaseController', 'controller');
        $base->setNamespace('App\Controller');
        $base->setFilePath($baseFile);
        $architecture->addComponent($base);

        // Create child controller
        $childContent = '<?php
namespace App\Controller;

class ChildController extends BaseController
{
}';
        $childFile = $tempDir . '/ChildController.php';
        file_put_contents($childFile, $childContent);

        $child = new Component('child1', 'ChildController', 'controller');
        $child->setNamespace('App\Controller');
        $child->setFilePath($childFile);
        $architecture->addComponent($child);

        try {
            $this->analyzer->analyze($architecture);

            $relations = $architecture->getRelations();
            $this->assertNotEmpty($relations);

            // Check if child -> base relation exists
            $childRelations = $architecture->getRelationsFrom('child1');
            $this->assertNotEmpty($childRelations);
        } finally {
            unlink($baseFile);
            unlink($childFile);
            rmdir($tempDir);
        }
    }

    public function testAnalyzeWithNonExistentFile(): void
    {
        $architecture = new Architecture();

        $component = new Component('comp1', 'TestComponent', 'service');
        $component->setFilePath('/non/existent/file.php');
        $architecture->addComponent($component);

        $this->analyzer->analyze($architecture);

        $this->assertEmpty($architecture->getRelations());
    }
}
