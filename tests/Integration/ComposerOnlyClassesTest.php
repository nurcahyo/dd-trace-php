<?php

namespace DDTrace\Tests\Integration;

use DDTrace\Tests\Unit\BaseTestCase;
use DDTrace\Util\ArrayKVStore;
use DDTrace\Util\ContainerInfo;
use DDTrace\Util\ObjectKVStore;
use DDTrace\Util\Runtime;
use DDTrace\Util\Versions;

final class ComposerOnlyClassesTest extends BaseTestCase
{
    private $resource;

    protected function setUp()
    {
        parent::setUp();
        $this->resource = fopen('php://memory', 'r');
    }

    protected function tearDown()
    {
        fclose($this->resource);
        parent::tearDown();
    }

    public function testDDTraceUtil()
    {
        // ArrayKVStore
        ArrayKVStore::putForResource($this->resource, 'key', 'value');

        // ObjectKVStore
        $this->assertNull(ObjectKVStore::get(new \stdClass(), null));

        // Container info
        new ContainerInfo();

        // Runtime
        Runtime::isAutoloaderRegistered('class', 'method');

        // Versions
        Versions::phpVersionMatches('7.4');
    }
}
