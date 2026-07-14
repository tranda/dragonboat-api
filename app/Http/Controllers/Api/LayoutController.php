<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Layout, ActivityLog, Race, Athlete, Competition};
use Illuminate\Http\Request;

class LayoutController extends Controller {
    public function show($raceId) {
        $l = Layout::where('race_id', $raceId)->firstOrFail();
        return response()->json($this->respond($l, $raceId));
    }

    public function update(Request $request, $raceId) {
        Competition::guardLocked(Race::where('id', $raceId)->value('competition_id'));
        $l = Layout::where('race_id', $raceId)->firstOrFail();

        $oldState = $this->captureState($l);
        $newState = [
            'drummer' => $request->input('drummer'),
            'helm' => $request->input('helm'),
            'left' => $request->input('left', []),
            'right' => $request->input('right', []),
            'reserves' => $request->input('reserves', []),
        ];

        $this->applyState($l, $newState);

        // Clear redo stack: any previously-undone entries for this race are abandoned
        ActivityLog::where('entity_type', 'layout')
            ->where('entity_id', (string)$raceId)
            ->where('is_undone', true)
            ->delete();

        $race = Race::find($raceId);
        $details = $this->buildDiff($oldState, $newState);
        ActivityLog::log('updated', 'layout', $race?->name ?? $raceId, $details, (string)$raceId, $oldState, $newState, $race?->competition_id, $race?->team_id);

        return response()->json($this->respond($l->fresh(), $raceId));
    }

    public function undo($raceId) {
        Competition::guardLocked(Race::where('id', $raceId)->value('competition_id'));
        $entry = ActivityLog::where('entity_type', 'layout')
            ->where('entity_id', (string)$raceId)
            ->where('is_undone', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        if (!$entry || !$entry->before_state) {
            return response()->json(['message' => 'Nothing to undo'], 404);
        }
        $l = Layout::where('race_id', $raceId)->firstOrFail();
        $this->applyState($l, $entry->before_state);
        $entry->update(['is_undone' => true]);
        return response()->json($this->respond($l->fresh(), $raceId));
    }

    public function redo($raceId) {
        Competition::guardLocked(Race::where('id', $raceId)->value('competition_id'));
        $entry = ActivityLog::where('entity_type', 'layout')
            ->where('entity_id', (string)$raceId)
            ->where('is_undone', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
        if (!$entry || !$entry->after_state) {
            return response()->json(['message' => 'Nothing to redo'], 404);
        }
        $l = Layout::where('race_id', $raceId)->firstOrFail();
        $this->applyState($l, $entry->after_state);
        $entry->update(['is_undone' => false]);
        return response()->json($this->respond($l->fresh(), $raceId));
    }

    private function captureState(Layout $l): array {
        return [
            'drummer' => $l->drummer_id,
            'helm' => $l->helm_id,
            'left' => $l->left_seats ?? [],
            'right' => $l->right_seats ?? [],
            'reserves' => $l->reserves ?? [],
        ];
    }

    private function applyState(Layout $l, array $state): void {
        $l->update([
            'drummer_id' => $state['drummer'] ?? null,
            'helm_id' => $state['helm'] ?? null,
            'left_seats' => $state['left'] ?? [],
            'right_seats' => $state['right'] ?? [],
            'reserves' => $state['reserves'] ?? [],
        ]);
    }

    private function respond(Layout $l, $raceId): array {
        $canUndo = ActivityLog::where('entity_type', 'layout')
            ->where('entity_id', (string)$raceId)
            ->where('is_undone', false)
            ->exists();
        $canRedo = ActivityLog::where('entity_type', 'layout')
            ->where('entity_id', (string)$raceId)
            ->where('is_undone', true)
            ->exists();
        return [
            'race_id' => $l->race_id,
            'drummer' => $l->drummer_id,
            'helm' => $l->helm_id,
            'left' => $l->left_seats,
            'right' => $l->right_seats,
            'reserves' => $l->reserves,
            'can_undo' => $canUndo,
            'can_redo' => $canRedo,
        ];
    }

    private function buildDiff(array $old, array $new): ?string {
        $norm = fn($id) => $id === null || $id === '' ? null : (int)$id;
        $oldDrummer = $norm($old['drummer'] ?? null); $newDrummer = $norm($new['drummer'] ?? null);
        $oldHelm = $norm($old['helm'] ?? null); $newHelm = $norm($new['helm'] ?? null);
        $oldLeft = array_map($norm, $old['left'] ?? []); $newLeft = array_map($norm, $new['left'] ?? []);
        $oldRight = array_map($norm, $old['right'] ?? []); $newRight = array_map($norm, $new['right'] ?? []);
        $oldReserves = array_map($norm, $old['reserves'] ?? []); $newReserves = array_map($norm, $new['reserves'] ?? []);

        $ids = array_filter(array_unique(array_merge(
            [$oldDrummer, $newDrummer, $oldHelm, $newHelm],
            $oldLeft, $newLeft, $oldRight, $newRight, $oldReserves, $newReserves
        )));
        $names = $ids ? Athlete::whereIn('id', $ids)->pluck('name', 'id')->all() : [];
        $nameOf = fn($id) => $id ? ($names[$id] ?? "#$id") : null;

        $changes = [];
        $describe = function ($label, $oldId, $newId) use ($nameOf) {
            $oldName = $nameOf($oldId);
            $newName = $nameOf($newId);
            if ($oldName && $newName) return "$label: $oldName → $newName";
            if ($newName) return "$label: + $newName";
            if ($oldName) return "$label: − $oldName";
            return null;
        };

        if ($oldDrummer !== $newDrummer) {
            $c = $describe('drummer', $oldDrummer, $newDrummer);
            if ($c) $changes[] = $c;
        }
        if ($oldHelm !== $newHelm) {
            $c = $describe('helm', $oldHelm, $newHelm);
            if ($c) $changes[] = $c;
        }
        $maxL = max(count($oldLeft), count($newLeft));
        for ($i = 0; $i < $maxL; $i++) {
            $o = $oldLeft[$i] ?? null;
            $n = $newLeft[$i] ?? null;
            if ($o !== $n) {
                $c = $describe('L' . ($i + 1), $o, $n);
                if ($c) $changes[] = $c;
            }
        }
        $maxR = max(count($oldRight), count($newRight));
        for ($i = 0; $i < $maxR; $i++) {
            $o = $oldRight[$i] ?? null;
            $n = $newRight[$i] ?? null;
            if ($o !== $n) {
                $c = $describe('R' . ($i + 1), $o, $n);
                if ($c) $changes[] = $c;
            }
        }
        $maxRes = max(count($oldReserves), count($newReserves));
        for ($i = 0; $i < $maxRes; $i++) {
            $o = $oldReserves[$i] ?? null;
            $n = $newReserves[$i] ?? null;
            if ($o !== $n) {
                $c = $describe('reserve ' . ($i + 1), $o, $n);
                if ($c) $changes[] = $c;
            }
        }

        return empty($changes) ? null : implode('; ', $changes);
    }
}
