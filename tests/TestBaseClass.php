<?php

declare(strict_types=1);

namespace Equit\XRayTests;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use LogicException;

/** Test base class for (Static)XRay tests to ensure they can access base class properties and methods. */
class TestBaseClass
{
    public const BaseStringArg = "base-method-arg";

    public const BaseIntArg = 99;

    private static CallTracker $m_baseCallTracker;

    private static string $m_basePrivateStaticProperty = "base-private-static-property";

    public static string $basePublicStaticProperty = "base-public-static-property";

    private string $m_basePrivateProperty = "base-private-property";

    public string $basePublicProperty = "base-public-property";

    private array $m_baseMagicProperties = [
        "m_baseMagicProperty" => "base-magic-property",
    ];

    public function __construct(CallTracker $baseTracker)
    {
        self::$m_baseCallTracker = $baseTracker;
    }

    private static function basePrivateStaticMethod(): string
    {
        TestCase::fail("basePrivateStaticMethod() should not be called.");
    }

    public static function basePublicStaticMethod(): string
    {
        TestCase::fail("basePublicStaticMethod() should not be called.");
    }

    private static function basePrivateStaticMethodWithArgs(string $arg1, int $arg2): string
    {
        TestCase::fail("basePrivateStaticMethodWithArgs() should not be called.");
    }

    public static function basePublicStaticMethodWithArgs(string $arg1, int $arg2): string
    {
        TestCase::fail("basePublicStaticMethodWithArgs() should not be called.");
    }

    public function basePublicMethod(): string
    {
        self::$m_baseCallTracker->increment();
        return "base-public-method";
    }

    public function basePublicMethodWithArgs(string $arg1, int $arg2): string
    {
        self::$m_baseCallTracker->increment();
        TestCase::assertEquals(self::BaseStringArg, $arg1);
        XRayTest::assertEquals(self::BaseIntArg, $arg2);
        return "{$arg1} {$arg2}";
    }

    private function basePrivateMethod(): string
    {
        self::$m_baseCallTracker->increment();
        return "base-private-method";
    }

    private function basePrivateMethodWithArgs(string $arg1, int $arg2): string
    {
        self::$m_baseCallTracker->increment();
        TestCase::assertEquals(self::BaseStringArg, $arg1);
        XRayTest::assertEquals(self::BaseIntArg, $arg2);
        return "private {$arg1} {$arg2}";
    }

    public function __get(string $property): string
    {
        if ("m_baseMagicProperty" === $property) {
            return $this->m_baseMagicProperties[$property];
        }

        throw new LogicException("Property {$property} does not exist.");
    }

    public function __set(string $property, string $value): void
    {
        if ("m_baseMagicProperty" === $property) {
            $this->m_baseMagicProperties[$property] = $value;
            return;
        }

        throw new LogicException("Property {$property} does not exist.");
    }

    public function __call(string $method, array $args): string
    {
        if ("baseMagicMethod" === $method) {
            self::$m_baseCallTracker->increment();
            return "base-magic-method";
        }

        if ("baseMagicMethodWithArgs" === $method) {
            self::$m_baseCallTracker->increment();
            return "{$args[0]} {$args[1]}";
        }

        throw new BadMethodCallException("Non-existent magic method.");
    }

    public static function __callStatic(string $method, array $args): void
    {
        TestCase::fail("__callStatic() should not be called.");
    }
}
