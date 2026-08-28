<?php

use App\Models\User;
use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', UserChannel::class);

Broadcast::channel('attendance.dashboard', function (User $user) {
    return $user->isActive() && $user->isManagement();
});
