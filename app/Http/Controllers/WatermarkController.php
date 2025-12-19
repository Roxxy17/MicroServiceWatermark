<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class WatermarkController extends Controller
{
    public function process(Request $request)
    {
        // Setup Resource
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'title'     => 'required|string|max:200',
            'image_url' => 'required|url',
        ]);

        try {
            $manager = new ImageManager(new Driver());
            
            // =========================================================
            // 0. LOGIKA ROTASI & RANDOMIZE OVERLAY
            // =========================================================
            $dayOfYear = now()->dayOfYear;
            
            // Tentukan warna berdasarkan hari (ganjil = green, genap = blue)
            if ($dayOfYear % 2 != 0) {
                $baseColor = 'green';
                $themeColor = '#56B5A0';
                $highlightColor = '#fff723';
            } else {
                $baseColor = 'blue';
                $themeColor = '#37499c';
                $highlightColor = '#b8d8ff';
            }
            
            // Random pilih versi up atau below
            $overlayVersion = (rand(0, 1) === 0) ? 'up' : 'below';
            $overlayFilename = "overlay_{$baseColor}_{$overlayVersion}.png";
            
            // Tentukan alignment berdasarkan versi overlay
            $isBelow = ($overlayVersion === 'below');
            $textAlign = $isBelow ? 'left' : 'center';
            $textX = $isBelow ? 60 : 540; // 60px dari kiri untuk left, 540 untuk center

            // =========================================================
            // LAYER 1 & 2 (BACKGROUND & OVERLAY)
            // =========================================================
            // 1. Buat Canvas Dasar Dulu (Wadah Utama)
            $finalImage = $manager->create(1080, 1350);

            // 2. Download Gambar User
            $bgContent = @file_get_contents($request->image_url, false, stream_context_create([
                "http" => ["header" => "User-Agent: Mozilla/5.0\r\n", "timeout" => 60]
            ]));

            // 3. Jika Gambar Ada, Tempelkan di posisi Y yang diinginkan
            if ($bgContent) {
                $userImage = $manager->read($bgContent);
                $userImage->cover(1080, 1350);
                
                // Geser gambar berdasarkan versi overlay
                // UP: geser ke bawah (positive), BELOW: geser ke atas (negative)
                $posisiY = $isBelow ? -150 : 150;
                
                $finalImage->place($userImage, 'top-left', 0, $posisiY);
            }

            // 4. Tempelkan Overlay
            $overlayPath = public_path($overlayFilename);
            if (file_exists($overlayPath)) {
                $overlay = $manager->read($overlayPath);
                $overlay->resize(1080, 1350);
                $finalImage->place($overlay, 'top-left', 0, 0);
            } else {
                // Fallback jika file overlay tidak ada
                $rgbaColor = $this->hexToRgba($themeColor, 0.7);
                $finalImage->drawRectangle(0, 0, function ($draw) use ($rgbaColor) {
                    $draw->size(1080, 1350);
                    $draw->background($rgbaColor);
                });
            }

            // =========================================================
            // LAYER 3: TEXT
            // =========================================================

            // A. Logo
            $logoPath = public_path('bukuerp_logo_white.png');
            if (file_exists($logoPath)) {
                $logo = $manager->read($logoPath)->scale(height: 50);
                $finalImage->place($logo, 'top-left', 60, 60);
            }

            // B. Logika Split Judul
            $rawTitle = $request->title;
            $topText = 'BUKUERP INSIGHT BISNIS';
            $rawTitle = str_replace("\r\n", "\n", $rawTitle);

            $whiteText = $rawTitle;
            $blueText = '';

            // Split Logic
            if (strpos($rawTitle, '|') !== false) {
                $parts = explode('|', $rawTitle, 2);
                $whiteText = trim($parts[0]);
                $blueText = trim($parts[1]);
            } elseif (strpos($rawTitle, "\n") !== false) {
                $parts = explode("\n", $rawTitle, 2);
                $whiteText = trim($parts[0]);
                $blueText = trim($parts[1]);
            } elseif (preg_match('/^(.+?[?!:])\s+(.+)$/s', $rawTitle, $matches)) {
                $whiteText = trim($matches[1]);
                $blueText = trim($matches[2]);
            }

            // --- APLIKASI BENTUK (hanya untuk overlay UP yang center) ---
            if (!$isBelow && strpos($whiteText, "\n") === false) {
                $whiteText = $this->applyTextShape($whiteText, 'pyramid');
            }

            // C. Render Teks
            $fontPath = public_path('fonts/Roboto_Condensed-SemiBold.ttf');
            $shadowColor = 'rgba(0, 0, 0, 0.5)';

            if ($isBelow) {
                // LAYOUT UNTUK OVERLAY BELOW (Rata Kiri)
                $currentY = 900; // Mulai dari bawah
                
                // 1. Label Kecil (Kuning) - Rata Kiri
                $this->renderText($finalImage, $topText, $currentY, 35, '#ffe817', $fontPath, 15, $shadowColor, $textAlign, $textX, 1000);
                
                // 2. Judul Utama (Putih) - Rata Kiri
                $this->renderText($finalImage, $whiteText, $currentY, 100, '#ffffff', $fontPath, 50, $shadowColor, $textAlign, $textX, 1000);
                
                // Cek apakah judul putih hanya sebaris
                $isSingleLine = (strpos($whiteText, "\n") === false);
                
                if ($isSingleLine) {
                    $currentY -= 80;
                } else {
                    $currentY -= 60;
                }
                
                // 3. Judul Highlight (Biru) - Rata Kiri
                if ($blueText) {
                    $this->renderText($finalImage, $blueText, $currentY, 60, $highlightColor, $fontPath, 5, $shadowColor, $textAlign, $textX, 1000);
                }
                
            } else {
                // LAYOUT UNTUK OVERLAY UP (Center - KODE LAMA)
                $currentY = 150;
                
                // 1. Label Kecil
                $this->renderText($finalImage, $topText, $currentY, 35, '#ffe817', $fontPath, 5, $shadowColor, $textAlign, $textX, 1050);
                
                // 2. Judul Utama (Putih)
                $this->renderText($finalImage, $whiteText, $currentY, 100, '#ffffff', $fontPath, 5, $shadowColor, $textAlign, $textX, 1050);
                
                // Cek apakah judul putih hanya sebaris
                $isSingleLine = (strpos($whiteText, "\n") === false);
                
                if ($isSingleLine) {
                    $currentY -= 80;
                } else {
                    $currentY -= 60;
                }
                
                // 3. Judul Highlight (Biru)
                if ($blueText) {
                    $this->renderText($finalImage, $blueText, $currentY, 60, $highlightColor, $fontPath, 5, $shadowColor, $textAlign, $textX, 1050);
                }
            }

            return response($finalImage->toJpeg(95)->toString())->header('Content-Type', 'image/jpeg');

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // --- LOGIKA PEMBENTUKAN PIRAMIDA (UPDATED: MAKS 2 BARIS) ---
    private function applyTextShape($text, $shape)
    {
        $words = explode(' ', $text);
        $count = count($words);

        // Kalau cuma 1 atau 2 kata, biarkan apa adanya
        if ($count < 3) return $text;

        $totalChars = strlen($text);
        
        if ($shape === 'pyramid') {
            $ratio = 0.45;
        } else {
            $ratio = 0.5;
        }

        $targetLength = $totalChars * $ratio;
        $currentLength = 0;
        $newText = '';
        $breakFound = false;

        foreach ($words as $index => $word) {
            $wordLen = strlen($word);
            
            if (!$breakFound && ($currentLength + $wordLen) >= $targetLength && $index > 0) {
                $newText .= "\n" . $word . ' ';
                $breakFound = true;
            } else {
                $newText .= $word . ' ';
            }
            
            $currentLength += $wordLen + 1;
        }

        return trim($newText);
    }

    // --- HELPER RENDER (UPDATED: Support alignment & custom X position) ---
    private function renderText($image, $text, &$y, $size, $color, $fontPath, $marginBottom, $shadowColor = null, $align = 'center', $posX = 540, $maxWidth = 1050)
    {
        if (empty($text)) return;
        $lineHeight = 1.25;

        // Shadow Layer
        if ($shadowColor) {
            $image->text($text, $posX + 4, $y + 4, function ($font) use ($fontPath, $size, $shadowColor, $maxWidth, $lineHeight, $align) {
                if (file_exists($fontPath)) $font->file($fontPath);
                $font->size($size);
                $font->color($shadowColor);
                $font->align($align);
                $font->valign('top');
                $font->wrap($maxWidth);
                $font->lineHeight($lineHeight);
            });
        }

        // Main Text Layer
        $image->text($text, $posX, $y, function ($font) use ($fontPath, $size, $color, $maxWidth, $lineHeight, $align) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($size);
            $font->color($color);
            $font->align($align);
            $font->valign('top');
            $font->wrap($maxWidth);
            $font->lineHeight($lineHeight);
        });

        $charPerLine = floor($maxWidth / ($size * 0.5));
        $lineCount = ceil(strlen($text) / $charPerLine);
        $lineCount = max($lineCount, substr_count($text, "\n") + 1);
        $y += ($size * $lineHeight * $lineCount) + $marginBottom;
    }

    private function hexToRgba($hex, $alpha = 1)
    {
        $hex = str_replace("#", "", $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return "rgba($r, $g, $b, $alpha)";
    }
}