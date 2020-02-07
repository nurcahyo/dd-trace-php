<?php

namespace DDTrace\Util;

use Datadog\Trace\Util;

/**
 * A key value store that stores metadata into an array. If you have an object that you can use as a carrier, then
 * prefer ObjectKVStore as is provides a better performance. Use this if you do not have an object you can use as
 * a carrier.
 */
class ArrayKVStore
{
    /**
     * Put or replaces a key with a specific value.
     *
     * @param resource $resource
     * @param string $key
     * @param mixed $value
     */
    public static function putForResource($resource, $key, $value)
    {
        Util\dd_util_array_kvstore_put_for_resource($resource, $key, $value);
    }

    /**
     * Extract a key's value from an instance. If the key is not set => fallbacks to default.
     *
     * @param resource $resource
     * @param string $key
     * @param mixed $default
     * @return mixed|null
     */
    public static function getForResource($resource, $key, $default = null)
    {
        return Util\dd_util_array_kvstore_get_for_resource($resource, $key, $default);
    }

    /**
     * Delete a key's value from an instance, if present.
     *
     * @param resource $resource
     */
    public static function deleteResource($resource)
    {
        Util\dd_util_array_kvstore_delete_resource($resource);
    }

    /**
     * Clears the storage.
     */
    public static function clear()
    {
        Util\dd_util_array_kvstore_clear();
    }

    /**
     * Tells whether or not a set of info is enough to be used in this storage.
     *
     * @param resource $resource
     * @param string $key
     * @return bool
     */
    private static function notEnoughResourceInfo($resource, $key)
    {
        return
            !is_resource($resource)
            || empty($key)
            || !is_string($key);
    }

    /**
     * Returns the unique resource key.
     *
     * @param resource $resource
     * @return int
     */
    private static function getResourceKey($resource)
    {
        // Converting to integer a resource results in the "unique resource number assigned to the resource by PHP at
        // runtime":
        //   - http://php.net/manual/en/language.types.integer.php#language.types.integer.casting
        // Resource ids are guaranteed to be unique per script execution:
        //   - http://www.php.net/manual/en/language.types.string.php#language.types.string.casting
        return intval($resource);
    }
}
