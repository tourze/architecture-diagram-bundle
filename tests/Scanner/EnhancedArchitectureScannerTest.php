<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Scanner\ControllerScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\EnhancedArchitectureScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\EntityScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\EventListenerScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\RelationAnalyzer;
use Tourze\ArchitectureDiagramBundle\Scanner\RepositoryScanner;
use Tourze\ArchitectureDiagramBundle\Scanner\ServiceScanner;

/**
 * @internal
 */
#[CoversClass(EnhancedArchitectureScanner::class)]
class EnhancedArchitectureScannerTest extends TestCase
{
    private EnhancedArchitectureScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $controllerScanner = new ControllerScanner();
        $entityScanner = new EntityScanner();
        $repositoryScanner = new RepositoryScanner();
        $serviceScanner = new ServiceScanner();
        $eventListenerScanner = new EventListenerScanner();
        $relationAnalyzer = new RelationAnalyzer();

        $this->scanner = new EnhancedArchitectureScanner(
            $controllerScanner,
            $entityScanner,
            $repositoryScanner,
            $serviceScanner,
            $eventListenerScanner,
            $relationAnalyzer
        );
    }

    public function testScanProjectWithInvalidPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->scanner->scanProject('/non/existent/path');
    }

    public function testScanProjectWithEmptyDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_enhanced_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $architecture = $this->scanner->scanProject($tempDir);

            $this->assertNotNull($architecture);
            $this->assertSame(basename($tempDir) . ' 系统架构', $architecture->getName());
            $this->assertArrayHasKey('scan_time', $architecture->getMetadata());
            $this->assertArrayHasKey('project_path', $architecture->getMetadata());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanProjectWithControllers(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_enhanced_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Controller');

        $controllerContent = '<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController
{
    #[Route("/test", name: "test")]
    public function index(): Response
    {
        return new Response("Test");
    }
}';
        file_put_contents($tempDir . '/src/Controller/TestController.php', $controllerContent);

        try {
            $architecture = $this->scanner->scanProject($tempDir);

            $components = $architecture->getComponentsByType('controller');
            $this->assertCount(1, $components);

            $controller = array_values($components)[0];
            $this->assertSame('TestController', $controller->getName());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanProjectAddsInfrastructure(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_enhanced_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        // Create a docker-compose file that suggests database usage
        file_put_contents($tempDir . '/docker-compose.yml', 'version: "3.8"\nservices:\n  mysql:\n    image: mysql:8.0');

        try {
            $architecture = $this->scanner->scanProject($tempDir);

            $infrastructures = $architecture->getInfrastructures();
            $this->assertNotEmpty($infrastructures);
            $this->assertArrayHasKey('mysql_server', $infrastructures);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanProjectAddsSecurityMeasures(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_enhanced_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $architecture = $this->scanner->scanProject($tempDir);

            $securityMeasures = $architecture->getSecurityMeasures();
            $this->assertNotEmpty($securityMeasures);
            $this->assertArrayHasKey('firewall', $securityMeasures);
            $this->assertArrayHasKey('ssl', $securityMeasures);
            $this->assertArrayHasKey('auth', $securityMeasures);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanProjectAddsExternalSystems(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_enhanced_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Service');

        // Create a service that suggests payment integration
        $serviceContent = '<?php
namespace App\Service;

class PaymentService
{
    public function processPayment(): void
    {
        // Payment logic
    }
}';
        file_put_contents($tempDir . '/src/Service/PaymentService.php', $serviceContent);

        // Create .env file with payment configuration
        file_put_contents($tempDir . '/.env', 'PAYMENT_SECRET_KEY=abc123');

        try {
            $architecture = $this->scanner->scanProject($tempDir);

            $externalSystems = $architecture->getExternalSystems();
            $this->assertNotEmpty($externalSystems);
            $this->assertArrayHasKey('payment_gateway', $externalSystems);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
