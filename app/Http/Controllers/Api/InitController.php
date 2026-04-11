<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Athlete, Race, Layout, AppConfig, BenchFactor};
use Illuminate\Http\Request;

class InitController extends Controller {
    public function index(Request $request) {
        $athletes = Athlete::all()->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'weight' => $a->weight ?? 0, 'gender' => $a->gender, 'yearOfBirth' => $a->year_of_birth, 'isBCP' => $a->is_bcp, 'isRemoved' => $a->is_removed, 'raceAssignments' => []]);
        $races = Race::orderBy('display_order')->orderBy('created_at')->get()->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'boatType' => $r->boat_type, 'numRows' => $r->num_rows, 'distance' => $r->distance, 'genderCategory' => $r->gender_category, 'ageCategory' => $r->age_category, 'category' => $r->category]);
        $layouts = [];
        foreach (Layout::all() as $l) $layouts[$l->race_id] = ['drummer' => $l->drummer_id, 'helm' => $l->helm_id, 'left' => $l->left_seats, 'right' => $l->right_seats, 'reserves' => $l->reserves];
        $cm = AppConfig::first();
        $config = $cm ? ['competitionYear' => $cm->competition_year, 'genderPolicy' => $cm->gender_policy, 'ageCategoryRules' => $cm->age_rules] : null;
        $bf = [];
        foreach (BenchFactor::all() as $b) $bf[$b->boat_type] = $b->factors;
        $user = $request->user()->load('role');
        return response()->json(['athletes' => $athletes, 'races' => $races, 'layouts' => $layouts, 'config' => $config, 'benchFactors' => $bf, 'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->name, 'athlete_id' => $user->athlete_id]]);
    }
}
