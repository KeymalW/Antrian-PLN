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
        // Secara default nampilin data 7 hari terakhir
        $startDate = $request->input('start_date', Carbon::now()->subDays(6)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        // Base query buat filter tanggal
        $query = Antrian::whereBetween('tanggal', [$startDate, $endDate]);

        // 1. Ringkasan Status
        $ringkasan = [
            'total' => (clone $query)->count(),
            'menunggu' => (clone $query)->where('status', 'menunggu')->count(),
            'dipanggil' => (clone $query)->where('status', 'dipanggil')->count(),
            'selesai' => (clone $query)->where('status', 'selesai')->count(),
            'lewati' => (clone $query)->where('status', 'lewati')->count(),
        ];

        // 2. Kinerja per Loket (Loket mana yang paling sibuk)
        $kinerjaLoket = (clone $query)->select('loket', DB::raw('count(*) as total'))
            ->whereNotNull('loket')
            ->groupBy('loket')
            ->get();

        // 3. Tren Harian (Buat dibikin grafik garis/bar nanti)
        $trenHarian = (clone $query)->select('tanggal', DB::raw('count(*) as total'))
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'periode' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'data' => [
                'ringkasan' => $ringkasan,
                'kinerja_loket' => $kinerjaLoket,
                'tren_harian' => $trenHarian
            ]
        ]);
    }

    public function export(Request $request)
    {
        // Ambil filter bulan & tahun (default: bulan ini)
        $bulan = $request->input('bulan', Carbon::now()->month);
        $tahun = $request->input('tahun', Carbon::now()->year);

        // Ambil data dari database
        $antrians = Antrian::whereMonth('tanggal', $bulan)
                           ->whereYear('tanggal', $tahun)
                           ->orderBy('tanggal', 'asc')
                           ->get();

        $fileName = "laporan_antrian_pln_{$bulan}_{$tahun}.csv";

        // Bikin header khusus biar browser ngenalin ini sebagai file download
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ['ID', 'Nomor Antrian', 'Status', 'Loket', 'Tanggal', 'Waktu Dipanggil', 'Waktu Selesai'];

        // Tulis datanya baris per baris pakai PHP stream
        $callback = function() use($antrians, $columns) {
            $file = fopen('php://output', 'w');
            
            // Tambahkan UTF-8 BOM biar karakter aneh terbaca bener di Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Pake pemisah titik koma (;) khusus biar rapi di Excel region Indonesia
            fputcsv($file, $columns, ';'); 

            foreach ($antrians as $antrian) {
                fputcsv($file, [
                    $antrian->id,
                    $antrian->nomor_antrian,
                    strtoupper($antrian->status),
                    $antrian->loket ? "Loket {$antrian->loket}" : '-',
                    $antrian->tanggal,
                    $antrian->panggil_at ?? '-',
                    $antrian->status === 'selesai' ? $antrian->updated_at : '-'
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
 