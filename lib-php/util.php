<?php

namespace Datadog\Trace\Util;

$_resource_registry = [];

/**
 * @param string $version
 * @return bool
 */
function dd_util_php_version_matches($version)
{
    return dd_util_version_matches($version, PHP_VERSION);
}

/**
 * @param string $expected
 * @param string $specimen
 * @return bool
 */
function dd_util_version_matches($expected, $specimen)
{
    $expectedFragments = _as_int_array($expected);
    $specimenFragments = _as_int_array($specimen);

    if (empty($expectedFragments) || empty($specimenFragments)) {
        return false;
    }

    $count = count($expectedFragments);
    for ($i = 0; $i < $count; $i++) {
        if ($specimenFragments[$i] !== $expectedFragments[$i]) {
            return false;
        }
    }

    return true;
}

/**
 * Extracts the container id if the application runs in a containerized environment, `null` otherwise.
 * Note that the value is not cached, so invoking this method multiple times might lead to performance
 * degradation as one IO operation and possibly a few regex match operations are required.
 *
 * @return string|null
 */
function dd_util_get_container_id($cgroupProcFile = '/proc/self/cgroup')
{
    // We do not want to emit a warning if user application uses ini setting 'open_basedir'
    // and '/proc/self' is not included in the ini setting.
    if (!@file_exists($cgroupProcFile)) {
        return null;
    }

    // Example Docker
    // 13:name=systemd:/docker/3726184226f5d3147c25fdeab5b60097e378e8a720503a5e19ecfdf29f869860
    // Example Kubernetes
    // 11:perf_event:/kubepods/something/pod3d274242-8ee0-11e9-a8a6-1e68d864ef1a/3e74d3fd9db4c9dd921ae05c2502fb984d0cde1b36e581b13f79c639da4518a1
    // Example ECS
    // 9:perf_event:/ecs/user-ecs-classic/5a0d5ceddf6c44c1928d367a815d890f/38fac3e99302b3622be089dd41e7ccf38aff368a86cc339972075136ee2710ce
    // Example Fargate
    // 11:something:/ecs/5a081c13-b8cf-4801-b427-f4601742204d/432624d2150b349fe35ba397284dea788c2bf66b885d14dfc1569b01890ca7da
    $lineRegex = '/^(\d+):([^:]*):.*\/([0-9a-f]{64})$/';

    $file = null;
    try {
        $file = fopen($cgroupProcFile, 'r');
        while (!feof($file)) {
            $line = fgets($file);
            $matches = array();
            \preg_match($lineRegex, trim($line), $matches);
            if (count($matches) > 3) {
                return $matches[3];
            }
        }
    } catch (\Exception $e) {
    }

    if ($file) {
        fclose($file);
    }

    return null;
}

/**
 * Tells whether or not a given autoloader is registered.
 *
 * @param string $class
 * @param string $method
 * @return bool
 */
function dd_util_is_autoloader_registered($class, $method)
{
    $class = trim($class, '\\');
    $autoloaders = spl_autoload_functions();
    foreach ($autoloaders as $autoloader) {
        if (!is_array($autoloader) || count($autoloader) !== 2) {
            continue;
        }

        $registeredAutoloader = $autoloader[0];
        $registeredMethod = $autoloader[1];
        if (is_string($registeredAutoloader)) {
            $compareClass = trim($registeredAutoloader, '\\');
        } elseif (is_object($registeredAutoloader)) {
            $compareClass = trim(get_class($registeredAutoloader), '\\');
        } else {
            continue;
        }

        if ($compareClass === $class && $registeredMethod === $method) {
            return true;
        }
    }

    return false;
}

/**
 * Put or replaces a key with a specific value.
 *
 * @param resource $resource
 * @param string $key
 * @param mixed $value
 */
function dd_util_kvstore_put_for_resource($resource, $key, $value)
{
    global $_resource_registry;
    if (_not_enough_resource_info($resource, $key)) {
        return;
    }
    $_resource_registry[_get_resource_key($resource)][$key] = $value;
}

/**
 * Extract a key's value from an instance. If the key is not set => fallbacks to default.
 *
 * @param resource $resource
 * @param string $key
 * @param mixed $default
 * @return mixed|null
 */
function dd_util_kvstore_get_for_resource($resource, $key, $default = null)
{
    global $_resource_registry;
    if (_not_enough_resource_info($resource, $key)) {
        return $default;
    }
    $resourceKey = _get_resource_key($resource);
    return isset($_resource_registry[$resourceKey][$key])
        ? $_resource_registry[$resourceKey][$key]
        : $default;
}


/**
 * Delete a key's value from an instance, if present.
 *
 * @param resource $resource
 */
function dd_util_kvstore_delete_resource($resource)
{
    global $_resource_registry;
    unset($_resource_registry[_get_resource_key($resource)]);
}

/**
 * Tells whether or not a set of info is enough to be used in this storage.
 *
 * @param resource $resource
 * @param string $key
 * @return bool
 */
function _not_enough_resource_info($resource, $key)
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
function _get_resource_key($resource)
{
    // Converting to integer a resource results in the "unique resource number assigned to the resource by PHP at
    // runtime":
    //   - http://php.net/manual/en/language.types.integer.php#language.types.integer.casting
    // Resource ids are guaranteed to be unique per script execution:
    //   - http://www.php.net/manual/en/language.types.string.php#language.types.string.casting
    return intval($resource);
}

/**
 * Converts a string '1.2.3' to an array of integers [1, 2, 3]
 *
 * @param string $versionAsString
 * @return int[]
 */
function _as_int_array($versionAsString)
{
    return array_values(
        array_filter(
            array_map(
                function ($fragment) {
                    return is_numeric($fragment) ? (int) $fragment : null;
                },
                explode('.', $versionAsString)
            )
        )
    );
}
