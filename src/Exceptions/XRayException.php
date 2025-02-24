<?php

declare(strict_types=1);

namespace Equit\XRay\Exceptions;

use RuntimeException;

/** Exception thrown when an XRay or StaticXRay object is asked to do something impossible. */
class XRayException extends RuntimeException
{
}
