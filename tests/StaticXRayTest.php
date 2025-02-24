<?php

declare(strict_types=1);

namespace Equit\XRayTests;

use BadMethodCallException;
use Equit\XRay\Exceptions\XRayException;
use Equit\XRay\StaticXRay;
use PHPUnit\Framework\TestCase;
use LogicException;
use ReflectionException;

final class StaticXRayTest extends TestCase
{
    public const StringArg = "hitch-hiker";
    public const IntArg = 42;

    private StaticXRay $m_xRay;

    private CallTracker $m_tracker;

    public function setUp(): void
    {
        $this->m_tracker = new CallTracker();

        $testObject = new class ($this->m_tracker)
        {
            private static CallTracker $callTracker;
            private static string $privateStaticProperty = "private-static-property";
            public static string $publicStaticProperty = "public-static-property";

            private string $privateProperty = "private-property";
            public string $publicProperty = "public-property";

            public function __construct(CallTracker $tracker)
            {
                self::$callTracker = $tracker;
                // we reset these every time the object is created so that they don't persist between tests
                self::$privateStaticProperty = "private-static-property";
                self::$publicStaticProperty = "public-static-property";
            }

            private static function privateStaticMethod(): string
            {
                self::$callTracker->increment();
                return "private-static-method";
            }

            public static function publicStaticMethod(): string
            {
                self::$callTracker->increment();
                return "public-static-method";
            }

            private static function privateStaticMethodWithArgs(string $arg1, int $arg2): string
            {
                self::$callTracker->increment();
                StaticXRayTest::assertEquals(StaticXRayTest::StringArg, $arg1);
                StaticXRayTest::assertEquals(StaticXRayTest::IntArg, $arg2);
                return "{$arg1} {$arg2}";
            }

            public static function publicStaticMethodWithArgs(string $arg1, int $arg2): string
            {
                self::$callTracker->increment();
                StaticXRayTest::assertEquals(StaticXRayTest::StringArg, $arg1);
                StaticXRayTest::assertEquals(StaticXRayTest::IntArg, $arg2);
                return "{$arg1} {$arg2}";
            }

            private static function privateStaticMethodThatThrows(): void
            {
                throw new ReflectionException("Test exception.");
            }

            private function privateMethod(): string
            {
                XRayTest::fail("privateMethod() should not be called.");
            }

            public function publicMethod(): string
            {
                XRayTest::fail("publicMethod() should not be called.");
            }

            private function privateMethodWithArgs(string $arg1, int $arg2): string
            {
                XRayTest::fail("privateMethodWithArgs() should not be called.");
            }

            public function publicMethodWithArgs(string $arg1, int $arg2): string
            {
                XRayTest::fail("publicMethodWithArgs() should not be called.");
            }

            public function __call(string $method, array $args): string
            {
                XRayTest::fail("__call() should not be called.");
            }

            public static function __callStatic(string $method, array $args): string
            {
                if ("staticMagicMethod" === $method) {
                    self::$callTracker->increment();
                    return "magic-method";
                }

                if ("staticMagicMethodWithArgs" === $method) {
                    self::$callTracker->increment();
                    return "{$args[0]} {$args[1]}";
                }

                throw new BadMethodCallException("Non-existent static magic method.");
            }
        };

        $this->m_xRay = new StaticXRay(get_class($testObject));
    }

    public function testConstructorThrows(): void
    {
        self::expectException(XRayException::class);
        self::expectExceptionMessage("The class 'non-existent-class' does not exist");
        $xRay = new StaticXRay("non-existent-class");
    }

    public function testPublicStaticProperty(): void
    {
        self::assertTrue($this->m_xRay->isPublicStaticProperty("publicStaticProperty"));
        self::assertFalse($this->m_xRay->isXRayedStaticProperty("publicStaticProperty"));
        self::assertEquals("public-static-property", $this->m_xRay->publicStaticProperty);
    }

    public function testXRayedStaticProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicStaticProperty("privateStaticProperty"));
        self::assertTrue($this->m_xRay->isXRayedStaticProperty("privateStaticProperty"));
        self::assertEquals("private-static-property", $this->m_xRay->privateStaticProperty);
    }

    public function testPublicProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicStaticProperty("publicProperty"));
        self::assertFalse($this->m_xRay->isXRayedStaticProperty("publicProperty"));
        self::assertEquals("", $this->m_xRay->publicProperty);
    }

    public function testPrivateProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicStaticProperty("privateProperty"));
        self::assertFalse($this->m_xRay->isXRayedStaticProperty("privateProperty"));
        self::assertEquals("", $this->m_xRay->privateProperty);
    }

    public function testNonExistentProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicStaticProperty("nonExistentProperty"));
        self::assertFalse($this->m_xRay->isXRayedStaticProperty("nonExistentProperty"));
        self::assertEquals("", $this->m_xRay->nonExistentProperty);
    }

    public function testEmptyProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicStaticProperty(""));
        self::assertFalse($this->m_xRay->isXRayedStaticProperty(""));
        self::assertEquals("", $this->m_xRay->{""});
    }

    public function testSetPublicStaticProperty(): void
    {
        if ("other-value" === $this->m_xRay->className()::$publicStaticProperty) {
            self::markTestSkipped("The public static property already has the value we want to change it to.");
        }

        $this->m_xRay->publicStaticProperty = "other-value";
        self::assertEquals("other-value", $this->m_xRay->className()::$publicStaticProperty);
    }

    public function testSetPrivateStaticProperty(): void
    {
        if ("other-value" === $this->m_xRay->privateStaticProperty) {
            self::markTestSkipped("The private static property already has the value we want to change it to.");
        }

        $this->m_xRay->privateStaticProperty = "other-value";
        self::assertEquals("other-value", $this->m_xRay->privateStaticProperty);
    }

    public function testSetNonExistentProperty(): void
    {
        if ($this->m_xRay->isXRayedStaticProperty("nonExistentProperty") || $this->m_xRay->isPublicStaticProperty("nonExistentProperty")) {
            self::markTestSkipped("The property 'nonExistentProperty' must not exist for this test to be valid.");
        }

        self::expectException(LogicException::class);
        self::expectExceptionMessage("Static property 'nonExistentProperty' does not exist on object of class '" . $this->m_xRay->className() . "'");
        $this->m_xRay->nonExistentProperty = "other-value";
    }

    public function testPublicStaticMethod(): void
    {
        $this->m_tracker->reset();
        self::assertTrue($this->m_xRay->isPublicStaticMethod("publicStaticMethod"));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod("publicStaticMethod"));
        self::assertEquals("public-static-method", $this->m_xRay->publicStaticMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testPublicStaticMethodWithArgs(): void
    {
        $this->m_tracker->reset();
        self::assertTrue($this->m_xRay->isPublicStaticMethod("publicStaticMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod("publicStaticMethodWithArgs"));
        $expected = self::StringArg . " " . self::IntArg;
        self::assertEquals($expected, $this->m_xRay->publicStaticMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testXRayedPrivateStaticMethod(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicStaticMethod("privateStaticMethod"));
        self::assertTrue($this->m_xRay->isXRayedStaticMethod("privateStaticMethod"));
        self::assertEquals("private-static-method", $this->m_xRay->privateStaticMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testXRayedPrivateStaticMethodWithArgs(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicStaticMethod("privateStaticMethodWithArgs"));
        self::assertTrue($this->m_xRay->isXRayedStaticMethod("privateStaticMethodWithArgs"));
        $expected = self::StringArg . " " . self::IntArg;
        self::assertEquals($expected, $this->m_xRay->privateStaticMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testXRayedPrivateStaticMethodThatThrows(): void
    {
        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage("Static method 'privateStaticMethodThatThrows' could not be invoked on class '" . $this->m_xRay->className() . "'");
        $this->m_xRay->privateStaticMethodThatThrows();
    }

    public function testPublicMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicStaticMethod("publicMethod"));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod("publicMethod"));
        self::assertEquals("", $this->m_xRay->publicMethod());
    }

    public function testPrivateMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicStaticMethod("privateMethod"));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod("privateMethod"));
        self::assertEquals("", $this->m_xRay->privateMethod());
    }

    public function testStaticMagicMethod(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicStaticMethod("staticMagicMethod"));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod("staticMagicMethod"));
        self::assertEquals("magic-method", $this->m_xRay->staticMagicMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testStaticMagicMethodWithArgs(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicStaticMethod("staticMagicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod("staticMagicMethodWithArgs"));
        self::assertEquals(self::StringArg . " " . self::IntArg, $this->m_xRay->staticMagicMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testMagicMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("magicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("magicMethod"));
        self::assertEquals("", $this->m_xRay->magicMethod());
    }

    public function testMagicMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("magicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("magicMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->magicMethodWithArgs(self::StringArg, self::IntArg));
    }

    public function testNonExistentMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicStaticMethod("nonExistentMethod"));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod("nonExistentMethod"));
        $this->m_xRay->nonExistentMethod();
    }

    public function testEmptyMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicStaticMethod(""));
        self::assertFalse($this->m_xRay->isXRayedStaticMethod(""));
        $this->m_xRay->{""}();
    }

    public function testNonExistentMethodNoCallStatic(): void
    {
        $object = (object)[];
        $xray = new StaticXRay(get_class($object));

        if ($xray->isPublicStaticMethod("__callStatic") || $xray->isPublicStaticMethod("nonExistentMethod") || $xray->isXRayedStaticMethod("nonExistentMethod")) {
            self::markTestSkipped("The methods nonExistentMethod and __callStatic must not exist on the test object for this test to be valid.");
        }

        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage("Static method 'nonExistentMethod' does not exist on class '" . get_class($object) . "'");
        $xray->nonExistentMethod();
    }
}
