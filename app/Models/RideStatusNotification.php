<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideStatusNotification extends Model
{
    protected $table = 'ride_status_notifications';

    protected $fillable = [
        'emergency_request_id',
        'rreb_id',
        'status',
        'status_label',
        'driver_name',
        'recipient_type',
        'recipient_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(EmergencyRequest::class, 'emergency_request_id');
    }
}
