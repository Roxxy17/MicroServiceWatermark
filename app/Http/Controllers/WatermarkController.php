<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver; // Kita pakai driver GD bawaan PHP

class WatermarkController extends Controller
{
    public function process(Request $request)
    {
        // 1. Validasi: Pastikan n8n mengirim parameter 'image_url'
        $request->validate([
            'image_url' => 'required|url'
        ]);

        try {
            // Setup Manager Gambar (Driver GD)
            $manager = new ImageManager(new Driver());

            // 2. Baca Gambar Utama dari URL (Input dari n8n)
            // file_get_contents aman digunakan untuk baca URL eksternal
            $imageContent = file_get_contents($request->image_url);
            
            if ($imageContent === false) {
                return response()->json(['error' => 'Gagal mendownload gambar dari URL'], 400);
            }

            $mainImage = $manager->read($imageContent);

            // 3. Baca File Watermark dari folder Public
            $watermarkPath = public_path('watermark.png');
            
            if (!file_exists($watermarkPath)) {
                return response()->json(['error' => 'File watermark.png tidak ditemukan di folder public!'], 500);
            }

            $watermark = $manager->read($watermarkPath);

            // 4. Atur Ukuran Watermark (Responsif)
            // Kita set lebar watermark jadi 25% dari lebar gambar asli
            $targetWidth = $mainImage->width() * 0.25; 
            
            // Resize watermark (tinggi menyesuaikan rasio otomatis)
            $watermark->scaleDown(width: $targetWidth);

            // 5. Tempel Watermark
            // Posisi: 'bottom-right' (Kanan Bawah)
            // Offset X: 30px, Offset Y: 30px (Jarak dari pinggir)
            $mainImage->place($watermark, 'bottom-right', 30, 30);

            // 6. Return Langsung sebagai Binary File (JPEG Quality 90)
            return response($mainImage->toJpeg(90)->toString())
                ->header('Content-Type', 'image/jpeg');

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}