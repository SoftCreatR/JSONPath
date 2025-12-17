<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use ArrayAccess;
use ArrayIterator;
use Flow\JSONPath\AccessHelper;
use Flow\JSONPath\JSONPathException;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Traversable;

#[CoversClass(AccessHelper::class)]
class AccessHelperTest extends TestCase
{
    public function testKeyExistsRespectsMagicGet(): void
    {
        $magic = new class {
            public function __get(string $name): string
            {
                return "magic-{$name}";
            }
        };

        self::assertTrue(AccessHelper::keyExists($magic, 'foo', true));
        self::assertFalse(AccessHelper::keyExists($magic, 'foo'));
    }

    public function testKeyExistsSupportsArrayAccessAndNegativeIndex(): void
    {
        $arrayAccess = new class implements ArrayAccess {
            /** @var array<string, string> */
            private array $store = ['bar' => 'baz'];

            public function offsetExists($offset): bool
            {
                return \array_key_exists($offset, $this->store);
            }

            public function offsetGet($offset): mixed
            {
                return $this->store[$offset];
            }

            public function offsetSet($offset, $value): void
            {
                $this->store[$offset] = $value;
            }

            public function offsetUnset($offset): void
            {
                unset($this->store[$offset]);
            }
        };

        self::assertTrue(AccessHelper::keyExists($arrayAccess, 'bar'));
        self::assertTrue(AccessHelper::keyExists([1 => 'foo'], -1));
    }

    public function testGetValueCoversMagicArrayAndArrayAccess(): void
    {
        $magic = new class {
            public function __get(string $name): string
            {
                return "magic-{$name}";
            }
        };

        $arrayAccess = new class implements ArrayAccess {
            /** @var array<string, string> */
            private array $store = ['bar' => 'baz'];

            public function offsetExists($offset): bool
            {
                return \array_key_exists($offset, $this->store);
            }

            public function offsetGet($offset): mixed
            {
                return $this->store[$offset];
            }

            public function offsetSet($offset, $value): void
            {
                $this->store[$offset] = $value;
            }

            public function offsetUnset($offset): void
            {
                unset($this->store[$offset]);
            }
        };

        self::assertSame('magic-foo', AccessHelper::getValue($magic, 'foo', true));
        self::assertSame('baz', AccessHelper::getValue($arrayAccess, 'bar'));
        self::assertSame('b', AccessHelper::getValue(['a', 'b'], -1));
        self::assertNull(AccessHelper::getValue(['a'], 'missing'));
        $plainObject = (object)['prop' => 'value'];
        self::assertSame('value', AccessHelper::getValue($plainObject, 'prop'));
    }

    public function testGetValueByIndexSupportsTraversableAndNegativeOffset(): void
    {
        $iterable = new class implements IteratorAggregate {
            public function getIterator(): Traversable
            {
                return new ArrayIterator(['first', 'second', 'third']);
            }
        };

        self::assertSame('third', AccessHelper::getValue($iterable, -1));
        self::assertSame('second', AccessHelper::getValue($iterable, 1));
    }

    public function testGetValueNullCases(): void
    {
        self::assertNull(AccessHelper::getValue('scalar', 'foo'));
        self::assertNull(AccessHelper::getValue('scalar', 5));

        $iterable = new ArrayIterator(['only']);
        self::assertNull(AccessHelper::getValue($iterable, 5));
    }

    public function testArrayValuesThrowsOnInvalidType(): void
    {
        $this->expectException(JSONPathException::class);
        AccessHelper::arrayValues('not-an-array');
    }

    /**
     * @throws JSONPathException
     */
    public function testArrayValuesCastsObject(): void
    {
        $obj = (object)['a' => 1, 'b' => 2];
        self::assertSame([1, 2], AccessHelper::arrayValues($obj));
    }

    public function testGetValueByIndexReturnsNullWhenOutOfRange(): void
    {
        $iterable = (static function () {
            yield 'first';
        })();

        self::assertNull(AccessHelper::getValue($iterable, 10));
    }

    public function testKeyExistsAndCollectionHelpers(): void
    {
        $object = (object)['a' => 1];
        self::assertTrue(AccessHelper::keyExists($object, 'a'));
        self::assertFalse(AccessHelper::keyExists('scalar', 'a'));
        self::assertSame(['a'], AccessHelper::collectionKeys($object));
        self::assertFalse(AccessHelper::isCollectionType('scalar'));
    }

    public function testSetAndUnsetValueAcrossTypes(): void
    {
        $object = (object)['a' => 1];
        AccessHelper::setValue($object, 'b', 2);
        self::assertSame(2, $object->b);

        $arrayAccess = new class implements ArrayAccess {
            /** @var array<string, string> */
            public array $store = [];

            public function offsetExists($offset): bool
            {
                return \array_key_exists($offset, $this->store);
            }

            public function offsetGet($offset): mixed
            {
                return $this->store[$offset];
            }

            public function offsetSet($offset, $value): void
            {
                $this->store[$offset] = $value;
            }

            public function offsetUnset($offset): void
            {
                unset($this->store[$offset]);
            }
        };

        AccessHelper::setValue($arrayAccess, 'k', 'v');
        self::assertSame('v', $arrayAccess->store['k']);
        AccessHelper::unsetValue($arrayAccess, 'k');
        self::assertArrayNotHasKey('k', $arrayAccess->store);

        $array = ['x' => 1];
        AccessHelper::unsetValue($array, 'x');
        self::assertArrayNotHasKey('x', $array);

        $obj = (object)['x' => 1];
        AccessHelper::unsetValue($obj, 'x');
        self::assertFalse(\property_exists($obj, 'x'));

        $arrayAccess->offsetUnset('missing');
    }
}
