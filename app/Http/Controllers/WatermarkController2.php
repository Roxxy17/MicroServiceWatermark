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
            'title'     => 'nullable|string',
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
            // LAYER 3: RENDER TEKS (JUDUL & BODY) - SEMUA CENTER
            // =========================================================
            
            $centerX = 540; // Titik tengah horizontal
            $textAreaWidth = 880; // Lebar maksimal teks

            // 1. RENDER JUDUL (Di bagian atas kotak, CENTER, BESAR)
            $titleText = $request->title ?? ''; 
            $nextY = $this->renderTitle($finalImage, $titleText, $centerX, 250, $textAreaWidth, '#000000');

            // 2. GARIS PEMISAH (Accent Line di bawah judul)
            if (!empty($titleText)) {
                $lineColor = ($dayOfYear % 2 != 0) ? '#56B5A0' : '#37499c'; // Warna sesuai tema
                $finalImage->drawRectangle(390, $nextY + 15, function ($draw) use ($lineColor) {
                    $draw->size(300, 4); // Garis horizontal 300px x 4px
                    $draw->background($lineColor);
                });
                $nextY += 30; // Tambah spacing setelah garis
            }

            // 3. RENDER BODY (Di bawah judul, CENTER, UKURAN BESAR)
            $captionText = $request->caption;
            
            // Cleaning Text
            $captionText = str_replace(["\r\n", "\r"], "\n", $captionText);
            $captionText = str_replace(['**', '*'], '', $captionText);
            
            // Tambahkan jarak ekstra antar paragraf
            $captionText = preg_replace('/\n(?!\n)/', "\n\n", $captionText);
            
            // Beri jarak antara Judul dan Body
            $bodyStartY = $nextY + 20;

            $fontPathBody = public_path('fonts/Roboto_Condensed-Regular.ttf'); // Gunakan Regular untuk body
            
            // Render dengan subtle shadow untuk depth
            $this->renderBodyText($finalImage, $captionText, $centerX, $bodyStartY, $textAreaWidth, '#2a2a2a', $fontPathBody, true);

            return response($finalImage->toJpeg(90)->toString())->header('Content-Type', 'image/jpeg');

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // --- FUNGSI RENDER JUDUL (CENTER, BOLD, BESAR) ---
    private function renderTitle($image, $text, $x, $y, $width, $color)
    {
        if (empty($text)) return $y;

        $fontSize = 70;
        $lineHeight = 1.35;
        $fontPath = public_path('fonts/Roboto_Condensed-Bold.ttf'); // Bold untuk judul

        // Subtle shadow untuk depth
        $image->text($text, $x + 2, $y + 2, function ($font) use ($fontPath, $fontSize, $width, $lineHeight) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($fontSize);
            $font->color('rgba(0, 0, 0, 0.1)'); // Shadow sangat subtle
            $font->align('center');
            $font->valign('top');
            $font->wrap($width);
            $font->lineHeight($lineHeight);
        });

        // Main text
        $image->text($text, $x, $y, function ($font) use ($fontPath, $fontSize, $color, $width, $lineHeight) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($fontSize);
            $font->color($color);
            $font->align('center');
            $font->valign('top');
            $font->wrap($width);
            $font->lineHeight($lineHeight);
        });

        $charLength = strlen($text);
        $lines = ceil($charLength / 20);
        $heightEstimate = $lines * ($fontSize * $lineHeight);

        return $y + $heightEstimate;
    }

    // --- FUNGSI RENDER BODY (CENTER, UKURAN LEBIH BESAR, JARAK LEGA) ---
    private function renderBodyText($image, $text, $x, $y, $width, $color, $fontPath, $withShadow = false)
    {
        if (empty($text)) return;

        $fontSize = 45;
        $lineHeight = 1.2; // Line height lebih lega untuk readability

        // Optional subtle shadow
        if ($withShadow) {
            $image->text($text, $x + 1, $y + 1, function ($font) use ($fontPath, $fontSize, $width, $lineHeight) {
                if (file_exists($fontPath)) $font->file($fontPath);
                $font->size($fontSize);
                $font->color('rgba(0, 0, 0, 0.08)'); // Shadow sangat subtle
                $font->align('center');
                $font->valign('top');
                $font->wrap($width);
                $font->lineHeight($lineHeight);
            });
        }

        // Main text
        $image->text($text, $x, $y, function ($font) use ($fontPath, $fontSize, $color, $width, $lineHeight) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($fontSize);
            $font->color($color);
            $font->align('center');
            $font->valign('top');
            $font->wrap($width);
            $font->lineHeight($lineHeight);
        });
    }
}