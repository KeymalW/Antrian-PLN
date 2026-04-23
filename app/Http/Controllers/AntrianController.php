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
    public function panggil($id)
    {
        // set semua yang sedang dipanggil jadi selesai
        Antrian::where('status', 'dipanggil')->update(['status' => 'selesai']);
        
        //ambil antrian berdasarkan id dan set statusnya jadi dipanggil
        $antrian = Antrian::findOrFail($id);
        $antrian->update(['status' => 'dipanggil']);

        return response()->json([
            'status' => 'success',
            'message' => 'Antrian dipanggil',
            'data' => $antrian
        ]);
    }
}
