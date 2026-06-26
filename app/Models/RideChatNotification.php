<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideChatNotification extends Model
{
    protected $table = 'ride_chat_notifications';

    protected $fillable = [
        'emergency_request_id',
        'ride_chat_message_id',
        'rreb_id',
        'recipient_type',
        'recipient_id',
        'sender_name',
        'sender_type',
        'message_preview',
        'is_read',
    ];

    protected $casts = ['is_read' => 'boolean'];

    public function emergencyRequest()
    {
        return $this->belongsTo(EmergencyRequest::class);
    }
}
