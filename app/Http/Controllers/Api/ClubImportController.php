<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http, Cache};

class ClubImportController extends Controller {
    private const CLUB_API = 'https://club.motion.rs/api';
    private const TOKEN_TTL = 600; // 10 minutes

    /** Log in to club.motion.rs and return a bearer token (cached), or null. */
    private function login(string $email, string $password): ?string {
        $cacheKey = 'club_token_' . md5($email . '|' . $password);
        if ($cached = Cache::get($cacheKey)) return $cached;

        $res = Http::acceptJson()->asJson()->post(self::CLUB_API . '/login', [
            'email' => $email,
            'password' => $password,
        ]);
        if (!$res->successful()) return null;
        $token = $res->json('access_token') ?? $res->json('token') ?? $res->json('data.token');
        if ($token) Cache::put($cacheKey, $token, self::TOKEN_TTL);
        return $token;
    }

    /** Fetch every active member across all pages. */
    private function fetchMembers(string $token): array {
        $members = [];
        $page = 1;
        do {
            $res = Http::withToken($token)->acceptJson()
                ->get(self::CLUB_API . '/members', ['active' => 'true', 'page' => $page]);
            if (!$res->successful()) break;
            $body = $res->json();
            $rows = $body['data'] ?? (array) $body;
            foreach ($rows as $r) $members[] = $r;
            $lastPage = $body['meta']['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage);
        return $members;
    }

    /** Diacritic-insensitive, case-insensitive key for name matching (Serbian latin). */
    private function normalizeName(?string $name): string {
        $from = ['č','ć','š','ž','đ','Č','Ć','Š','Ž','Đ'];
        $to   = ['c','c','s','z','d','c','c','s','z','d'];
        $n = str_replace($from, $to, (string) $name);
        $n = mb_strtolower(trim($n));
        return preg_replace('/\s+/', ' ', $n); // collapse internal whitespace
    }

    /**
     * Sync membership numbers from club.motion.rs onto the active team's athletes.
     * Matches by name (disambiguating same-name athletes by birth year). Never
     * creates athletes; unmatched records on either side are reported back.
     * Pass dry_run=true to preview without writing.
     */
    public function sync(Request $request) {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'dry_run' => 'nullable|boolean',
        ]);
        $dryRun = $request->boolean('dry_run');
        $teamId = $request->header('X-Team-Id') ?: $request->user()->team_id;
        if (!$teamId) abort(422, 'No active team to sync into.');

        $token = $this->login($request->email, $request->password);
        if (!$token) return response()->json(['error' => 'Invalid credentials for club.motion.rs'], 401);

        $members = $this->fetchMembers($token);
        if (!$members) return response()->json(['error' => 'No members returned from club.motion.rs'], 502);

        // Index the team's live athletes by normalized name (a name can map to several).
        $athletes = Athlete::where('team_id', $teamId)->where('is_removed', false)->get();
        $byName = [];
        foreach ($athletes as $a) $byName[$this->normalizeName($a->name)][] = $a;

        $matched = [];        // [member_id, name] applied/would-apply
        $ambiguous = [];      // same name on >1 athlete, couldn't disambiguate
        $noAthlete = [];      // club member with no athlete in this team
        $matchedAthleteIds = [];

        foreach ($members as $m) {
            $memberId = $m['membership_number'] ?? null;
            $name = $m['name'] ?? '';
            if ($memberId === null || $name === '') continue;
            $candidates = $byName[$this->normalizeName($name)] ?? [];

            if (count($candidates) === 0) { $noAthlete[] = ['membership_number' => $memberId, 'name' => $name]; continue; }

            $athlete = null;
            if (count($candidates) === 1) {
                $athlete = $candidates[0];
            } else {
                // Disambiguate by birth year when the member has a date_of_birth.
                $year = isset($m['date_of_birth']) ? (int) substr((string) $m['date_of_birth'], 0, 4) : 0;
                $narrowed = $year ? array_values(array_filter($candidates, fn($c) => (int) $c->year_of_birth === $year)) : [];
                if (count($narrowed) === 1) $athlete = $narrowed[0];
            }

            if (!$athlete) { $ambiguous[] = ['membership_number' => $memberId, 'name' => $name]; continue; }

            $matchedAthleteIds[$athlete->id] = true;
            $matched[] = ['membership_number' => (int) $memberId, 'name' => $athlete->name];
            if (!$dryRun) {
                $update = ['member_id' => (int) $memberId];
                // Backfill birth year only when missing — never overwrite existing data.
                if (!$athlete->year_of_birth && isset($m['date_of_birth'])) {
                    $update['year_of_birth'] = (int) substr((string) $m['date_of_birth'], 0, 4) ?: null;
                }
                $athlete->update($update);
            }
        }

        // Team athletes that no club member matched.
        $noMember = $athletes->reject(fn($a) => isset($matchedAthleteIds[$a->id]))
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values();

        return response()->json([
            'dryRun' => $dryRun,
            'totalMembers' => count($members),
            'matchedCount' => count($matched),
            'matched' => $matched,
            'membersWithoutAthlete' => $noAthlete,
            'athletesWithoutMember' => $noMember,
            'ambiguous' => $ambiguous,
        ]);
    }
}
