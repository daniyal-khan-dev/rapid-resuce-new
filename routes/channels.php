<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth:users']]);

Broadcast::routes(['middleware' => ['web', 'auth:driver'], 'prefix' => 'driver']);

Broadcast::channel('contact.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
}, ['guards' => ['users']]);

Broadcast::channel('driver.{driverId}', function ($driver, $driverId) {
    return (int) $driver->id === (int) $driverId;
}, ['guards' => ['driver']]);
