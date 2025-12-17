<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class WatermarkController2 extends Controller
{
    public function process(Request $request)
    {
        // 1. Setup Resource
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'image_url' => 'required|url',
            // Kita tampung caption instagram disini
            'caption'   => 'required|string', 
        ]);

        try {
            $manager = new ImageManager(new Driver());

            // =========================================================
            // 0. LOGIKA HARI (HIJAU VS BIRU)
            // =========================================================
            // Sama persis dengan slide 1 agar konsisten
            $dayOfYear = now()->dayOfYear;
            
            if ($dayOfYear % 2 != 0) {
                // Hari Ganjil = Hijau
                $overlayFilename = 'slide2_overlay_green.png'; 
            } else {
                // Hari Genap = Biru
                $overlayFilename = 'slide2_overlay_blue.png';
            }

            // =========================================================
            // LAYER 1: BACKGROUND (GAMBAR GENERATE)
            // =========================================================
            $finalImage = $manager->create(1080, 1350);
            
            // Download gambar AI
            $bgContent = @file_get_contents($request->image_url, false, stream_context_create([
                "http" => ["header" => "User-Agent: Mozilla/5.0\r\n", "timeout" => 60]
            ]));

            if ($bgContent) {
                $userImage = $manager->read($bgContent);
                // Resize full cover
                $userImage->cover(1080, 1350);
                // Tempel di posisi 0,0
                $finalImage->place($userImage, 'top-left', 0, 0);
            } else {
                // Fallback jika gagal download
                $finalImage->fill('#f0f0f0');
            }

            // =========================================================
            // LAYER 2: TEMPLATE OVERLAY (KOTAK PUTIH)
            // =========================================================
            // Pastikan file ini ada di folder public/
            $overlayPath = public_path($overlayFilename);
            
            if (file_exists($overlayPath)) {
                $overlay = $manager->read($overlayPath);
                $overlay->resize(1080, 1350);
                $finalImage->place($overlay, 'top-left', 0, 0);
            } else {
                // Fallback: Buat kotak putih manual jika file overlay hilang
                // Simulasi area kotak putih di tengah
                $finalImage->drawRectangle(100, 150, function ($draw) {
                    $draw->size(880, 1050);
                    $draw->background('rgba(255, 255, 255, 0.9)');
                });
            }

            // =========================================================
            // LAYER 3: TEKS CAPTION (KECIL-KECIL RAPI)
            // =========================================================
            
            // Ambil teks caption dari n8n
            $captionText = $request->caption;
            
            // Bersihkan format teks (ubah enter windows jadi unix)
            $captionText = str_replace(["\r\n", "\r"], "\n", $captionText);

            // KONFIGURASI POSISI TEKS (Sesuaikan dengan kotak putih di overlaymu)
            // Angka ini estimasi berdasarkan template overlay kotak putih pada umumnya
            $textAreaX = 140;      // Jarak dari kiri (padding kiri kotak putih)
            $textAreaY = 200;      // Jarak dari atas (padding atas kotak putih)
            $textAreaWidth = 800;  // Lebar area teks (jangan sampai nabrak pinggir)
            
            // Gunakan font Regular/SemiBold agar terbaca enak sebagai body text
            $fontPath = public_path('fonts/Roboto_Condensed-SemiBold.ttf'); 

            // Render Caption
            // Warna teks GELAP (#333333) karena background kotak putih
            $this->renderBodyText($finalImage, $captionText, $textAreaX, $textAreaY, $textAreaWidth, '#333333', $fontPath);

            // Output JPEG
            return response($finalImage->toJpeg(90)->toString())->header('Content-Type', 'image/jpeg');

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // --- HELPER KHUSUS TEXT BODY (CAPTION) ---
    private function renderBodyText($image, $text, $x, $y, $width, $color, $fontPath)
    {
        if (empty($text)) return;

        // Ukuran font body (caption) biasanya sekitar 30-40px untuk resolusi 1080
        $fontSize = 32; 
        
        // Line Height agak lega (1.5x) agar enak dibaca
        $lineHeight = 1.5; 

        $image->text($text, $x, $y, function ($font) use ($fontPath, $fontSize, $color, $width, $lineHeight) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($fontSize);
            $font->color($color);
            $font->align('left');      // Rata Kiri
            $font->valign('top');      // Mulai dari atas
            $font->wrap($width);       // Bungkus teks otomatis jika kepanjangan
            $font->lineHeight($lineHeight);
        });
    }

    // Helper Hex to RGBA (Opsional untuk fallback)
    private function hexToRgba($hex, $alpha = 1) {
        $hex = str_replace("#", "", $hex);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2));
        }
        return "rgba($r, $g, $b, $alpha)";
    }
}