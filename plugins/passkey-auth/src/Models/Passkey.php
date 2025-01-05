<?php

namespace PasskeyAuth\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Passkey extends Model
{
    protected $fillable = ['name', 'credential_id', 'public_key', 'counter', 'user_id'];

    protected $casts = [
        'counter' => 'integer',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }
}