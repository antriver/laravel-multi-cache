# Laravel Multi Cache

Allows you to use multiple Laravel cache stores as one. Retrieves items from the first cache they are found in.

An example use is to have an array cache and Redis cache. Fetch items from the array cache first (faster), and from Redis if the key was not found. The value will be stored in the array cache if it was found in Redis.

## Installation
```
composer require antriver/laravel-multi-cache
```

## Version support

The package major version matches the newest Laravel major it supports, and each release supports as many earlier Laravel versions as it can still test.

| Package | Laravel | PHP |
|---|---|---|
| 13.x | 10.x, 11.x, 12.x, 13.x | 8.3+ |
| 10.1.x | 10.x, 11.x | 8.1+ |
| 10.0.x | 10.x | 7.4+ |
| 1.x | 5.x | |

13.x requires the same PHP version as Laravel 13. CI tests every PHP version from 8.3 to 8.5 with every Laravel version.

Laravel 10 and 11 no longer receive security fixes, so new projects should use Laravel 12 or 13.

Add this to your config/app.php `providers` array:
```php
Antriver\LaravelMultiCache\MultiStoreServiceProvider::class
```

Add the `multi` store to your `config/cache.php` `stores` array:
```
    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
        'database' => [
            'driver' => 'database',
            'table'  => 'cache',
            'connection' => null,
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'redis-cache',
        ],
        'multi' => [
            'driver' => 'multi',
            'stores' => [
                'array',
                'redis',
                'database'
            ],
            'sync_missed_stores' => true,
        ]
    ],
```

Set your `CACHE_DRIVER` in `.env`:
```
CACHE_DRIVER=multi
```

## Usage

The cache implements the standard cache interface, so you use all the normal `get()` and `put()` methods.

### `get($key)`

Returns the value from the first store `$key` is found in (in the order defined in `stores`). The value will be saved in any higher 'stores'.
e.g. If the value is not found it `array`, but is in `redis`, the value from `redis` will be returned and put in `array`, but it will not be put in `database`.
This behaviour can be disabled by setting `sync_missed_stores => false` in config.


### `put($key, $value, $minutes)`

Stores an item in all of the stores.

All of the other methods (`increment()`, `forget()`, `flush()`, etc.) perform the operation on all of the stores.
