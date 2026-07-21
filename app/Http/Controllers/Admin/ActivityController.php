<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class ActivityController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.activity', compact('logs'));
    }
}
