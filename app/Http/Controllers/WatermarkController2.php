<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class WatermarkController2 extends Controller
{
    public function process(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'image_url' => 'required|url',
            'title'     => 'nullable|string', // Field baru untuk Judul
            'caption'   => 'required|string', 
        ]);

        try {
            $manager = new ImageManager(new Driver());

            // 0. LOGIKA HARI
            $dayOfYear = now()->dayOfYear;
            $overlayFilename = ($dayOfYear % 2 != 0) ? 'slide2_overlay_green.png' : 'slide2_overlay_blue.png';

            // LAYER 1: BACKGROUND
            $finalImage = $manager->create(1080, 1350);
            
            $bgContent = @file_get_contents($request->image_url, false, stream_context_create([
                "http" => ["header" => "User-Agent: Mozilla/5.0\r\n", "timeout" => 60]
            ]));

            if ($bgContent) {
                $userImage = $manager->read($bgContent);
                $userImage->cover(1080, 1350);
                $finalImage->place($userImage, 'top-left', 0, 0);
            } else {
                $finalImage->fill('#f0f0f0');
            }

            // LAYER 2: OVERLAY KOTAK PUTIH
            $overlayPath = public_path($overlayFilename);
            if (file_exists($overlayPath)) {
                $overlay = $manager->read($overlayPath);
                $overlay->resize(1080, 1350);
                $finalImage->place($overlay, 'top-left', 0, 0);
            } else {
                // Fallback kotak putih transparan
                $finalImage->drawRectangle(100, 200, function ($draw) {
                    $draw->size(880, 950);
                    $draw->background('rgba(255, 255, 255, 0.92)');
                });
            }

            // =========================================================
            // LAYER 3: RENDER TEKS (JUDUL & BODY)
            // =========================================================
            
            // Konfigurasi Area Teks
            // Area X dimulai dari 150 agar ada padding kiri kanan yang lega
            // Lebar 780 agar teks tidak mepet pinggir kotak
            $textAreaX = 150; 
            $textAreaWidth = 780; 

            // 1. RENDER JUDUL (Di bagian atas kotak)
            $titleText = $request->title ?? ''; 
            // Posisi Y judul dimulai di 280 (agak turun dari atas kotak)
            $nextY = $this->renderTitle($finalImage, $titleText, 540, 280, $textAreaWidth, '#1a1a1a');

            // 2. RENDER BODY (Di bawah judul)
            $captionText = $request->caption;
            
            // Cleaning Text
            $captionText = str_replace(["\r\n", "\r"], "\n", $captionText);
            $captionText = str_replace(['**', '*'], '', $captionText); // Hapus markdown
            $captionText = str_replace("\n-", "\n\n-", $captionText); // Tambah jarak antar poin

            // Beri jarak (margin top) dari Judul ke Body, misal 60px
            $bodyStartY = $nextY + 60; 

            $fontPathBody = public_path('fonts/Roboto_Condensed-Regular.ttf'); // Gunakan font Regular biar enak dibaca
            
            $this->renderBodyText($finalImage, $captionText, $textAreaX, $bodyStartY, $textAreaWidth, '#333333', $fontPathBody);

            return response($finalImage->toJpeg(90)->toString())->header('Content-Type', 'image/jpeg');

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // --- FUNGSI RENDER JUDUL (CENTER, BOLD, BESAR) ---
    private function renderTitle($image, $text, $x, $y, $width, $color)
    {
        if (empty($text)) return $y; // Jika tidak ada judul, kembalikan Y awal

        $fontSize = 55; // Font Besar
        $lineHeight = 1.3;
        $fontPath = public_path('fonts/Roboto_Condensed-Bold.ttf'); // Wajib Bold

        // Kita hitung estimasi tinggi judul agar body text bisa menyesuaikan posisinya
        // (Sangat basic approximation, idealnya pakai box size calculation)
        $charLength = strlen($text);
        $lines = ceil($charLength / 25); // Asumsi 25 karakter per baris untuk font 55
        $heightEstimate = $lines * ($fontSize * $lineHeight);

        $image->text($text, $x, $y, function ($font) use ($fontPath, $fontSize, $color, $width, $lineHeight) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($fontSize);
            $font->color($color);
            $font->align('center'); // JUDUL RATA TENGAH
            $font->valign('top');
            $font->wrap($width);
            $font->lineHeight($lineHeight);
        });

        return $y + $heightEstimate; // Kembalikan posisi Y terakhir
    }

    // --- FUNGSI RENDER BODY (LEFT ALIGN TAPI RAPI) ---
    private function renderBodyText($image, $text, $x, $y, $width, $color, $fontPath)
    {
        if (empty($text)) return;

        $fontSize = 40; // Ukuran pas untuk kalimat (tidak terlalu besar/kecil)
        $lineHeight = 1.5; // Jarak antar baris dalam 1 kalimat (lega)

        $image->text($text, $x, $y, function ($font) use ($fontPath, $fontSize, $color, $width, $lineHeight) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($fontSize);
            $font->color($color);
            $font->align('left'); // Body text Rata Kiri lebih enak dibaca untuk kalimat panjang
            $font->valign('top');
            $font->wrap($width);
            $font->lineHeight($lineHeight);
        });
    }
}