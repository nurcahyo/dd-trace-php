<?php

namespace DDTrace\Util;

use Datadog\Trace\Util;

/**
 * Utilities related to the PHP runtime
 */
final class Runtime
{
    /**
     * Tells whether or not a given autoloader is registered.
     *
     * @param string $class
     * @param string $method
     * @return bool
     */
    public static function isAutoloaderRegistered($class, $method)
    {
        return Util\dd_util_is_autoloader_registered($class, $method);
    }
}
