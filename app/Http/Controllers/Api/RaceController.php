<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Race;
use App\Models\Layout;
use Illuminate\Http\Request;

class RaceController extends Controller {
    public function index() { return response()->json(Race::all()); }

    public function store(Request $request) {
        $request->validate(['id' => 'required|string|unique:races,id', 'name' => 'required|string', 'boat_type' => 'required|in:standard,small', 'num_rows' => 'required|integer', 'gender_category' => 'required|in:Open,Women,Mixed', 'age_category' => 'required|string']);
        $race = Race::create($request->only(['id', 'name', 'boat_type', 'num_rows', 'distance', 'gender_category', 'age_category', 'category']));
        Layout::create(['race_id' => $race->id, 'drummer_id' => null, 'helm_id' => null, 'left_seats' => array_fill(0, $race->num_rows, null), 'right_seats' => array_fill(0, $race->num_rows, null), 'reserves' => []]);
        return response()->json($race, 201);
    }

    public function update(Request $request, $id) {
        $race = Race::findOrFail($id);
        $race->update($request->only(['name', 'boat_type', 'num_rows', 'distance', 'gender_category', 'age_category', 'category']));
        return response()->json($race);
    }

    public function destroy($id) {
        Race::findOrFail($id)->delete();
        return response()->json(['message' => 'Race deleted']);
    }

    public function duplicate($id) {
        $race = Race::findOrFail($id);
        $layout = Layout::where('race_id', $id)->first();
        $newId = $race->id . '_copy_' . time();
        $newRace = Race::create(['id' => $newId, 'name' => $race->name . ' (copy)', 'boat_type' => $race->boat_type, 'num_rows' => $race->num_rows, 'distance' => $race->distance, 'gender_category' => $race->gender_category, 'age_category' => $race->age_category, 'category' => $race->category]);
        if ($layout) Layout::create(['race_id' => $newId, 'drummer_id' => $layout->drummer_id, 'helm_id' => $layout->helm_id, 'left_seats' => $layout->left_seats, 'right_seats' => $layout->right_seats, 'reserves' => $layout->reserves]);
        return response()->json($newRace, 201);
    }
}
