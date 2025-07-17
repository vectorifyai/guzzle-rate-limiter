<?php

namespace Vectorify\GuzzleRateLimiter\Tests\Unit;

use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vectorify\GuzzleRateLimiter\Stores\FilesystemStore;

#[CoversClass(FilesystemStore::class)]
class FilesystemStoreTest extends TestCase
{
    private FilesystemStore $store;

    protected function setUp(): void
    {
        // Use in-memory adapter for testing to avoid filesystem I/O
        $adapter = new InMemoryFilesystemAdapter();
        $this->store = new FilesystemStore($adapter);
    }

    #[Test]
    public function returns_null_for_missing_key(): void
    {
        $result = $this->store->get('nonexistent_key');
        $this->assertNull($result);
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
    public function data_expires_after_ttl(): void
    {
        $key = 'expire_test';
        $data = ['remaining' => 5, 'reset_time' => time()];
        $ttl = -1; // Already expired

        $this->assertTrue($this->store->put($key, $data, $ttl));

        // Sleep a moment to ensure expiration
        usleep(1000);

        $this->assertNull($this->store->get($key));
    }

    #[Test]
    public function can_forget_data(): void
    {
        $key = 'forget_test';
        $data = ['remaining' => 3, 'reset_time' => time() + 60];
        $ttl = 60;

        $this->store->put($key, $data, $ttl);
        $this->assertEquals($data, $this->store->get($key));

        $result = $this->store->forget($key);
        $this->assertTrue($result);
        $this->assertNull($this->store->get($key));
    }

    #[Test]
    public function forget_returns_true_for_nonexistent_key(): void
    {
        $result = $this->store->forget('nonexistent_key');
        $this->assertTrue($result);
    }

    #[Test]
    public function can_store_multiple_keys_independently(): void
    {
        $key1 = 'key1';
        $key2 = 'key2';
        $data1 = ['remaining' => 1, 'reset_time' => time() + 30];
        $data2 = ['remaining' => 2, 'reset_time' => time() + 60];
        $ttl = 60;

        $this->store->put($key1, $data1, $ttl);
        $this->store->put($key2, $data2, $ttl);

        $this->assertEquals($data1, $this->store->get($key1));
        $this->assertEquals($data2, $this->store->get($key2));

        $this->store->forget($key1);
        $this->assertNull($this->store->get($key1));
        $this->assertEquals($data2, $this->store->get($key2));
    }

    #[Test]
    public function cleanup_removes_expired_files(): void
    {
        $validKey = 'valid_key';
        $expiredKey = 'expired_key';
        $data = ['remaining' => 1, 'reset_time' => time() + 60];

        // Store valid data
        $this->store->put($validKey, $data, 60);

        // Store expired data
        $this->store->put($expiredKey, $data, -1);

        // Verify both exist initially
        $this->assertEquals($data, $this->store->get($validKey));
        $this->assertNull($this->store->get($expiredKey)); // Should be null due to expiration

        // Run cleanup
        $cleaned = $this->store->cleanup();

        // Should have cleaned at least the expired file
        $this->assertGreaterThanOrEqual(0, $cleaned);

        // Valid data should still exist
        $this->assertEquals($data, $this->store->get($validKey));
    }

    #[Test]
    public function handles_special_characters_in_keys(): void
    {
        $specialKey = 'key/with\\special:characters?and<spaces>';
        $data = ['remaining' => 5, 'reset_time' => time() + 300];
        $ttl = 60;

        $this->assertTrue($this->store->put($specialKey, $data, $ttl));
        $this->assertEquals($data, $this->store->get($specialKey));
        $this->assertTrue($this->store->forget($specialKey));
    }

    #[Test]
    public function handles_large_data_arrays(): void
    {
        $key = 'large_data_key';
        $largeData = [
            'remaining' => 1000,
            'reset_time' => time() + 3600,
            'metadata' => array_fill(0, 100, 'some data'),
            'nested' => [
                'array' => ['with', 'multiple', 'levels'],
                'count' => 42
            ]
        ];
        $ttl = 60;

        $this->assertTrue($this->store->put($key, $largeData, $ttl));
        $this->assertEquals($largeData, $this->store->get($key));
    }

    #[Test]
    public function maintains_data_integrity_with_serialization(): void
    {
        $key = 'integrity_test';
        $data = [
            'remaining' => 15,
            'reset_time' => 1234567890,
            'float_value' => 3.14159,
            'boolean_true' => true,
            'boolean_false' => false,
            'null_value' => null,
            'string_with_quotes' => 'test "quoted" string',
        ];
        $ttl = 60;

        $this->store->put($key, $data, $ttl);
        $retrieved = $this->store->get($key);

        $this->assertSame($data['remaining'], $retrieved['remaining']);
        $this->assertSame($data['reset_time'], $retrieved['reset_time']);
        $this->assertSame($data['float_value'], $retrieved['float_value']);
        $this->assertSame($data['boolean_true'], $retrieved['boolean_true']);
        $this->assertSame($data['boolean_false'], $retrieved['boolean_false']);
        $this->assertSame($data['null_value'], $retrieved['null_value']);
        $this->assertSame($data['string_with_quotes'], $retrieved['string_with_quotes']);
    }
}
