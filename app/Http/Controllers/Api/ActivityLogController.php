<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller {
    public function index(Request $request) {
        $limit = min((int) ($request->query('limit', 50)), 200);
        $logs = ActivityLog::orderByDesc('created_at')->limit($limit)->get();
        return response()->json($logs);
    }
}
