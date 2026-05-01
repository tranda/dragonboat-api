<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Layout, ActivityLog, Race, Athlete};
use Illuminate\Http\Request;

class LayoutController extends Controller {
    public function show($raceId) {
        $l = Layout::where('race_id', $raceId)->firstOrFail();
        return response()->json(['race_id' => $l->race_id, 'drummer' => $l->drummer_id, 'helm' => $l->helm_id, 'left' => $l->left_seats, 'right' => $l->right_seats, 'reserves' => $l->reserves]);
    }

    public function update(Request $request, $raceId) {
        $l = Layout::where('race_id', $raceId)->firstOrFail();

        $oldDrummer = $l->drummer_id;
        $oldHelm = $l->helm_id;
        $oldLeft = $l->left_seats ?? [];
        $oldRight = $l->right_seats ?? [];
        $oldReserves = $l->reserves ?? [];

        $newDrummer = $request->input('drummer');
        $newHelm = $request->input('helm');
        $newLeft = $request->input('left', []);
        $newRight = $request->input('right', []);
        $newReserves = $request->input('reserves', []);

        $l->update([
            'drummer_id' => $newDrummer,
            'helm_id' => $newHelm,
            'left_seats' => $newLeft,
            'right_seats' => $newRight,
            'reserves' => $newReserves,
        ]);

        $race = Race::find($raceId);
        $details = $this->buildDiff(
            $oldDrummer, $newDrummer,
            $oldHelm, $newHelm,
            $oldLeft, $newLeft,
            $oldRight, $newRight,
            $oldReserves, $newReserves
        );
        ActivityLog::log('updated', 'layout', $race?->name ?? $raceId, $details);
        return response()->json(['message' => 'Layout saved']);
    }

    private function buildDiff($oldDrummer, $newDrummer, $oldHelm, $newHelm, $oldLeft, $newLeft, $oldRight, $newRight, $oldReserves, $newReserves): ?string {
        $norm = fn($id) => $id === null || $id === '' ? null : (int)$id;
        $oldDrummer = $norm($oldDrummer); $newDrummer = $norm($newDrummer);
        $oldHelm = $norm($oldHelm); $newHelm = $norm($newHelm);
        $oldLeft = array_map($norm, $oldLeft); $newLeft = array_map($norm, $newLeft);
        $oldRight = array_map($norm, $oldRight); $newRight = array_map($norm, $newRight);
        $oldReserves = array_map($norm, $oldReserves); $newReserves = array_map($norm, $newReserves);

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
