<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http, Cache};

class EventsImportController extends Controller {
    private const EVENTS_API = 'https://events.motion.rs/api';
    private const TOKEN_TTL = 600; // 10 minutes

    private function login(string $username, string $password) {
        $cacheKey = 'events_token_' . md5($username . '|' . $password);
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        $loginRes = Http::post(self::EVENTS_API . '/login', [
            'username' => $username,
            'password' => $password,
        ]);
        if (!$loginRes->successful()) return null;
        $token = $loginRes->json('data.token') ?? $loginRes->json('token');
        if ($token) Cache::put($cacheKey, $token, self::TOKEN_TTL);
        return $token;
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

    // List available events for the race-import picker. The events.motion.rs
    // /events endpoint is public (no auth), returning events with available=1.
    public function fetchEventsList(Request $request) {
        $res = Http::get(self::EVENTS_API . '/events');
        if (!$res->successful()) return response()->json(['error' => 'Failed to fetch events'], 502);

        $events = $res->json();
        $normalized = array_map(fn($e) => [
            'id' => $e['id'] ?? null,
            'name' => $e['name'] ?? '',
            'year' => $e['year'] ?? null,
            'location' => $e['location'] ?? null,
        ], is_array($events) ? $events : []);
        // Newest first (by year), then name.
        usort($normalized, fn($a, $b) => ($b['year'] ?? 0) <=> ($a['year'] ?? 0) ?: strcmp($a['name'], $b['name']));

        return response()->json($normalized);
    }

    // List clubs for the race-import picker. The events.motion.rs /clubs
    // endpoint is public (no auth), returning active clubs ordered by name.
    public function fetchRaceClubs(Request $request) {
        $res = Http::get(self::EVENTS_API . '/clubs', ['active' => 1]);
        if (!$res->successful()) return response()->json(['error' => 'Failed to fetch clubs'], 502);

        $clubs = $res->json();
        $normalized = array_map(fn($c) => [
            'id' => $c['id'] ?? null,
            'name' => $c['name'] ?? '',
            'country' => $c['country'] ?? null,
        ], is_array($clubs) ? $clubs : []);

        return response()->json($normalized);
    }

    // Fetch an event's race program for the chosen club and normalize it into our
    // Race shape: one race per discipline, each with a schedule[] of its stages.
    // Auth is a long-lived races.read API key from config/env; event and club
    // are chosen in the UI and passed as request params.
    public function fetchRaces(Request $request) {
        $request->validate(['event_id' => 'required|integer', 'club_id' => 'required|integer']);

        $apiKey = config('services.events.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'Race import is not configured (set EVENTS_API_KEY).'], 500);
        }

        $res = Http::withHeaders(['X-API-Key' => $apiKey])
            ->get(self::EVENTS_API . '/race-results/for-club', [
                'event_id' => $request->input('event_id'),
                'club_id' => $request->input('club_id'),
            ]);
        if (in_array($res->status(), [401, 403], true)) {
            return response()->json(['error' => 'Events API key rejected (check the key and its races.read scope).'], 502);
        }
        if (!$res->successful()) {
            return response()->json(['error' => 'Failed to fetch races from events.motion.rs'], 502);
        }

        $rows = $res->json('data') ?? [];
        $byDiscipline = [];
        foreach ($rows as $row) {
            $d = $row['discipline'] ?? [];
            $did = $row['discipline_id'] ?? ($d['id'] ?? null);
            if ($did === null) continue;

            if (!isset($byDiscipline[$did])) {
                $boatGroup = trim((string) ($d['boat_group'] ?? ''));
                $genderGroup = trim((string) ($d['gender_group'] ?? ''));
                $ageGroup = trim((string) ($d['age_group'] ?? ''));
                $distance = $d['distance'] ?? null;
                $name = trim(preg_replace('/\s+/', ' ', "$boatGroup $genderGroup $ageGroup " . ($distance !== null ? "{$distance}m" : '')));
                $byDiscipline[$did] = [
                    'discipline_id' => $did,
                    'name' => $name,
                    'boat_type' => $this->mapBoatType($boatGroup),
                    'num_rows' => $this->mapBoatType($boatGroup) === 'small' ? 5 : 10,
                    'distance' => $distance !== null ? (string) $distance : '',
                    'gender_category' => $this->mapGender($genderGroup),
                    'age_category' => $this->mapAge($ageGroup),
                    'category' => $name,
                    'schedule' => [],
                ];
            }

            $stage = $this->normalizeStage((string) ($row['stage'] ?? ''));
            $time = $row['race_time'] ?? null;
            if ($stage !== '' && $time) {
                $byDiscipline[$did]['schedule'][] = ['stage' => $stage, 'time' => $time];
            }
        }

        return response()->json(array_values($byDiscipline));
    }

    private function mapBoatType(string $g): string {
        return str_contains(strtolower($g), '10') ? 'small' : 'standard';
    }

    private function mapGender(string $g): string {
        $g = strtolower($g);
        if (str_contains($g, 'mix')) return 'Mixed';
        if (str_contains($g, 'women') || str_contains($g, 'female') || $g === 'w') return 'Women';
        return 'Open';
    }

    private function mapAge(string $g): string {
        $s = strtolower(trim($g));
        if ($s === '') return 'Premier';
        if (str_contains($s, 'bcp') || str_contains($s, 'breast')) return 'BCP';
        if (str_contains($s, '18')) return '18U';
        if (str_contains($s, '24')) return '24U';
        if (str_contains($s, 'premier')) return 'Premier';
        if (preg_match('/senior\s*([a-d])/i', $s, $m)) return 'Senior ' . strtoupper($m[1]);
        if (str_contains($s, 'senior')) return 'Senior A';
        return 'Premier';
    }

    private function normalizeStage(string $s): string {
        $s = trim($s);
        if ($s === '') return '';
        if (preg_match('/^semi\s*final(.*)$/i', $s, $m)) return 'Semifinal' . rtrim($m[1]);
        return $s;
    }
}
