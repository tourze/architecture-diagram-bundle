<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Scanner\EventListenerScanner;

/**
 * @internal
 */
#[CoversClass(EventListenerScanner::class)]
class EventListenerScannerTest extends TestCase
{
    private EventListenerScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scanner = new EventListenerScanner();
    }

    public function testScanEmptyDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_event_scanner_' . uniqid();
        mkdir($tempDir);

        try {
            $components = $this->scanner->scan($tempDir);
            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testScanDirectoryWithEventListener(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_event_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/EventListener');

        $listenerContent = '<?php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: "kernel.request", method: "onKernelRequest")]
class TestEventListener
{
    public function onKernelRequest($event): void
    {
        // Handle event
    }
}';
        file_put_contents($tempDir . '/EventListener/TestEventListener.php', $listenerContent);

        try {
            $components = $this->scanner->scan($tempDir);
            $this->assertCount(1, $components);

            $component = $components[0];
            $this->assertSame('TestEventListener', $component->getName());
            $this->assertSame('event_listener', $component->getType());
            $this->assertStringContainsString('Event Listener', $component->getDescription());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('events', $metadata);
            $this->assertContains('kernel.request', $metadata['events']);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanDirectoryWithEventSubscriber(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_event_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/EventSubscriber');

        $subscriberContent = '<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            "kernel.request" => "onKernelRequest",
            "kernel.response" => "onKernelResponse",
        ];
    }

    public function onKernelRequest($event): void
    {
        // Handle request
    }

    public function onKernelResponse($event): void
    {
        // Handle response
    }
}';
        file_put_contents($tempDir . '/EventSubscriber/TestEventSubscriber.php', $subscriberContent);

        try {
            $components = $this->scanner->scan($tempDir);
            $this->assertCount(1, $components);

            $component = $components[0];
            $this->assertSame('TestEventSubscriber', $component->getName());
            $this->assertSame('event_subscriber', $component->getType());
            $this->assertStringContainsString('Event Subscriber', $component->getDescription());

            $metadata = $component->getMetadata();
            $this->assertTrue($metadata['isSubscriber']);
            $this->assertContains('kernel.request', $metadata['events']);
            $this->assertContains('kernel.response', $metadata['events']);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanFileWithInvalidPhpSyntax(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_event_scanner_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/EventListener');

        file_put_contents($tempDir . '/EventListener/Invalid.php', '<?php invalid syntax');

        try {
            $components = $this->scanner->scan($tempDir);
            $this->assertEmpty($components);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testScanNonExistentDirectory(): void
    {
        $components = $this->scanner->scan('/non/existent/path');
        $this->assertIsArray($components);
        $this->assertEmpty($components);
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
