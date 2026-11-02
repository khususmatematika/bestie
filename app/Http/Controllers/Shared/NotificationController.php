<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifikasi dalam aplikasi milik pengguna yang sedang masuk (tidak pernah milik orang lain).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(20)->get()->map(static fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'title' => (string) ($n->data['title'] ?? ''),
                'body' => (string) ($n->data['body'] ?? ''),
                'url' => (string) ($n->data['url'] ?? '/'),
                'kind' => (string) ($n->data['kind'] ?? ''),
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->first()?->markAsRead();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }
}
