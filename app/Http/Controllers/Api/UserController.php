<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{User, Role, Team};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller {
    public function index() {
        return response()->json(User::with('role', 'teams')->get()->map(fn($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
            'role' => $u->role->name, 'athlete_id' => $u->athlete_id,
            'is_active' => $u->is_active,
            'teams' => $u->teams->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'type' => $t->type]),
        ]));
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,coach,athlete',
            'team_ids' => $request->role === 'admin' ? 'nullable|array' : 'required|array|min:1',
            'team_ids.*' => 'exists:teams,id',
        ]);

        if ($request->team_ids) $this->validateTeamAssignment($request->team_ids);

        $role = Role::where('name', $request->role)->firstOrFail();
        $teamIds = $request->team_ids ?? [];
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id,
            'team_id' => $teamIds[0] ?? null,
        ]);
        if (count($teamIds) > 0) $user->teams()->sync($teamIds);

        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role->name], 201);
    }

    public function update(Request $request, $id) {
        $user = User::findOrFail($id);
        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->filled('password')) $user->password = Hash::make($request->password);
        if ($request->has('role')) $user->role_id = Role::where('name', $request->role)->firstOrFail()->id;
        if ($request->has('athlete_id')) $user->athlete_id = $request->athlete_id;
        if ($request->has('is_active')) $user->is_active = $request->boolean('is_active');

        if ($request->has('team_ids')) {
            $request->validate([
                'team_ids' => 'required|array|min:1',
                'team_ids.*' => 'exists:teams,id',
            ]);
            $this->validateTeamAssignment($request->team_ids);
            $user->teams()->sync($request->team_ids);
            // Set active team to first if current not in list
            if (!in_array($user->team_id, $request->team_ids)) {
                $user->team_id = $request->team_ids[0];
            }
        }

        $user->save();
        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->name]);
    }

    public function destroy($id) {
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'User deleted']);
    }

    private function validateTeamAssignment(array $teamIds): void {
        $teams = Team::whereIn('id', $teamIds)->get();
        $clubs = $teams->where('type', 'club')->count();
        $nationals = $teams->where('type', 'national')->count();
        if ($clubs > 1) abort(422, 'A user can only be in one club team.');
        if ($nationals > 1) abort(422, 'A user can only be in one national team.');
    }
}
