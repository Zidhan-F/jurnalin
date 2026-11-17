<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spec;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Notifikasi dalam aplikasi (P15). Setiap pengguna hanya melihat notifikasinya sendiri. */
final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = UserNotification::query()->where('user_id', $request->user()->id)->latest()->limit(100)->get();

        return view('spec.notifications', ['notifications' => $notifications, 'layout' => $this->layoutFor($request)]);
    }

    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        $url = (string) $notification->url;

        return redirect(str_starts_with($url, '/') ? $url : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function layoutFor(Request $request): string
    {
        return $request->user()->role === \App\Enums\UserRole::Student ? 'layouts.student-pwa' : 'layouts.app';
    }
}
