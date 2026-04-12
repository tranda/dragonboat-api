<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Race, Layout, Athlete};
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Barryvdh\DomPDF\Facade\Pdf;

class CrewSheetController extends Controller {
    public function show(Request $request) {
        $token = $request->query('token');
        if (!$token) return response('Unauthorized', 401);
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) return response('Unauthorized', 401);

        $ids = explode(',', $request->query('ids', ''));
        $races = Race::whereIn('id', $ids)->orderBy('display_order')->get();
        $athletes = Athlete::all()->keyBy('id');

        $getName = function($id) use ($athletes) {
            if (!$id) return '';
            return $athletes[$id]->name ?? '?';
        };

        $pages = '';
        foreach ($races as $ri => $race) {
            $layout = Layout::where('race_id', $race->id)->first();
            if (!$layout) continue;

            $left = $layout->left_seats ?? [];
            $right = $layout->right_seats ?? [];
            $reserves = $layout->reserves ?? [];

            $paddlersFilled = count(array_filter($left)) + count(array_filter($right));
            $totalPaddlers = $race->num_rows * 2;

            $rows = '';
            // Drummer
            $rows .= '<tr style="background:#fff8eb"><td class="seat">DR</td><td class="num">1</td><td class="name">' . e($getName($layout->drummer_id)) . '</td><td class="num"></td><td></td></tr>';

            // Paddlers
            for ($i = 0; $i < $race->num_rows; $i++) {
                $leftNum = $i + 2;
                $rightNum = $race->num_rows + $i + 2;
                $ln = $left[$i] ?? null;
                $rn = $right[$i] ?? null;
                $rows .= '<tr>'
                    . '<td class="seat">' . ($i + 1) . '</td>'
                    . '<td class="num">' . $leftNum . '</td>'
                    . '<td class="name">' . e($getName($ln)) . '</td>'
                    . '<td class="num">' . $rightNum . '</td>'
                    . '<td class="name">' . e($getName($rn)) . '</td>'
                    . '</tr>';
            }

            // Helm
            $helmNum = $race->num_rows * 2 + 2;
            $rows .= '<tr style="background:#fff8eb"><td class="seat">HM</td><td class="num"></td><td></td><td class="num">' . $helmNum . '</td><td class="name">' . e($getName($layout->helm_id)) . '</td></tr>';

            // Reserves
            $reserveHtml = '';
            if (count($reserves) > 0) {
                $resNames = array_map(function($id) use ($getName) { return $getName($id); }, array_filter($reserves));
                $reserveHtml = '<p class="reserves"><b>Reserves:</b> ' . e(implode(', ', $resNames)) . '</p>';
            }

            $pageBreak = $ri < count($races) - 1 ? 'page-break-after: always;' : '';

            $pages .= '
            <div class="page" style="' . $pageBreak . '">
                <h2>' . e($race->name) . '</h2>
                <p class="sub">' . ($race->boat_type === 'standard' ? 'Standard (20)' : 'Small (10)') . ' &middot; ' . e($race->distance) . ' &middot; ' . $paddlersFilled . '/' . $totalPaddlers . ' paddlers</p>
                <table>
                    <thead><tr>
                        <th style="width:30px"></th>
                        <th style="width:24px">#</th>
                        <th>Left</th>
                        <th style="width:24px">#</th>
                        <th>Right</th>
                    </tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
                ' . $reserveHtml . '
            </div>';
        }

        $html = '<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 12px; }
.page { padding: 15mm; }
h2 { font-size: 16px; margin-bottom: 4px; }
.sub { font-size: 10px; color: #888; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #ccc; padding: 3px 6px; text-align: left; }
th { background: #f0f0f0; font-weight: 600; color: #555; text-align: center; font-size: 10px; }
th:nth-child(3), th:nth-child(5) { text-align: left; }
.seat { text-align: center; color: #999; font-size: 9px; width: 30px; }
.num { text-align: center; color: #999; font-size: 9px; width: 24px; }
.name { font-weight: 500; }
.reserves { margin-top: 8px; font-size: 11px; }
</style>
</head><body>' . $pages . '</body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $filename = count($races) === 1 ? ($races[0]->name . '.pdf') : 'crew-sheets.pdf';

        return $pdf->stream($filename);
    }
}
