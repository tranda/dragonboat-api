<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller {
    public function index() {
        return response()->json(Team::withCount('users', 'athletes')->orderBy('name')->get());
    }

    public function store(Request $request) {
        $request->validate(['name' => 'required|string']);
        $team = Team::create($request->only(['name', 'country', 'type']));
        return response()->json($team, 201);
    }

    public function update(Request $request, $id) {
        $team = Team::findOrFail($id);
        $team->update($request->only(['name', 'country', 'type']));
        return response()->json($team);
    }

    public function destroy($id) {
        Team::findOrFail($id)->delete();
        return response()->json(['message' => 'Team deleted']);
    }
}
