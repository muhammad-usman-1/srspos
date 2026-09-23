<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Audit trail: every sale, payment, purchase, stock and price change, with who and when. */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user')
            ->when($request->input('action'), fn($q, $a) => $q->where('action', $a))
            ->when($request->input('user'), fn($q, $u) => $q->where('user_id', $u))
            ->when($request->input('date_from'), fn($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->input('date_to'), fn($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->when($request->input('search'), fn($q, $s) => $q->where('description', 'like', '%' . $s . '%'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('activity.index', [
            'logs' => $logs,
            'actions' => ActivityLog::ACTIONS,
            'users' => User::where('store_id', $request->user()->store_id)->orderBy('first_name')->get(),
        ]);
    }
}
