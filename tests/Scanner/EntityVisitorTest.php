<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PhpParser\Error;
use PhpParser\Modifiers;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Scanner\EntityVisitor;

/**
 * @internal
 */
#[CoversClass(EntityVisitor::class)]
class EntityVisitorTest extends TestCase
{
    private Parser $parser;

    private NodeTraverser $traverser;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
    }

    public function testEntityWithAttribute(): void
    {
        $code = '<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "users")]
class User
{
    #[ORM\Id]
    private int $id;

    #[ORM\Column(type: "string")]
    private string $email;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $name;

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    private function validateEmail(): bool
    {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Entity', $visitor->getNamespace());
        $this->assertEquals('User', $visitor->getClassName());
        $this->assertTrue($visitor->isEntity());
        $this->assertEquals('users', $visitor->getTableName());

        $properties = $visitor->getProperties();
        $this->assertCount(3, $properties);
        $this->assertContains('id', $properties);
        $this->assertContains('email', $properties);
        $this->assertContains('name', $properties);

        $methods = $visitor->getMethods();
        $this->assertCount(4, $methods);
        $this->assertContains('getId', $methods);
        $this->assertContains('getEmail', $methods);
        $this->assertContains('setEmail', $methods);
        $this->assertContains('validateEmail', $methods);
    }

    public function testEntityWithDocCommentAnnotation(): void
    {
        $code = '<?php
namespace App\Entity;

/**
 * @Entity
 * @Table(name="products")
 */
class Product
{
    private int $id;
    private string $name;
    private float $price;

    public function __construct(string $name, float $price)
    {
        $this->name = $name;
        $this->price = $price;
    }

    public function getName(): string
    {
        return $this->name;
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Entity', $visitor->getNamespace());
        $this->assertEquals('Product', $visitor->getClassName());
        $this->assertTrue($visitor->isEntity());
        $this->assertEquals('products', $visitor->getTableName());

        $properties = $visitor->getProperties();
        $this->assertCount(3, $properties);
        $this->assertContains('id', $properties);
        $this->assertContains('name', $properties);
        $this->assertContains('price', $properties);

        $methods = $visitor->getMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('__construct', $methods);
        $this->assertContains('getName', $methods);
    }

    public function testEntityWithOrmPrefix(): void
    {
        $code = '<?php
namespace App\Entity;

/**
 * @ORM\Entity
 * @ORM\Table(name="orders")
 */
class Order
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isEntity());
        $this->assertEquals('Order', $visitor->getClassName());
        $this->assertEquals('orders', $visitor->getTableName());
    }

    public function testNonEntityClass(): void
    {
        $code = '<?php
namespace App\Service;

class UserService
{
    private string $name;

    public function getUsers(): array
    {
        return [];
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Service', $visitor->getNamespace());
        $this->assertEquals('UserService', $visitor->getClassName());
        $this->assertFalse($visitor->isEntity());
        $this->assertNull($visitor->getTableName());

        $properties = $visitor->getProperties();
        $this->assertCount(1, $properties);
        $this->assertContains('name', $properties);

        $methods = $visitor->getMethods();
        $this->assertCount(1, $methods);
        $this->assertContains('getUsers', $methods);
    }

    public function testEntityWithoutTableName(): void
    {
        $code = '<?php
namespace App\Entity;

#[ORM\Entity]
class Category
{
    private int $id;
    private string $name;

    public function getId(): int
    {
        return $this->id;
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isEntity());
        $this->assertEquals('Category', $visitor->getClassName());
        $this->assertNull($visitor->getTableName());

        $properties = $visitor->getProperties();
        $this->assertCount(2, $properties);
        $this->assertContains('id', $properties);
        $this->assertContains('name', $properties);
    }

    public function testEntityWithMultipleProperties(): void
    {
        $code = '<?php
namespace App\Entity;

#[ORM\Entity]
class ComplexEntity
{
    private int $id;
    private string $field1;
    private ?string $field2;
    private bool $active;
    private \DateTime $createTime;

    private string $anotherField;
    private int $numericField;

    public function getId(): int
    {
        return $this->id;
    }

    public function getField1(): string
    {
        return $this->field1;
    }

    public function setField1(string $field1): void
    {
        $this->field1 = $field1;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    protected function protectedMethod(): void
    {
    }

    private function privateMethod(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isEntity());
        $this->assertEquals('ComplexEntity', $visitor->getClassName());

        $properties = $visitor->getProperties();
        $this->assertCount(7, $properties);
        $this->assertContains('id', $properties);
        $this->assertContains('field1', $properties);
        $this->assertContains('field2', $properties);
        $this->assertContains('active', $properties);
        $this->assertContains('createTime', $properties);
        $this->assertContains('anotherField', $properties);
        $this->assertContains('numericField', $properties);

        $methods = $visitor->getMethods();
        $this->assertCount(6, $methods);
        $this->assertContains('getId', $methods);
        $this->assertContains('getField1', $methods);
        $this->assertContains('setField1', $methods);
        $this->assertContains('isActive', $methods);
        $this->assertContains('protectedMethod', $methods);
        $this->assertContains('privateMethod', $methods);
    }

    public function testEntityWithoutNamespace(): void
    {
        $code = '<?php
#[Entity]
class GlobalEntity
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertNull($visitor->getNamespace());
        $this->assertEquals('GlobalEntity', $visitor->getClassName());
        $this->assertTrue($visitor->isEntity());
    }

    public function testEmptyEntity(): void
    {
        $code = '<?php
namespace App\Entity;

#[ORM\Entity]
class EmptyEntity
{
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Entity', $visitor->getNamespace());
        $this->assertEquals('EmptyEntity', $visitor->getClassName());
        $this->assertTrue($visitor->isEntity());
        $this->assertNull($visitor->getTableName());
        $this->assertEmpty($visitor->getProperties());
        $this->assertEmpty($visitor->getMethods());
    }

    public function testTableAttributeWithoutName(): void
    {
        $code = '<?php
namespace App\Entity;

#[ORM\Entity]
#[ORM\Table]
class SimpleEntity
{
    private int $id;
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isEntity());
        $this->assertEquals('SimpleEntity', $visitor->getClassName());
        $this->assertNull($visitor->getTableName()); // no name specified in Table attribute
    }

    public function testEntityDetectionWithMultipleAttributes(): void
    {
        $code = '<?php
namespace App\Entity;

#[SomeAttribute]
#[ORM\Entity]
#[AnotherAttribute]
#[ORM\Table(name: "test_table")]
class MultiAttributeEntity
{
    private int $id;
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isEntity());
        $this->assertEquals('test_table', $visitor->getTableName());
    }

    public function testClassWithPropertiesAndMethods(): void
    {
        $code = '<?php
namespace App\Entity;

#[ORM\Entity]
class TestEntity
{
    public int $publicProp;
    protected string $protectedProp;
    private bool $privateProp;

    static private string $staticProp;

    public function publicMethod(): void {}
    protected function protectedMethod(): void {}
    private function privateMethod(): void {}
    public static function staticMethod(): void {}
}';

        $visitor = $this->parseCode($code);

        $properties = $visitor->getProperties();
        $this->assertCount(4, $properties);
        $this->assertContains('publicProp', $properties);
        $this->assertContains('protectedProp', $properties);
        $this->assertContains('privateProp', $properties);
        $this->assertContains('staticProp', $properties);

        $methods = $visitor->getMethods();
        $this->assertCount(4, $methods);
        $this->assertContains('publicMethod', $methods);
        $this->assertContains('protectedMethod', $methods);
        $this->assertContains('privateMethod', $methods);
        $this->assertContains('staticMethod', $methods);
    }

    public function testEnterNode(): void
    {
        $visitor = new EntityVisitor();

        // Test with Namespace node
        $namespaceNode = new Namespace_(
            new Name('App\Entity')
        );
        $result = $visitor->enterNode($namespaceNode);
        $this->assertNull($result);
        $this->assertEquals('App\Entity', $visitor->getNamespace());

        // Test with Class node with Entity attribute
        $classNode = new Class_(
            new Identifier('User')
        );
        $classNode->attrGroups = [
            new AttributeGroup([
                new Attribute(new Name('Entity')),
            ]),
        ];
        $result = $visitor->enterNode($classNode);
        $this->assertNull($result);
        $this->assertEquals('User', $visitor->getClassName());
        $this->assertTrue($visitor->isEntity());

        // Test with Property node
        $propertyNode = new Property(
            Modifiers::PRIVATE,
            [new PropertyProperty(new VarLikeIdentifier('id'))]
        );
        $result = $visitor->enterNode($propertyNode);
        $this->assertNull($result);
        $this->assertContains('id', $visitor->getProperties());

        // Test with ClassMethod node
        $methodNode = new ClassMethod(
            new Identifier('getId')
        );
        $result = $visitor->enterNode($methodNode);
        $this->assertNull($result);
        $this->assertContains('getId', $visitor->getMethods());

        // Test with unrelated node
        $unrelatedNode = new Variable('test');
        $result = $visitor->enterNode($unrelatedNode);
        $this->assertNull($result);
    }

    public function testInvalidSyntax(): void
    {
        $this->expectException(Error::class);
        $this->parseCode('<?php invalid syntax {{{');
    }

    private function parseCode(string $code): EntityVisitor
    {
        $stmts = $this->parser->parse($code);
        $visitor = new EntityVisitor();

        $this->traverser->addVisitor($visitor);
        if (null !== $stmts) {
            $this->traverser->traverse($stmts);
        }

        return $visitor;
    }
}
