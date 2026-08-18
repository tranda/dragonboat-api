<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Athlete, Race, Layout, Competition};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportExportController extends Controller {
    /**
     * Stream a per-member achievements pivot for one event (competition) and one
     * club (team) as a CSV download. One row per member; columns are ID, Name,
     * then one column per race in the event holding that member's medal.
     */
    public function csv(Request $request) {
        $user = $request->user()->load('teams');
        $isAdmin = $user->isAdmin();

        // Club (team): query param overrides active team. Non-admins may only
        // export a team they belong to.
        $teamId = $request->query('team_id') ?: $request->header('X-Team-Id') ?: $user->team_id;
        if (!$teamId) abort(422, 'No team selected to export.');
        if (!$isAdmin && !$user->teams->contains('id', (int) $teamId)) {
            abort(403, 'You do not have access to that team.');
        }

        // Event (competition): query param overrides active competition.
        $compId = $request->query('competition_id') ?: $request->header('X-Competition-Id');
        if (!$compId) {
            $compId = Competition::where('is_active', true)->value('id');
        }
        $comp = Competition::findOrFail($compId);

        // Races (competition classes) for this team + competition, in event order.
        $races = Race::where('team_id', $teamId)->where('competition_id', $compId)
            ->orderBy('display_order')->orderBy('created_at')
            ->get(['id', 'name', 'medal']);

        // Layouts keyed by race id → the athletes (any seat, incl. reserves) in that crew.
        $layouts = Layout::whereIn('race_id', $races->pluck('id'))->get()
            ->keyBy(fn($l) => (string) $l->race_id);

        // medalByAthleteRace[athleteId][raceId] = 'GOLD' | 'SILVER' | 'BRONZE'
        $medalByAthleteRace = [];
        foreach ($races as $race) {
            if (!$race->medal) continue;
            $layout = $layouts->get((string) $race->id);
            if (!$layout) continue;
            $crew = array_filter(array_merge(
                $layout->left_seats ?? [],
                $layout->right_seats ?? [],
                [$layout->drummer_id, $layout->helm_id],
                $layout->reserves ?? []
            ), fn($id) => $id !== null);
            foreach ($crew as $athleteId) {
                $medalByAthleteRace[(int) $athleteId][(string) $race->id] = strtoupper($race->medal);
            }
        }

        // Members: registered, non-removed athletes of this team — matches the Report panel's roster.
        $registeredIds = DB::table('competition_athlete')->where('competition_id', $compId)->pluck('athlete_id');
        $members = Athlete::where('team_id', $teamId)
            ->where('is_removed', false)
            ->whereIn('id', $registeredIds)
            ->get(['id', 'name', 'edbf_id']);

        // Sort rows by membership number ascending (numeric when possible, blanks last).
        $members = $members->sort(function ($a, $b) {
            $an = is_numeric($a->edbf_id); $bn = is_numeric($b->edbf_id);
            if ($an && $bn) return (int) $a->edbf_id <=> (int) $b->edbf_id;
            if ($an !== $bn) return $an ? -1 : 1; // numeric IDs before blank/non-numeric
            return strcmp((string) $a->edbf_id, (string) $b->edbf_id)
                ?: strcmp((string) $a->name, (string) $b->name);
        })->values();

        $filename = $comp->name;
        if ($comp->year && strpos($filename, (string) $comp->year) === false) {
            $filename .= ' ' . $comp->year;
        }
        $filename .= '.csv';

        return response()->streamDownload(function () use ($races, $members, $medalByAthleteRace) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Serbian diacritics correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $header = array_merge(['ID', 'Name'], $races->pluck('name')->all());
            fputcsv($out, $header);

            foreach ($members as $m) {
                $row = [$m->edbf_id, $m->name];
                foreach ($races as $race) {
                    $row[] = $medalByAthleteRace[$m->id][(string) $race->id] ?? '';
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
