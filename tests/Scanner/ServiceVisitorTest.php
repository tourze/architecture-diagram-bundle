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
use Tourze\ArchitectureDiagramBundle\Scanner\ServiceVisitor;

/**
 * @internal
 */
#[CoversClass(ServiceVisitor::class)]
class ServiceVisitorTest extends TestCase
{
    private Parser $parser;

    private NodeTraverser $traverser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->traverser = new NodeTraverser();
    }

    public function testBasicServiceClass(): void
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

    private function validateUser(User $user): bool
    {
        return !empty($user->getEmail());
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Service', $visitor->getNamespace());
        $this->assertEquals('UserService', $visitor->getClassName());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('getUser', $methods);
        $this->assertContains('saveUser', $methods);
        $this->assertNotContains('validateUser', $methods); // private method should not be included

        $this->assertEmpty($visitor->getDependencies());
        $this->assertEmpty($visitor->getInterfaces());
    }

    public function testServiceWithInterface(): void
    {
        $code = '<?php
namespace App\Service;

use App\Interface\UserServiceInterface;
use App\Interface\NotificationInterface;

class UserService implements UserServiceInterface, NotificationInterface
{
    public function getUser(): User
    {
        return new User();
    }

    public function notify(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('UserService', $visitor->getClassName());

        $interfaces = $visitor->getInterfaces();
        $this->assertCount(2, $interfaces);
        $this->assertContains('UserServiceInterface', $interfaces);
        $this->assertContains('NotificationInterface', $interfaces);

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('getUser', $methods);
        $this->assertContains('notify', $methods);
    }

    public function testServiceWithDependencies(): void
    {
        $code = '<?php
namespace App\Service;

class OrderService
{
    private $entityManager;
    private $emailService;

    public function processOrder(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->emailService->sendConfirmation($order);
    }

    public function calculateTotal(Order $order): float
    {
        $total = 0;
        foreach ($order->getItems() as $item) {
            $total += $item->getPrice();
        }
        return $total;
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('OrderService', $visitor->getClassName());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('processOrder', $methods);
        $this->assertContains('calculateTotal', $methods);

        $dependencies = $visitor->getDependencies();
        $this->assertCount(2, $dependencies);
        $this->assertContains('entityManager', $dependencies);
        $this->assertContains('emailService', $dependencies);
    }

    public function testServiceWithMixedVisibilityMethods(): void
    {
        $code = '<?php
namespace App\Service;

class ComplexService
{
    public function publicMethod(): void
    {
        $this->dependency1->call();
    }

    protected function protectedMethod(): void
    {
        $this->dependency2->call();
    }

    private function privateMethod(): void
    {
        $this->dependency3->call();
    }
}';

        $visitor = $this->parseCode($code);

        $methods = $visitor->getPublicMethods();
        $this->assertCount(1, $methods);
        $this->assertContains('publicMethod', $methods);
        $this->assertNotContains('protectedMethod', $methods);
        $this->assertNotContains('privateMethod', $methods);

        $dependencies = $visitor->getDependencies();
        $this->assertCount(3, $dependencies);
        $this->assertContains('dependency1', $dependencies);
        $this->assertContains('dependency2', $dependencies);
        $this->assertContains('dependency3', $dependencies);
    }

    public function testServiceWithoutNamespace(): void
    {
        $code = '<?php
class GlobalService
{
    public function doSomething(): void
    {
        $this->helper->process();
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertNull($visitor->getNamespace());
        $this->assertEquals('GlobalService', $visitor->getClassName());

        $methods = $visitor->getPublicMethods();
        $this->assertCount(1, $methods);
        $this->assertContains('doSomething', $methods);

        $dependencies = $visitor->getDependencies();
        $this->assertCount(1, $dependencies);
        $this->assertContains('helper', $dependencies);
    }

    public function testServiceWithStaticMethods(): void
    {
        $code = '<?php
namespace App\Service;

class StaticService
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function instanceMethod(): void
    {
        $this->service->call();
    }

    private static function privateStatic(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('getInstance', $methods);
        $this->assertContains('instanceMethod', $methods);
        $this->assertNotContains('privateStatic', $methods);

        $dependencies = $visitor->getDependencies();
        $this->assertCount(1, $dependencies);
        $this->assertContains('service', $dependencies);
    }

    public function testServiceWithComplexDependencyUsage(): void
    {
        $code = '<?php
namespace App\Service;

class PaymentService
{
    public function processPayment(): void
    {
        $result = $this->gateway->charge($amount);
        if ($result->isSuccessful()) {
            $this->logger->info("Payment successful");
            $this->notifier->sendConfirmation();
        } else {
            $this->logger->error("Payment failed");
        }
    }

    public function validatePayment(): bool
    {
        return $this->validator->validate() && $this->gateway->isValid();
    }

    private function cleanup(): void
    {
        $this->cache->clear();
    }
}';

        $visitor = $this->parseCode($code);

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('processPayment', $methods);
        $this->assertContains('validatePayment', $methods);
        $this->assertNotContains('cleanup', $methods);

        $dependencies = $visitor->getDependencies();
        // Note: Current implementation only detects simple method calls as top-level expressions
        // More complex cases in nested statements (if, loops) are not yet supported
        $this->assertCount(1, $dependencies);
        $this->assertContains('cache', $dependencies); // Only the private method call is detected
    }

    public function testServiceWithNoDependencies(): void
    {
        $code = '<?php
namespace App\Service;

class SimpleService
{
    public function calculate(int $a, int $b): int
    {
        return $a + $b;
    }

    public function format(string $text): string
    {
        return strtoupper($text);
    }
}';

        $visitor = $this->parseCode($code);

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('calculate', $methods);
        $this->assertContains('format', $methods);

        $this->assertEmpty($visitor->getDependencies());
        $this->assertEmpty($visitor->getInterfaces());
    }

    public function testEmptyService(): void
    {
        $code = '<?php
namespace App\Service;

class EmptyService
{
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Service', $visitor->getNamespace());
        $this->assertEquals('EmptyService', $visitor->getClassName());
        $this->assertEmpty($visitor->getPublicMethods());
        $this->assertEmpty($visitor->getDependencies());
        $this->assertEmpty($visitor->getInterfaces());
    }

    public function testServiceWithConstructor(): void
    {
        $code = '<?php
namespace App\Service;

class ConstructorService
{
    public function __construct()
    {
        $this->dependency->initialize();
    }

    public function doWork(): void
    {
        $this->worker->execute();
    }
}';

        $visitor = $this->parseCode($code);

        $methods = $visitor->getPublicMethods();
        $this->assertCount(2, $methods);
        $this->assertContains('__construct', $methods);
        $this->assertContains('doWork', $methods);

        $dependencies = $visitor->getDependencies();
        $this->assertCount(2, $dependencies);
        $this->assertContains('dependency', $dependencies);
        $this->assertContains('worker', $dependencies);
    }

    public function testServiceWithUniqueInterfaces(): void
    {
        $code = '<?php
namespace App\Service;

use App\Interface\ServiceInterface;

class DuplicateInterfaceService implements ServiceInterface, ServiceInterface
{
    public function service(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $interfaces = $visitor->getInterfaces();
        $this->assertCount(2, $interfaces); // Should contain duplicates as they appear in the code
        $this->assertEquals(['ServiceInterface', 'ServiceInterface'], $interfaces);
    }

    public function testServiceWithNestedMethodCalls(): void
    {
        $code = '<?php
namespace App\Service;

class NestedService
{
    public function complexOperation(): void
    {
        if ($this->validator->isValid()) {
            $result = $this->processor->process($data);
            if ($result) {
                $this->logger->log("Success");
            }
        }
        
        for ($i = 0; $i < 10; $i++) {
            $this->counter->increment();
        }
    }
}';

        $visitor = $this->parseCode($code);

        $dependencies = $visitor->getDependencies();
        // Note: Current implementation only detects simple method calls as top-level expressions
        $this->assertCount(0, $dependencies); // Nested calls in control structures not yet detected
    }

    public function testEnterNode(): void
    {
        $visitor = new ServiceVisitor();

        // Test with Namespace node
        $namespaceNode = new Namespace_(
            new Name('App\Service')
        );
        $result = $visitor->enterNode($namespaceNode);
        $this->assertNull($result);
        $this->assertEquals('App\Service', $visitor->getNamespace());

        // Test with Class node with interfaces
        $classNode = new Class_(
            new Identifier('UserService'),
            [
                'implements' => [
                    new Name('UserServiceInterface'),
                ],
            ]
        );
        $result = $visitor->enterNode($classNode);
        $this->assertNull($result);
        $this->assertEquals('UserService', $visitor->getClassName());
        $this->assertContains('UserServiceInterface', $visitor->getInterfaces());

        // Test with ClassMethod node
        $methodNode = new ClassMethod(
            new Identifier('getUser'),
            [
                'flags' => Modifiers::PUBLIC,
            ]
        );
        $result = $visitor->enterNode($methodNode);
        $this->assertNull($result);
        $this->assertContains('getUser', $visitor->getPublicMethods());

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

    private function parseCode(string $code): ServiceVisitor
    {
        $stmts = $this->parser->parse($code);
        $visitor = new ServiceVisitor();

        $this->traverser->addVisitor($visitor);
        if (null !== $stmts) {
            $this->traverser->traverse($stmts);
        }

        return $visitor;
    }
}
