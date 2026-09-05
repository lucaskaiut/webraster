<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function paginate(int $userId, int $perPage = 20, ?bool $unreadOnly = null): LengthAwarePaginator
    {
        return UserNotification::query()
            ->with(['alert.vehicle'])
            ->where('user_id', $userId)
            ->when($unreadOnly === true, fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function unreadCount(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(UserNotification $notification): UserNotification
    {
        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markAllRead(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
