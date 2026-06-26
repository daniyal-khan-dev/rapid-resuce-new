<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ContentNotification extends Model
{
    protected $table = 'content_notifications';

    protected $fillable = [
        'module',
        'action',
        'subject',
        'preview',
        'module_url',
        'actor',
        'is_read',
        'data',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data'    => 'array',
    ];
}
