<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\EmergencyRequest;

class EmergencyNotification extends Model
{
    protected $table = 'emergency_notifications';

    protected $fillable = [
        'emergency_request_id',
        'rreb_id',
        'mobile_no',
        'pickup_address',
        'type',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function emergencyRequest()
    {
        return $this->belongsTo(EmergencyRequest::class, 'emergency_request_id');
    }
}
