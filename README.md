# Laravel Multi Cache

Allows you to use multiple Laravel cache stores as one. Retrieves items from the first cache they are found in.

An example use is to have an array cache and Redis cache. Fetch items from the array cache first (faster), and from Redis if the key was not found. The value will be stored in the array cache if it was found in Redis.

## Installation
```
composer require antriver/laravel-multi-cache
```

The service provider will autoload, if you have disabled this add this to your config/app.php `providers` array:
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
