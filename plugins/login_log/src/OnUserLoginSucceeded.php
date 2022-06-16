<?php

namespace loginlog;

use App\Models\User;

class OnUserLoginSucceeded
{
    public function handle(User $user)
    {
        $user->login_at = date('Y-m-d H:i:s', time());
        $user->save();
    }
}
