<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Antrian;
use Carbon\Carbon;

class AntrianController extends Controller
{
    //buat antrian baru
    public function store()
    {
        $today = Carbon::today();

        $last = Antrian::whereDate('tanggal', $today)
        ->orderBy('id', 'desc')
        ->first();

        $no = $last ?
        intval(substr($last->nomor_antrian, 1)) + 1 : 1;

        $nomor = 'A' . str_pad($no, 3, '0', STR_PAD_LEFT);

        $antrian = Antrian::create([
            'nomor_antrian' => $nomor,
            'tanggal' => $today,
            'status' => 'menunggu'
        ]);

        return response()->json($antrian);
    }

    //list antrian hari ini
    public function index()
    {
        $today = now()->toDateString();

        $data = Antrian::whereDate('tanggal', $today)
        ->orderBy('id', 'asc')
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //panggil antrian
    public function panggil(Request $request, $id)
    {
        $loket = $request->input('loket', '1');

        // set antrian yang sebelumnya ada di LOKET INI saja jadi selesai
        Antrian::where('status', 'dipanggil')
            ->where('loket', $loket)
            ->whereDate('tanggal', Carbon::today())
            ->update(['status' => 'selesai']);
        
        //ambil antrian berdasarkan id dan set statusnya jadi dipanggil
        $antrian = Antrian::findOrFail($id);
        $antrian->update([
            'status' => 'dipanggil',
            'loket' => $loket,
            'panggil_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Antrian dipanggil di Loket $loket",
            'data' => $antrian
        ]);
    }

    //ambil antrian yang sedang dipanggil
    public function aktif(Request $request)
    {
        $query = Antrian::where('status', 'dipanggil')
            ->whereDate('tanggal', Carbon::today());

        if ($request->has('loket')) {
            $query->where('loket', $request->loket);
        }

        $antrian = $query->latest('panggil_at')->first();

        return response()->json([
            'status' => 'success',
            'data' => $antrian
        ]);
    }

    //lewati antrian
    public function lewati($id)
    {
        $antrian = Antrian::findOrFail($id);
        $antrian->update([
            'status' => 'lewati'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Antrian berhasil dilewati',
            'data' => $antrian
        ]);
    }

    //statistik antrian hari ini
    public function statistik()
    {
        $today = now()->toDateString();

        $total = Antrian::whereDate('tanggal', $today)->count();
        $menunggu = Antrian::whereDate('tanggal', $today)->where('status', 'menunggu')->count();
        $dipanggil = Antrian::whereDate('tanggal', $today)->where('status', 'dipanggil')->count();
        $selesai = Antrian::whereDate('tanggal', $today)->where('status', 'selesai')->count();
        $lewati = Antrian::whereDate('tanggal', $today)->where('status', 'lewati')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'menunggu' => $menunggu,
                'dipanggil' => $dipanggil,
                'selesai' => $selesai,
                'lewati' => $lewati,
            ]
        ]);
    }

    /**
     * Hitung Estimasi Waktu Tunggu (ETA) dalam menit
     * Rumus: (Antrian di depan * Rata-rata waktu pelayanan) / Jumlah loket aktif
     */
    private function hitungETA($antrian)
    {
        $today = Carbon::today();

        // 1. Hitung jumlah antrian di depan yang statusnya masih 'menunggu'
        $antrianDiDepan = Antrian::whereDate('tanggal', $today)
            ->where('status', 'menunggu')
            ->where('id', '<', $antrian->id)
            ->count();

        // 2. Hitung rata-rata waktu pelayanan hari ini (dari panggil_at sampai selesai/updated_at)
        $antrianSelesai = Antrian::whereDate('tanggal', $today)
            ->where('status', 'selesai')
            ->whereNotNull('panggil_at')
            ->get();

        $totalWaktu = 0;
        $jumlahSelesai = $antrianSelesai->count();

        if ($jumlahSelesai > 0) {
            foreach ($antrianSelesai as $a) {
                // Selisih waktu dalam menit antara dipanggil dan selesai
                $waktu = Carbon::parse($a->panggil_at)->diffInMinutes($a->updated_at);
                $totalWaktu += $waktu;
            }
            $rataRataWaktu = $totalWaktu / $jumlahSelesai;
        } else {
            // Default kalau belum ada yang selesai hari ini: 5 menit per orang
            $rataRataWaktu = 5;
        }

        // 3. Hitung jumlah loket yang lagi aktif (sedang melayani)
        $loketAktif = Antrian::whereDate('tanggal', $today)
            ->where('status', 'dipanggil')
            ->distinct('loket')
            ->count('loket');

        // Biar nggak error dibagi nol (misal petugas belum pada login/manggil)
        if ($loketAktif == 0) {
            $loketAktif = 1; 
        }

        // 4. Kalkulasi ETA akhir
        $etaMenit = ($antrianDiDepan * $rataRataWaktu) / $loketAktif;

        // Bulatkan ke atas
        return ceil($etaMenit);
    }

    // Cek status tiket pelanggan (Publik - Buat nampilin ETA di HP)
    public function status($id)
    {
        $antrian = Antrian::findOrFail($id);

        $antrianDiDepan = Antrian::whereDate('tanggal', Carbon::today())
            ->where('status', 'menunggu')
            ->where('id', '<', $antrian->id)
            ->count();

        $eta = $this->hitungETA($antrian);

        return response()->json([
            'status' => 'success',
            'data' => [
                'antrian' => $antrian,
                'sisa_antrian_di_depan' => $antrianDiDepan,
                'estimasi_waktu_menit' => $eta
            ]
        ]);
    }
}



