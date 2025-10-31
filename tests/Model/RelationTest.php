<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Model\Relation;

/**
 * @internal
 */
#[CoversClass(Relation::class)]
class RelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        // No setup needed for this test
    }

    public function testConstructorWithRequiredParameters(): void
    {
        $from = 'component-a';
        $to = 'component-b';

        $relation = new Relation($from, $to);

        $this->assertSame($from, $relation->getFrom());
        $this->assertSame($to, $relation->getTo());
        $this->assertSame('uses', $relation->getType());
        $this->assertSame('', $relation->getDescription());
        $this->assertSame('', $relation->getTechnology());
    }

    public function testConstructorWithAllParameters(): void
    {
        $from = 'component-a';
        $to = 'component-b';
        $type = 'extends';
        $description = 'Component A extends Component B';
        $technology = 'PHP Inheritance';

        $relation = new Relation($from, $to, $type, $description, $technology);

        $this->assertSame($from, $relation->getFrom());
        $this->assertSame($to, $relation->getTo());
        $this->assertSame($type, $relation->getType());
        $this->assertSame($description, $relation->getDescription());
        $this->assertSame($technology, $relation->getTechnology());
    }

    public function testGetFrom(): void
    {
        $relation = new Relation('service-a', 'service-b');
        $this->assertSame('service-a', $relation->getFrom());
    }

    public function testGetTo(): void
    {
        $relation = new Relation('service-a', 'service-b');
        $this->assertSame('service-b', $relation->getTo());
    }

    public function testGetType(): void
    {
        $relation = new Relation('service-a', 'service-b', 'implements');
        $this->assertSame('implements', $relation->getType());
    }

    public function testGetTypeDefault(): void
    {
        $relation = new Relation('service-a', 'service-b');
        $this->assertSame('uses', $relation->getType());
    }

    public function testSetDescription(): void
    {
        $relation = new Relation('service-a', 'service-b');
        $description = 'Service A depends on Service B';

        $relation->setDescription($description);

        $this->assertSame($description, $relation->getDescription());
    }

    public function testSetDescriptionEmpty(): void
    {
        $relation = new Relation('service-a', 'service-b', 'uses', 'Initial description');

        $relation->setDescription('');

        $this->assertSame('', $relation->getDescription());
    }

    public function testSetTechnology(): void
    {
        $relation = new Relation('service-a', 'service-b');
        $technology = 'HTTP REST API';

        $relation->setTechnology($technology);

        $this->assertSame($technology, $relation->getTechnology());
    }

    public function testSetTechnologyEmpty(): void
    {
        $relation = new Relation('service-a', 'service-b', 'uses', '', 'Initial tech');

        $relation->setTechnology('');

        $this->assertSame('', $relation->getTechnology());
    }

    public function testEquals(): void
    {
        $relation1 = new Relation('service-a', 'service-b', 'uses');
        $relation2 = new Relation('service-a', 'service-b', 'uses');

        $this->assertTrue($relation1->equals($relation2));
        $this->assertTrue($relation2->equals($relation1));
    }

    public function testEqualsWithDifferentDescriptionsButSameCore(): void
    {
        $relation1 = new Relation('service-a', 'service-b', 'uses', 'Description 1', 'Tech 1');
        $relation2 = new Relation('service-a', 'service-b', 'uses', 'Description 2', 'Tech 2');

        $this->assertTrue($relation1->equals($relation2));
    }

    public function testNotEqualsDifferentFrom(): void
    {
        $relation1 = new Relation('service-a', 'service-b', 'uses');
        $relation2 = new Relation('service-x', 'service-b', 'uses');

        $this->assertFalse($relation1->equals($relation2));
    }

    public function testNotEqualsDifferentTo(): void
    {
        $relation1 = new Relation('service-a', 'service-b', 'uses');
        $relation2 = new Relation('service-a', 'service-x', 'uses');

        $this->assertFalse($relation1->equals($relation2));
    }

    public function testNotEqualsDifferentType(): void
    {
        $relation1 = new Relation('service-a', 'service-b', 'uses');
        $relation2 = new Relation('service-a', 'service-b', 'extends');

        $this->assertFalse($relation1->equals($relation2));
    }

    public function testNotEqualsMultipleDifferences(): void
    {
        $relation1 = new Relation('service-a', 'service-b', 'uses');
        $relation2 = new Relation('service-x', 'service-y', 'extends');

        $this->assertFalse($relation1->equals($relation2));
    }

    public function testEqualsWithDifferentRelationTypes(): void
    {
        $usesRelation = new Relation('component-a', 'component-b', 'uses');
        $extendsRelation = new Relation('component-a', 'component-b', 'extends');
        $implementsRelation = new Relation('component-a', 'component-b', 'implements');
        $composesRelation = new Relation('component-a', 'component-b', 'composes');

        $this->assertFalse($usesRelation->equals($extendsRelation));
        $this->assertFalse($usesRelation->equals($implementsRelation));
        $this->assertFalse($usesRelation->equals($composesRelation));
        $this->assertFalse($extendsRelation->equals($implementsRelation));
        $this->assertFalse($extendsRelation->equals($composesRelation));
        $this->assertFalse($implementsRelation->equals($composesRelation));
    }

    public function testFluentInterface(): void
    {
        $relation = new Relation('service-a', 'service-b');

        $relation->setDescription('Service A uses Service B for data processing');
        $relation->setTechnology('AMQP Message Queue');

        $this->assertSame('Service A uses Service B for data processing', $relation->getDescription());
        $this->assertSame('AMQP Message Queue', $relation->getTechnology());
    }

    public function testEqualsSymmetric(): void
    {
        $relation1 = new Relation('comp-1', 'comp-2', 'uses');
        $relation2 = new Relation('comp-1', 'comp-2', 'uses');

        $this->assertTrue($relation1->equals($relation2));
        $this->assertTrue($relation2->equals($relation1));
    }

    public function testEqualsReflexive(): void
    {
        $relation = new Relation('comp-1', 'comp-2', 'uses');

        $this->assertTrue($relation->equals($relation));
    }

    public function testEqualsWithEmptyStrings(): void
    {
        $relation1 = new Relation('', '', '');
        $relation2 = new Relation('', '', '');

        $this->assertTrue($relation1->equals($relation2));
    }

    public function testNotEqualsWithEmptyAndNonEmpty(): void
    {
        $relation1 = new Relation('', '', '');
        $relation2 = new Relation('comp-1', 'comp-2', 'uses');

        $this->assertFalse($relation1->equals($relation2));
    }

    public function testConstructorWithEmptyType(): void
    {
        $relation = new Relation('comp-1', 'comp-2', '');

        $this->assertSame('', $relation->getType());
    }
}
