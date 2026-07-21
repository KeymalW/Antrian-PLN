<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Antrian;
use App\Services\WebSocketService;
use Carbon\Carbon;

class QueueController extends Controller
{
    private function getPrefix(string $serviceType): string
    {
        return match ($serviceType) {
            'pengaduan' => 'G',
            'pb_pd_migrasi' => 'M',
            'p2tl' => 'T',
            default => 'A',
        };
    }

    private function getStats(): array
    {
        $today = now()->toDateString();

        return [
            'total' => Antrian::whereDate('tanggal', $today)->count(),
            'waiting' => Antrian::whereDate('tanggal', $today)->where('status', 'waiting')->count(),
            'called' => Antrian::whereDate('tanggal', $today)->where('status', 'called')->count(),
            'serving' => Antrian::whereDate('tanggal', $today)->where('status', 'serving')->count(),
            'completed' => Antrian::whereDate('tanggal', $today)->where('status', 'completed')->count(),
            'skipped' => Antrian::whereDate('tanggal', $today)->where('status', 'skipped')->count(),
        ];
    }

    private function broadcast(string $type, $payload): void
    {
        try {
            app(WebSocketService::class)->broadcast($type, $payload);
        } catch (\Throwable) {
            // silent
        }
    }

    private function broadcastTicket(string $event, Antrian $ticket): void
    {
        $this->broadcast($event, $ticket->fresh()->toArray());
    }

    private function broadcastAll(Antrian $ticket, string $event): void
    {
        $this->broadcastTicket($event, $ticket);
        $this->broadcast('stats_update', $this->getStats());
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
            'serviceType' => 'required|in:pengaduan,pb_pd_migrasi,p2tl',
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

        $this->broadcastAll($antrian, 'queue_update');

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

        $antrian = Antrian::find($id);
        if (!$antrian) {
            return response()->json([
                'success' => false,
                'message' => 'Antrian tidak ditemukan',
            ], 404);
        }

        Antrian::whereIn('status', ['called', 'serving'])
            ->where('counter_number', $counterNumber)
            ->whereDate('tanggal', $today)
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $antrian->update([
            'status' => 'called',
            'counter_number' => $counterNumber,
            'called_at' => now(),
        ]);

        $this->broadcastAll($antrian, 'queue_call');

        return response()->json([
            'success' => true,
            'message' => "Antrian dipanggil ke Loket {$counterNumber}",
            'data' => $antrian,
        ]);
    }

    public function serveQueue($id)
    {
        $antrian = Antrian::find($id);
        if (!$antrian) {
            return response()->json([
                'success' => false,
                'message' => 'Antrian tidak ditemukan',
            ], 404);
        }

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

        $this->broadcastAll($antrian, 'queue_update');

        return response()->json([
            'success' => true,
            'message' => 'Antrian sedang dilayani',
            'data' => $antrian,
        ]);
    }

    public function skipQueue($id)
    {
        $antrian = Antrian::find($id);
        if (!$antrian) {
            return response()->json([
                'success' => false,
                'message' => 'Antrian tidak ditemukan',
            ], 404);
        }

        $antrian->update([
            'status' => 'skipped'
        ]);

        $this->broadcastAll($antrian, 'queue_skip');

        return response()->json([
            'success' => true,
            'message' => 'Antrian berhasil dilewati',
            'data' => $antrian,
        ]);
    }

    public function completeQueue($id)
    {
        $antrian = Antrian::find($id);
        if (!$antrian) {
            return response()->json([
                'success' => false,
                'message' => 'Antrian tidak ditemukan',
            ], 404);
        }

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

        $this->broadcastAll($antrian, 'queue_complete');

        return response()->json([
            'success' => true,
            'message' => 'Antrian selesai dilayani',
            'data' => $antrian,
        ]);
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getStats(),
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

        return response()->json([
            'success' => true,
            'data' => $antrian,
        ]);
    }

    public function activeCall()
    {
        $antrian = Antrian::whereIn('status', ['called', 'serving'])
            ->whereDate('tanggal', Carbon::today())
            ->latest('called_at')
            ->first();

        if (!$antrian) {
            $antrian = Antrian::where('status', 'completed')
                ->whereDate('tanggal', Carbon::today())
                ->latest('completed_at')
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => $antrian ? [
                'id' => (string) $antrian->id,
                'nomor_antrian' => $antrian->nomor_antrian,
                'loket' => $antrian->counter_number,
            ] : null,
        ]);
    }

    public function weekly()
    {
        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeek;
        $monday = $today->copy()->subDays($dayOfWeek === Carbon::SUNDAY ? 6 : $dayOfWeek - 1)->startOfDay();
        $friday = $monday->copy()->addDays(4)->endOfDay();

        $tickets = Antrian::whereBetween('created_at', [$monday, $friday])->get();

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    public function clearHistory()
    {
        Antrian::whereIn('status', ['completed', 'skipped'])->delete();

        $this->broadcast('stats_update', $this->getStats());

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

        $this->broadcast('stats_update', $this->getStats());

        return response()->json([
            'success' => true,
            'message' => 'Sampah berhasil dikosongkan',
        ]);
    }

    public function restore($id)
    {
        $antrian = Antrian::find($id);
        if (!$antrian) {
            return response()->json([
                'success' => false,
                'message' => 'Antrian tidak ditemukan',
            ], 404);
        }

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

        $this->broadcastAll($antrian, 'queue_update');

        return response()->json([
            'success' => true,
            'message' => 'Antrian berhasil direstore',
            'data' => $antrian,
        ]);
    }
}
