<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Race, Layout, ActivityLog};
use Illuminate\Http\Request;

class RaceController extends Controller {
    public function index() { return response()->json(Race::orderBy('display_order')->orderBy('created_at')->get()); }

    public function store(Request $request) {
        $request->validate(['id' => 'required|string|unique:races,id', 'name' => 'required|string', 'boat_type' => 'required|in:standard,small', 'num_rows' => 'required|integer', 'gender_category' => 'required|in:Open,Women,Mixed', 'age_category' => 'required|string']);
        $maxOrder = (int) Race::max('display_order');
        $race = Race::create(array_merge(
            $request->only(['id', 'name', 'boat_type', 'num_rows', 'distance', 'gender_category', 'age_category', 'category']),
            ['display_order' => $maxOrder + 1]
        ));
        Layout::create(['race_id' => $race->id, 'drummer_id' => null, 'helm_id' => null, 'left_seats' => array_fill(0, $race->num_rows, null), 'right_seats' => array_fill(0, $race->num_rows, null), 'reserves' => []]);
        ActivityLog::log('created', 'race', $race->name);
        return response()->json($race, 201);
    }

    public function reorder(Request $request) {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);
        foreach ($request->ids as $i => $id) {
            Race::where('id', $id)->update(['display_order' => $i]);
        }
        ActivityLog::log('reordered', 'race');
        return response()->json(['message' => 'Reordered']);
    }

    public function update(Request $request, $id) {
        $race = Race::findOrFail($id);
        $race->update($request->only(['name', 'boat_type', 'num_rows', 'distance', 'gender_category', 'age_category', 'category']));
        ActivityLog::log('updated', 'race', $race->name);
        return response()->json($race);
    }

    public function destroy($id) {
        $race = Race::findOrFail($id);
        ActivityLog::log('deleted', 'race', $race->name);
        $race->delete();
        return response()->json(['message' => 'Race deleted']);
    }

    public function duplicate($id) {
        $race = Race::findOrFail($id);
        $layout = Layout::where('race_id', $id)->first();
        $newId = $race->id . '_copy_' . time();
        $newRace = Race::create(['id' => $newId, 'name' => $race->name . ' (copy)', 'boat_type' => $race->boat_type, 'num_rows' => $race->num_rows, 'distance' => $race->distance, 'gender_category' => $race->gender_category, 'age_category' => $race->age_category, 'category' => $race->category]);
        if ($layout) Layout::create(['race_id' => $newId, 'drummer_id' => $layout->drummer_id, 'helm_id' => $layout->helm_id, 'left_seats' => $layout->left_seats, 'right_seats' => $layout->right_seats, 'reserves' => $layout->reserves]);
        ActivityLog::log('duplicated', 'race', $race->name, 'New: ' . $newRace->name);
        return response()->json($newRace, 201);
    }
}
