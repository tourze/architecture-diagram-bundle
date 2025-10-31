<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Scanner\DependencyVisitor;

/**
 * @internal
 */
#[CoversClass(DependencyVisitor::class)]
class DependencyVisitorTest extends TestCase
{
    private DependencyVisitor $visitor;

    /** @var array<string, string> */
    private array $classMap;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->classMap = [
            'App\Service\UserService' => 'user_service',
            'App\Repository\UserRepository' => 'user_repository',
            'App\Controller\UserController' => 'user_controller',
            'App\Entity\User' => 'user_entity',
        ];
        $this->visitor = new DependencyVisitor($this->classMap);
    }

    public function testEnterNodeWithClass(): void
    {
        $classNode = new Class_('TestClass');

        $result = $this->visitor->enterNode($classNode);

        $this->assertNull($result);
    }

    public function testEnterNodeWithClassExtends(): void
    {
        $parentName = new Name\FullyQualified('App\Controller\UserController');

        $classNode = new Class_('TestClass');
        $classNode->extends = $parentName;

        $this->visitor->enterNode($classNode);

        $dependencies = $this->visitor->getDependencies();
        $this->assertContains('user_controller', $dependencies);
    }

    public function testEnterNodeWithClassImplements(): void
    {
        $interfaceName = new Name\FullyQualified('App\Entity\User');

        $classNode = new Class_('TestClass');
        $classNode->implements = [$interfaceName];

        $this->visitor->enterNode($classNode);

        $dependencies = $this->visitor->getDependencies();
        $this->assertContains('user_entity', $dependencies);
    }

    public function testEnterNodeWithConstructor(): void
    {
        $typeName = new Name\FullyQualified('App\Service\UserService');
        $param = new Param(new Node\Expr\Variable('userService'), null, $typeName);

        $constructorNode = new ClassMethod('__construct');
        $constructorNode->params = [$param];

        $this->visitor->enterNode($constructorNode);

        $dependencies = $this->visitor->getDependencies();
        $this->assertContains('user_service', $dependencies);
    }

    public function testEnterNodeWithNullableTypeConstructor(): void
    {
        $typeName = new Name\FullyQualified('App\Repository\UserRepository');
        $nullableType = new NullableType($typeName);
        $param = new Param(new Node\Expr\Variable('userRepository'), null, $nullableType);

        $constructorNode = new ClassMethod('__construct');
        $constructorNode->params = [$param];

        $this->visitor->enterNode($constructorNode);

        $dependencies = $this->visitor->getDependencies();
        $this->assertContains('user_repository', $dependencies);
    }

    public function testEnterNodeWithNewExpression(): void
    {
        $className = new Name\FullyQualified('App\Entity\User');
        $newNode = new New_($className);

        $this->visitor->enterNode($newNode);

        $dependencies = $this->visitor->getDependencies();
        $this->assertContains('user_entity', $dependencies);
    }

    public function testEnterNodeWithStaticCall(): void
    {
        $className = new Name\FullyQualified('App\Service\UserService');
        $methodName = 'getInstance';
        $staticCallNode = new StaticCall($className, $methodName);

        $this->visitor->enterNode($staticCallNode);

        $dependencies = $this->visitor->getDependencies();
        $this->assertContains('user_service', $dependencies);
    }

    public function testGetDependenciesReturnsUnique(): void
    {
        $className = new Name\FullyQualified('App\Service\UserService');

        $newNode1 = new New_($className);
        $newNode2 = new New_($className);

        $this->visitor->enterNode($newNode1);
        $this->visitor->enterNode($newNode2);

        $dependencies = $this->visitor->getDependencies();
        $this->assertCount(1, $dependencies);
        $this->assertContains('user_service', $dependencies);
    }

    public function testEnterNodeWithUnmappedClass(): void
    {
        $className = new Name\FullyQualified('Unknown\Class');
        $newNode = new New_($className);

        $this->visitor->enterNode($newNode);

        $dependencies = $this->visitor->getDependencies();
        $this->assertEmpty($dependencies);
    }
}
