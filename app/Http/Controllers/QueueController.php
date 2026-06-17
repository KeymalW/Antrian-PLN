<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Antrian;
use Carbon\Carbon;

class QueueController extends Controller
{
    private function getPrefix(string $serviceType): string
    {
        return match ($serviceType) {
            'pembayaran' => 'P',
            'pengaduan' => 'G',
            'pendaftaran' => 'D',
            'informasi' => 'I',
            default => 'A',
        };
    }

    public function index(Request $request)
    {
        $query = Antrian::whereDate('tanggal', Carbon::today());

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('serviceType')) {
            $query->where('service_type', $request->serviceType);
        }

        $perPage = (int) $request->input('perPage', 50);
        $page = (int) $request->input('page', 1);

        $antrians = $query->orderBy('created_at', 'asc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $antrians->items(),
            'meta' => [
                'total' => $antrians->total(),
                'page' => $antrians->currentPage(),
                'perPage' => $antrians->perPage(),
                'totalPages' => $antrians->lastPage(),
            ]
        ]);
    }

    public function takeTicket(Request $request)
    {
        $request->validate([
            'serviceType' => 'required|in:pembayaran,pengaduan,pendaftaran,informasi',
        ]);

        $serviceType = $request->serviceType;
        $today = Carbon::today();
        $prefix = $this->getPrefix($serviceType);

        $last = Antrian::whereDate('tanggal', $today)
            ->where('service_type', $serviceType)
            ->orderBy('id', 'desc')
            ->first();

        if ($last) {
            $parts = explode('-', $last->nomor_antrian);
            $lastNum = (int) ($parts[1] ?? 0);
            $no = $lastNum + 1;
        } else {
            $no = 1;
        }

        $nomor = $prefix . '-' . str_pad($no, 3, '0', STR_PAD_LEFT);

        $antrian = Antrian::create([
            'nomor_antrian' => $nomor,
            'service_type' => $serviceType,
            'tanggal' => $today,
            'status' => 'waiting',
        ]);

        return response()->json([
            'success' => true,
            'data' => $antrian,
        ], 201);
    }

    public function callQueue(Request $request, $id)
    {
        $request->validate([
            'counterNumber' => 'required|integer|min:1|max:99',
        ]);

        $counterNumber = (int) $request->counterNumber;
        $today = Carbon::today();

        Antrian::whereIn('status', ['called', 'serving'])
            ->where('counter_number', $counterNumber)
            ->whereDate('tanggal', $today)
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $antrian = Antrian::findOrFail($id);
        $antrian->update([
            'status' => 'called',
            'counter_number' => $counterNumber,
            'called_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Antrian dipanggil ke Loket {$counterNumber}",
            'data' => $antrian,
        ]);
    }

    public function serveQueue($id)
    {
        $antrian = Antrian::findOrFail($id);

        if ($antrian->status !== 'called') {
            return response()->json([
                'success' => false,
                'message' => 'Antrian harus dalam status dipanggil sebelum dilayani',
            ], 400);
        }

        $antrian->update([
            'status' => 'serving',
            'serving_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Antrian sedang dilayani',
            'data' => $antrian,
        ]);
    }

    public function skipQueue($id)
    {
        $antrian = Antrian::findOrFail($id);
        $antrian->update([
            'status' => 'skipped'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Antrian berhasil dilewati',
            'data' => $antrian,
        ]);
    }

    public function completeQueue($id)
    {
        $antrian = Antrian::findOrFail($id);

        if (!in_array($antrian->status, ['called', 'serving'])) {
            return response()->json([
                'success' => false,
                'message' => 'Antrian harus dalam status dipanggil atau dilayani',
            ], 400);
        }

        $antrian->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Antrian selesai dilayani',
            'data' => $antrian,
        ]);
    }

    public function stats()
    {
        $today = now()->toDateString();

        $total = Antrian::whereDate('tanggal', $today)->count();
        $waiting = Antrian::whereDate('tanggal', $today)->where('status', 'waiting')->count();
        $called = Antrian::whereDate('tanggal', $today)->where('status', 'called')->count();
        $serving = Antrian::whereDate('tanggal', $today)->where('status', 'serving')->count();
        $completed = Antrian::whereDate('tanggal', $today)->where('status', 'completed')->count();
        $skipped = Antrian::whereDate('tanggal', $today)->where('status', 'skipped')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'waiting' => $waiting,
                'called' => $called,
                'serving' => $serving,
                'completed' => $completed,
                'skipped' => $skipped,
            ]
        ]);
    }

    public function show(Request $request, $id)
    {
        $antrian = Antrian::find($id);

        if (!$antrian) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $antrian,
        ]);
    }

    public function lastCalled(Request $request, $counterNumber)
    {
        $antrian = Antrian::whereIn('status', ['called', 'serving'])
            ->where('counter_number', (int) $counterNumber)
            ->whereDate('tanggal', Carbon::today())
            ->latest('called_at')
            ->first();

        if (!$antrian) {
            $antrian = Antrian::where('status', 'completed')
                ->where('counter_number', (int) $counterNumber)
                ->whereDate('tanggal', Carbon::today())
                ->latest('completed_at')
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => $antrian,
        ]);
    }

    public function clearHistory()
    {
        Antrian::whereIn('status', ['completed', 'skipped'])->delete();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat antrian dibersihkan',
        ]);
    }

    public function getTrash()
    {
        $antrians = Antrian::whereIn('status', ['completed', 'skipped'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $antrians,
        ]);
    }

    public function emptyTrash()
    {
        Antrian::whereIn('status', ['completed', 'skipped'])->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sampah berhasil dikosongkan',
        ]);
    }

    public function restore($id)
    {
        $antrian = Antrian::findOrFail($id);

        if (!in_array($antrian->status, ['completed', 'skipped'])) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya antrian completed/skipped yang bisa direstore',
            ], 400);
        }

        $antrian->update([
            'status' => 'waiting',
            'counter_number' => null,
            'called_at' => null,
            'serving_at' => null,
            'completed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Antrian berhasil direstore',
            'data' => $antrian,
        ]);
    }
}
