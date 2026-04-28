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
}
 