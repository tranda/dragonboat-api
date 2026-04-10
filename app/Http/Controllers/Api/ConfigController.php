<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;

class ConfigController extends Controller {
    public function show() {
        $c = AppConfig::first();
        if (!$c) $c = AppConfig::create(['competition_year' => 2026, 'gender_policy' => ['mixedRatio' => ['standard' => ['minSameGender' => 8, 'maxSameGender' => 12], 'small' => ['minSameGender' => 4, 'maxSameGender' => 6]]], 'age_rules' => [['category' => '18U', 'maxAge' => 18], ['category' => '24U', 'maxAge' => 24], ['category' => 'Premier'], ['category' => 'Senior A', 'minAge' => 40], ['category' => 'Senior B', 'minAge' => 50], ['category' => 'Senior C', 'minAge' => 60], ['category' => 'Senior D', 'minAge' => 70], ['category' => 'BCP']]]);
        return response()->json(['competitionYear' => $c->competition_year, 'genderPolicy' => $c->gender_policy, 'ageCategoryRules' => $c->age_rules]);
    }

    public function update(Request $request) {
        $c = AppConfig::first() ?? new AppConfig();
        if ($request->has('competitionYear')) $c->competition_year = $request->input('competitionYear');
        if ($request->has('genderPolicy')) $c->gender_policy = $request->input('genderPolicy');
        if ($request->has('ageCategoryRules')) $c->age_rules = $request->input('ageCategoryRules');
        $c->save();
        return response()->json(['message' => 'Config saved']);
    }
}
