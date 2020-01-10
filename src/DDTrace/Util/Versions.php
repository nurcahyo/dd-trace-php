<?php

namespace DDTrace\Util;

use function Datadog\Trace\Util\dd_util_php_version_matches;
use function Datadog\Trace\Util\dd_util_version_matches;

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
        return dd_util_php_version_matches($version);
    }

    /**
     * @param string $expected
     * @param string $specimen
     * @return bool
     */
    public static function versionMatches($expected, $specimen)
    {
        return dd_util_version_matches($expected, $specimen);
    }
}
