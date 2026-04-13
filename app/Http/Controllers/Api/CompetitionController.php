<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use Illuminate\Http\Request;

class CompetitionController extends Controller {
    public function index() {
        return response()->json(Competition::with('teams:id,name')->orderByDesc('year')->get());
    }

    public function store(Request $request) {
        $request->validate(['name' => 'required|string', 'year' => 'required|integer']);
        $comp = Competition::create($request->only(['name', 'year', 'location', 'is_active', 'gender_policy', 'reserves']));
        return response()->json($comp, 201);
    }

    public function update(Request $request, $id) {
        $comp = Competition::findOrFail($id);
        $comp->update($request->only(['name', 'year', 'location', 'is_active', 'gender_policy', 'reserves']));
        return response()->json($comp);
    }

    public function destroy($id) {
        Competition::findOrFail($id)->delete();
        return response()->json(['message' => 'Competition deleted']);
    }

    public function addTeam(Request $request, $id) {
        $request->validate(['team_id' => 'required|exists:teams,id']);
        $comp = Competition::findOrFail($id);
        $comp->teams()->syncWithoutDetaching([$request->team_id]);
        return response()->json(['message' => 'Team added']);
    }

    public function removeTeam($id, $teamId) {
        $comp = Competition::findOrFail($id);
        $comp->teams()->detach($teamId);
        return response()->json(['message' => 'Team removed']);
    }
}
