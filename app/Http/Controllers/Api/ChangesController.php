<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Lightweight polling endpoint that lets clients discover what changed without
 * a websocket server. The activity_log table already records every mutation
 * with an auto-increment id, a competition scope and an entity_type, so we use
 * its max id as a monotonic cursor.
 *
 * GET /api/changes?since=<cursor>
 *   { "cursor": <int>, "changed": ["race", "layout", ...] }
 *
 * A client seeds its cursor with one call (since omitted / 0 -> changed=[]),
 * then polls; whenever `cursor` advances it refetches the affected data.
 */
class ChangesController extends Controller
{
    public function index(Request $request)
    {
        $since = (int) $request->query('since', 0);
        $competitionId = $request->header('X-Competition-Id') ?: null;
        $teamId = $request->header('X-Team-Id') ?: null;

        // Scope: changes for the active competition, plus global changes
        // (competition_id null) that are either team-agnostic or belong to the
        // active team — e.g. athlete roster, users, teams, config.
        $scope = function ($q) use ($competitionId, $teamId) {
            $q->where(function ($w) use ($competitionId, $teamId) {
                if ($competitionId) {
                    $w->where('competition_id', $competitionId);
                }
                $w->orWhere(function ($g) use ($teamId) {
                    $g->whereNull('competition_id')
                        ->where(function ($t) use ($teamId) {
                            $t->whereNull('team_id');
                            if ($teamId) {
                                $t->orWhere('team_id', $teamId);
                            }
                        });
                });
            });
        };

        $cursor = (int) (ActivityLog::query()->where($scope)->max('id') ?? 0);

        $changed = [];
        if ($since > 0 && $cursor > $since) {
            $changed = ActivityLog::query()
                ->where($scope)
                ->where('id', '>', $since)
                ->distinct()
                ->pluck('entity_type')
                ->filter()
                ->values()
                ->all();
        }

        return response()->json([
            'cursor' => $cursor,
            'changed' => $changed,
        ]);
    }
}
