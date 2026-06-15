<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Antrian;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function analitik(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(6)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $query = Antrian::whereBetween('tanggal', [$startDate, $endDate]);

        $ringkasan = [
            'total' => (clone $query)->count(),
            'waiting' => (clone $query)->where('status', 'waiting')->count(),
            'called' => (clone $query)->where('status', 'called')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'skipped' => (clone $query)->where('status', 'skipped')->count(),
        ];

        $kinerjaLoket = (clone $query)->select('counter_number', DB::raw('count(*) as total'))
            ->whereNotNull('counter_number')
            ->groupBy('counter_number')
            ->get()
            ->map(function ($item) {
                return [
                    'loket' => (string) $item->counter_number,
                    'total' => $item->total,
                ];
            });

        $trenHarian = (clone $query)->select('tanggal', DB::raw('count(*) as total'))
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'periode' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'data' => [
                'ringkasan' => $ringkasan,
                'kinerja_loket' => $kinerjaLoket,
                'tren_harian' => $trenHarian,
            ]
        ]);
    }

    public function export(Request $request)
    {
        $bulan = $request->input('bulan', Carbon::now()->month);
        $tahun = $request->input('tahun', Carbon::now()->year);

        $antrians = Antrian::whereMonth('tanggal', $bulan)
                           ->whereYear('tanggal', $tahun)
                           ->orderBy('tanggal', 'asc')
                           ->get();

        $fileName = "laporan_antrian_pln_{$bulan}_{$tahun}.csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0",
        ];

        $columns = ['ID', 'Nomor Antrian', 'Layanan', 'Status', 'Loket', 'Tanggal', 'Waktu Dipanggil', 'Waktu Selesai'];

        $callback = function () use ($antrians, $columns) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, $columns, ';');

            foreach ($antrians as $antrian) {
                fputcsv($file, [
                    $antrian->id,
                    $antrian->nomor_antrian,
                    $antrian->service_type,
                    strtoupper($antrian->status),
                    $antrian->counter_number ? "Loket {$antrian->counter_number}" : '-',
                    $antrian->tanggal,
                    $antrian->called_at ?? '-',
                    $antrian->completed_at ?? '-',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
