<?php

namespace Antriver\LaravelMultiCacheTests;

use Antriver\LaravelMultiCache\MultiStore;
use Antriver\LaravelMultiCache\MultiStoreServiceProvider;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;

class MultiStoreTest extends TestCase
{
    protected function getApplicationProviders($app)
    {
        return [
            ...parent::getApplicationProviders($app),
            MultiStoreServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'multi');
        config()->set(
            'cache.stores',
            [
                'array-primary' => [
                    'driver' => 'array',
                ],
                'array-secondary' => [
                    'driver' => 'array',
                ],
                'multi' => [
                    'driver' => 'multi',
                    'stores' => [
                        'array-primary',
                        'array-secondary',
                    ],
                    'sync_missed_stores' => true,
                ],
            ]
        );
    }

    protected function tearDown(): void
    {
        $this->getPrimaryStore()->flush();
        $this->getSecondaryStore()->flush();

        parent::tearDown();
    }

    /**
     * @return MultiStore
     */
    protected function getMultiStore()
    {
        return Cache::store('multi');
    }

    /**
     * @return ArrayStore
     */
    protected function getPrimaryStore()
    {
        return Cache::store('array-primary');
    }

    /**
     * @return ArrayStore
     */
    protected function getSecondaryStore()
    {
        return Cache::store('array-secondary');
    }

    /**
     * Creating a MultiStore with no stores should throw an exception.
     */
    public function testCreateWithoutStoresThrowsException()
    {
        $this->expectException(\Exception::class);

        $multiStore = new MultiStore(
            app(),
            [
                'stores' => [],
            ],
            app(CacheManager::class)
        );
    }

    /**
     * Test the defined stores are created in the MultiStore.
     */
    public function testStoresAreCreated()
    {
        $this->assertContainsOnlyInstancesOf(
            \Illuminate\Cache\Repository::class,
            $this->getMultiStore()->getStores()
        );

        $this->assertSame(2, count($this->getMultiStore()->getStores()));
        $this->assertSame(2, $this->getMultiStore()->getStoreCount());
    }

    /**
     * Should return null if the value is not in any store.
     */
    public function testGetReturnsNull()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));

        $this->assertNull($this->getMultiStore()->get('hello'));
    }

    /**
     * Test the value is returned from the primary store if it exists in it.
     */
    public function testGetFromPrimary()
    {
        $this->getPrimaryStore()->put('hello', 'world', 1);
        $this->getSecondaryStore()->put('hello', 'world2', 1);
        $this->assertSame('world', $this->getMultiStore()->get('hello'));
    }

    /**
     * Test the value is returned from the secondary store if not in the primary.
     */
    public function testGetFromSecondary()
    {
        $value = uniqid();

        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->getSecondaryStore()->put('hello', $value, 1);
        $this->assertSame($value, $this->getMultiStore()->get('hello'));
    }

    /**
     * Test the value is returned from the secondary store, and then stored in the primary,
     * if not already in the primary.
     */
    public function testGetFromSecondStoresInPrimary()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));

        $value = uniqid();

        $this->getSecondaryStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getMultiStore()->get('hello'));

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
    }

    /**
     * Testing storing a value stores it in all stores.
     */
    public function testPutStoresInAllStores()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));

        $value = uniqid();

        $this->getMultiStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
        $this->assertSame($value, $this->getSecondaryStore()->get('hello'));
    }

    public function testPutReturnsTrue()
    {
        $this->assertTrue($this->getMultiStore()->put('hello', 'world', 1));
    }

    /**
     * The PSR-16 set() and setMultiple() methods are typed to return bool, so they fail if put() returns nothing.
     */
    public function testSetAndSetMultipleReturnTrue()
    {
        $this->assertTrue($this->getMultiStore()->set('hello', 'world', 1));
        $this->assertTrue($this->getMultiStore()->setMultiple(['a' => 1, 'b' => 2], 1));

        $this->assertSame(2, $this->getSecondaryStore()->get('b'));
    }

    public function testForeverReturnsTrue()
    {
        $this->assertTrue($this->getMultiStore()->forever('hello', 'world'));
    }

    /**
     * Testing storing a value stores it in all stores.
     */
    public function testForeverStoresInAllStores()
    {
        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));

        $value = uniqid();

        $this->getMultiStore()->forever('hello', $value);

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
        $this->assertSame($value, $this->getSecondaryStore()->get('hello'));
    }

    /**
     * Increment should increment all stores.
     */
    public function testIncrement()
    {
        $this->getPrimaryStore()->put('number', 1, 1);
        $this->getSecondaryStore()->put('number', 1, 1);

        $this->getMultiStore()->increment('number');

        $this->assertSame(2, $this->getPrimaryStore()->get('number'));
        $this->assertSame(2, $this->getSecondaryStore()->get('number'));
    }

    /**
     * Increment should decrement all stores.
     */
    public function testDecrement()
    {
        $this->getPrimaryStore()->put('number', 1, 1);
        $this->getSecondaryStore()->put('number', 1, 1);

        $this->getMultiStore()->decrement('number');

        $this->assertSame(0, $this->getPrimaryStore()->get('number'));
        $this->assertSame(0, $this->getSecondaryStore()->get('number'));
    }

    /**
     * Forget should forget in all stores.
     */
    public function testForget()
    {
        $this->getPrimaryStore()->put('hello', 'world1', 1);
        $this->getPrimaryStore()->put('goodbye', 'world2', 1);
        $this->getSecondaryStore()->put('hello', 'world3', 1);

        $this->assertSame('world1', $this->getPrimaryStore()->get('hello'));
        $this->assertSame('world2', $this->getPrimaryStore()->get('goodbye'));
        $this->assertSame('world3', $this->getSecondaryStore()->get('hello'));

        $this->getMultiStore()->forget('hello');

        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertSame('world2', $this->getPrimaryStore()->get('goodbye'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));
    }

    /**
     * Flush should flush in all stores.
     */
    public function testFlush()
    {
        $this->getPrimaryStore()->put('hello', 'world1', 1);
        $this->getPrimaryStore()->put('goodbye', 'world2', 1);
        $this->getSecondaryStore()->put('hello', 'world3', 1);

        $this->assertSame('world1', $this->getPrimaryStore()->get('hello'));
        $this->assertSame('world2', $this->getPrimaryStore()->get('goodbye'));
        $this->assertSame('world3', $this->getSecondaryStore()->get('hello'));

        $this->getMultiStore()->flush();

        $this->assertNull($this->getPrimaryStore()->get('hello'));
        $this->assertNull($this->getPrimaryStore()->get('goodbye'));
        $this->assertNull($this->getSecondaryStore()->get('hello'));
    }

    /**
     * Skips the test on Laravel versions whose cache repository has no touch() method.
     */
    protected function requireTouch()
    {
        if (!method_exists(\Illuminate\Cache\Repository::class, 'touch')) {
            $this->markTestSkipped('Cache touch() needs Laravel 13+');
        }
    }

    /**
     * Touch should extend the expiration in all stores.
     */
    public function testTouch()
    {
        $this->requireTouch();
        $this->getPrimaryStore()->put('hello', 'world1', 1);
        $this->getSecondaryStore()->put('hello', 'world2', 1);

        $this->assertTrue($this->getMultiStore()->touch('hello', 60));
        $this->travel(30)->seconds();

        $this->assertSame('world1', $this->getPrimaryStore()->get('hello'));
        $this->assertSame('world2', $this->getSecondaryStore()->get('hello'));
    }

    public function testTouchReturnsTrueWhenOnlyInSecondary()
    {
        $this->requireTouch();
        $this->getSecondaryStore()->put('hello', 'world', 1);

        $this->assertTrue($this->getMultiStore()->touch('hello', 60));
        $this->travel(30)->seconds();

        $this->assertSame('world', $this->getSecondaryStore()->get('hello'));
    }

    public function testTouchReturnsFalseWhenMissing()
    {
        $this->requireTouch();

        $this->assertFalse($this->getMultiStore()->touch('hello', 60));
    }

    public function testGetPrefixReturnsEmptyString()
    {
        $this->assertSame('', $this->getMultiStore()->getPrefix());
    }

    public function testSyncMissedStoresIsFalse()
    {
        config()->set('cache.stores.multi.sync_missed_stores', false);

        $this->assertNull($this->getPrimaryStore()->get('hello'));

        $value = uniqid();

        $this->getSecondaryStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getMultiStore()->get('hello'));

        $this->assertNull($this->getPrimaryStore()->get('hello'));
    }

    public function testSyncMissedStoresConfigIsMissing()
    {
        // Config is first written inside setUp function. Here we overwrite it
        // so that sync_missed_stores is deleted from config.
        config()->set(
            'cache.stores.multi',
            [
                'driver' => 'multi',
                'stores' => [
                    'array-primary',
                    'array-secondary',
                ],
            ]
        );

        $this->assertNull($this->getPrimaryStore()->get('hello'));

        $value = uniqid();

        $this->getSecondaryStore()->put('hello', $value, 1);

        $this->assertSame($value, $this->getMultiStore()->get('hello'));

        $this->assertSame($value, $this->getPrimaryStore()->get('hello'));
    }
}
