<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Athlete, ActivityLog, Race, Layout};
use Illuminate\Http\Request;

class AthleteController extends Controller {
    public function index(Request $request) {
        return response()->json(Athlete::where('team_id', $request->user()->team_id)->get());
    }

    public function store(Request $request) {
        $request->validate(['name' => 'required|string|max:255', 'gender' => 'required|in:M,F']);
        $data = $request->only(['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'preferred_side', 'is_helm', 'is_drummer', 'edbf_id', 'notes']);
        $data['team_id'] = $request->user()->team_id;
        $athlete = Athlete::create($data);
        ActivityLog::log('created', 'athlete', $athlete->name);
        return response()->json($athlete, 201);
    }

    public function update(Request $request, $id) {
        $athlete = Athlete::where('team_id', $request->user()->team_id)->findOrFail($id);
        $athlete->update($request->only(['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'preferred_side', 'is_helm', 'is_drummer', 'edbf_id', 'notes', 'is_removed']));
        ActivityLog::log('updated', 'athlete', $athlete->name);
        return response()->json($athlete);
    }

    public function destroy($id) {
        $athlete = Athlete::where('team_id', request()->user()->team_id)->findOrFail($id);
        $athlete->update(['is_removed' => true]);
        ActivityLog::log('removed', 'athlete', $athlete->name);
        return response()->json(['message' => 'Athlete removed']);
    }

    public function restore($id) {
        $athlete = Athlete::where('team_id', request()->user()->team_id)->findOrFail($id);
        $athlete->update(['is_removed' => false]);
        ActivityLog::log('restored', 'athlete', $athlete->name);
        return response()->json(['message' => 'Athlete restored']);
    }

    public function register(Request $request, $id) {
        $compId = $request->input('competition_id');
        $athlete = Athlete::where('team_id', $request->user()->team_id)->findOrFail($id);
        $athlete->competitions()->syncWithoutDetaching([$compId]);
        return response()->json(['message' => 'Registered']);
    }

    public function unregister(Request $request, $id) {
        $compId = $request->input('competition_id');
        $athlete = Athlete::where('team_id', $request->user()->team_id)->findOrFail($id);
        $athlete->competitions()->detach($compId);
        $this->removeFromCompetitionLayouts($athlete, $compId, $request->user()->team_id);
        return response()->json(['message' => 'Unregistered']);
    }

    /**
     * Remove an athlete from every crew layout belonging to a single competition.
     * Each changed layout gets its own undoable activity-log entry, matching the
     * format LayoutController uses so the existing undo/redo endpoints work on it.
     */
    private function removeFromCompetitionLayouts(Athlete $athlete, $compId, $teamId): void {
        $athleteId = (int)$athlete->id;
        $races = Race::where('competition_id', $compId)->where('team_id', $teamId)->get()->keyBy('id');
        $layouts = Layout::whereIn('race_id', $races->keys())->get();

        foreach ($layouts as $layout) {
            $oldState = [
                'drummer' => $layout->drummer_id,
                'helm' => $layout->helm_id,
                'left' => $layout->left_seats ?? [],
                'right' => $layout->right_seats ?? [],
                'reserves' => $layout->reserves ?? [],
            ];

            $newState = $oldState;
            $seatsRemoved = [];
            if ((int)$newState['drummer'] === $athleteId) { $newState['drummer'] = null; $seatsRemoved[] = 'drummer'; }
            if ((int)$newState['helm'] === $athleteId) { $newState['helm'] = null; $seatsRemoved[] = 'helm'; }

            // Positional seats: null the matching slot so other rows keep their place.
            foreach (['left' => 'L', 'right' => 'R'] as $key => $label) {
                foreach ($newState[$key] as $i => $seatId) {
                    if ((int)$seatId === $athleteId) { $newState[$key][$i] = null; $seatsRemoved[] = $label . ($i + 1); }
                }
            }

            // Reserves are a plain list, so drop the entry entirely.
            $filtered = array_values(array_filter($newState['reserves'], fn($rid) => (int)$rid !== $athleteId));
            if (count($filtered) !== count($newState['reserves'])) { $newState['reserves'] = $filtered; $seatsRemoved[] = 'reserve'; }

            if (empty($seatsRemoved)) continue;

            $layout->update([
                'drummer_id' => $newState['drummer'],
                'helm_id' => $newState['helm'],
                'left_seats' => $newState['left'],
                'right_seats' => $newState['right'],
                'reserves' => $newState['reserves'],
            ]);

            // Clear redo stack for this race, then log an undoable entry.
            ActivityLog::where('entity_type', 'layout')
                ->where('entity_id', (string)$layout->race_id)
                ->where('is_undone', true)
                ->delete();

            $race = $races->get($layout->race_id);
            $details = "unregistered {$athlete->name}: " . implode(', ', $seatsRemoved);
            ActivityLog::log('updated', 'layout', $race?->name ?? $layout->race_id, $details, (string)$layout->race_id, $oldState, $newState);
        }
    }
}
