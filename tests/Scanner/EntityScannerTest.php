<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Tourze\ArchitectureDiagramBundle\Model\Component;
use Tourze\ArchitectureDiagramBundle\Scanner\EntityScanner;

/**
 * @internal
 */
#[CoversClass(EntityScanner::class)]
final class EntityScannerTest extends TestCase
{
    private EntityScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->scanner = new EntityScanner();
    }

    public function testScanEmptyDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertEmpty($components);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testScanDirectoryWithNonEntityFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
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

    public function testScanDirectoryWithEntityFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entityFile = $tempDir . '/User.php';
        $entityCode = '<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "users")]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    private string $email;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertCount(1, $components);

            $component = $components[0];
            $this->assertInstanceOf(Component::class, $component);
            $this->assertEquals('User', $component->getName());
            $this->assertEquals('entity', $component->getType());
            $this->assertEquals('Doctrine ORM', $component->getTechnology());
            $this->assertEquals('App\Entity', $component->getNamespace());
            $this->assertEquals(realpath($entityFile), $component->getFilePath());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('properties', $metadata);
            $this->assertArrayHasKey('methods', $metadata);
            $this->assertArrayHasKey('table', $metadata);
        } finally {
            unlink($entityFile);
            rmdir($tempDir);
        }
    }

    public function testScanFileWithInvalidPhpSyntax(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $invalidFile = $tempDir . '/Invalid.php';
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
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $emptyFile = $tempDir . '/Empty.php';
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

    public function testScanMultipleEntityFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entity1 = $tempDir . '/User.php';
        $entity1Code = '<?php
namespace App\Entity;


#[ORM\Entity]
class User
{
    #[ORM\Id]
    private int $id;

    #[ORM\Column(type: "string")]
    private string $name;
}';
        file_put_contents($entity1, $entity1Code);

        $entity2 = $tempDir . '/Product.php';
        $entity2Code = '<?php
namespace App\Entity;


#[ORM\Entity]
class Product
{
    #[ORM\Id]
    private int $id;

    #[ORM\Column(type: "string")]
    private string $title;
}';
        file_put_contents($entity2, $entity2Code);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertIsArray($components);
            $this->assertCount(2, $components);

            $componentNames = array_map(fn (Component $c) => $c->getName(), $components);
            $this->assertContains('User', $componentNames);
            $this->assertContains('Product', $componentNames);
        } finally {
            unlink($entity1);
            unlink($entity2);
            rmdir($tempDir);
        }
    }

    public function testScanNonExistentDirectory(): void
    {
        $this->expectException(DirectoryNotFoundException::class);

        $this->scanner->scan('/non/existent/directory');
    }

    public function testScanEntityWithNoNamespace(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entityFile = $tempDir . '/SimpleEntity.php';
        $entityCode = '<?php

#[ORM\Entity]
class SimpleEntity
{
    #[ORM\Id]
    private int $id;
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('SimpleEntity', $component->getName());
            $this->assertNull($component->getNamespace());
        } finally {
            unlink($entityFile);
            rmdir($tempDir);
        }
    }

    public function testScanEntityWithTableAnnotation(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entityFile = $tempDir . '/UserProfile.php';
        $entityCode = '<?php
namespace App\Entity;


#[ORM\Entity]
#[ORM\Table(name: "user_profiles")]
class UserProfile
{
    #[ORM\Id]
    private int $id;

    #[ORM\Column(type: "text")]
    private string $bio;
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('UserProfile', $component->getName());
            $this->assertEquals('entity', $component->getType());

            $description = $component->getDescription();
            $this->assertStringContainsString('Entity with', $description);
            $this->assertStringContainsString('properties', $description);
        } finally {
            unlink($entityFile);
            rmdir($tempDir);
        }
    }

    public function testScanEntityWithoutProperties(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entityFile = $tempDir . '/EmptyEntity.php';
        $entityCode = '<?php
namespace App\Entity;


#[ORM\Entity]
class EmptyEntity
{
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('EmptyEntity', $component->getName());
            $this->assertEquals('entity', $component->getType());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('properties', $metadata);
            $this->assertArrayHasKey('methods', $metadata);
            $this->assertArrayHasKey('table', $metadata);
        } finally {
            unlink($entityFile);
            rmdir($tempDir);
        }
    }

    public function testDescriptionGeneration(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entityFile = $tempDir . '/TestEntity.php';
        $entityCode = '<?php
namespace App\Entity;


#[ORM\Entity]
#[ORM\Table(name: "test_table")]
class TestEntity
{
    #[ORM\Id]
    private int $id;

    #[ORM\Column(type: "string")]
    private string $name;

    #[ORM\Column(type: "string")]
    private string $email;
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];

            $description = $component->getDescription();
            $this->assertStringContainsString('Entity with', $description);
            $this->assertStringContainsString('properties', $description);
        } finally {
            unlink($entityFile);
            rmdir($tempDir);
        }
    }

    public function testComponentIdGeneration(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entityFile = $tempDir . '/TestEntity.php';
        $entityCode = '<?php
namespace App\Entity\User;


#[ORM\Entity]
class TestEntity
{
    #[ORM\Id]
    private int $id;
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];

            $expectedId = 'entity_' . strtolower(str_replace('\\', '_', 'App\Entity\User\TestEntity'));
            $this->assertEquals($expectedId, $component->getId());
        } finally {
            unlink($entityFile);
            rmdir($tempDir);
        }
    }

    public function testScanEntityWithComplexStructure(): void
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('test_entity_');
        mkdir($tempDir);

        $entityFile = $tempDir . '/Order.php';
        $entityCode = '<?php
namespace App\Entity;

use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: "orders")]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string")]
    private string $status;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private string $total;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: "order")]
    private Collection $items;

    public function getId(): int { return $this->id; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): self { $this->total = $total; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getItems(): Collection { return $this->items; }
}';
        file_put_contents($entityFile, $entityCode);

        try {
            $components = $this->scanner->scan($tempDir);

            $this->assertCount(1, $components);
            $component = $components[0];
            $this->assertEquals('Order', $component->getName());
            $this->assertEquals('entity', $component->getType());

            $metadata = $component->getMetadata();
            $this->assertArrayHasKey('properties', $metadata);
            $this->assertArrayHasKey('methods', $metadata);
            $this->assertArrayHasKey('table', $metadata);
        } finally {
            unlink($entityFile);
            rmdir($tempDir);
        }
    }
}
