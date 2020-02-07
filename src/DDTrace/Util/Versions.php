<?php

namespace DDTrace\Util;

use Datadog\Trace\Util;

/**
 * Utility functions to handle version numbers and matching.
 */
final class Versions
{
    /**
     * @param string $version
     * @return bool
     */
    public static function phpVersionMatches($version)
    {
        return Util\dd_util_php_version_matches($version);
    }

    /**
     * @param string $expected
     * @param string $specimen
     * @return bool
     */
    public static function versionMatches($expected, $specimen)
    {
        return Util\dd_util_version_matches($expected, $specimen);
    }
}
