<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Race, Layout, Athlete};
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CrewSheetController extends Controller {
    public function show(Request $request) {
        // Authenticate via token query parameter (since this opens in a new tab)
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
        foreach ($races as $race) {
            $layout = Layout::where('race_id', $race->id)->first();
            if (!$layout) continue;

            $left = $layout->left_seats ?? [];
            $right = $layout->right_seats ?? [];
            $reserves = $layout->reserves ?? [];

            $paddlersFilled = count(array_filter($left)) + count(array_filter($right));
            $totalPaddlers = $race->num_rows * 2;

            $rows = '';
            // Drummer - seat 1
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

            $reserveHtml = '';
            if (count($reserves) > 0) {
                $resNames = array_map(function($id) use ($getName) { return $getName($id); }, array_filter($reserves));
                $reserveHtml = '<p style="margin-top:10px;font-size:13px"><b>Reserves:</b> ' . e(implode(', ', $resNames)) . '</p>';
            }

            $pages .= '
            <div class="page">
                <h2>' . e($race->name) . '</h2>
                <p class="sub">' . ($race->boat_type === 'standard' ? 'Standard (20)' : 'Small (10)') . ' · ' . e($race->distance) . ' · ' . $paddlersFilled . '/' . $totalPaddlers . ' paddlers</p>
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
<html><head>
<meta charset="utf-8">
<title>Crew Sheets</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #222; }
.page { padding: 20mm 15mm; page-break-after: always; }
.page:last-child { page-break-after: auto; }
h2 { font-size: 18px; margin-bottom: 4px; }
.sub { font-size: 11px; color: #888; margin-bottom: 12px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th, td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; }
th { background: #f0f0f0; font-weight: 600; color: #555; text-align: center; }
th:nth-child(3), th:nth-child(5) { text-align: left; }
.seat { text-align: center; color: #999; font-size: 10px; width: 30px; }
.num { text-align: center; color: #999; font-size: 10px; width: 24px; }
.name { font-weight: 500; }
p { font-size: 12px; }
.toolbar { position: sticky; top: 0; z-index: 10; padding: 12px 20px; border-bottom: 1px solid #ddd; display: flex; align-items: center; justify-content: space-between; background: #f8f8f8; }
.btn { padding: 8px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
@media print {
    .toolbar { display: none !important; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head><body>
<div class="toolbar">
    <span style="font-size:13px;color:#666">Use Print or Save as PDF from your browser.</span>
    <button class="btn" onclick="window.print()">Print</button>
</div>
' . $pages . '
</body></html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
