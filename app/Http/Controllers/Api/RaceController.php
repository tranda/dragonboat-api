<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Race, Layout, ActivityLog, Competition};
use Illuminate\Http\Request;

class RaceController extends Controller {
    private function getScope(Request $request): array {
        $teamId = $request->user()->team_id;
        $compId = $request->header('X-Competition-Id');
        if (!$compId) {
            $comp = Competition::where('is_active', true)
                ->whereHas('teams', fn($q) => $q->where('teams.id', $teamId))
                ->first();
            $compId = $comp?->id;
        }
        return [$teamId, $compId];
    }

    public function index(Request $request) {
        [$teamId, $compId] = $this->getScope($request);
        return response()->json(Race::where('team_id', $teamId)->where('competition_id', $compId)->orderBy('display_order')->orderBy('created_at')->get());
    }

    public function store(Request $request) {
        $request->validate(['id' => 'required|string|unique:races,id', 'name' => 'required|string', 'boat_type' => 'required|in:standard,small', 'num_rows' => 'required|integer', 'gender_category' => 'required|in:Open,Women,Mixed', 'age_category' => 'required|string']);
        [$teamId, $compId] = $this->getScope($request);
        $maxOrder = (int) Race::where('team_id', $teamId)->where('competition_id', $compId)->max('display_order');
        $race = Race::create(array_merge(
            $request->only(['id', 'name', 'boat_type', 'num_rows', 'distance', 'gender_category', 'age_category', 'category']),
            ['display_order' => $maxOrder + 1, 'team_id' => $teamId, 'competition_id' => $compId]
        ));
        Layout::create(['race_id' => $race->id, 'drummer_id' => null, 'helm_id' => null, 'left_seats' => array_fill(0, $race->num_rows, null), 'right_seats' => array_fill(0, $race->num_rows, null), 'reserves' => []]);
        ActivityLog::log('created', 'race', $race->name);
        return response()->json($race, 201);
    }

    public function reorder(Request $request) {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        foreach ($request->ids as $i => $id) {
            Race::where('id', $id)->where('team_id', $request->user()->team_id)->update(['display_order' => $i]);
        }
        ActivityLog::log('reordered', 'race');
        return response()->json(['message' => 'Reordered']);
    }

    public function update(Request $request, $id) {
        $race = Race::where('team_id', $request->user()->team_id)->findOrFail($id);
        $race->update($request->only(['name', 'boat_type', 'num_rows', 'distance', 'gender_category', 'age_category', 'category']));
        ActivityLog::log('updated', 'race', $race->name);
        return response()->json($race);
    }

    public function destroy(Request $request, $id) {
        $race = Race::where('team_id', $request->user()->team_id)->findOrFail($id);
        ActivityLog::log('deleted', 'race', $race->name);
        $race->delete();
        return response()->json(['message' => 'Race deleted']);
    }

    public function duplicate(Request $request, $id) {
        [$teamId, $compId] = $this->getScope($request);
        $race = Race::where('team_id', $teamId)->findOrFail($id);
        $layout = Layout::where('race_id', $id)->first();
        $newId = $race->id . '_copy_' . time();
        $newRace = Race::create(['id' => $newId, 'name' => $race->name . ' (copy)', 'boat_type' => $race->boat_type, 'num_rows' => $race->num_rows, 'distance' => $race->distance, 'gender_category' => $race->gender_category, 'age_category' => $race->age_category, 'category' => $race->category, 'team_id' => $teamId, 'competition_id' => $compId]);
        if ($layout) Layout::create(['race_id' => $newId, 'drummer_id' => $layout->drummer_id, 'helm_id' => $layout->helm_id, 'left_seats' => $layout->left_seats, 'right_seats' => $layout->right_seats, 'reserves' => $layout->reserves]);
        ActivityLog::log('duplicated', 'race', $race->name, 'New: ' . $newRace->name);
        return response()->json($newRace, 201);
    }
}
