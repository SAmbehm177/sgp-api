<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $request->boolean('non_lues')
            ? $user->unreadNotifications()
            : $user->notifications();

        return $query->paginate(20);
    }

    public function marquerLu(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $notification;
    }

    public function toutMarquerLu(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Notifications marquées comme lues.']);
    }
}
