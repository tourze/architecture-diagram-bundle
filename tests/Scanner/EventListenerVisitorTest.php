<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Scanner;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Scanner\EventListenerVisitor;

/**
 * @internal
 */
#[CoversClass(EventListenerVisitor::class)]
class EventListenerVisitorTest extends TestCase
{
    private EventListenerVisitor $visitor;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        $this->visitor = new EventListenerVisitor();
    }

    public function testEnterNodeWithEventListenerAttribute(): void
    {
        $eventValue = new String_('kernel.request');
        $eventArg = new Arg($eventValue, false, false, [], new Identifier('event'));

        $attribute = new Attribute(
            new Name\FullyQualified('Symfony\Component\EventDispatcher\Attribute\AsEventListener'),
            [$eventArg]
        );
        $attributeGroup = new AttributeGroup([$attribute]);

        $classNode = new Class_('TestEventListener');
        $classNode->attrGroups = [$attributeGroup];

        $result = $this->visitor->enterNode($classNode);

        $this->assertNull($result);
        $events = $this->visitor->getEvents();
        $this->assertCount(1, $events);
        $this->assertContains('kernel.request', $events);
    }

    public function testEnterNodeWithEventListenerMethod(): void
    {
        $eventValue = new String_('kernel.response');
        $eventArg = new Arg($eventValue, false, false, [], new Identifier('event'));

        $attribute = new Attribute(
            new Name\FullyQualified('Symfony\Component\EventDispatcher\Attribute\AsEventListener'),
            [$eventArg]
        );
        $attributeGroup = new AttributeGroup([$attribute]);

        $methodNode = new ClassMethod('onKernelResponse');
        $methodNode->attrGroups = [$attributeGroup];

        $this->visitor->enterNode($methodNode);

        $events = $this->visitor->getEvents();
        $this->assertCount(1, $events);
        $this->assertContains('kernel.response', $events);
        $methods = $this->visitor->getMethods();
        $this->assertContains('onKernelResponse', $methods);
    }

    public function testEnterNodeWithMultipleEventListeners(): void
    {
        $event1 = new String_('kernel.request');
        $eventArg1 = new Arg($event1, false, false, [], new Identifier('event'));
        $attribute1 = new Attribute(
            new Name\FullyQualified('Symfony\Component\EventDispatcher\Attribute\AsEventListener'),
            [$eventArg1]
        );
        $attributeGroup1 = new AttributeGroup([$attribute1]);

        $event2 = new String_('kernel.response');
        $eventArg2 = new Arg($event2, false, false, [], new Identifier('event'));
        $attribute2 = new Attribute(
            new Name\FullyQualified('Symfony\Component\EventDispatcher\Attribute\AsEventListener'),
            [$eventArg2]
        );
        $attributeGroup2 = new AttributeGroup([$attribute2]);

        $classNode = new Class_('TestEventListener');
        $classNode->attrGroups = [$attributeGroup1, $attributeGroup2];

        $this->visitor->enterNode($classNode);

        $events = $this->visitor->getEvents();
        $this->assertCount(2, $events);
        $this->assertContains('kernel.request', $events);
        $this->assertContains('kernel.response', $events);
    }

    public function testEnterNodeWithoutEventListenerAttribute(): void
    {
        $classNode = new Class_('RegularClass');
        $classNode->attrGroups = [];

        $result = $this->visitor->enterNode($classNode);

        $this->assertNull($result);
        $events = $this->visitor->getEvents();
        $this->assertEmpty($events);
    }

    public function testGetEventsReturnsEmpty(): void
    {
        $events = $this->visitor->getEvents();
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    public function testEnterNodeWithDifferentAttribute(): void
    {
        $attribute = new Attribute(new Name\FullyQualified('Some\Other\Attribute'));
        $attributeGroup = new AttributeGroup([$attribute]);

        $classNode = new Class_('TestClass');
        $classNode->attrGroups = [$attributeGroup];

        $this->visitor->enterNode($classNode);

        $events = $this->visitor->getEvents();
        $this->assertEmpty($events);
    }

    public function testEnterNodeWithNamespace(): void
    {
        $namespaceName = new Name\FullyQualified('App\EventListener');
        $namespaceNode = new Node\Stmt\Namespace_($namespaceName);

        $this->visitor->enterNode($namespaceNode);

        $this->assertSame('App\EventListener', $this->visitor->getNamespace());
    }

    public function testIsSubscriberWithEventSubscriberInterface(): void
    {
        $interfaceName = new Name\FullyQualified('Symfony\Component\EventDispatcher\EventSubscriberInterface');

        $classNode = new Class_('TestSubscriber');
        $classNode->implements = [$interfaceName];

        $this->visitor->enterNode($classNode);

        $this->assertTrue($this->visitor->isSubscriber());
        $this->assertSame('TestSubscriber', $this->visitor->getClassName());
    }

    public function testGetSubscribedEventsMethod(): void
    {
        $eventKey = new String_('kernel.request');
        $eventValue = new String_('onKernelRequest');
        $arrayItem = new ArrayItem($eventValue, $eventKey);
        $arrayExpr = new Array_([$arrayItem]);
        $returnStmt = new Node\Stmt\Return_($arrayExpr);

        $methodNode = new ClassMethod('getSubscribedEvents');
        $methodNode->stmts = [$returnStmt];

        $this->visitor->enterNode($methodNode);

        $methods = $this->visitor->getMethods();
        $this->assertContains('getSubscribedEvents', $methods);
    }
}
