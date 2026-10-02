<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController
{
    public function __construct(private readonly NotificationCenterService $notifications) {}

    public function index(Request $request): View
    {
        return view('notifications.index', ['notifications' => $this->notifications->paginate($request->user())]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->notifications->markRead($request->user(), $notification);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back();
    }
}
