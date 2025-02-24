<?php

declare(strict_types=1);

namespace XRay;

use BadMethodCallException;
use XRay\Exceptions\XRayException;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

/**
 * Make visible the static inner workings of an object.
 *
 * Provides an easy-to-use interface to use reflection to access the inner implementation details of objects. Use the
 * XRay object like an instance with member corresponding to the static member of the original class, including its
 * protected and private static members. For example, if a class Foo has a private static member foo(), you can call it
 * using `(new StaticXRay(Foo::class))->foo();`. Similarly you can read and write properties:
 * `(new StaticXRay(Foo::class))->someProperty = "a value";`.
 *
 * WARNING implementation details are private for a reason. You should strive never to use this class. If you do need to
 * use it, if it's for anything other than testing you're probably using it incorrectly.
 */
class StaticXRay
{
    /** @var ReflectionObject The ReflectionClass for the class being examined. */
    private ReflectionClass $m_subjectReflector;

    /** @var string The fully-qualified name of the class being examined. */
    private string $m_subjectClass;

    /** @var string[] Cache of resolved public static methods. */
    private array $m_publicMethods = [];

    /** @var ReflectionMethod[] Cache of the resolved ReflectionMethod instances for inaccessible static methods. */
    private array $m_xRayedMethods = [];

    /** @var string[] Cache of methods that cannot be resolved. */
    private array $m_unresolvableMethods = [];

    /** @var string[] Cache of resolved public static properties. */
    private array $m_publicProperties = [];

    /**
     * @var ReflectionProperty[] Cache of the resolved ReflectionProperty instances for inaccessible static properties.
     */
    private array $m_xRayedProperties = [];

    /** @var string[] Cache of properties that cannot be resolved. */
    private array $m_unresolvableProperties = [];

    /**
     * Initialise a new static x-ray for a named class.
     *
     * @param string $class The fully-qualified name of the class to x-ray.
     *
     * @throws XRayException
     */
    public function __construct(string $class)
    {
        $this->m_subjectClass = $class;

        try {
            $this->m_subjectReflector = new ReflectionClass($class);
        } catch (ReflectionException $err) {
            throw new XRayException("The class '{$class}' does not exist", 0, $err);
        }
    }

    /**
     * Helper to resolve a named static method for the xray.
     *
     * @param string $method The static method name to resolve.
     */
    protected function resolveMethod(string $method): void
    {
        if (in_array($method, $this->m_publicMethods) || in_array($method, $this->m_unresolvableMethods) || isset($this->m_xRayedMethods[$method])) {
            return;
        }

        try {
            $reflector = $this->m_subjectReflector->getMethod($method);
        } catch (ReflectionException $err) {
            $reflector = null;
        }

        if (!isset($reflector) || !$reflector->isStatic()) {
            $this->m_unresolvableMethods[] = $method;
            return;
        }

        if ($reflector->isPublic()) {
            $this->m_publicMethods[] = $method;
            return;
        }

        $reflector->setAccessible(true);
        $this->m_xRayedMethods[$method] = $reflector;
    }

    /**
     * Helper to resolve a named static property for the xray.
     *
     * @param string $property The static property name to resolve.
     */
    protected function resolveProperty(string $property): void
    {
        if (in_array($property, $this->m_publicProperties) || in_array($property, $this->m_unresolvableProperties) || isset($this->m_xRayedProperties[$property])) {
            return;
        }

        try {
            $reflector = $this->m_subjectReflector->getProperty($property);
        } catch (ReflectionException $err) {
            $reflector = null;
        }

        if (!isset($reflector) || !$reflector->isStatic()) {
            $this->m_unresolvableProperties[] = $property;
            return;
        }

        if ($reflector->isPublic()) {
            $this->m_publicProperties[] = $property;
            return;
        }

        $reflector->setAccessible(true);
        $this->m_xRayedProperties[$property] = $reflector;
    }

    /**
     * Fetch the name of the class being x-rayed.
     *
     * @return string The fully-qualified class name.
     */
    public function className(): string
    {
        return $this->m_subjectClass;
    }

    /**
     * Check whether a named static method is public.
     *
     * @param string $method The static method name. It is case-sensitive.
     *
     * @return bool `true` if the method exists on the x-rayed object, is public and is static, `false` otherwise.
     */
    public function isPublicStaticMethod(string $method): bool
    {
        $this->resolveMethod($method);

        return in_array($method, $this->m_publicMethods);
    }

    /**
     * Check whether a named static method has been made accessible by the XRay.
     *
     * @param string $method The static method name. It is case-sensitive.
     *
     * @return bool `true` if the method exists and the XRay has made it visible, `false` if it doesn't exist, is not
     * static or is a public static method.
     */
    public function isXRayedStaticMethod(string $method): bool
    {
        $this->resolveMethod($method);

        return isset($this->m_xRayedMethods[$method]);
    }

    /**
     * Check whether a named static property is public.
     *
     * @param string $property The static property name. It is case-sensitive.
     *
     * @return bool `true` if the property exists on the x-rayed object, is public and is static, `false` otherwise.
     */
    public function isPublicStaticProperty(string $property): bool
    {
        $this->resolveProperty($property);

        return in_array($property, $this->m_publicProperties);
    }

    /**
     * Check whether a named static property has been made accessible by the XRay.
     *
     * @param string $property The static property name. It is case-sensitive.
     *
     * @return bool `true` if the property exists and the XRay has made it visible, `false` if it doesn't exist, is not
     * static or is a public static property.
     */
    public function isXRayedStaticProperty(string $property): bool
    {
        $this->resolveProperty($property);

        return isset($this->m_xRayedProperties[$property]);
    }

    /**
     * Invoke a static method of the x-rayed class.
     *
     * @param string $method The method name. It is case-sensitive.
     * @param array $args The arguments to pass to the method.
     *
     * @return mixed The return value of the static method call.
     * @throws BadMethodCallException if the named method does not exist or is not static.
     */
    public function __call(string $method, array $args)
    {
        if ($this->isPublicStaticMethod($method)) {
            return [$this->className(), $method](...$args);
        } elseif ($this->isXRayedStaticMethod($method)) {
            try {
                return $this->m_xRayedMethods[$method]->invoke(null, ...$args);
            } catch (ReflectionException $err) {
                throw new BadMethodCallException("Static method '{$method}' could not be invoked on class '{$this->className()}'", 0, $err);
            }
        } elseif (method_exists($this->className(), "__callStatic")) {
            return [$this->className(), "__callStatic"]($method, $args);
        }

        throw new BadMethodCallException("Static method '{$method}' does not exist on class '{$this->className()}'");
    }

    /**
     * Get the value of a static property of the x-rayed class.
     *
     * @param string $property The property name. It is case-sensitive.
     *
     * @return mixed The value of the class's static property.
     * @throws LogicException if the named property does not exist or is not static.
     */
    public function __get(string $property)
    {
        if ($this->isPublicStaticProperty($property)) {
            return $this->className()::$$property;
        } elseif ($this->isXRayedStaticProperty($property)) {
            return $this->m_xRayedProperties[$property]->getValue();
        }

        throw new LogicException("Static property '{$property}' does not exist on object of class '{$this->m_subjectReflector->getName()}'");
    }

    /**
     * Set the value of a static property of the x-rayed class.
     *
     * @param string $property The static property name. It is case-sensitive.
     * @param mixed $value The value to set.
     *
     * @throws LogicException if the named property does not exist or is not static.
     */
    public function __set(string $property, $value)
    {
        if ($this->isPublicStaticProperty($property)) {
            return $this->className()::$$property = $value;
        } elseif ($this->isXRayedStaticProperty($property)) {
            $this->m_xRayedProperties[$property]->setValue(null, $value);
            return $this->m_xRayedProperties[$property]->getValue(null);
        }

        throw new LogicException("Static property '{$property}' does not exist on object of class '{$this->m_subjectReflector->getName()}'");
    }
}
