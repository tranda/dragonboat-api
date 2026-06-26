<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Competition, Team};
use Illuminate\Http\Request;

class ActivityLogController extends Controller {
    public function index(Request $request) {
        $limit = min((int) ($request->query('limit', 50)), 200);
        $teamId = $request->user()->team_id;

        $query = ActivityLog::where('team_id', $teamId);
        // Optionally narrow to the active competition (plus team-wide entries
        // that carry no competition scope). Pass ?scope=competition to enable.
        if ($request->query('scope') === 'competition') {
            $compId = $request->header('X-Competition-Id');
            if ($compId) {
                $query->where(fn($q) => $q->where('competition_id', $compId)->orWhereNull('competition_id'));
            }
        }
        $logs = $query->orderByDesc('created_at')->limit($limit)->get();

        // Resolve human-readable names for display.
        $compNames = Competition::whereIn('id', $logs->pluck('competition_id')->filter()->unique())->pluck('name', 'id');
        $teamNames = Team::whereIn('id', $logs->pluck('team_id')->filter()->unique())->pluck('name', 'id');
        $logs->each(function ($log) use ($compNames, $teamNames) {
            $log->competition_name = $compNames[$log->competition_id] ?? null;
            $log->team_name = $teamNames[$log->team_id] ?? null;
        });

        return response()->json($logs);
    }
}
