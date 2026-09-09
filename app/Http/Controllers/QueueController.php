<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Antrian;
use App\Models\Service;
use App\Services\WebSocketService;
use Carbon\Carbon;

class QueueController extends Controller
{
    private function getStats(?int $tenantId = null): array
    {
        $today = now()->toDateString();
        $base = Antrian::withoutGlobalScope('tenant');
        if ($tenantId) $base->where('tenant_id', $tenantId);
        else {
            $user = Auth::user() ?? Auth::guard('sanctum')->user();
            if ($user && isset($user->tenant_id) && $user->tenant_id) $base->where('tenant_id', $user->tenant_id);
            else {
                // Try from current request bearer token
                $req = request();
                $tid = $this->currentTenantId($req);
                if ($tid) $base->where('tenant_id', $tid);
            }
        }
        return [
            'total' => (clone $base)->whereDate('tanggal', $today)->count(),
            'waiting' => (clone $base)->whereDate('tanggal', $today)->where('status', 'waiting')->count(),
            'called' => (clone $base)->whereDate('tanggal', $today)->where('status', 'called')->count(),
            'serving' => (clone $base)->whereDate('tanggal', $today)->where('status', 'serving')->count(),
            'completed' => (clone $base)->whereDate('tanggal', $today)->where('status', 'completed')->count(),
            'skipped' => (clone $base)->whereDate('tanggal', $today)->where('status', 'skipped')->count(),
        ];
    }

    private function currentTenantId(Request $request): ?int
    {
        $user = $request->user('sanctum') ?? Auth::user();
        if ($user && isset($user->tenant_id) && $user->tenant_id) return (int) $user->tenant_id;
        return null;
    }

    private function resolveTenantIdForRequest(Request $request, ?string $serviceCode = null): ?int
    {
        $tid = $this->currentTenantId($request);
        if ($tid) return $tid;
        if ($serviceCode) {
            $svc = Service::withoutGlobalScope('tenant')->where('code', $serviceCode)->where('is_active', 1)->first();
            if ($svc && $svc->tenant_id) return (int) $svc->tenant_id;
        }
        return null;
    }

    private function broadcast(string $type, $payload, ?int $tenantId = null): void
    {
        try {
            app(WebSocketService::class)->broadcast($type, $payload, $tenantId);
        } catch (\Throwable) {
            // silent
        }
    }

    private function broadcastTicket(string $event, Antrian $ticket): void
    {
        $data = $ticket->fresh()->toArray();
        $this->broadcast($event, $data, $ticket->tenant_id ? (int)$ticket->tenant_id : null);
    }

    private function broadcastAll(Antrian $ticket, string $event): void
    {
        $this->broadcastTicket($event, $ticket);
        $tid = $ticket->tenant_id ? (int)$ticket->tenant_id : $this->currentTenantId(request());
        $stats = $this->getStats($tid);
        // Ensure stats payload carries tenantId for server filtering
        if ($tid) $stats['tenantId'] = $tid;
        $this->broadcast('stats_update', $stats, $tid);
    }

    public function index(Request $request)
    {
        $tenantId = $this->currentTenantId($request);
        $query = $tenantId ? Antrian::withoutGlobalScope('tenant')->where('tenant_id', $tenantId) : Antrian::query();

        // Rentang tanggal opsional — dipakai halaman Laporan.
        // Tanpa parameter from/to, default tetap hari ini.
        $from = $request->input('from');
        $to = $request->input('to');

        if ($from && $to) {
            $query->whereBetween('tanggal', [$from, $to]);
        } elseif ($from) {
            $query->whereDate('tanggal', '>=', $from);
        } elseif ($to) {
            $query->whereDate('tanggal', '<=', $to);
        } else {
            $query->whereDate('tanggal', Carbon::today());
        }

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
        // For multi-tenant: if authenticated (kiosk), scope service lookup to its tenant; else fallback to first matching service code.
        $serviceType = $request->input('serviceType');
        $tenantId = $this->resolveTenantIdForRequest($request, $serviceType);

        // Validate existence within resolved tenant if possible, otherwise global.
        $request->validate([
            'serviceType' => 'required|string',
        ]);
        $serviceQuery = Service::withoutGlobalScope('tenant')->where('code', $serviceType)->where('is_active', 1);
        if ($tenantId) $serviceQuery->where('tenant_id', $tenantId);
        if (!$serviceQuery->exists()) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak ditemukan'], 422);
        }

        $today = Carbon::today();
        $prefix = (string) (Service::withoutGlobalScope('tenant')->where('code', $serviceType)->when($tenantId, fn($q)=>$q->where('tenant_id',$tenantId))->value('prefix') ?? 'A');

        // Tenant-scoped sequence
        $lastQuery = Antrian::withoutGlobalScope('tenant')->whereDate('tanggal', $today)->where('service_type', $serviceType);
        if ($tenantId) $lastQuery->where('tenant_id', $tenantId);
        $last = $lastQuery->orderBy('id', 'desc')->first();

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
            'tenant_id' => $tenantId,
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

        // Model jalur per layanan: layanan yang sama hanya boleh memiliki
        // satu antrian aktif di satu loket — layanan berbeda tetap bebas.
        $service = Service::where('code', $antrian->service_type)->first();
        $serviceName = $service?->name ?? $antrian->service_type;

        $activeDuplicate = Antrian::whereIn('status', ['called', 'serving'])
            ->where('counter_number', $counterNumber)
            ->where('service_type', $antrian->service_type)
            ->whereDate('tanggal', $today)
            ->orderByDesc('called_at')
            ->first();

        if ($activeDuplicate) {
            return response()->json([
                'success' => false,
                'message' => "Masih ada antrian aktif {$serviceName} ({$activeDuplicate->nomor_antrian}) di Loket {$counterNumber}. Selesaikan atau lewati terlebih dahulu.",
            ], 409);
        }

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

    public function recall($id)
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
                'message' => 'Hanya antrian berstatus dipanggil/dilayani yang bisa dipanggil ulang',
            ], 400);
        }

        // Panggil ulang tidak mengubah data — hanya menyiarkan ulang
        // agar suara pengumuman keluar dari TV display.
        $this->broadcastTicket('queue_recall', $antrian);

        return response()->json([
            'success' => true,
            'message' => 'Antrian dipanggil ulang',
            'data' => $antrian->fresh(),
        ]);
    }

    public function stats(Request $request)
    {
        $tid = $this->currentTenantId($request);
        return response()->json([
            'success' => true,
            'data' => $this->getStats($tid),
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
