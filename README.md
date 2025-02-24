# XRay

A PHP testing utility for accessing protected and private class members.

Sometimes in tests you need to access protected or private class members. PHP provides a Reflection API for this. XRay
provides a convenience layer on top of this to make it easier to use, and the code using it easier to understand. The
guiding principle is to attempt to make accessing inaccessible members the same as if the class were declared with those
members `public`.

It comes in two flavours, one for instance members, the other for static members. The principles of the two are the same:

- Create an `XRay` (or `StaticXRay`) for the object (or class) under test
- Access the protected or private member as if it were a member of the (Static)XRay

## Calling methods

The most common use-case is to unit test otheriwse inaccessible methods.

For instance methods, given the class

```php
class TestThis
{
    protected function testMethod(): void
    {
        // ... do something testable
    }

    private function otherTestMethod(): void
    {
        // ... do something else testable
    }
}
```

You can call protected or private methods like this:

```php
$objectUnderTest = new TestThis();
$xray = new XRay($objectUnderTest);
$xray->testMethod();
$xray->otherTestMethod();
```

You probably want to set some test expectations on your object under test; but if you don't need to set any, you can call its constructor directly
in the `XRay` constructor:

```php
$xray = new XRay(new TestThis());
```

For static methods it's similar, except you pass the fully-qualified class name to the `StaticXRay` constructor as a string, rather than an instance
of the class:

```php
class TestThis
{
    protected static function staticTestMethod(): void
    {
        // ... do something testable
    }

    protected static function otherStaticTestMethod(): void
    {
        // ... do something else testable
    }
}

$xray = new StaticXRay(TestThis::class);
$xray->staticTestMethod();
$xray->otherStaticTestMethod();
```

Note that you call static methods with a `StaticXRay` _as if they were instance methods_.

### Passing arguments

Pass arguments exactly as you would if you were calling the method directly:

```php
$xray->testMethod($var, "literal", new OtherObject());
```

Methods that accept arguments by reference, methods with optional arguments and methods that accept variable argument lists and/or parameter
packs are fully supported.

### Return values

Receving return values is similarly unaltered:

```php
$returnValue = $xray->testMethod(...$args);
```

Methods that return `void` will return `null` when invoked via an `XRay` (or `StaticXRay`). This is what PHP itself does, but you may find your
IDE won't warn you as it would if you tried to do something with a `void` return directly.

Calling xrayed methods that return `never` will not return, as expected.

## Accessing properties

Less commonly, you may want to access inaccessible class properties. As with calling methods, you can just treat your `XRay` as if it were an the
xrayed object itself when accessing properties:

```php
class TestThis
{
    private string $value;
}

$xray = new XRay(new TestThis());
$theValue = $xray->value;
```

Static properties can be accessed too:

```php
class TestThis
{
    private static string $staticValue;
}

$xray = new StaticXRay(TestThis::class);
$theValue = $xray->staticValue;
```

### Setting property values

If you need to you can set the values for protected and private properties:

```php
class TestThis
{
    private string $value;
}

$xray = new XRay(new TestThis());
$xray->value = "the test value";
```

As elsewhere, this works with static properties using StaticXRay:

```php
class TestThis
{
    private static string $staticValue;
}

$xray = new StaticXRay(TestThis::class);
$xray->staticValue = "the test value";
```

The only caveat to this is when you want to manipulate the content of array properties:

```php
class TestThis
{
    private array $arrayValue;
}

$xray = new XRay(new TestThis());
$xray->arrayValue["test-key"] = "test-value";
```

Contrary to what you might expect, this does **not** set the value of the `"test-key"` key in the `$arrayValue` property of the object under
test. What it does is set the value of that key on a local copy of the array. This is because when the code executes, `$xray->arrayValue` is
a fetch of a copy of the property, to which `["test-key"] = "test-value"` adds the key and value. The local copy of the array is then
immediately discarded since it's not assigned to anything. The property in the xrayed object remains unmodified throughout.

### Updating array properties

The way to set (or unset) a key on an array property is this:

```php
class TestThis
{
    private array $arrayValue;
}

$xray = new XRay(new TestThis());
$temporaryArray = $xray->arrayValue["test-key"];
$temporaryArray["test-key"] = "test-value";
$xray->arrayValue = $temporaryArray;
```

It works this way for static array properties also:

```php
class TestThis
{
    private static array $staticArrayValue;
}

$xray = new StaticXRay(TestThis::class);
$temporaryArray = $xray->staticArrayValue["test-key"];
$temporaryArray["test-key"] = "test-value";
$xray->staticArrayValue = $temporaryArray;
```

While this is more verbose than the rest of the library, and doesn't align with the principle of "an XRay behaves the same as the object it
xrays", it's still clearer and more concise than using PHP's Reflection API directly.

## Inherited members

XRays work with members that the xrayed object or class inherits from base classes, as well as its own members. This is
regardless of how far back up the inheritance tree the target method is. The syntax for accessing inherited members is
no different from how you access the object or class's own members:

```php
class BaseTestThis
{
    protected function doSomething(): void
    {
        // ... do something testable
    }
}

class TestThis extends BaseTestThis
{
    protected function doSomethingElse(): void
    {
        // ... do something else testable
    }
}

$xray = new XRay(new TestThis());
$xray->doSomething();
$xray->doSomethingElse();
```

If the object under test reimplements the inherited method, the reimplementation is the one that's called (just as it
would be if it were a regular PHP method call):

```php
class BaseTestThis
{
    protected function doSomething(): void
    {
        // ... the xray does not call this method
    }
}

class TestThis extends BaseTestThis
{
    protected function doSomething(): void
    {
        // ... this method gets called by the xray
    }
}

$xray = new XRay(new TestThis());
$xray->doSomething();
```

Inherited protected properties are supported too:

```php
class BaseTestThis
{
    protected string $baseValue;
}

class TestThis extends BaseTestThis
{
}

$xray = new XRay(new TestThis());
$xray->baseValue = "test-value";
$theValue = $xray->baseValue;
```

### Base class private members

`XRay` and `StaticXRay` will permit access to private properties of ancestors of the xrayed object or class. Such
members are not inherited by the xrayed object/class itself, and therefore following the principle of _**as if the class
were declared with `protected`/`private` members `public`**_, they ought not to be made visible in the xray. The reason
they are visible is that the XRay applies the principle to the full inheritance hierarchy of the xrayed class/object.
This facilitates setting test expectations when performing some operation on the object/class under test is expected to
result in a state change in an ancestor class.

This applies only to properties - `XRay`s and `StaticXRay`s don't make private methods of base classes accessible. This
is because the main purpose of xraying is to enable testing of inaccessible members where necessary. Private methods are
not inherited, and don't need testing with the inheriting class. Therefore there's no need for an xray to make it
possible to invoke them.

## Public members

You can use `XRay` and `StaticXRay` objects to access `public` members just as you would `protected` and `private` members, and just as you would
on the xrayed object itself (subject to the array member property caveat above). Doing so is entirely optional: if you'd prefer to access public
members directly on the original object, it won't cause any problems for the XRay; if you'd prefer to use the `XRay` because it's more readable
in your view, that's also fine.

## Non-test uses

Using `XRay` or `StaticXRay` for anything other than testing is not recommended. I've not yet come across any other use-case for which this type
of approach is appropriate.
