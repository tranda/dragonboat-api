<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Layout;
use Illuminate\Http\Request;

class LayoutController extends Controller {
    public function show($raceId) {
        $l = Layout::where('race_id', $raceId)->firstOrFail();
        return response()->json(['race_id' => $l->race_id, 'drummer' => $l->drummer_id, 'helm' => $l->helm_id, 'left' => $l->left_seats, 'right' => $l->right_seats, 'reserves' => $l->reserves]);
    }

    public function update(Request $request, $raceId) {
        $l = Layout::where('race_id', $raceId)->firstOrFail();
        $l->update(['drummer_id' => $request->input('drummer'), 'helm_id' => $request->input('helm'), 'left_seats' => $request->input('left'), 'right_seats' => $request->input('right'), 'reserves' => $request->input('reserves', [])]);
        return response()->json(['message' => 'Layout saved']);
    }
}
