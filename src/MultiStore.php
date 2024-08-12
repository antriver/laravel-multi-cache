<?php

namespace Antriver\LaravelMultiCache;

use Exception;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RetrievesMultipleKeys;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Application;

class MultiStore implements Store
{
    use RetrievesMultipleKeys;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Store[]
     */
    protected $stores = [];

    /**
     * @var int
     */
    protected $storeCount = 0;

    /**
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * MultiStore constructor.
     *
     * @param Application  $app
     * @param array        $config
     * @param CacheManager $cacheManager
     *
     * @throws Exception
     */
    public function __construct(Application $app, array $config, CacheManager $cacheManager)
    {
        $this->app = $app;
        $this->config = $config;
        $this->cacheManager = $cacheManager;

        if (empty($config['stores'])) {
            throw new Exception("No stores are defined for multi cache.");
        }

        foreach ($config['stores'] as $name) {
            $this->stores[$name] = $this->cacheManager->store($name);
        }

        $this->storeCount = count($this->stores);

        $this->prefix = $config['prefix'] ?? '';
    }

    /**
     * @return Store[]
     */
    public function getStores()
    {
        return $this->stores;
    }

    /**
     * @return int
     */
    public function getStoreCount()
    {
        return $this->storeCount;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array $key
     *
     * @return mixed
     */
    public function get($key)
    {
        /** @var Store[] $missedStores */
        $missedStores = [];

        $foundValue = null;

        foreach ($this->stores as $name => $store) {
            if (($value = $store->get($key)) !== null) {
                $foundValue = $value;
                break;
            } else {
                $missedStores[] = $store;
            }
        }

        if ($foundValue) {
            foreach ($missedStores as $store) {
                // Remember in the higher cache store for 1 day.
                $store->put($key, $foundValue, 1440);
            }
        }

        return $foundValue;
    }

    /**
     * Store an item in the cache for a given number of minutes.
     *
     * @param  string    $key
     * @param  mixed     $value
     * @param  float|int $minutes
     */
    public function put($key, $value, $minutes)
    {
        foreach ($this->stores as $store) {
            $store->put($key, $value, $minutes);
        }
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        $returnValue = null;

        foreach ($this->stores as $store) {
            $returnValue = $store->increment($key, $value);
        }

        return $returnValue;
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        $returnValue = null;

        foreach ($this->stores as $store) {
            $returnValue = $store->decrement($key, $value);
        }

        return $returnValue;
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string $key
     * @param  mixed  $value
     */
    public function forever($key, $value)
    {
        foreach ($this->stores as $store) {
            $store->forever($key, $value);
        }
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     *
     * @return bool
     */
    public function forget($key)
    {
        $forgotten = 0;

        foreach ($this->stores as $store) {
            if ($store->forget($key)) {
                ++$forgotten;
            }
        }

        return $forgotten === $this->storeCount;
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $flushed = 0;

        foreach ($this->stores as $store) {
            if ($store->flush()) {
                ++$flushed;
            }
        }

        return $flushed === $this->storeCount;
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }
}
