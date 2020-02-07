<?php

namespace DDTrace\Util;

use Datadog\Trace\Util;

/**
 * Utility class to extract container info.
 */
class ContainerInfo
{
    private $cgroupProcFile;

    public function __construct($cgroupProcFile = '/proc/self/cgroup')
    {
        $this->cgroupProcFile = $cgroupProcFile;
    }

    /**
     * Extracts the container id if the application runs in a containerized environment, `null` otherwise.
     * Note that the value is not cached, so invoking this method multiple times might lead to performance
     * degradation as one IO operation and possibly a few regex match operations are required.
     *
     * @return string|null
     */
    public function getContainerId()
    {
        return Util\dd_util_get_container_id($this->cgroupProcFile);
    }
}
