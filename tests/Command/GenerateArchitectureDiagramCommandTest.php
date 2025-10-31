<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\ArchitectureDiagramBundle\Command\GenerateArchitectureDiagramCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(GenerateArchitectureDiagramCommand::class)]
#[RunTestsInSeparateProcesses]
final class GenerateArchitectureDiagramCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        // Nothing to setup specifically for this test
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(GenerateArchitectureDiagramCommand::class);
        self::assertInstanceOf(GenerateArchitectureDiagramCommand::class, $command);

        return new CommandTester($command);
    }

    public function testCommandIsRegistered(): void
    {
        $command = self::getContainer()->get(GenerateArchitectureDiagramCommand::class);
        self::assertInstanceOf(GenerateArchitectureDiagramCommand::class, $command);
        self::assertSame('app:generate-architecture-diagram', $command->getName());
        self::assertSame('生成项目架构图（PlantUML格式）', $command->getDescription());
    }

    public function testArgumentProject(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $result = $this->getCommandTester()->execute([
                'project' => $tempDir,
            ]);

            self::assertSame(Command::SUCCESS, $result);
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testOptionOutput(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Entity');

        // Create a simple entity file to ensure there's content to generate
        file_put_contents(
            $tempDir . '/src/Entity/TestEntity.php',
            '<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TestEntity
{
    #[ORM\Id]
    private int $id;
}'
        );

        $outputFile = sys_get_temp_dir() . '/test_' . uniqid() . '.puml';

        try {
            $result = $this->getCommandTester()->execute([
                'project' => $tempDir,
                '--output' => $outputFile,
            ]);

            self::assertSame(Command::SUCCESS, $result);
            self::assertFileExists($outputFile);
            $content = file_get_contents($outputFile);
            self::assertIsString($content);
            self::assertStringContainsString('@startuml', $content);
        } finally {
            @unlink($outputFile);
            unlink($tempDir . '/src/Entity/TestEntity.php');
            rmdir($tempDir . '/src/Entity');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testOptionFormat(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $result = $this->getCommandTester()->execute([
                'project' => $tempDir,
                '--format' => 'plantuml',
            ]);

            self::assertSame(Command::SUCCESS, $result);
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testOptionLevel(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $result = $this->getCommandTester()->execute([
                'project' => $tempDir,
                '--level' => 'context',
            ]);

            self::assertSame(Command::SUCCESS, $result);
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testOptionNoC4(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $result = $this->getCommandTester()->execute([
                'project' => $tempDir,
                '--no-c4' => true,
            ]);

            self::assertSame(Command::SUCCESS, $result);
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testOptionNoLayers(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $result = $this->getCommandTester()->execute([
                'project' => $tempDir,
                '--no-layers' => true,
            ]);

            self::assertSame(Command::SUCCESS, $result);
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testOptionShowStats(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_project_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--show-stats' => true,
            ]);

            self::assertSame(Command::SUCCESS, $result);
            $output = $commandTester->getDisplay();
            self::assertStringContainsString('架构图生成器', $output);
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testCommandExecutionWithInvalidPath(): void
    {
        $commandTester = $this->getCommandTester();
        $result = $commandTester->execute([
            'project' => '/non/existent/path',
        ]);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('项目路径不存在', $commandTester->getDisplay());
    }

    public function testCommandExecutionWithValidPathButNoComponents(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_command_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
            ]);

            self::assertSame(Command::SUCCESS, $result);
            $output = $commandTester->getDisplay();
            self::assertStringContainsString('架构图生成器', $output);
            self::assertStringContainsString('未找到任何组件', $output);
        } finally {
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testCommandExecutionWithComponents(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_command_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Entity');

        file_put_contents(
            $tempDir . '/src/Entity/User.php',
            '<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    private int $id;
}'
        );

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
            ]);

            self::assertSame(Command::SUCCESS, $result);
            $output = $commandTester->getDisplay();
            self::assertStringContainsString('@startuml', $output);
            self::assertStringContainsString('User', $output);
        } finally {
            unlink($tempDir . '/src/Entity/User.php');
            rmdir($tempDir . '/src/Entity');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testCommandExecutionWithOutputFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_command_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Entity');

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

        $outputFile = sys_get_temp_dir() . '/test_' . uniqid() . '.puml';

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--output' => $outputFile,
            ]);

            self::assertSame(Command::SUCCESS, $result);
            self::assertStringContainsString('架构图已生成到', $commandTester->getDisplay());
            self::assertFileExists($outputFile);
            $content = file_get_contents($outputFile);
            self::assertIsString($content);
            self::assertStringContainsString('@startuml', $content);
        } finally {
            @unlink($outputFile);
            unlink($entityFile);
            rmdir($tempDir . '/src/Entity');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testCommandExecutionWithStatistics(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_command_');
        mkdir($tempDir);
        mkdir($tempDir . '/src');
        mkdir($tempDir . '/src/Entity');

        file_put_contents(
            $tempDir . '/src/Entity/User.php',
            '<?php
namespace App\Entity;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    private int $id;
}'
        );

        try {
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--show-stats' => true,
            ]);

            self::assertSame(Command::SUCCESS, $result);
            $output = $commandTester->getDisplay();
            self::assertStringContainsString('架构统计信息', $output);
            self::assertStringContainsString('组件总数', $output);
        } finally {
            unlink($tempDir . '/src/Entity/User.php');
            rmdir($tempDir . '/src/Entity');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }

    public function testCommandExecutionWithException(): void
    {
        $commandTester = $this->getCommandTester();
        $result = $commandTester->execute([
            'project' => '/non/existent/path',
        ]);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('ERROR', $commandTester->getDisplay());
    }

    public function testCommandExecutionWithCustomOptions(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_command_');
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
            $commandTester = $this->getCommandTester();
            $result = $commandTester->execute([
                'project' => $tempDir,
                '--level' => 'context',
                '--no-c4' => true,
                '--no-layers' => true,
            ]);

            self::assertSame(Command::SUCCESS, $result);
            $output = $commandTester->getDisplay();
            self::assertStringContainsString('@startuml', $output);
        } finally {
            unlink($controllerFile);
            rmdir($tempDir . '/src/Controller');
            rmdir($tempDir . '/src');
            rmdir($tempDir);
        }
    }
}
