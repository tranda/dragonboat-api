<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Race, Layout, Athlete, Competition};
use Illuminate\Http\Request;

// Public, read-only medal results feed for club.motion.rs to PULL and upsert into
// its achievements. One flat record per (member, medaling race). Keyed by member_id
// so the club app can dedupe against its own data. dbcrews never writes anything.
//
// Access: open while CLUB_RESULTS_KEY is unset; once set, callers must pass it as
// `?key=` or the `X-Api-Key` header.
class PublicResultsController extends Controller
{
    public function index(Request $request)
    {
        $requiredKey = config('services.club_results.key');
        if ($requiredKey) {
            $provided = $request->header('X-Api-Key') ?? $request->query('key');
            if (!is_string($provided) || !hash_equals($requiredKey, $provided)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        // Scope: one competition (?competition=) or all; optionally one team (?team=).
        $competitions = $request->query('competition')
            ? Competition::where('id', $request->query('competition'))->get()
            : Competition::orderByDesc('year')->get();
        $compById = $competitions->keyBy('id');

        $raceQuery = Race::whereIn('competition_id', $competitions->pluck('id'))
            ->whereNotNull('medal');
        if ($request->query('team')) {
            $raceQuery->where('team_id', $request->query('team'));
        }
        $races = $raceQuery->orderBy('display_order')->orderBy('created_at')->get();

        // Only athletes that carry a club membership number can be matched club-side.
        $athletes = Athlete::whereNotNull('member_id')->get()->keyBy('id');
        $layouts = Layout::whereIn('race_id', $races->pluck('id'))->get()->keyBy(fn($l) => (string) $l->race_id);

        $results = [];
        foreach ($races as $race) {
            $layout = $layouts->get((string) $race->id);
            if (!$layout) continue;
            $crew = array_filter(array_merge(
                $layout->left_seats ?? [],
                $layout->right_seats ?? [],
                [$layout->drummer_id, $layout->helm_id],
                $layout->reserves ?? []
            ), fn($id) => $id !== null);

            $comp = $compById->get($race->competition_id);
            foreach (array_unique($crew) as $athleteId) {
                $a = $athletes->get($athleteId);
                if (!$a) continue; // no member_id → not matchable club-side
                $results[] = [
                    // Stable competition PK — dedupe on this, not the display name (which
                    // changes on rename). Same id as GET /api/public/competitions.
                    'competitionId' => (int) $race->competition_id,
                    'memberId' => (int) $a->member_id,
                    'name' => $a->name,
                    'event' => $comp?->name,
                    'year' => $comp?->year,
                    'race' => $race->name,
                    'distance' => $race->distance,
                    'genderCategory' => $race->gender_category,
                    'ageCategory' => $race->age_category,
                    'medal' => $race->medal,
                ];
            }
        }

        return response()->json([
            'count' => count($results),
            'results' => $results,
        ]);
    }
}
