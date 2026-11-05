<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider', 'event_id', 'type', 'processed_at'];
}
