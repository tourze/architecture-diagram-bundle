<?php

declare(strict_types=1);

namespace Tourze\ArchitectureDiagramBundle\Tests\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ArchitectureDiagramBundle\Model\Component;

/**
 * @internal
 */
#[CoversClass(Component::class)]
class ComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        parent::setUp();

        // No setup needed for this test
    }

    public function testConstructorWithRequiredParameters(): void
    {
        $id = 'test-id';
        $name = 'Test Component';
        $type = 'service';

        $component = new Component($id, $name, $type);

        $this->assertSame($id, $component->getId());
        $this->assertSame($name, $component->getName());
        $this->assertSame($type, $component->getType());
        $this->assertSame('', $component->getDescription());
        $this->assertSame('PHP', $component->getTechnology());
        $this->assertEmpty($component->getMetadata());
        $this->assertNull($component->getNamespace());
        $this->assertNull($component->getFilePath());
    }

    public function testConstructorWithAllParameters(): void
    {
        $id = 'test-id';
        $name = 'Test Component';
        $type = 'service';
        $description = 'A test service component';
        $technology = 'Symfony';
        $metadata = ['key1' => 'value1', 'key2' => 42];

        $component = new Component($id, $name, $type, $description, $technology, $metadata);

        $this->assertSame($id, $component->getId());
        $this->assertSame($name, $component->getName());
        $this->assertSame($type, $component->getType());
        $this->assertSame($description, $component->getDescription());
        $this->assertSame($technology, $component->getTechnology());
        $this->assertSame($metadata, $component->getMetadata());
    }

    public function testGetId(): void
    {
        $component = new Component('unique-id', 'Component Name', 'controller');
        $this->assertSame('unique-id', $component->getId());
    }

    public function testGetName(): void
    {
        $component = new Component('id', 'My Component', 'entity');
        $this->assertSame('My Component', $component->getName());
    }

    public function testGetType(): void
    {
        $component = new Component('id', 'Component', 'repository');
        $this->assertSame('repository', $component->getType());
    }

    public function testSetDescription(): void
    {
        $component = new Component('id', 'Component', 'service');
        $description = 'New description';

        $component->setDescription($description);

        $this->assertSame($description, $component->getDescription());
    }

    public function testSetDescriptionEmpty(): void
    {
        $component = new Component('id', 'Component', 'service', 'Initial description');

        $component->setDescription('');

        $this->assertSame('', $component->getDescription());
    }

    public function testSetTechnology(): void
    {
        $component = new Component('id', 'Component', 'service');
        $technology = 'Spring Boot';

        $component->setTechnology($technology);

        $this->assertSame($technology, $component->getTechnology());
    }

    public function testSetTechnologyEmpty(): void
    {
        $component = new Component('id', 'Component', 'service', '', 'PHP');

        $component->setTechnology('');

        $this->assertSame('', $component->getTechnology());
    }

    public function testSetMetadata(): void
    {
        $component = new Component('id', 'Component', 'service');
        $metadata = ['version' => '1.0', 'maintainer' => 'John Doe'];

        $component->setMetadata($metadata);

        $this->assertSame($metadata, $component->getMetadata());
    }

    public function testSetMetadataEmpty(): void
    {
        $component = new Component('id', 'Component', 'service', '', 'PHP', ['initial' => 'data']);

        $component->setMetadata([]);

        $this->assertEmpty($component->getMetadata());
    }

    public function testAddMetadata(): void
    {
        $component = new Component('id', 'Component', 'service');

        $component->addMetadata('key1', 'value1');
        $this->assertSame(['key1' => 'value1'], $component->getMetadata());

        $component->addMetadata('key2', 42);
        $this->assertSame(['key1' => 'value1', 'key2' => 42], $component->getMetadata());
    }

    public function testAddMetadataOverwrite(): void
    {
        $component = new Component('id', 'Component', 'service', '', 'PHP', ['key1' => 'old_value']);

        $component->addMetadata('key1', 'new_value');

        $this->assertSame(['key1' => 'new_value'], $component->getMetadata());
    }

    public function testAddMetadataWithDifferentTypes(): void
    {
        $component = new Component('id', 'Component', 'service');

        $component->addMetadata('string', 'text');
        $component->addMetadata('number', 123);
        $component->addMetadata('boolean', true);
        $component->addMetadata('array', ['nested' => 'value']);
        $component->addMetadata('null', null);

        $expected = [
            'string' => 'text',
            'number' => 123,
            'boolean' => true,
            'array' => ['nested' => 'value'],
            'null' => null,
        ];

        $this->assertSame($expected, $component->getMetadata());
    }

    public function testSetNamespace(): void
    {
        $component = new Component('id', 'Component', 'service');
        $namespace = 'App\Service\UserManagement';

        $component->setNamespace($namespace);

        $this->assertSame($namespace, $component->getNamespace());
    }

    public function testSetNamespaceNull(): void
    {
        $component = new Component('id', 'Component', 'service');
        $component->setNamespace('Initial\Namespace');

        $component->setNamespace(null);

        $this->assertNull($component->getNamespace());
    }

    public function testSetFilePath(): void
    {
        $component = new Component('id', 'Component', 'service');
        $filePath = '/path/to/Component.php';

        $component->setFilePath($filePath);

        $this->assertSame($filePath, $component->getFilePath());
    }

    public function testSetFilePathNull(): void
    {
        $component = new Component('id', 'Component', 'service');
        $component->setFilePath('/initial/path.php');

        $component->setFilePath(null);

        $this->assertNull($component->getFilePath());
    }

    public function testGetShortNameWithoutNamespace(): void
    {
        $component = new Component('id', 'UserService', 'service');

        $this->assertSame('UserService', $component->getShortName());
    }

    public function testGetShortNameWithNamespace(): void
    {
        $component = new Component('id', 'App\Service\UserManagement\UserService', 'service');
        $component->setNamespace('App\Service\UserManagement');

        $this->assertSame('UserService', $component->getShortName());
    }

    public function testGetShortNameWithNamespaceMultipleLevels(): void
    {
        $component = new Component('id', 'Very\Deep\Namespace\Path\MyClass', 'entity');
        $component->setNamespace('Very\Deep\Namespace\Path');

        $this->assertSame('MyClass', $component->getShortName());
    }

    public function testGetShortNameWithNamespaceSingleClass(): void
    {
        $component = new Component('id', 'SimpleClass', 'model');
        $component->setNamespace('SimpleNamespace');

        $this->assertSame('SimpleClass', $component->getShortName());
    }

    public function testGetShortNameWithEmptyNamespaceReturnsShortName(): void
    {
        $component = new Component('id', 'Full\Class\Name', 'service');
        $component->setNamespace('');

        $this->assertSame('Name', $component->getShortName());
    }

    public function testFluentInterface(): void
    {
        $component = new Component('id', 'Component', 'service');

        $component->setDescription('Test description');
        $component->setTechnology('Laravel');
        $component->setMetadata(['version' => '2.0']);
        $component->addMetadata('author', 'Jane Doe');
        $component->setNamespace('App\Components');
        $component->setFilePath('/app/Components/Component.php');

        $this->assertSame('Test description', $component->getDescription());
        $this->assertSame('Laravel', $component->getTechnology());
        $this->assertSame(['version' => '2.0', 'author' => 'Jane Doe'], $component->getMetadata());
        $this->assertSame('App\Components', $component->getNamespace());
        $this->assertSame('/app/Components/Component.php', $component->getFilePath());
    }
}
