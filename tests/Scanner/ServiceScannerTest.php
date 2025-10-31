<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Scanner\ServiceScanner;

/**
 * @internal
 */
#[CoversClass(ServiceScanner::class)]
class ServiceScannerTest extends TestCase
{
    private ServiceScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scanner = new ServiceScanner();
    }

    public function testScanEmptyDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/service_scanner_test_' . uniqid();
        mkdir($tempDir, 0o777, true);

        try {
            $components = $this->scanner->scan($tempDir);
            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanServiceFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/service_scanner_test_' . uniqid();
        mkdir($tempDir, 0o777, true);

        try {
            $serviceContent = '<?php
namespace App\Service;

class UserService
{
    public function findUser(int $id): ?User
    {
        return null;
    }

    public function saveUser(User $user): void
    {
    }

    private function validateUser(User $user): bool
    {
        return true;
    }
}';

            $this->createFile($tempDir . '/UserService.php', $serviceContent);

            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertInstanceOf(Component::class, $component);
            $this->assertEquals('UserService', $component->getName());
            $this->assertEquals('service', $component->getType());
            $this->assertEquals('App\Service', $component->getNamespace());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('methods', $metadata);
            $this->assertContains('findUser', $metadata['methods']);
            $this->assertContains('saveUser', $metadata['methods']);
            $this->assertNotContains('validateUser', $metadata['methods']); // private method should not be included

            $this->assertStringContainsString('2 public methods', $component->getDescription());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanNonexistentDirectory(): void
    {
        $components = $this->scanner->scan('/nonexistent/path');
        $this->assertIsArray($components);
        $this->assertEmpty($components);
    }

    private function createFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($path, $content);
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
