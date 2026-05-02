<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EventsImportController extends Controller {
    private const EVENTS_API = 'https://events.motion.rs/api';

    private function login(string $username, string $password) {
        $loginRes = Http::post(self::EVENTS_API . '/login', [
            'username' => $username,
            'password' => $password,
        ]);
        if (!$loginRes->successful()) return null;
        return $loginRes->json('data.token') ?? $loginRes->json('token');
    }

    public function fetchClubs(Request $request) {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
        $token = $this->login($request->username, $request->password);
        if (!$token) return response()->json(['error' => 'Invalid credentials for events.motion.rs'], 401);

        $res = Http::withToken($token)->get(self::EVENTS_API . '/clubs', ['active' => 1]);
        if (!$res->successful()) return response()->json(['error' => 'Failed to fetch clubs'], 500);

        $clubs = $res->json();
        // Normalize to a simple shape
        $normalized = array_map(fn($c) => [
            'id' => $c['id'] ?? null,
            'name' => $c['name'] ?? '',
            'country' => $c['country'] ?? null,
        ], is_array($clubs) ? $clubs : []);

        return response()->json($normalized);
    }

    public function fetchAthletes(Request $request) {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'club_id' => 'nullable|integer',
        ]);
        $token = $this->login($request->username, $request->password);
        if (!$token) return response()->json(['error' => 'Invalid credentials for events.motion.rs'], 401);

        $params = [];
        if ($request->filled('club_id')) $params['club_id'] = $request->input('club_id');

        $athleteRes = Http::withToken($token)->get(self::EVENTS_API . '/athletes', $params);
        if (!$athleteRes->successful()) {
            return response()->json(['error' => 'Failed to fetch athletes from events.motion.rs'], 500);
        }
        return response()->json($athleteRes->json());
    }
}
