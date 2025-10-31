<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ArchitectureDiagramBundle\Command\GenerateEnhancedArchitectureCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(GenerateEnhancedArchitectureCommand::class)]
#[RunTestsInSeparateProcesses]
class GenerateEnhancedArchitectureCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        // Nothing to setup specifically for this test
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(GenerateEnhancedArchitectureCommand::class);

        return new CommandTester($command);
    }

    public function testArgumentProject(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_architecture_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $result = $this->getCommandTester()->execute([
                'project' => $tempDir,
            ]);

            $this->assertSame(0, $result);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testOptionType(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_architecture_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--type' => 'layered',
            ]);

            $output = $commandTester->getDisplay();
            $this->assertStringContainsString('layered', $output);
            $this->assertSame(0, $result);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testOptionOutputDir(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_architecture_' . uniqid();
        $outputDir = sys_get_temp_dir() . '/test_output_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--output-dir' => $outputDir,
            ]);

            $this->assertDirectoryExists($outputDir);
            $this->assertSame(0, $result);
        } finally {
            $this->removeDirectory($tempDir);
            if (is_dir($outputDir)) {
                $this->removeDirectory($outputDir);
            }
        }
    }

    public function testOptionShowStats(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_architecture_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--show-stats' => true,
            ]);

            $output = $commandTester->getDisplay();
            $this->assertStringContainsString('架构统计信息', $output);
            $this->assertSame(0, $result);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testCommandExecutionWithInvalidPath(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            'project' => '/non/existent/path',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('项目路径不存在', $output);
        $this->assertSame(1, $commandTester->getStatusCode());
    }

    public function testCommandExecutionWithValidPath(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_architecture_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
            ]);

            $output = $commandTester->getDisplay();
            $this->assertStringContainsString('增强架构图生成器', $output);
            $this->assertStringContainsString('正在深度扫描项目', $output);
            $this->assertSame(0, $result);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testCommandWithTypeOption(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_architecture_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--type' => 'layered',
            ]);

            $output = $commandTester->getDisplay();
            $this->assertStringContainsString('layered', $output);
            $this->assertSame(0, $result);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testCommandWithOutputDirOption(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_architecture_' . uniqid();
        $outputDir = sys_get_temp_dir() . '/test_output_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--output-dir' => $outputDir,
            ]);

            $this->assertDirectoryExists($outputDir);
            $this->assertSame(0, $result);
        } finally {
            $this->removeDirectory($tempDir);
            if (is_dir($outputDir)) {
                $this->removeDirectory($outputDir);
            }
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
