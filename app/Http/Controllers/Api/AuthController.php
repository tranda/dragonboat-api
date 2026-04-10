<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller {
    public function login(Request $request) {
        $request->validate(['email' => 'required|email', 'password' => 'required']);
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password))
            return response()->json(['error' => 'Invalid credentials'], 401);
        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->name, 'athlete_id' => $user->athlete_id],
        ]);
    }
    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
    public function user(Request $request) {
        $user = $request->user()->load('role');
        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->name, 'athlete_id' => $user->athlete_id]);
    }
}
