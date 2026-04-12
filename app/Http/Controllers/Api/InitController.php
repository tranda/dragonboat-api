<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Athlete, Race, Layout, AppConfig, BenchFactor, Competition};
use Illuminate\Http\Request;

class InitController extends Controller {
    public function index(Request $request) {
        $user = $request->user()->load('role', 'team', 'teams');
        $isAdmin = $user->isAdmin();

        // Active team: from header, or user's team_id, or first team
        $teamId = $request->header('X-Team-Id') ?: $user->team_id;
        if (!$teamId && $user->teams->count() > 0) {
            $teamId = $user->teams->first()->id;
        }

        // Update active team if changed
        if ($teamId && $user->team_id != $teamId) {
            $user->update(['team_id' => $teamId]);
        }

        // Active competition: from header, or find active one
        $compId = $request->header('X-Competition-Id');
        if (!$compId) {
            $compQuery = Competition::where('is_active', true);
            if (!$isAdmin && $teamId) {
                $compQuery->whereHas('teams', fn($q) => $q->where('teams.id', $teamId));
            }
            $comp = $compQuery->first();
            $compId = $comp?->id;
        }

        // Athletes scoped by team (admin sees all if viewing another team)
        $athleteQuery = Athlete::query();
        if ($teamId) {
            $athleteQuery->where('team_id', $teamId);
        } elseif (!$isAdmin) {
            $athleteQuery->where('team_id', 0); // no results
        }

        $athletes = $athleteQuery->get()->map(fn($a) => [
            'id' => $a->id, 'name' => $a->name, 'weight' => $a->weight ?? 0,
            'gender' => $a->gender, 'yearOfBirth' => $a->year_of_birth,
            'isBCP' => $a->is_bcp, 'preferredSide' => $a->preferred_side,
            'notes' => $a->notes, 'isRemoved' => $a->is_removed, 'raceAssignments' => [],
        ]);

        // Races scoped by team + competition
        $raceQuery = Race::where('competition_id', $compId);
        if ($teamId) {
            $raceQuery->where('team_id', $teamId);
        } elseif (!$isAdmin) {
            $raceQuery->where('team_id', 0);
        }

        $races = $raceQuery->orderBy('display_order')->orderBy('created_at')
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

        // Competitions: admin sees all, others see only their teams' competitions
        $compQuery = Competition::orderByDesc('is_active')->orderByDesc('year');
        if (!$isAdmin && $teamId) {
            $compQuery->whereHas('teams', fn($q) => $q->where('teams.id', $teamId));
        }
        $competitions = $compQuery->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'year' => $c->year, 'location' => $c->location, 'isActive' => $c->is_active]);

        // User's teams
        $userTeams = $user->teams->map(fn($t) => ['id' => $t->id, 'name' => $t->name]);
        if ($isAdmin) {
            // Admin can switch to any team
            $userTeams = \App\Models\Team::orderBy('name')->get()->map(fn($t) => ['id' => $t->id, 'name' => $t->name]);
        }

        return response()->json([
            'athletes' => $athletes,
            'races' => $races,
            'layouts' => $layouts,
            'config' => $config,
            'benchFactors' => $bf,
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'role' => $user->role->name, 'athlete_id' => $user->athlete_id,
                'team' => $teamId ? ($user->teams->find($teamId) ?? \App\Models\Team::find($teamId)) : null,
            ],
            'teams' => $userTeams,
            'competitions' => $competitions,
            'activeTeamId' => $teamId ? (int)$teamId : null,
            'activeCompetitionId' => $compId ? (int)$compId : null,
        ]);
    }
}
