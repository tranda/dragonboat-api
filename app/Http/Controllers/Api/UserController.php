<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{User, Role};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller {
    public function index() {
        return response()->json(User::with('role', 'team')->get()->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role->name, 'athlete_id' => $u->athlete_id, 'is_active' => $u->is_active, 'team' => $u->team ? $u->team->name : null]));
    }
    public function store(Request $request) {
        $request->validate(['name' => 'required|string', 'email' => 'required|email|unique:users', 'password' => 'required|min:6', 'role' => 'required|in:admin,coach,athlete']);
        $role = Role::where('name', $request->role)->firstOrFail();
        $user = User::create(['name' => $request->name, 'email' => $request->email, 'password' => Hash::make($request->password), 'role_id' => $role->id, 'athlete_id' => $request->athlete_id]);
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
        $user->save();
        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->name]);
    }
    public function destroy($id) {
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
