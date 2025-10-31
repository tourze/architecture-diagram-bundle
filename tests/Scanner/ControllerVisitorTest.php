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
use Tourze\ArchitectureDiagramBundle\Scanner\ControllerVisitor;

/**
 * @internal
 */
#[CoversClass(ControllerVisitor::class)]
class ControllerVisitorTest extends TestCase
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

    public function testBasicControllerDetection(): void
    {
        $code = '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UserController extends AbstractController
{
    public function index(): Response
    {
        return $this->render("user/index.html.twig");
    }

    private function helper(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Controller', $visitor->getNamespace());
        $this->assertEquals('UserController', $visitor->getClassName());
        $this->assertEquals('AbstractController', $visitor->getParentClass());
        $this->assertTrue($visitor->isController());

        $actions = $visitor->getActions();
        $this->assertCount(1, $actions);
        $this->assertContains('index', $actions);
        $this->assertNotContains('helper', $actions); // private methods should not be included
    }

    public function testControllerWithRouteAttribute(): void
    {
        $code = '<?php
namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

#[Route("/api")]
class ApiController
{
    #[Route("/users", methods: ["GET", "POST"])]
    public function getUsers(): Response
    {
        return new JsonResponse([]);
    }

    #[Route("/user/{id}", methods: ["GET"])]
    public function getUser(int $id): Response
    {
        return new JsonResponse(["id" => $id]);
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isController());
        $this->assertEquals('ApiController', $visitor->getClassName());

        $actions = $visitor->getActions();
        $this->assertCount(2, $actions);
        $this->assertContains('getUsers', $actions);
        $this->assertContains('getUser', $actions);

        $routes = $visitor->getRoutes();
        $this->assertCount(2, $routes);

        $route1 = $routes[0];
        $this->assertEquals('/users', $route1['path']);
        $this->assertEquals(['GET', 'POST'], $route1['methods']);

        $route2 = $routes[1];
        $this->assertEquals('/user/{id}', $route2['path']);
        $this->assertEquals(['GET'], $route2['methods']);
    }

    public function testControllerWithDocCommentAnnotation(): void
    {
        $code = '<?php
namespace App\Controller;

/**
 * @Controller
 */
class DocumentedController
{
    /**
     * @Route("/test", methods={"GET"})
     */
    public function testAction(): Response
    {
        return new Response("test");
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isController());
        $this->assertEquals('DocumentedController', $visitor->getClassName());
        $this->assertContains('testAction', $visitor->getActions());
    }

    public function testRouteWithoutPath(): void
    {
        $code = '<?php
namespace App\Controller;


class SimpleController
{
    #[Route(methods: ["POST"])]
    public function create(): Response
    {
        return new Response();
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isController());

        $routes = $visitor->getRoutes();
        $this->assertCount(1, $routes);

        $route = $routes[0];
        $this->assertEquals('/create', $route['path']); // should default to lowercase method name
        $this->assertEquals(['POST'], $route['methods']);
    }

    public function testRouteWithDefaultMethod(): void
    {
        $code = '<?php
namespace App\Controller;

class DefaultController
{
    #[Route("/default")]
    public function defaultAction(): Response
    {
        return new Response();
    }
}';

        $visitor = $this->parseCode($code);

        $routes = $visitor->getRoutes();
        $this->assertCount(1, $routes);

        $route = $routes[0];
        $this->assertEquals('/default', $route['path']);
        $this->assertEquals(['GET'], $route['methods']); // should default to GET
    }

    public function testNonControllerClass(): void
    {
        $code = '<?php
namespace App\Service;

class UserService
{
    public function getUser(): User
    {
        return new User();
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Service', $visitor->getNamespace());
        $this->assertEquals('UserService', $visitor->getClassName());
        $this->assertNull($visitor->getParentClass());
        $this->assertFalse($visitor->isController());

        $actions = $visitor->getActions();
        $this->assertCount(1, $actions);
        $this->assertContains('getUser', $actions);

        $routes = $visitor->getRoutes();
        $this->assertEmpty($routes);
    }

    public function testControllerWithoutNamespace(): void
    {
        $code = '<?php
class GlobalController
{
    public function action(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertNull($visitor->getNamespace());
        $this->assertEquals('GlobalController', $visitor->getClassName());
        $this->assertFalse($visitor->isController()); // no parent class or attributes to identify as controller
        $this->assertContains('action', $visitor->getActions());
    }

    public function testControllerWithMultipleAttributes(): void
    {
        $code = '<?php
namespace App\Controller;


#[Route("/api")]
#[SomeOtherAttribute]
class MultiAttributeController
{
    #[Route("/test")]
    #[Cache(expires: "+1 hour")]
    public function testAction(): Response
    {
        return new Response();
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isController());
        $this->assertEquals('MultiAttributeController', $visitor->getClassName());

        $routes = $visitor->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/test', $routes[0]['path']);
    }

    public function testEmptyClass(): void
    {
        $code = '<?php
namespace App\Controller;

class EmptyController
{
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('App\Controller', $visitor->getNamespace());
        $this->assertEquals('EmptyController', $visitor->getClassName());
        $this->assertNull($visitor->getParentClass());
        $this->assertFalse($visitor->isController());
        $this->assertEmpty($visitor->getActions());
        $this->assertEmpty($visitor->getRoutes());
    }

    public function testControllerWithProtectedAndPrivateMethods(): void
    {
        $code = '<?php
namespace App\Controller;


class MixedMethodsController extends AbstractController
{
    public function publicAction(): Response
    {
        return new Response();
    }

    protected function protectedHelper(): void
    {
    }

    private function privateHelper(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertTrue($visitor->isController());

        $actions = $visitor->getActions();
        $this->assertCount(1, $actions);
        $this->assertContains('publicAction', $actions);
        $this->assertNotContains('protectedHelper', $actions);
        $this->assertNotContains('privateHelper', $actions);
    }

    public function testControllerDetectionByParentClass(): void
    {
        $code = '<?php
namespace App\Controller;

class CustomBase
{
}

class MyController extends CustomBase
{
    public function action(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('MyController', $visitor->getClassName());
        $this->assertEquals('CustomBase', $visitor->getParentClass());
        $this->assertFalse($visitor->isController()); // parent doesn't contain "Controller"
    }

    public function testControllerDetectionByParentNameContainingController(): void
    {
        $code = '<?php
namespace App\Controller;

class MyController extends SomeController
{
    public function action(): void
    {
    }
}';

        $visitor = $this->parseCode($code);

        $this->assertEquals('MyController', $visitor->getClassName());
        $this->assertEquals('SomeController', $visitor->getParentClass());
        $this->assertTrue($visitor->isController()); // parent contains "Controller"
    }

    public function testEnterNode(): void
    {
        $visitor = new ControllerVisitor();

        // Test with Namespace node
        $namespaceNode = new Namespace_(
            new Name('App\Controller')
        );
        $result = $visitor->enterNode($namespaceNode);
        $this->assertNull($result);
        $this->assertEquals('App\Controller', $visitor->getNamespace());

        // Test with Class node
        $classNode = new Class_(
            new Identifier('TestController'),
            [
                'extends' => new Name('AbstractController'),
            ]
        );
        $result = $visitor->enterNode($classNode);
        $this->assertNull($result);
        $this->assertEquals('TestController', $visitor->getClassName());
        $this->assertEquals('AbstractController', $visitor->getParentClass());
        $this->assertTrue($visitor->isController());

        // Test with ClassMethod node
        $methodNode = new ClassMethod(
            new Identifier('index'),
            [
                'flags' => Modifiers::PUBLIC,
            ]
        );
        $result = $visitor->enterNode($methodNode);
        $this->assertNull($result);
        $this->assertContains('index', $visitor->getActions());

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

    private function parseCode(string $code): ControllerVisitor
    {
        $stmts = $this->parser->parse($code);
        $visitor = new ControllerVisitor();

        $this->traverser->addVisitor($visitor);
        if (null !== $stmts) {
            $this->traverser->traverse($stmts);
        }

        return $visitor;
    }
}
