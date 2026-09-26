<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id || $user->canManageCurrentTenant();
    }
}
