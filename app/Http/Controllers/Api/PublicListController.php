<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Team, Competition};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

// Public, read-only lists so club.motion.rs can populate its team + competition
// dropdowns before pulling /api/public/results. Same optional key as the results feed
// (CLUB_RESULTS_KEY via ?key= or X-Api-Key).
class PublicListController extends Controller
{
    private function guardKey(Request $request): ?JsonResponse
    {
        $requiredKey = config('services.club_results.key');
        if ($requiredKey) {
            $provided = $request->header('X-Api-Key') ?? $request->query('key');
            if (!is_string($provided) || !hash_equals($requiredKey, $provided)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }
        return null;
    }

    // All teams (a member may belong to several — club + national). Pass id as ?team=.
    public function teams(Request $request)
    {
        if ($resp = $this->guardKey($request)) return $resp;
        $teams = Team::orderBy('name')->get(['id', 'name', 'type'])
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'type' => $t->type]);
        return response()->json(['count' => $teams->count(), 'teams' => $teams]);
    }

    // Competitions, optionally scoped to a team via the competition_team pivot —
    // mirrors the dbcrews UI's second selector. Pass id as ?competition=.
    public function competitions(Request $request)
    {
        if ($resp = $this->guardKey($request)) return $resp;
        $query = Competition::orderByDesc('year')->orderByDesc('is_active');
        if ($request->query('team')) {
            $query->whereHas('teams', fn($q) => $q->where('teams.id', $request->query('team')));
        }
        $comps = $query->get(['id', 'name', 'year', 'location'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'year' => $c->year, 'location' => $c->location]);
        return response()->json(['count' => $comps->count(), 'competitions' => $comps]);
    }
}
