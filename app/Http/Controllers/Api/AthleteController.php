<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\Request;

class AthleteController extends Controller {
    public function index() { return response()->json(Athlete::all()); }

    public function store(Request $request) {
        $request->validate(['name' => 'required|string|max:255', 'gender' => 'required|in:M,F']);
        $athlete = Athlete::create($request->only(['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'preferred_side']));
        return response()->json($athlete, 201);
    }

    public function update(Request $request, $id) {
        $athlete = Athlete::findOrFail($id);
        $athlete->update($request->only(['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'preferred_side', 'is_removed']));
        return response()->json($athlete);
    }

    public function destroy($id) {
        Athlete::findOrFail($id)->update(['is_removed' => true]);
        return response()->json(['message' => 'Athlete removed']);
    }

    public function restore($id) {
        Athlete::findOrFail($id)->update(['is_removed' => false]);
        return response()->json(['message' => 'Athlete restored']);
    }
}
