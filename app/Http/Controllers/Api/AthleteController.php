<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Athlete, ActivityLog};
use Illuminate\Http\Request;

class AthleteController extends Controller {
    public function index() { return response()->json(Athlete::all()); }

    public function store(Request $request) {
        $request->validate(['name' => 'required|string|max:255', 'gender' => 'required|in:M,F']);
        $athlete = Athlete::create($request->only(['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'preferred_side', 'notes']));
        ActivityLog::log('created', 'athlete', $athlete->name);
        return response()->json($athlete, 201);
    }

    public function update(Request $request, $id) {
        $athlete = Athlete::findOrFail($id);
        $athlete->update($request->only(['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'preferred_side', 'notes', 'is_removed']));
        ActivityLog::log('updated', 'athlete', $athlete->name);
        return response()->json($athlete);
    }

    public function destroy($id) {
        $athlete = Athlete::findOrFail($id);
        $athlete->update(['is_removed' => true]);
        ActivityLog::log('removed', 'athlete', $athlete->name);
        return response()->json(['message' => 'Athlete removed']);
    }

    public function restore($id) {
        $athlete = Athlete::findOrFail($id);
        $athlete->update(['is_removed' => false]);
        ActivityLog::log('restored', 'athlete', $athlete->name);
        return response()->json(['message' => 'Athlete restored']);
    }
}
