<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
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
}
