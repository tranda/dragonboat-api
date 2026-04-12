<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EventsImportController extends Controller {
    private const EVENTS_API = 'https://events.motion.rs/api';

    public function fetchAthletes(Request $request) {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Login to events.motion.rs
        $loginRes = Http::post(self::EVENTS_API . '/login', [
            'username' => $request->username,
            'password' => $request->password,
        ]);

        if (!$loginRes->successful()) {
            return response()->json(['error' => 'Invalid credentials for events.motion.rs'], 401);
        }

        $token = $loginRes->json('data.token') ?? $loginRes->json('token');
        if (!$token) {
            return response()->json(['error' => 'No token received from events.motion.rs'], 500);
        }

        // Fetch athletes
        $athleteRes = Http::withToken($token)->get(self::EVENTS_API . '/athletes');
        if (!$athleteRes->successful()) {
            return response()->json(['error' => 'Failed to fetch athletes from events.motion.rs'], 500);
        }

        return response()->json($athleteRes->json());
    }
}
