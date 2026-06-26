<?php

use App\Models\Admin\Log;

function logHistory($username, $ipAddress, $action)
{
    Log::create([
        'username'   => $username,
        'ip_address' => $ipAddress,
        'action'     => $action,
    ]);
}
