<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    private function getSettingsPath(): string
    {
        return storage_path('app/settings/video-volume.json');
    }

    public function getVideoVolume()
    {
        $path = $this->getSettingsPath();

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $volume = 0.2;

        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            $volume = (float) ($data['volume'] ?? $volume);
        }

        return response()->json([
            'success' => true,
            'data' => ['volume' => $volume],
        ]);
    }

    public function setVideoVolume(Request $request)
    {
        $request->validate([
            'volume' => 'required|numeric|min:0|max:1',
        ]);

        $path = $this->getSettingsPath();

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode([
            'volume' => (float) $request->volume,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Volume berhasil disimpan',
            'data' => ['volume' => (float) $request->volume],
        ]);
    }

    public function getVideos()
    {
        Storage::disk('public')->makeDirectory('monitor');

        $files = Storage::disk('public')->files('monitor');

        $videos = array_map(function ($file) {
            $basename = basename($file);
            return [
                'url' => url('storage/monitor/' . $basename),
                'filename' => $basename,
            ];
        }, $files);

        $videos = array_values(array_filter($videos, function ($v) {
            $ext = strtolower(pathinfo($v['filename'], PATHINFO_EXTENSION));
            return in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'webm']);
        }));

        usort($videos, function ($a, $b) {
            return $b['filename'] <=> $a['filename'];
        });

        return response()->json([
            'success' => true,
            'data' => $videos
        ]);
    }

    public function uploadVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,avi,wmv,webm|max:204800',
        ]);

        try {
            Storage::disk('public')->makeDirectory('monitor');

            $file = $request->file('video');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName) . '.' . $ext;

            $file->storeAs('monitor', $filename, 'public');

            return response()->json([
                'success' => true,
                'message' => 'Video berhasil diupload',
                'data' => [
                    'url' => url('storage/monitor/' . $filename),
                    'filename' => $filename,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Video upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload video'
            ], 500);
        }
    }

    public function deleteVideo($filename)
    {
        $path = 'public/monitor/' . basename($filename);

        if (Storage::exists($path)) {
            Storage::delete($path);
        }

        return response()->json([
            'success' => true,
            'message' => 'Video berhasil dihapus',
        ]);
    }

    /* ================================================================
     |  Helper JSON file store (konsisten dgn pola video-volume.json)
     * ================================================================ */

    private function readJson(string $relative, array $default): array
    {
        $path = storage_path('app/settings/' . $relative);

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (!file_exists($path)) {
            return $default;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : $default;
    }

    private function writeJson(string $relative, array $data): void
    {
        $path = storage_path('app/settings/' . $relative);

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($data));
    }

    /* ================================================================
     |  Identitas Instansi (nama + logo)
     * ================================================================ */

    private function getGeneralData(): array
    {
        $data = $this->readJson('general.json', [
            'institution_name' => 'QServe',
            'logo_url' => '/assets/logo-pln.png',
        ]);

        return [
            'institutionName' => $data['institution_name'] ?? 'QServe',
            'logoUrl' => $data['logo_url'] ?? '',
        ];
    }

    public function getGeneral()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getGeneralData(),
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $request->validate([
            'institutionName' => 'required|string|max:100',
            'logoUrl' => 'nullable|string|max:2048',
        ]);

        $current = $this->readJson('general.json', [
            'institution_name' => 'QServe',
            'logo_url' => '/assets/logo-pln.png',
        ]);

        $current['institution_name'] = trim($request->input('institutionName'));
        $current['logo_url'] = (string) $request->input('logoUrl', $current['logo_url'] ?? '');

        $this->writeJson('general.json', $current);

        return response()->json([
            'success' => true,
            'message' => 'Identitas berhasil disimpan',
            'data' => $this->getGeneralData(),
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|file|max:512',
        ]);

        $file = $request->file('logo');

        $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, $allowed)) {
            return response()->json([
                'success' => false,
                'message' => 'Format logo harus PNG, JPG, SVG, atau WEBP',
            ], 422);
        }

        try {
            Storage::disk('public')->makeDirectory('branding');

            // Hapus logo lama dengan ekstensi berbeda agar tidak menumpuk.
            foreach ($allowed as $old) {
                $oldPath = 'public/branding/logo.' . $old;
                if ($old !== $ext && Storage::exists($oldPath)) {
                    Storage::delete($oldPath);
                }
            }

            $file->storeAs('branding', 'logo.' . $ext, 'public');

            $current = $this->readJson('general.json', [
                'institution_name' => 'QServe',
                'logo_url' => '/assets/logo-pln.png',
            ]);
            $current['logo_url'] = url('storage/branding/logo.' . $ext);
            $this->writeJson('general.json', $current);

            return response()->json([
                'success' => true,
                'message' => 'Logo berhasil diupload',
                'data' => $this->getGeneralData(),
            ]);
        } catch (\Exception $e) {
            Log::error('Logo upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload logo'
            ], 500);
        }
    }

    /* ================================================================
     |  Teks Tiket Kiosk
     * ================================================================ */

    private function getTicketTextData(): array
    {
        $data = $this->readJson('ticket-text.json', [
            'header_text' => 'NOMOR ANTRIAN',
            'sub_header_text' => 'Nomor antrian Anda',
            'footer_message' => 'Terima kasih telah mengambil tiket.',
        ]);

        return [
            'headerText' => $data['header_text'] ?? '',
            'subHeaderText' => $data['sub_header_text'] ?? '',
            'footerMessage' => $data['footer_message'] ?? '',
        ];
    }

    public function getTicketText()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getTicketTextData(),
        ]);
    }

    public function updateTicketText(Request $request)
    {
        $request->validate([
            'headerText' => 'required|string|max:60',
            'subHeaderText' => 'required|string|max:80',
            'footerMessage' => 'required|string|max:200',
        ]);

        $this->writeJson('ticket-text.json', [
            'header_text' => trim($request->input('headerText')),
            'sub_header_text' => trim($request->input('subHeaderText')),
            'footer_message' => trim($request->input('footerMessage')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Teks tiket berhasil disimpan',
            'data' => $this->getTicketTextData(),
        ]);
    }

    /* ================================================================
     |  Teks Halaman Kiosk
     * ================================================================ */

    private function getKioskTextData(): array
    {
        $data = $this->readJson('kiosk-text.json', [
            'welcome_text' => 'Selamat Datang di',
            'subtitle_text' => 'Silakan pilih layanan yang Anda butuhkan',
            'hint_text' => 'Sentuh layar untuk mencetak tiket',
            'footer_text' => 'PT PLN (Persero) · ULP Subang',
        ]);

        return [
            'welcomeText' => $data['welcome_text'] ?? '',
            'subtitleText' => $data['subtitle_text'] ?? '',
            'hintText' => $data['hint_text'] ?? '',
            'footerText' => $data['footer_text'] ?? '',
        ];
    }

    public function getKioskText()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getKioskTextData(),
        ]);
    }

    public function updateKioskText(Request $request)
    {
        $request->validate([
            'welcomeText' => 'required|string|max:80',
            'subtitleText' => 'required|string|max:120',
            'hintText' => 'required|string|max:60',
            'footerText' => 'required|string|max:120',
        ]);

        $this->writeJson('kiosk-text.json', [
            'welcome_text' => trim($request->input('welcomeText')),
            'subtitle_text' => trim($request->input('subtitleText')),
            'hint_text' => trim($request->input('hintText')),
            'footer_text' => trim($request->input('footerText')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Teks kiosk berhasil disimpan',
            'data' => $this->getKioskTextData(),
        ]);
    }

    /* ================================================================
     |  Video TV Display via Link (URL)
     * ================================================================ */

    private function mapVideoLink(array $link): array
    {
        return [
            'id' => $link['id'],
            'url' => $link['url'],
            'filename' => $link['title'],
            'title' => $link['title'],
        ];
    }

    public function getVideoLinks()
    {
        $links = $this->readJson('video-links.json', []);

        return response()->json([
            'success' => true,
            'data' => array_map([$this, 'mapVideoLink'], array_values($links)),
        ]);
    }

    public function addVideoLink(Request $request)
    {
        $request->validate([
            'url' => 'required|url|max:500',
            'title' => 'nullable|string|max:100',
        ]);

        $links = $this->readJson('video-links.json', []);

        $link = [
            'id' => 'video-' . uniqid(),
            'url' => trim($request->input('url')),
            'title' => trim((string) $request->input('title')) ?: trim($request->input('url')),
        ];

        $links[] = $link;
        $this->writeJson('video-links.json', $links);

        return response()->json([
            'success' => true,
            'message' => 'Link video berhasil ditambahkan',
            'data' => $this->mapVideoLink($link),
        ], 201);
    }

    public function deleteVideoLink($id)
    {
        $links = $this->readJson('video-links.json', []);
        $remaining = array_values(array_filter($links, fn ($link) => ($link['id'] ?? null) !== $id));

        if (count($remaining) === count($links)) {
            return response()->json([
                'success' => false,
                'message' => 'Link video tidak ditemukan',
            ], 404);
        }

        $this->writeJson('video-links.json', $remaining);

        return response()->json([
            'success' => true,
            'message' => 'Link video berhasil dihapus',
        ]);
    }
}
