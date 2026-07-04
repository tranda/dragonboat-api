<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Race, Layout, Athlete, Competition};
use Illuminate\Http\Request;

// Public, read-only crew export for feeding rosters into EDBF.
// Access is open for now. To lock it down later, set PUBLIC_CREWS_KEY in .env and callers
// must pass it as `?key=` or the `X-Api-Key` header — no code change required.
class PublicCrewController extends Controller
{
    public function index(Request $request)
    {
        // Optional API key gate (disabled while PUBLIC_CREWS_KEY is unset).
        $requiredKey = config('services.public_crews.key');
        if ($requiredKey) {
            $provided = $request->header('X-Api-Key') ?? $request->query('key');
            if (!is_string($provided) || !hash_equals($requiredKey, $provided)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        // Competition: explicit ?competition=, else the active one.
        $competition = $request->query('competition')
            ? Competition::find($request->query('competition'))
            : Competition::where('is_active', true)->orderByDesc('year')->first();
        if (!$competition) {
            return response()->json(['error' => 'No competition found'], 404);
        }

        $raceQuery = Race::where('competition_id', $competition->id);
        if ($request->query('team')) {
            $raceQuery->where('team_id', $request->query('team'));
        }
        $races = $raceQuery->orderBy('display_order')->orderBy('created_at')->get();

        $athletes = Athlete::all()->keyBy('id');
        $teams = \App\Models\Team::all()->keyBy('id');

        $mapAthlete = function ($id, string $role, ?string $side, ?int $row, int $boatSeat) use ($athletes) {
            if (!$id || !isset($athletes[$id])) return null;
            $a = $athletes[$id];
            return [
                'role' => $role,
                'side' => $side,
                'row' => $row,
                'boatSeat' => $boatSeat,
                'name' => $a->name,
                'gender' => $a->gender,
                'yearOfBirth' => $a->year_of_birth,
                'edbfId' => $a->edbf_id,
                'isBCP' => (bool) $a->is_bcp,
            ];
        };

        $crews = $races->map(function ($race) use ($mapAthlete, $teams) {
            $layout = Layout::where('race_id', $race->id)->first();
            $roster = [];

            if ($layout) {
                $left = $layout->left_seats ?? [];
                $right = $layout->right_seats ?? [];
                $reserves = $layout->reserves ?? [];
                $numRows = $race->num_rows;

                // drummer = boat seat 1
                if ($e = $mapAthlete($layout->drummer_id, 'drummer', null, null, 1)) $roster[] = $e;
                // paddlers, boat order: row 1 (left, right), row 2 (left, right), ...
                for ($i = 0; $i < $numRows; $i++) {
                    if ($e = $mapAthlete($left[$i] ?? null, 'paddler', 'left', $i + 1, $i + 2)) $roster[] = $e;
                    if ($e = $mapAthlete($right[$i] ?? null, 'paddler', 'right', $i + 1, $numRows + 2 + $i)) $roster[] = $e;
                }
                // helm
                if ($e = $mapAthlete($layout->helm_id, 'helm', null, null, $numRows * 2 + 2)) $roster[] = $e;
                // reserves
                foreach (array_values($reserves) as $j => $rid) {
                    if ($e = $mapAthlete($rid, 'reserve', null, null, $numRows * 2 + 3 + $j)) $roster[] = $e;
                }
            }

            $paddlers = collect($roster)->where('role', 'paddler')->count();

            return [
                'id' => $race->id,
                'name' => $race->name,
                'team' => $teams[$race->team_id]->name ?? null,
                'boatType' => $race->boat_type,
                'distance' => $race->distance,
                'genderCategory' => $race->gender_category,
                'ageCategory' => $race->age_category,
                'schedule' => $race->schedule ?? [],
                'paddlerCount' => $paddlers,
                'athletes' => $roster,
            ];
        });

        return response()->json([
            'competition' => [
                'id' => $competition->id,
                'name' => $competition->name,
                'year' => $competition->year,
                'location' => $competition->location,
            ],
            'crewCount' => $crews->count(),
            'crews' => $crews->values(),
        ]);
    }
}
