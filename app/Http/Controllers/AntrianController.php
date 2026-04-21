<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Antrian;
use Carbon\Carbon;

class AntrianController extends Controller
{
    
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
}
