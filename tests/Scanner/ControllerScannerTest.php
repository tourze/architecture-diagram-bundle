<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Scanner\ControllerScanner;

/**
 * @internal
 */
#[CoversClass(ControllerScanner::class)]
final class ControllerScannerTest extends TestCase
{
    private ControllerScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->scanner = new ControllerScanner();
    }

    public function testScanEmptyDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testScanDirectoryWithNonControllerFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $regularFile = $tempDir . '/RegularClass.php';
        file_put_contents($regularFile, '<?php class RegularClass {}');

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            unlink($regularFile);
            rmdir($tempDir);
        }
    }

    public function testScanDirectoryWithControllerFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $controllerFile = $tempDir . '/TestController.php';
        $controllerCode = '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route("/test", name: "test")]
    public function index(): Response
    {
        return new Response("Test");
    }

    #[Route("/test/{id}", name: "test_show")]
    public function show(int $id): Response
    {
        return new Response("Show " . $id);
    }
}';
        file_put_contents($controllerFile, $controllerCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertCount(1, $components);

            $component = $components[0];
            $this->assertInstanceOf(Component::class, $component);
            $this->assertEquals('TestController', $component->getName());
            $this->assertEquals('controller', $component->getType());
            $this->assertEquals('Symfony Controller', $component->getTechnology());
            $this->assertEquals('App\Controller', $component->getNamespace());
            $this->assertEquals(realpath($controllerFile), $component->getFilePath());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('actions', $metadata);
            $this->assertArrayHasKey('routes', $metadata);
            $this->assertArrayHasKey('extends', $metadata);
        } finally {
            unlink($controllerFile);
            rmdir($tempDir);
        }
    }

    public function testScanFileWithInvalidPhpSyntax(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $invalidFile = $tempDir . '/InvalidController.php';
        file_put_contents($invalidFile, '<?php invalid syntax here');

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            unlink($invalidFile);
            rmdir($tempDir);
        }
    }

    public function testScanFileWithEmptyContent(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $emptyFile = $tempDir . '/EmptyController.php';
        file_put_contents($emptyFile, '');

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            unlink($emptyFile);
            rmdir($tempDir);
        }
    }

    public function testScanMultipleControllerFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $controller1 = $tempDir . '/UserController.php';
        $controller1Code = '<?php
namespace App\Controller;


class UserController extends AbstractController
{
    public function index() {}
    public function show() {}
}';
        file_put_contents($controller1, $controller1Code);

        $controller2 = $tempDir . '/AdminController.php';
        $controller2Code = '<?php
namespace App\Controller\Admin;


class AdminController extends AbstractController
{
    public function dashboard() {}
}';
        file_put_contents($controller2, $controller2Code);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertCount(2, $components);

            $componentNames = array_map(fn (Component $c) => $c->getName(), $components);
            $this->assertContains('UserController', $componentNames);
            $this->assertContains('AdminController', $componentNames);
        } finally {
            unlink($controller1);
            unlink($controller2);
            rmdir($tempDir);
        }
    }

    public function testScanNonExistentDirectory(): void
    {
        $this->expectException(DirectoryNotFoundException::class);

        $this->scanner->scan('/non/existent/directory');
    }

    public function testScanControllerWithNoNamespace(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $controllerFile = $tempDir . '/SimpleController.php';
        $controllerCode = '<?php

class SimpleController extends AbstractController
{
    public function index() {}
}';
        file_put_contents($controllerFile, $controllerCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('SimpleController', $component->getName());
            $this->assertNull($component->getNamespace());
        } finally {
            unlink($controllerFile);
            rmdir($tempDir);
        }
    }

    public function testScanControllerWithoutActionsOrRoutes(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $controllerFile = $tempDir . '/EmptyController.php';
        $controllerCode = '<?php
namespace App\Controller;


class EmptyController extends AbstractController
{
}';
        file_put_contents($controllerFile, $controllerCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('EmptyController', $component->getName());
            $this->assertEquals('controller', $component->getType());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('actions', $metadata);
            $this->assertArrayHasKey('routes', $metadata);
        } finally {
            unlink($controllerFile);
            rmdir($tempDir);
        }
    }

    public function testDescriptionGeneration(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $controllerFile = $tempDir . '/TestController.php';
        $controllerCode = '<?php
namespace App\Controller;


class TestController extends AbstractController
{
    #[Route("/test1", name: "test1")]
    public function action1() {}

    #[Route("/test2", name: "test2")]
    public function action2() {}

    #[Route("/test3", name: "test3")]
    public function action3() {}
}';
        file_put_contents($controllerFile, $controllerCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];

            $description = $component->getDescription();
            $this->assertStringContainsString('Controller with', $description);
            $this->assertStringContainsString('actions', $description);
        } finally {
            unlink($controllerFile);
            rmdir($tempDir);
        }
    }

    public function testScanControllerWithComplexInheritance(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $controllerFile = $tempDir . '/CustomController.php';
        $controllerCode = '<?php
namespace App\Controller;

use App\Base\BaseController;

class CustomController extends BaseController
{
    public function customAction() {}
}';
        file_put_contents($controllerFile, $controllerCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('CustomController', $component->getName());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('extends', $metadata);
        } finally {
            unlink($controllerFile);
            rmdir($tempDir);
        }
    }

    public function testComponentIdGeneration(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_controller_');
        mkdir($tempDir);

        $controllerFile = $tempDir . '/TestController.php';
        $controllerCode = '<?php
namespace App\Controller\Admin;


class TestController extends AbstractController
{
}';
        file_put_contents($controllerFile, $controllerCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];

            $expectedId = 'controller_' . strtolower(str_replace('\\', '_', 'App\Controller\Admin\TestController'));
            $this->assertEquals($expectedId, $component->getId());
        } finally {
            unlink($controllerFile);
            rmdir($tempDir);
        }
    }
}
