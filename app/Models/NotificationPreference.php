<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'type', 'channel', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
