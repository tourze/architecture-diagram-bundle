<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Scanner\RepositoryScanner;

/**
 * @internal
 */
#[CoversClass(RepositoryScanner::class)]
final class RepositoryScannerTest extends TestCase
{
    private RepositoryScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scanner = new RepositoryScanner();
    }

    public function testScanEmptyDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testScanDirectoryWithNonRepositoryFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
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

    public function testScanDirectoryWithRepositoryFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/UserRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy([\'email\' => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder(\'u\')
            ->where(\'u.active = :active\')
            ->setParameter(\'active\', true)
            ->getQuery()
            ->getResult();
    }

    public function countTotalUsers(): int
    {
        return $this->createQueryBuilder(\'u\')
            ->select(\'COUNT(u.id)\')
            ->getQuery()
            ->getSingleScalarResult();
    }
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertCount(1, $components);

            $component = $components[0];
            $this->assertInstanceOf(Component::class, $component);
            $this->assertEquals('UserRepository', $component->getName());
            $this->assertEquals('repository', $component->getType());
            $this->assertEquals('Doctrine Repository', $component->getTechnology());
            $this->assertEquals('App\Repository', $component->getNamespace());
            $this->assertEquals(realpath($repositoryFile), $component->getFilePath());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('methods', $metadata);
            $this->assertArrayHasKey('entity', $metadata);
            $this->assertArrayHasKey('extends', $metadata);
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testScanFileWithInvalidPhpSyntax(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $invalidFile = $tempDir . '/InvalidRepository.php';
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
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $emptyFile = $tempDir . '/EmptyRepository.php';
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

    public function testScanMultipleRepositoryFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repository1 = $tempDir . '/UserRepository.php';
        $repository1Code = '<?php
namespace App\Repository;


class UserRepository extends ServiceEntityRepository
{
    public function findByEmail(string $email) {}
    public function findActiveUsers(): array {}
}';
        file_put_contents($repository1, $repository1Code);

        $repository2 = $tempDir . '/ProductRepository.php';
        $repository2Code = '<?php
namespace App\Repository;


class ProductRepository extends ServiceEntityRepository
{
    public function findByCategory(string $category): array {}
}';
        file_put_contents($repository2, $repository2Code);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertCount(2, $components);

            $componentNames = array_map(fn (Component $c) => $c->getName(), $components);
            $this->assertContains('UserRepository', $componentNames);
            $this->assertContains('ProductRepository', $componentNames);
        } finally {
            unlink($repository1);
            unlink($repository2);
            rmdir($tempDir);
        }
    }

    public function testScanNonExistentDirectory(): void
    {
        $this->expectException(DirectoryNotFoundException::class);

        $this->scanner->scan('/non/existent/directory');
    }

    public function testScanRepositoryWithNoNamespace(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/SimpleRepository.php';
        $repositoryCode = '<?php

class SimpleRepository extends ServiceEntityRepository
{
    public function findSomething() {}
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('SimpleRepository', $component->getName());
            $this->assertNull($component->getNamespace());
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testScanRepositoryWithoutCustomMethods(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/BasicRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;


class BasicRepository extends ServiceEntityRepository
{
    public function __construct() {}
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('BasicRepository', $component->getName());
            $this->assertEquals('repository', $component->getType());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('methods', $metadata);
            $this->assertArrayHasKey('entity', $metadata);
            $this->assertArrayHasKey('extends', $metadata);
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testDescriptionGenerationWithCustomMethods(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/TestRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;

use App\Entity\Test;

class TestRepository extends ServiceEntityRepository
{
    public function __construct() {}

    public function customMethod1(): array {}
    public function customMethod2(string $param): ?Test {}
    public function customMethod3(): int {}

    // These should be filtered out from custom methods count
    public function find($id) {}
    public function findAll(): array {}
    public function findBy(array $criteria): array {}
    public function findOneBy(array $criteria): ?object {}
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];

            $description = $component->getDescription();
            $this->assertStringContainsString('Repository with', $description);
            $this->assertStringContainsString('custom methods', $description);
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testDescriptionGenerationWithoutCustomMethods(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/StandardRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;


class StandardRepository extends ServiceEntityRepository
{
    public function __construct() {}
    public function find($id) {}
    public function findAll(): array {}
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];

            $description = $component->getDescription();
            $this->assertStringContainsString('Repository with 0 custom methods', $description);
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testComponentIdGeneration(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/TestRepository.php';
        $repositoryCode = '<?php
namespace App\Repository\Admin;


class TestRepository extends ServiceEntityRepository
{
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];

            $expectedId = 'repository_' . strtolower(str_replace('\\', '_', 'App\Repository\Admin\TestRepository'));
            $this->assertEquals($expectedId, $component->getId());
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testScanRepositoryWithEntityClass(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/OrderRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;

use App\Entity\Order;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findRecentOrders(): array
    {
        return $this->createQueryBuilder(\'o\')
            ->where(\'o.createTime > :date\')
            ->setParameter(\'date\', new \DateTime(\'-30 days\'))
            ->getQuery()
            ->getResult();
    }
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('OrderRepository', $component->getName());

            $description = $component->getDescription();
            $this->assertStringContainsString('Repository with', $description);
            $this->assertStringContainsString('custom methods', $description);
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }

    public function testScanRepositoryWithComplexMethods(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_repository_');
        mkdir($tempDir);

        $repositoryFile = $tempDir . '/ComplexRepository.php';
        $repositoryCode = '<?php
namespace App\Repository;


class ComplexRepository extends ServiceEntityRepository
{
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder(\'c\');
        
        if (isset($filters[\'status\'])) {
            $qb->andWhere(\'c.status = :status\')
               ->setParameter(\'status\', $filters[\'status\']);
        }
        
        return $qb->getQuery()->getResult();
    }

    public function findByDateRange(\DateTime $start, \DateTime $end): array
    {
        return $this->createQueryBuilder(\'c\')
            ->where(\'c.createTime BETWEEN :start AND :end\')
            ->setParameter(\'start\', $start)
            ->setParameter(\'end\', $end)
            ->orderBy(\'c.createTime\', \'DESC\')
            ->getQuery()
            ->getResult();
    }

    protected function privateHelper(): void
    {
        // This should not be counted in public methods
    }

    private function anotherPrivateMethod(): string
    {
        // This should not be counted in public methods
        return \'test\';
    }
}';
        file_put_contents($repositoryFile, $repositoryCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('ComplexRepository', $component->getName());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('methods', $metadata);
            $this->assertArrayHasKey('entity', $metadata);
            $this->assertArrayHasKey('extends', $metadata);
        } finally {
            unlink($repositoryFile);
            rmdir($tempDir);
        }
    }
}
