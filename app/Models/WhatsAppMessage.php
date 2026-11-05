<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'whatsapp_messages';

    protected $fillable = ['tenant_id', 'user_id', 'direction', 'wa_message_id', 'kind', 'template', 'status', 'created_at'];
}
