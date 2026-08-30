<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Builds query-free notification tray rows for Blade (queries stay here).
 */
class NotificationViewService
{
    private const TRAY_LIMIT = 20;

    /**
     * @return list<array{id: string, message: string, url: string|null, is_read: bool, created_at_label: string}>
     */
    public function trayRows(User $user): array
    {
        return $this->recentNotifications($user)
            ->map(fn (DatabaseNotification $notification): array => $this->row($notification))
            ->values()
            ->all();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    private function recentNotifications(User $user): Collection
    {
        return $user->notifications()
            ->latest()
            ->limit(self::TRAY_LIMIT)
            ->get();
    }

    /**
     * @return array{id: string, message: string, url: string|null, is_read: bool, created_at_label: string}
     */
    private function row(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => (string) $notification->id,
            'message' => (string) ($data['message'] ?? ''),
            'url' => $this->resolveUrl($data),
            'is_read' => $notification->read_at !== null,
            'created_at_label' => $notification->created_at
                ?->timezone(config('app.timezone'))
                ->format('Y-m-d H:i') ?? '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveUrl(array $data): ?string
    {
        $type = (string) ($data['type'] ?? '');

        return match ($type) {
            'order_placed' => isset($data['parent_order_id'])
                ? route('account.orders.show', $data['parent_order_id'])
                : null,
            'vendor_order_status_changed' => ($data['audience'] ?? '') === 'vendor'
                ? (isset($data['vendor_order_id']) ? route('vendor.orders.show', $data['vendor_order_id']) : null)
                : (isset($data['parent_order_id']) ? route('account.orders.show', $data['parent_order_id']) : null),
            'vendor_order_received' => isset($data['vendor_order_id'])
                ? route('vendor.orders.show', $data['vendor_order_id'])
                : null,
            'order_cancelled' => ($data['audience'] ?? '') === 'vendor'
                ? (isset($data['vendor_order_id']) ? route('vendor.orders.show', $data['vendor_order_id']) : null)
                : (isset($data['parent_order_id']) ? route('account.orders.show', $data['parent_order_id']) : null),
            'vendor_application_approved' => route('vendor.dashboard'),
            'vendor_application_rejected' => route('account.vendor-application'),
            default => null,
        };
    }
}
