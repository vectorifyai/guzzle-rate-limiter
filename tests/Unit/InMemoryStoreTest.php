<?php

namespace Vectorify\GuzzleRateLimiter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vectorify\GuzzleRateLimiter\Stores\InMemoryStore;

#[CoversClass(InMemoryStore::class)]
class InMemoryStoreTest extends TestCase
{
    private InMemoryStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
    }

    #[Test]
    public function can_store_and_retrieve_data(): void
    {
        $key = 'test_key';
        $data = ['remaining' => 10, 'reset_time' => time() + 60];
        $ttl = 60;

        $result = $this->store->put($key, $data, $ttl);
        $this->assertTrue($result);

        $retrieved = $this->store->get($key);
        $this->assertEquals($data, $retrieved);
    }

    #[Test]
    public function returns_null_for_missing_key(): void
    {
        $retrieved = $this->store->get('nonexistent_key');
        $this->assertNull($retrieved);
    }

    #[Test]
    public function data_expires_after_ttl(): void
    {
        $key = 'expire_test';
        $data = ['remaining' => 5];
        $ttl = 1; // 1 second

        $this->store->put($key, $data, $ttl);

        // Should exist immediately
        $this->assertEquals($data, $this->store->get($key));

        // Sleep to let it expire
        sleep(2);

        // Should be null after expiry
        $this->assertNull($this->store->get($key));
    }

    #[Test]
    public function can_forget_data(): void
    {
        $key = 'forget_test';
        $data = ['remaining' => 3];

        $this->store->put($key, $data, 60);
        $this->assertEquals($data, $this->store->get($key));

        $result = $this->store->forget($key);
        $this->assertTrue($result);
        $this->assertNull($this->store->get($key));
    }

    #[Test]
    public function can_clear_all_data(): void
    {
        $this->store->put('key1', ['data' => 1], 60);
        $this->store->put('key2', ['data' => 2], 60);

        $this->assertCount(2, $this->store->keys());

        $this->store->clear();

        $this->assertCount(0, $this->store->keys());
        $this->assertNull($this->store->get('key1'));
        $this->assertNull($this->store->get('key2'));
    }
}
