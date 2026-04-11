<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\BenchFactor;
use App\Models\Layout;
use App\Models\Race;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bulk import from the Google Apps Script "Export Crews" flow.
 *
 * Payload shape matches the old convert_excel.py output (src/data/data.json
 * in the frontend repo), so the sheet remains the single source of truth for
 * crew composition.
 *
 * Semantics:
 *   - Athletes: upsert by name. Manual edits made in the UI survive as long
 *     as the name still matches in the sheet. Athletes absent from the sheet
 *     are marked is_removed=true (soft-removed), never hard deleted, because
 *     historical layouts may still reference them.
 *   - Races + layouts: fully reseeded. The sheet wins.
 *   - Bench factors: upsert per boat type.
 */
class ImportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'mode'                    => 'nullable|in:merge,overwrite',

            'athletes'                  => 'required|array|min:1',
            'athletes.*.id'             => 'required|integer',
            'athletes.*.name'           => 'required|string|max:255',
            'athletes.*.weight'         => 'nullable|numeric',
            'athletes.*.gender'         => 'required|in:M,F,U',
            'athletes.*.yearOfBirth'    => 'nullable|integer',

            'races'                   => 'required|array|min:1',
            'races.*.id'              => 'required|string',
            'races.*.name'            => 'required|string',
            'races.*.boatType'        => 'required|in:standard,small',
            'races.*.numRows'         => 'required|integer',
            'races.*.distance'        => 'nullable|string',
            'races.*.category'        => 'nullable|string',

            'layouts'                 => 'required|array',

            'benchFactors.standard'   => 'required|array',
            'benchFactors.small'      => 'required|array',
        ]);

        $mode = $data['mode'] ?? 'merge';

        return DB::transaction(function () use ($data, $mode) {
            // ---- Athletes: upsert by name, build payloadId → dbId map ----
            $idMap = [];
            $sheetNames = [];
            foreach ($data['athletes'] as $a) {
                $name = trim($a['name']);
                $sheetNames[] = $name;

                // Map 'U' (unknown) to 'M' since the schema enum is M/F only.
                $gender = $a['gender'] === 'U' ? 'M' : $a['gender'];

                $yob = $a['yearOfBirth'] ?? null;

                $athlete = Athlete::where('name', $name)->first();
                if ($athlete) {
                    $athlete->update([
                        'weight'        => $a['weight'] ?? null,
                        'gender'        => $gender,
                        'year_of_birth' => $yob,
                        'is_removed'    => false,
                    ]);
                } else {
                    $athlete = Athlete::create([
                        'name'          => $name,
                        'weight'        => $a['weight'] ?? null,
                        'gender'        => $gender,
                        'year_of_birth' => $yob,
                    ]);
                }
                $idMap[$a['id']] = $athlete->id;
            }

            // Athletes that no longer appear in the sheet → soft-remove.
            Athlete::whereNotIn('name', $sheetNames)->update(['is_removed' => true]);

            // ---- Races + layouts ----
            // overwrite: wipe all races and layouts, then recreate from payload
            //            (old behavior — resets display_order and drops races
            //             that aren't in the payload).
            // merge (default): match existing races by name, update them in
            //            place preserving id and display_order. New races are
            //            appended. Races in DB but not in payload are kept.
            if ($mode === 'overwrite') {
                Layout::query()->delete();
                Race::query()->delete();
                $nextOrder = 0;
            } else {
                $nextOrder = ((int) Race::max('display_order')) + 1;
            }

            $map = function ($pid) use ($idMap) {
                if ($pid === null) return null;
                return $idMap[$pid] ?? null;
            };

            foreach ($data['races'] as $r) {
                $name = $r['name'];

                // Derive gender_category from the sheet tab name.
                $genderCategory = 'Open';
                if (stripos($name, 'Women') !== false) {
                    $genderCategory = 'Women';
                } elseif (stripos($name, 'Mixed') !== false) {
                    $genderCategory = 'Mixed';
                }

                // Derive age_category from the sheet tab name. Matches the
                // frontend excelImport.ts logic.
                $ageCategory = 'Premier';
                if (stripos($name, 'Senior A') !== false)      $ageCategory = 'Senior A';
                elseif (stripos($name, 'Senior B') !== false)  $ageCategory = 'Senior B';
                elseif (stripos($name, 'Senior C') !== false)  $ageCategory = 'Senior C';
                elseif (stripos($name, 'Senior D') !== false)  $ageCategory = 'Senior D';
                elseif (stripos($name, '18U') !== false)       $ageCategory = '18U';
                elseif (stripos($name, '24U') !== false)       $ageCategory = '24U';
                elseif (stripos($name, 'BCP') !== false)       $ageCategory = 'BCP';

                $fields = [
                    'name'            => $name,
                    'boat_type'       => $r['boatType'],
                    'num_rows'        => $r['numRows'],
                    'distance'        => $r['distance'] ?? null,
                    'gender_category' => $genderCategory,
                    'age_category'    => $ageCategory,
                    'category'        => $r['category'] ?? null,
                ];

                $race = Race::where('name', $name)->first();
                if ($race) {
                    // Update existing: preserve id and display_order.
                    $race->update($fields);
                } else {
                    $race = Race::create(array_merge(
                        ['id' => $r['id'], 'display_order' => $nextOrder++],
                        $fields
                    ));
                }

                // Replace the layout for this race with the imported one.
                Layout::where('race_id', $race->id)->delete();
                $layout = $data['layouts'][$r['id']] ?? null;
                if (!$layout) {
                    Layout::create([
                        'race_id'     => $race->id,
                        'drummer_id'  => null,
                        'helm_id'     => null,
                        'left_seats'  => array_fill(0, $r['numRows'], null),
                        'right_seats' => array_fill(0, $r['numRows'], null),
                        'reserves'    => [],
                    ]);
                    continue;
                }

                Layout::create([
                    'race_id'     => $race->id,
                    'drummer_id'  => $map($layout['drummer'] ?? null),
                    'helm_id'     => $map($layout['helm'] ?? null),
                    'left_seats'  => array_map($map, $layout['left'] ?? []),
                    'right_seats' => array_map($map, $layout['right'] ?? []),
                    'reserves'    => array_values(array_filter(
                        array_map($map, $layout['reserves'] ?? []),
                        fn ($v) => $v !== null
                    )),
                ]);
            }

            // ---- Bench factors ----
            BenchFactor::updateOrCreate(
                ['boat_type' => 'standard'],
                ['factors' => array_map('floatval', $data['benchFactors']['standard'])]
            );
            BenchFactor::updateOrCreate(
                ['boat_type' => 'small'],
                ['factors' => array_map('floatval', $data['benchFactors']['small'])]
            );

            return response()->json([
                'message'  => 'Import OK',
                'athletes' => count($data['athletes']),
                'races'    => count($data['races']),
            ]);
        });
    }
}
