<?php

namespace XRayTests;

use BadMethodCallException;
use XRay\XRay;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class XRayTest extends TestCase
{
    public const StringArg = "hitch-hiker";

    public const IntArg = 42;

    private XRay $m_xRay;

    private CallTracker $m_tracker;

    private CallTracker $m_baseTracker;

    public function setUp(): void
    {
        $this->m_tracker = new CallTracker();
        $this->m_baseTracker = new CallTracker();

        $testObject = new class ($this->m_tracker, $this->m_baseTracker) extends TestBaseClass
        {
            private static CallTracker $m_tracker;
            private static string $m_privateStaticProperty = "private-static-property";
            public static string $publicStaticProperty = "public-static-property";

            private string $m_privateProperty = "private-property";
            public string $publicProperty = "public-property";

            private array $m_magicProperties = [
                "m_magicProperty" => "magic-property",
            ];

            public function __construct(CallTracker $tracker, CallTracker $baseTracker)
            {
                parent::__construct($baseTracker);
                self::$m_tracker = $tracker;
            }

            private static function privateStaticMethod(): string
            {
                XRayTest::fail("privateStaticMethod() should not be called.");
            }

            public static function publicStaticMethod(): string
            {
                XRayTest::fail("publicStaticMethod() should not be called.");
            }

            private static function privateStaticMethodWithArgs(string $arg1, int $arg2): string
            {
                XRayTest::fail("privateStaticMethodWithArgs() should not be called.");
            }

            public static function publicStaticMethodWithArgs(string $arg1, int $arg2): string
            {
                XRayTest::fail("publicStaticMethodWithArgs() should not be called.");
            }

            private function privateMethod(): string
            {
                self::$m_tracker->increment();
                return "private-method";
            }

            public function publicMethod(): string
            {
                self::$m_tracker->increment();
                return "public-method";
            }

            private function privateMethodWithArgs(string $arg1, int $arg2): string
            {
                self::$m_tracker->increment();
                XRayTest::assertEquals(XRayTest::StringArg, $arg1);
                XRayTest::assertEquals(XRayTest::IntArg, $arg2);
                return "{$arg1} {$arg2}";
            }

            public function publicMethodWithArgs(string $arg1, int $arg2): string
            {
                self::$m_tracker->increment();
                XRayTest::assertEquals(XRayTest::StringArg, $arg1);
                XRayTest::assertEquals(XRayTest::IntArg, $arg2);
                return "{$arg1} {$arg2}";
            }

            public function __get(string $property): string
            {
                if ("m_magicProperty" === $property) {
                    return $this->m_magicProperties[$property];
                }

                return parent::__get($property);
            }

            public function __set(string $property, string $value): void
            {
                if ("m_magicProperty" === $property) {
                    $this->m_magicProperties[$property] = $value;
                    return;
                }

                parent::__set($property, $value);
            }

            public function __call(string $method, array $args): string
            {
                if ("magicMethod" === $method) {
                    self::$m_tracker->increment();
                    return "magic-method";
                }

                if ("magicMethodWithArgs" === $method) {
                    self::$m_tracker->increment();
                    return "{$args[0]} {$args[1]}";
                }

                return parent::__call($method, $args);
            }

            public static function __callStatic(string $method, array $args): void
            {
                XRayTest::fail("__callStatic() should not be called.");
            }
        };

        $this->m_xRay = new XRay($testObject);
    }

    /** Ensure we can access a public property defined on the class itself. */
    public function testPublicProperty(): void
    {
        self::assertTrue($this->m_xRay->isPublicProperty("publicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("publicProperty"));
        self::assertEquals("public-property", $this->m_xRay->publicProperty);
    }

    /** Ensure we can access a public property on a base class. */
    public function testBasePublicProperty(): void
    {
        self::assertTrue($this->m_xRay->isPublicProperty("basePublicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("basePublicProperty"));
        self::assertEquals("base-public-property", $this->m_xRay->basePublicProperty);
    }

    /** Ensure we can set a public property defined on the class itself. */
    public function testSetPublicProperty(): void
    {
        self::assertTrue($this->m_xRay->isPublicProperty("publicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("publicProperty"));
        self::assertEquals("public-property", $this->m_xRay->publicProperty);
        $this->m_xRay->publicProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->publicProperty);
    }

    /** Ensure we can set a public property on a base class. */
    public function testSetBasePublicProperty(): void
    {
        self::assertTrue($this->m_xRay->isPublicProperty("basePublicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("basePublicProperty"));
        self::assertEquals("base-public-property", $this->m_xRay->basePublicProperty);
        $this->m_xRay->basePublicProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->basePublicProperty);
    }

    /** Ensure we can access a private property defined on the class itself. */
    public function testXRayedProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_privateProperty"));
        self::assertTrue($this->m_xRay->isXRayedProperty("m_privateProperty"));
        self::assertEquals("private-property", $this->m_xRay->m_privateProperty);
    }

    /** Ensure we can access a private property on a base class. */
    public function testXRayedBaseProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_basePrivateProperty"));
        self::assertTrue($this->m_xRay->isXRayedProperty("m_basePrivateProperty"));
        self::assertEquals("base-private-property", $this->m_xRay->m_basePrivateProperty);
    }

    /** Ensure we can set a private property defined on the class itself. */
    public function testSetXRayedProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_privateProperty"));
        self::assertTrue($this->m_xRay->isXRayedProperty("m_privateProperty"));
        self::assertEquals("private-property", $this->m_xRay->m_privateProperty);
        $this->m_xRay->m_privateProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->m_privateProperty);
    }

    /** Ensure we can set a private property on a base class. */
    public function testSetXRayedBaseProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_basePrivateProperty"));
        self::assertTrue($this->m_xRay->isXRayedProperty("m_basePrivateProperty"));
        self::assertEquals("base-private-property", $this->m_xRay->m_basePrivateProperty);
        $this->m_xRay->m_basePrivateProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->m_basePrivateProperty);
    }

    /** Ensure we can access a magic property on the class itself. */
    public function testMagicProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_magicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_magicProperty"));
        self::assertEquals("magic-property", $this->m_xRay->m_magicProperty);
    }

    /** Ensure we can access a magic property on a base class. */
    public function testBaseMagicProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_baseMagicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_baseMagicProperty"));
        self::assertEquals("base-magic-property", $this->m_xRay->m_baseMagicProperty);
    }

    /** Ensure we can set a magic property on the class itself. */
    public function testSetMagicProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_magicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_magicProperty"));
        self::assertEquals("magic-property", $this->m_xRay->m_magicProperty);
        $this->m_xRay->m_magicProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->m_magicProperty);
    }

    /** Ensure we can set a magic property on a base class. */
    public function testSetBaseMagicProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_baseMagicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_baseMagicProperty"));
        self::assertEquals("base-magic-property", $this->m_xRay->m_baseMagicProperty);
        $this->m_xRay->m_baseMagicProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->m_baseMagicProperty);
    }

    /** Ensure public static properties defined on the class itself aren't accessible. */
    public function testPublicStaticProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty("publicStaticProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("publicStaticProperty"));
        self::assertEquals("", $this->m_xRay->publicStaticProperty);
    }

    /** Ensure public static properties on a base class aren't accessible. */
    public function testBasePublicStaticProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty("basePublicStaticProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("basePublicStaticProperty"));
        self::assertEquals("", $this->m_xRay->publicStaticProperty);
    }

    /** Ensure private static properties defined on the class itself aren't accessible. */
    public function testPrivateStaticProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty("m_privateStaticProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_privateStaticProperty"));
        self::assertEquals("", $this->m_xRay->m_privateStaticProperty);
    }

    /** Ensure private static properties on a base class aren't accessible. */
    public function testBasePrivateStaticProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty("m_basePrivateStaticProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_basePrivateStaticProperty"));
        self::assertEquals("", $this->m_xRay->m_basePrivateStaticProperty);
    }

    /** Ensure non-existent properties error. */
    public function testNonExistentProperty(): void
    {
        // have to test on class with no magic __get(), otherwise __get() will be called with the property name
        $object = new class {
        };

        $xRay = new XRay($object);
        $className = $object::class;
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Property \"nonExistentProperty\" does not exist on object of class \"{$className}\"");
        self::assertFalse($xRay->isPublicProperty("nonExistentProperty"));
        self::assertFalse($xRay->isXRayedProperty("nonExistentProperty"));
        $ignored = $xRay->nonExistentProperty;
    }

    /** Ensure non-existent properties error. */
    public function testSetNonExistentProperty(): void
    {
        // have to test on class with no magic __get(), otherwise __get() will be called with the property name
        $object = new class {
        };

        $xRay = new XRay($object);
        $className = $object::class;
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Property \"nonExistentProperty\" does not exist on object of class \"{$className}\"");
        self::assertFalse($xRay->isPublicProperty("nonExistentProperty"));
        self::assertFalse($xRay->isXRayedProperty("nonExistentProperty"));
        $xRay->nonExistentProperty = "should-not-get-this";
    }

    /** Ensure empty property names error. */
    public function testEmptyProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty(""));
        self::assertFalse($this->m_xRay->isXRayedProperty(""));
        self::assertEquals("", $this->m_xRay->{""});
    }

    /** Ensure we can call a public method defined on the class itself. */
    public function testPublicMethod(): void
    {
        $this->m_tracker->reset();
        self::assertTrue($this->m_xRay->isPublicMethod("publicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicMethod"));
        self::assertEquals("public-method", $this->m_xRay->publicMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    /** Ensure we can call a public method inherited from a base class. */
    public function testBasePublicMethod(): void
    {
        $this->m_baseTracker->reset();
        self::assertTrue($this->m_xRay->isPublicMethod("basePublicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("basePublicMethod"));
        self::assertEquals("base-public-method", $this->m_xRay->basePublicMethod());
        self::assertEquals(1, $this->m_baseTracker->callCount());
    }

    /** Ensure we can call a public method defined on the class itself with arguments. */
    public function testPublicMethodWithArgs(): void
    {
        self::assertTrue($this->m_xRay->isPublicMethod("publicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicMethodWithArgs"));
        $this->m_tracker->reset();
        $expected = self::StringArg . " " . self::IntArg;
        self::assertEquals($expected, $this->m_xRay->publicMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    /** Ensure we can call a public method with arguments inherited from a base class. */
    public function testBasePublicMethodWithArgs(): void
    {
        self::assertTrue($this->m_xRay->isPublicMethod("basePublicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("basePublicMethodWithArgs"));
        $this->m_baseTracker->reset();
        $expected = TestBaseClass::BaseStringArg . " " . TestBaseClass::BaseIntArg;
        self::assertEquals($expected, $this->m_xRay->basePublicMethodWithArgs(TestBaseClass::BaseStringArg, TestBaseClass::BaseIntArg));
        self::assertEquals(1, $this->m_baseTracker->callCount());
    }

    /** Ensure we can call a private method on the class itself. */
    public function testXRayedMethod(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("privateMethod"));
        self::assertTrue($this->m_xRay->isXRayedMethod("privateMethod"));
        self::assertEquals("private-method", $this->m_xRay->privateMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    /** Ensure we can call a private method inherited from a base class. */
    public function testXRayedBaseMethod(): void
    {
        $this->m_baseTracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("basePrivateMethod"));
        self::assertTrue($this->m_xRay->isXRayedMethod("basePrivateMethod"));
        self::assertEquals("base-private-method", $this->m_xRay->basePrivateMethod());
        self::assertEquals(1, $this->m_baseTracker->callCount());
    }

    /** Ensure we can call a private method defined on the class itself with arguments. */
    public function testXRayedMethodWithArgs(): void
    {
        self::assertFalse($this->m_xRay->isPublicMethod("privateMethodWithArgs"));
        self::assertTrue($this->m_xRay->isXRayedMethod("privateMethodWithArgs"));
        $this->m_tracker->reset();
        $expected = self::StringArg . " " . self::IntArg;
        self::assertEquals($expected, $this->m_xRay->privateMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    /** Ensure we can call a private method with arguments inherited from a base class. */
    public function testXRayedBaseMethodWithArgs(): void
    {
        self::assertFalse($this->m_xRay->isPublicMethod("basePrivateMethodWithArgs"));
        self::assertTrue($this->m_xRay->isXRayedMethod("basePrivateMethodWithArgs"));
        $this->m_baseTracker->reset();
        $expected = "private " . TestBaseClass::BaseStringArg . " " . TestBaseClass::BaseIntArg;
        self::assertEquals($expected, $this->m_xRay->basePrivateMethodWithArgs(TestBaseClass::BaseStringArg, TestBaseClass::BaseIntArg));
        self::assertEquals(1, $this->m_baseTracker->callCount());
    }

    /** Ensure we can call a magic method on the class itself. */
    public function testMagicMethod(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("magicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("magicMethod"));
        self::assertEquals("magic-method", $this->m_xRay->magicMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    /** Ensure we can call a magic method inherited from a base class. */
    public function testBaseMagicMethod(): void
    {
        $this->m_baseTracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("baseMagicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("baseMagicMethod"));
        self::assertEquals("base-magic-method", $this->m_xRay->baseMagicMethod());
        self::assertEquals(1, $this->m_baseTracker->callCount());
    }

    /** Ensure we can call a magic method defined on the class itself with arguments. */
    public function testMagicMethodWithArgs(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("magicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("magicMethodWithArgs"));
        self::assertEquals(self::StringArg . " " . self::IntArg, $this->m_xRay->magicMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    /** Ensure we can call a magic method with arguments inherited from a base class. */
    public function testBaseMagicMethodWithArgs(): void
    {
        $this->m_baseTracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("baseMagicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("baseMagicMethodWithArgs"));
        self::assertEquals(TestBaseClass::BaseStringArg . " " . TestBaseClass::BaseIntArg, $this->m_xRay->baseMagicMethodWithArgs(TestBaseClass::BaseStringArg, TestBaseClass::BaseIntArg));
        self::assertEquals(1, $this->m_baseTracker->callCount());
    }

    /** Ensure we can't call a public static method defined on the class itself. */
    public function testPublicStaticMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("publicStaticMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicStaticMethod"));
        self::assertEquals("", $this->m_xRay->publicStaticMethod());
    }

    /** Ensure we can't call a public static method from a base class. */
    public function testBasePublicStaticMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("basePublicStaticMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("basePublicStaticMethod"));
        self::assertEquals("", $this->m_xRay->basePublicStaticMethod());
    }

    /** Ensure we can't call a public static method defined on the class itself with arguments. */
    public function testPublicStaticMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("publicStaticMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicStaticMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->publicStaticMethodWithArgs(self::StringArg, self::IntArg));
    }

    /** Ensure we can't call a public static method with arguments from a base class. */
    public function testBasePublicStaticMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("basePublicStaticMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("basePublicStaticMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->basePublicStaticMethodWithArgs(TestBaseClass::BaseStringArg, TestBaseClass::BaseIntArg));
    }

    /** Ensure we can't call a private static method defined on the class itself. */
    public function testPrivateStaticMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("privateStaticMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("privateStaticMethod"));
        self::assertEquals("", $this->m_xRay->privateStaticMethod());
    }

    /** Ensure we can't call a private static method from a base class. */
    public function testBasePrivateStaticMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("basePrivateStaticMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("basePrivateStaticMethod"));
        self::assertEquals("", $this->m_xRay->basePrivateStaticMethod());
    }

    /** Ensure we can't call a private static method defined on the class itself with arguments. */
    public function testPrivateStaticMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("privateStaticMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("privateStaticMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->privateStaticMethodWithArgs());
    }

    /** Ensure we can't call a private static method with arguments from a base class. */
    public function testBasePrivateStaticMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("basePrivateStaticMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("basePrivateStaticMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->basePrivateStaticMethodWithArgs());
    }

    /** Ensure we can't call a maagic static method odefined n the class itself. */
    public function testStaticMagicMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("staticMagicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("staticMagicMethod"));
        self::assertEquals("", $this->m_xRay->staticMagicMethod());
    }

    /** Ensure we can't call a magic static method from a base class. */
    public function testBaseStaticMagicMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("baseStaticMagicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("baseStaticMagicMethod"));
        self::assertEquals("", $this->m_xRay->baseStaticMagicMethod());
    }

    /** Ensure we can't call a magic static method defined on the class itself with arguments. */
    public function testStaticMagicMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("staticMagicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("staticMagicMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->staticMagicMethodWithArgs(self::StringArg, self::IntArg));
    }

    /** Ensure we can't call a magic static method with arguments from a base class. */
    public function testBaseStaticMagicMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("baseStaticMagicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("baseStaticMagicMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->baseStaticMagicMethodWithArgs(self::StringArg, self::IntArg));
    }

    /** Ensure we can't call methods that don't exist. */
    public function testNonExistentMethod(): void
    {
        // have to test on class with no magic __call(), otherwise __call() will be called with the method name
        $object = new class {
        };

        $className = $object::class;
        $xRay = new XRay($object);
        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage("Method \"nonExistentMethod\" does not exist on object of class \"{$className}\"");
        self::assertFalse($xRay->isPublicMethod("nonExistentMethod"));
        self::assertFalse($xRay->isXRayedMethod("nonExistentMethod"));
        $xRay->nonExistentMethod();
    }

    /** Ensure we get a BadMethodCall excception when invoke() throws a ReflectionException. */
    public function testInvokeThrows(): void
    {
        $object = new class {
            private function mockInvokeThrowsReflectionException(): void
            {
                throw new ReflectionException("");
            }
        };

        $className = $object::class;
        $xRay = new XRay($object);

        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage("Method \"mockInvokeThrowsReflectionException\" could not be invoked on instance of class \"{$className}\"");
        self::assertFalse($xRay->isPublicMethod("mockInvokeThrowsReflectionException"));
        self::assertTrue($xRay->isXRayedMethod("mockInvokeThrowsReflectionException"));
        $xRay->mockInvokeThrowsReflectionException();
    }

    /** Ensure we can't call methods with an empty name. */
    public function testEmptyMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod(""));
        self::assertFalse($this->m_xRay->isXRayedMethod(""));
        self::assertEquals("", $this->m_xRay->{""}());
    }
}
