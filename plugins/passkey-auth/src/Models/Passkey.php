<?php

namespace PasskeyAuth\Models;

use Illuminate\Database\Eloquent\Model;

class Passkey extends Model
{
    protected $fillable = ['name', 'credential_id', 'public_key', 'counter'];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}