<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
protected $fillable = ['from', 'text', 'id_message', 'received_at'];



    protected $table = 'whatsapp_messages';
    
    
       public function user()
    {
        return $this->belongsTo(User::class);
    }
}