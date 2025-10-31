<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PhpParser\Error;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Scanner\RepositoryVisitor;

/**
 * @internal
 */
#[CoversClass(RepositoryVisitor::class)]
class RepositoryVisitorTest extends TestCase
{
    private Parser $parser;

    private NodeTraverser $traverser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
    }

    public function testBasicRepositoryClass(): void
    {
        $code = '<?php
namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class UserRepository extends ServiceEntityRepository
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(["email" => $email]);
    }

    public function findActiveUsers(): array
    {
        return $this->findBy(["active" => true]);
    }

    private function buildQuery(): QueryBuilder
    {
        return $this->createQueryBuilder("u");
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Repository', $visitor->getNamespace());
        $this->assertEquals('UserRepository', $visitor->getClassName());
        $this->assertEquals('ServiceEntityRepository', $visitor->getParentClass());
        $this->assertEquals('User', $visitor->getEntityClass());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(3, $methods);
        $this->assertContains('__construct', $methods);
        $this->assertContains('findByEmail', $methods);
        $this->assertContains('findActiveUsers', $methods);
        $this->assertNotContains('buildQuery', $methods); // private method should not be included
    }

    public function testRepositoryWithoutEntityInConstructor(): void
    {
        $code = '<?php
namespace App\Repository;

class ProductRepository
{
    public function findAll(): array
    {
        return [];
    }

    public function find(int $id): ?Product
    {
        return null;
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('ProductRepository', $visitor->getClassName());
        $this->assertEquals('Product', $visitor->getEntityClass()); // derived from class name
        $this->assertNull($visitor->getParentClass());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('findAll', $methods);
        $this->assertContains('find', $methods);
    }

    public function testRepositoryWithComplexConstructor(): void
    {
        $code = '<?php
namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;

class OrderRepository
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct(Order::class);
    }

    public function findByStatus(string $status): array
    {
        return $this->findBy(["status" => $status]);
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('OrderRepository', $visitor->getClassName());
        $this->assertEquals('Order', $visitor->getEntityClass());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('__construct', $methods);
        $this->assertContains('findByStatus', $methods);
    }

    public function testRepositoryWithoutNamespace(): void
    {
        $code = '<?php
class GlobalRepository
{
    public function findSomething(): array
    {
        return [];
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertNull($visitor->getNamespace());
        $this->assertEquals('GlobalRepository', $visitor->getClassName());
        $this->assertNull($visitor->getParentClass());
        $this->assertEquals('Global', $visitor->getEntityClass()); // "Repository" removed from class name

        $methods = $visitor->getPublicMethods();
        $this->assertCount(1, $methods);
        $this->assertContains('findSomething', $methods);
    }

    public function testRepositoryWithMixedVisibilityMethods(): void
    {
        $code = '<?php
namespace App\Repository;

class CategoryRepository
{
    public function findAll(): array
    {
        return $this->getAllCategories();
    }

    public function findById(int $id): ?Category
    {
        return $this->find($id);
    }

    protected function getAllCategories(): array
    {
        return [];
    }

    private function buildBaseQuery(): QueryBuilder
    {
        return $this->createQueryBuilder("c");
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('CategoryRepository', $visitor->getClassName());
        $this->assertEquals('Category', $visitor->getEntityClass());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('findAll', $methods);
        $this->assertContains('findById', $methods);
        $this->assertNotContains('getAllCategories', $methods);
        $this->assertNotContains('buildBaseQuery', $methods);
    }

    public function testRepositoryWithStaticMethods(): void
    {
        $code = '<?php
namespace App\Repository;

class StaticRepository
{
    public static function createInstance(): self
    {
        return new self();
    }

    public function findData(): array
    {
        return [];
    }

    private static function getDefaultOptions(): array
    {
        return [];
    }
}';

        $visitor = $this->parseCode($code);

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('createInstance', $methods);
        $this->assertContains('findData', $methods);
        $this->assertNotContains('getDefaultOptions', $methods);
    }

    public function testNonRepositoryClass(): void
    {
        $code = '<?php
namespace App\Service;

class UserService
{
    public function getUser(int $id): User
    {
        return new User();
    }

    public function saveUser(User $user): void
    {
        // save logic
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Service', $visitor->getNamespace());
        $this->assertEquals('UserService', $visitor->getClassName());
        $this->assertNull($visitor->getParentClass());
        $this->assertNull($visitor->getEntityClass()); // no entity class since it doesn't end with Repository

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('getUser', $methods);
        $this->assertContains('saveUser', $methods);
    }

    public function testEmptyRepository(): void
    {
        $code = '<?php
namespace App\Repository;

class EmptyRepository
{
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('EmptyRepository', $visitor->getClassName());
        $this->assertEquals('Empty', $visitor->getEntityClass());
        $this->assertEmpty($visitor->getPublicMethods());
    }

    public function testRepositoryWithConstructorButNoEntityClass(): void
    {
        $code = '<?php
namespace App\Repository;

class CustomRepository
{
    private $someService;

    public function __construct($someService)
    {
        $this->someService = $someService;
        // no parent::__construct call
    }

    public function customMethod(): array
    {
        return [];
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('CustomRepository', $visitor->getClassName());
        $this->assertEquals('Custom', $visitor->getEntityClass()); // fallback to class name without Repository

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('__construct', $methods);
        $this->assertContains('customMethod', $methods);
    }

    public function testRepositoryWithMultipleConstructorStatements(): void
    {
        $code = '<?php
namespace App\Repository;

class ComplexRepository
{
    public function __construct()
    {
        $this->initializeDefaults();
        parent::__construct(ComplexEntity::class);
        $this->setupCaching();
    }

    private function initializeDefaults(): void
    {
    }

    private function setupCaching(): void
    {
    }

    public function findComplex(): array
    {
        return [];
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('ComplexRepository', $visitor->getClassName());
        $this->assertEquals('ComplexEntity', $visitor->getEntityClass());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('__construct', $methods);
        $this->assertContains('findComplex', $methods);
        $this->assertNotContains('initializeDefaults', $methods);
        $this->assertNotContains('setupCaching', $methods);
    }

    public function testRepositoryClassWithoutRepositorySuffix(): void
    {
        $code = '<?php
namespace App\Data;

class UserData
{
    public function getData(): array
    {
        return [];
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('UserData', $visitor->getClassName());
        $this->assertNull($visitor->getEntityClass()); // no entity class since it doesn't end with Repository

        $methods = $visitor->getPublicMethods();
        $this->assertCount(1, $methods);
        $this->assertContains('getData', $methods);
    }

    public function testEnterNode(): void
    {
        $visitor = new RepositoryVisitor();

        // Test with Namespace node
        $namespaceNode = new Namespace_(
            new Name('App\Repository')
        );
        $result = $visitor->enterNode($namespaceNode);
        $this->assertNull($result);
        $this->assertEquals('App\Repository', $visitor->getNamespace());

        // Test with Class node
        $classNode = new Class_(
            new Identifier('UserRepository'),
            [
                'extends' => new Name('ServiceEntityRepository'),
            ]
        );
        $result = $visitor->enterNode($classNode);
        $this->assertNull($result);
        $this->assertEquals('UserRepository', $visitor->getClassName());
        $this->assertEquals('ServiceEntityRepository', $visitor->getParentClass());
        $this->assertEquals('User', $visitor->getEntityClass());

        // Test with ClassMethod node
        $methodNode = new ClassMethod(
            new Identifier('findByEmail'),
            [
                'flags' => Modifiers::PUBLIC,
            ]
        );
        $result = $visitor->enterNode($methodNode);
        $this->assertNull($result);
        $this->assertContains('findByEmail', $visitor->getPublicMethods());

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

    private function parseCode(string $code): RepositoryVisitor
    {
        $stmts = $this->parser->parse($code);
        $visitor = new RepositoryVisitor();

        $this->traverser->addVisitor($visitor);
        if (null !== $stmts) {
            $this->traverser->traverse($stmts);
        }

        return $visitor;
    }
}
