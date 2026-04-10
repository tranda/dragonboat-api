<?php
namespace Database\Seeders;
use App\Models\{Athlete, Race, Layout, AppConfig, BenchFactor, User, Role};
use Illuminate\Database\Seeder;

class DataSeeder extends Seeder {
    public function run(): void {
        $dataPath = base_path('../dragonboat-sittinglayout/src/data/data.json');
        if (!file_exists($dataPath)) { $this->command->warn("data.json not found — skipping"); return; }
        $data = json_decode(file_get_contents($dataPath), true);

        foreach ($data['athletes'] as $a) {
            Athlete::create(['name' => $a['name'], 'weight' => $a['weight'] ?? 0, 'gender' => $a['gender'], 'year_of_birth' => $a['yearOfBirth'] ?? null, 'is_bcp' => $a['isBCP'] ?? false]);
        }
        foreach ($data['races'] as $r) {
            Race::create(['id' => $r['id'], 'name' => $r['name'], 'boat_type' => $r['boatType'], 'num_rows' => $r['numRows'], 'distance' => $r['distance'] ?? null, 'gender_category' => $r['genderCategory'] ?? 'Open', 'age_category' => $r['ageCategory'] ?? 'Premier', 'category' => $r['category'] ?? null]);
        }
        foreach ($data['layouts'] as $raceId => $l) {
            Layout::create(['race_id' => $raceId, 'drummer_id' => $l['drummer'], 'helm_id' => $l['helm'], 'left_seats' => $l['left'], 'right_seats' => $l['right'], 'reserves' => $l['reserves'] ?? []]);
        }
        BenchFactor::create(['boat_type' => 'standard', 'factors' => $data['benchFactors']['standard']]);
        BenchFactor::create(['boat_type' => 'small', 'factors' => $data['benchFactors']['small']]);
        AppConfig::create(['competition_year' => 2026, 'gender_policy' => ['mixedRatio' => ['standard' => ['minSameGender' => 8, 'maxSameGender' => 12], 'small' => ['minSameGender' => 4, 'maxSameGender' => 6]]], 'age_rules' => [['category' => '18U', 'maxAge' => 18], ['category' => '24U', 'maxAge' => 24], ['category' => 'Premier'], ['category' => 'Senior A', 'minAge' => 40], ['category' => 'Senior B', 'minAge' => 50], ['category' => 'Senior C', 'minAge' => 60], ['category' => 'Senior D', 'minAge' => 70], ['category' => 'BCP']]]);

        $admin = Role::where('name', 'admin')->first();
        User::create(['name' => 'Admin', 'email' => 'admin@dragonboat.com', 'password' => bcrypt('password123'), 'role_id' => $admin->id]);
        $this->command->info('Seeded: ' . Athlete::count() . ' athletes, ' . Race::count() . ' races');
    }
}
