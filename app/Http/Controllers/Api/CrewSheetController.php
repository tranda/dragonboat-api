<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Race, Layout, Athlete};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class CrewSheetController extends Controller {
    public function createToken(Request $request) {
        // Clean up expired tokens
        DB::table('pdf_tokens')->where('expires_at', '<', now())->delete();

        $token = Str::random(48);
        DB::table('pdf_tokens')->insert([
            'token' => $token,
            'user_id' => $request->user()->id,
            'expires_at' => now()->addSeconds(60),
        ]);
        return response()->json(['token' => $token]);
    }

    public function show(Request $request) {
        $token = $request->query('token');
        if (!$token) return response('Unauthorized', 401);

        // Validate short-lived PDF token
        $row = DB::table('pdf_tokens')->where('token', $token)->where('expires_at', '>=', now())->first();
        if (!$row) return response('Token expired or invalid', 401);

        // Delete after use (single-use)
        DB::table('pdf_tokens')->where('token', $token)->delete();

        $ids = explode(',', $request->query('ids', ''));
        $races = Race::whereIn('id', $ids)->orderBy('display_order')->get();
        $athletes = Athlete::all()->keyBy('id');

        $getName = function($id) use ($athletes) {
            if (!$id) return '';
            return $athletes[$id]->name ?? '';
        };

        $pages = '';
        foreach ($races as $ri => $race) {
            $layout = Layout::where('race_id', $race->id)->first();
            if (!$layout) continue;

            $left = $layout->left_seats ?? [];
            $right = $layout->right_seats ?? [];
            $reserves = $layout->reserves ?? [];
            $numRows = $race->num_rows;
            $helmNum = $numRows * 2 + 2;
            $reservePairs = $race->boat_type === 'standard' ? 2 : 1;

            $paddlersFilled = count(array_filter($left)) + count(array_filter($right));
            $totalPaddlers = $numRows * 2;

            // Build left and right column entries
            // Left: 01=drummer, 02..numRows+1=left paddlers, reserves left
            // Right: helmNum=helm, numRows+2..numRows*2+1=right paddlers, reserves right

            $leftRows = '';
            $rightRows = '';

            // Row 1: Drummer (left) | Helm (right) — separated section
            $leftRows .= '<tr class="special"><td class="vn">' . sprintf('%02d', 1) . '</td><td class="nm">' . e($getName($layout->drummer_id)) . '</td></tr>';
            $rightRows .= '<tr class="special"><td class="vn">' . sprintf('%02d', $helmNum) . '</td><td class="nm">' . e($getName($layout->helm_id)) . '</td></tr>';

            // Spacer
            $leftRows .= '<tr class="spacer"><td colspan="2"></td></tr>';
            $rightRows .= '<tr class="spacer"><td colspan="2"></td></tr>';

            // Paddlers
            for ($i = 0; $i < $numRows; $i++) {
                $leftNum = $i + 2;
                $rightNum = $numRows + $i + 2;
                $ln = $left[$i] ?? null;
                $rn = $right[$i] ?? null;
                $leftRows .= '<tr><td class="vn">' . sprintf('%02d', $leftNum) . '</td><td class="nm">' . e($getName($ln)) . '</td></tr>';
                $rightRows .= '<tr><td class="vn">' . sprintf('%02d', $rightNum) . '</td><td class="nm">' . e($getName($rn)) . '</td></tr>';
            }

            // Spacer before reserves
            $leftRows .= '<tr class="spacer"><td colspan="2"></td></tr>';
            $rightRows .= '<tr class="spacer"><td colspan="2"></td></tr>';

            // Reserves
            for ($p = 0; $p < $reservePairs; $p++) {
                $li = $p;
                $ri = $reservePairs + $p;
                $leftResNum = $helmNum + $li + 1;
                $rightResNum = $helmNum + $ri + 1;
                $lid = $reserves[$li] ?? null;
                $rid = $reserves[$ri] ?? null;
                $leftRows .= '<tr><td class="vn">' . sprintf('%02d', $leftResNum) . '</td><td class="nm">' . e($getName($lid)) . '</td></tr>';
                $rightRows .= '<tr><td class="vn">' . sprintf('%02d', $rightResNum) . '</td><td class="nm">' . e($getName($rid)) . '</td></tr>';
            }

            $pageBreak = $ri < count($races) - 1 ? 'page-break-after: always;' : '';

            $pages .= '
            <div class="page" style="' . $pageBreak . '">
                <h2>' . e($race->name) . '</h2>
                <p class="sub">' . ($race->boat_type === 'standard' ? 'Standard (20)' : 'Small (10)') . ' &middot; ' . e($race->distance) . ' &middot; ' . $paddlersFilled . '/' . $totalPaddlers . ' paddlers</p>
                <table class="outer"><tr>
                    <td class="col-left">
                        <table class="crew">
                            <thead><tr><th class="vnh">Vest No</th><th class="nmh">Competitors\' Name</th></tr></thead>
                            <tbody>' . $leftRows . '</tbody>
                        </table>
                    </td>
                    <td class="col-gap"></td>
                    <td class="col-right">
                        <table class="crew">
                            <thead><tr><th class="vnh">Vest No</th><th class="nmh">Competitors\' Name</th></tr></thead>
                            <tbody>' . $rightRows . '</tbody>
                        </table>
                    </td>
                </tr></table>
            </div>';
        }

        $html = '<!DOCTYPE html>
<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 11px; }
.page { padding: 12mm 15mm; }
h2 { font-size: 16px; margin-bottom: 3px; }
.sub { font-size: 9px; color: #888; margin-bottom: 10px; }
table.outer { width: 100%; border-collapse: collapse; }
table.outer td { vertical-align: top; border: none; padding: 0; }
.col-left { width: 48%; }
.col-right { width: 48%; }
.col-gap { width: 4%; }
table.crew { border-collapse: collapse; width: 100%; }
table.crew th, table.crew td { border-bottom: 1px solid #999; padding: 4px 6px; }
.vnh { text-align: left; font-size: 9px; font-weight: normal; color: #555; width: 40px; border-bottom: 2px solid #333 !important; }
.nmh { text-align: left; font-size: 9px; font-weight: normal; color: #555; border-bottom: 2px solid #333 !important; }
.vn { font-weight: bold; font-size: 11px; width: 40px; }
.nm { font-size: 11px; }
tr.special td { border-bottom: 2px solid #333; }
tr.spacer td { border-bottom: none; height: 8px; }
</style>
</head><body>' . $pages . '</body></html>';

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $filename = count($races) === 1 ? ($races[0]->name . '.pdf') : 'crew-sheets.pdf';

        return $pdf->stream($filename);
    }
}
