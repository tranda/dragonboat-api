<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Athlete, Race, Layout, AppConfig, BenchFactor, Competition};
use Illuminate\Http\Request;

class InitController extends Controller {
    public function index(Request $request) {
        $user = $request->user()->load('role', 'team');
        $teamId = $user->team_id;

        // Get competition: from header, or fallback to user's team's active competition
        $compId = $request->header('X-Competition-Id');
        if (!$compId) {
            $comp = Competition::where('is_active', true)
                ->whereHas('teams', fn($q) => $q->where('teams.id', $teamId))
                ->first();
            $compId = $comp?->id;
        }

        $athletes = Athlete::where('team_id', $teamId)->get()->map(fn($a) => [
            'id' => $a->id, 'name' => $a->name, 'weight' => $a->weight ?? 0,
            'gender' => $a->gender, 'yearOfBirth' => $a->year_of_birth,
            'isBCP' => $a->is_bcp, 'preferredSide' => $a->preferred_side,
            'notes' => $a->notes, 'isRemoved' => $a->is_removed, 'raceAssignments' => [],
        ]);

        $races = Race::where('team_id', $teamId)->where('competition_id', $compId)
            ->orderBy('display_order')->orderBy('created_at')
            ->get()->map(fn($r) => [
                'id' => $r->id, 'name' => $r->name, 'boatType' => $r->boat_type,
                'numRows' => $r->num_rows, 'distance' => $r->distance,
                'genderCategory' => $r->gender_category, 'ageCategory' => $r->age_category,
                'category' => $r->category,
            ]);

        $raceIds = $races->pluck('id');
        $layouts = [];
        foreach (Layout::whereIn('race_id', $raceIds)->get() as $l) {
            $layouts[$l->race_id] = ['drummer' => $l->drummer_id, 'helm' => $l->helm_id, 'left' => $l->left_seats, 'right' => $l->right_seats, 'reserves' => $l->reserves];
        }

        $cm = AppConfig::where('team_id', $teamId)->where('competition_id', $compId)->first()
            ?? AppConfig::where('team_id', $teamId)->first()
            ?? AppConfig::first();
        $config = $cm ? ['competitionYear' => $cm->competition_year, 'genderPolicy' => $cm->gender_policy, 'ageCategoryRules' => $cm->age_rules] : null;

        $bf = [];
        foreach (BenchFactor::all() as $b) $bf[$b->boat_type] = $b->factors;

        // Available competitions for this team
        $competitions = Competition::whereHas('teams', fn($q) => $q->where('teams.id', $teamId))
            ->orderByDesc('is_active')->orderByDesc('year')
            ->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'year' => $c->year, 'location' => $c->location, 'isActive' => $c->is_active]);

        return response()->json([
            'athletes' => $athletes,
            'races' => $races,
            'layouts' => $layouts,
            'config' => $config,
            'benchFactors' => $bf,
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'role' => $user->role->name, 'athlete_id' => $user->athlete_id,
                'team' => $user->team ? ['id' => $user->team->id, 'name' => $user->team->name] : null,
            ],
            'competitions' => $competitions,
            'activeCompetitionId' => $compId,
        ]);
    }
}
