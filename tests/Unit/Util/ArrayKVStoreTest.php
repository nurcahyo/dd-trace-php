<?php

namespace DDTrace\Tests\Unit\Util;

use Datadog\Trace\Util;
use DDTrace\Util\ArrayKVStore;
use PHPUnit\Framework\TestCase;

final class ArrayKVStoreTest extends TestCase
{
    private $resource1;
    private $resource2;

    protected function setUp()
    {
        parent::setUp();
        $this->resource1 = fopen('php://memory', 'r');
        $this->resource2 = fopen('php://memory', 'r');
    }

    protected function tearDown()
    {
        fclose($this->resource1);
        fclose($this->resource2);
        parent::tearDown();
    }

    public function testPutForResourceNull()
    {
        ArrayKVStore::putForResource(null, 'key', 'value');
        $this->assertSame('default', ArrayKVStore::getForResource(null, 'key', 'default'));

        Util\dd_util_array_kvstore_put_for_resource(null, 'key1', 'value1');
        $this->assertSame('default', Util\dd_util_array_kvstore_get_for_resource(null, 'key1', 'default'));
    }

    public function testPutForResourceKeyNull()
    {
        ArrayKVStore::putForResource($this->resource1, null, 'value');
        $this->assertSame('default', ArrayKVStore::getForResource($this->resource1, null, 'default'));

        Util\dd_util_array_kvstore_put_for_resource($this->resource1, null, 'value1');
        $this->assertSame('default', Util\dd_util_array_kvstore_get_for_resource($this->resource1, null, 'default'));
    }

    public function testPutForResourceKeyValid()
    {
        ArrayKVStore::putForResource($this->resource1, 'key', 'value');
        $this->assertSame('value', ArrayKVStore::getForResource($this->resource1, 'key', 'default'));

        Util\dd_util_array_kvstore_put_for_resource($this->resource1, 'key1', 'value1');
        $this->assertSame('value1', Util\dd_util_array_kvstore_get_for_resource($this->resource1, 'key1', 'default'));
    }

    public function testPutForMultipleResourcesKeyValid()
    {
        ArrayKVStore::putForResource($this->resource1, 'key', 'value1');
        ArrayKVStore::putForResource($this->resource2, 'key', 'value2');
        $this->assertSame('value1', ArrayKVStore::getForResource($this->resource1, 'key', 'default'));
        $this->assertSame('value2', ArrayKVStore::getForResource($this->resource2, 'key', 'default'));


        Util\dd_util_array_kvstore_put_for_resource($this->resource1, 'key1', 'value1');
        Util\dd_util_array_kvstore_put_for_resource($this->resource2, 'key1', 'value2');
        $this->assertSame('value1', Util\dd_util_array_kvstore_get_for_resource($this->resource1, 'key1', 'default'));
        $this->assertSame('value2', Util\dd_util_array_kvstore_get_for_resource($this->resource2, 'key1', 'default'));
    }
}
