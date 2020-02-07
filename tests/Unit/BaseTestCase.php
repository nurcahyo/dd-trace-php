<?php

namespace DDTrace\Tests\Unit;

use Datadog\Trace\Util;
use DDTrace\Log\Logger;
use DDTrace\Tests\DebugLogger;
use PHPUnit\Framework;

abstract class BaseTestCase extends Framework\TestCase
{
    protected function tearDown()
    {
        \Mockery::close();
        Logger::reset();
        parent::tearDown();
    }

    protected function matchesPhpVersion($version)
    {
        return Util\dd_util_php_version_matches($version);
    }

    /**
     * Sets and return a debug logger which accumulates log messages.
     * @return DebugLogger
     */
    protected function withDebugLogger()
    {
        $logger = new DebugLogger();
        Logger::set($logger);
        return $logger;
    }
}
