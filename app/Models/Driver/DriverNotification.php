<?php

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;
use App\Models\EmergencyRequest;

class DriverNotification extends Model
{
    protected $table = 'driver_notifications';

    protected $fillable = [
        'driver_id',
        'emergency_request_id',
        'type',
        'title',
        'body',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function emergencyRequest()
    {
        return $this->belongsTo(EmergencyRequest::class, 'emergency_request_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
