<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class WatermarkController extends Controller
{
    public function process(Request $request)
    {
        // --- KONFIGURASI ---
        // Perpanjang batas waktu eksekusi (5 menit) agar tidak timeout saat download gambar AI
        set_time_limit(300);
        // Perbesar batas memori untuk menangani pengolahan gambar resolusi tinggi
        ini_set('memory_limit', '512M');

        // --- VALIDASI INPUT DARI N8N ---
        $request->validate([
            'image_url' => 'required|url',            // Link gambar dari Pollinations
            'title'     => 'required|string|max:100', // Judul konten untuk ditulis
            'template'  => 'nullable|integer|in:1,2,3' // Pilihan template (opsional)
        ]);

        try {
            // Inisialisasi Manager Gambar (Driver GD)
            $manager = new ImageManager(new Driver());

            // ==================================================
            // LANGKAH 1: DOWNLOAD & SIAPKAN GAMBAR AI
            // ==================================================
            // Gunakan context stream untuk download yang lebih stabil
            $opts = ["http" => ["header" => "User-Agent: Mozilla/5.0\r\n", "timeout" => 120]];
            $context = stream_context_create($opts);
            $imageContent = file_get_contents($request->image_url, false, $context);
            
            if (!$imageContent) return response()->json(['error' => 'Gagal download gambar AI dari URL'], 400);

            $aiImage = $manager->read($imageContent);
            // Paksa resize gambar AI menjadi kotak sempurna 1080x1080
            // agar konsisten ditaruh di bagian atas kanvas.
            $aiImage->resize(1080, 1080);


            // ==================================================
            // LANGKAH 2: TENTUKAN TEMPLATE WARNA
            // ==================================================
            // Jika n8n tidak mengirim nomor template, pilih acak 1 sampai 3.
            $templateChoice = $request->template ?? rand(1, 3);
            
            // Default config
            $bgColor = '#ffffff'; $textColor = '#111827'; $accentColor = '#2563EB';

            switch ($templateChoice) {
                case 1: // TEMPLATE 1: CLEAN WHITE (Putih Bersih)
                    $bgColor = '#ffffff';
                    $textColor = '#1f2937'; // Abu gelap
                    $accentColor = '#2563EB'; // Biru terang
                    break;
                case 2: // TEMPLATE 2: TRUST CORPORATE (Biru Tua)
                    $bgColor = '#1e3a8a';
                    $textColor = '#ffffff'; // Putih
                    $accentColor = '#fbbf24'; // Kuning emas
                    break;
                case 3: // TEMPLATE 3: BOLD MODERN (Hitam/Gelap)
                    $bgColor = '#111827';
                    $textColor = '#f3f4f6'; // Putih abu
                    $accentColor = '#ef4444'; // Merah
                    break;
            }


            // ==================================================
            // LANGKAH 3: RAKIT KANVAS UTAMA
            // ==================================================
            // Buat kanvas kosong ukuran Portrait (1080 lebar x 1350 tinggi)
            // Isi warnanya sesuai background template yang dipilih ($bgColor)
            $finalImage = $manager->create(1080, 1350)->fill($bgColor);


            // ==================================================
            // LANGKAH 4: TEMPEL ELEMEN-ELEMEN
            // ==================================================

            // A. Tempel Gambar AI di bagian ATAS TENGAH
            $finalImage->place($aiImage, 'top-center');

            // B. Buat & Tempel Garis Aksen
            // Garis ini memisahkan gambar AI dengan area footer teks
            $accentLine = $manager->create(1080, 15)->fill($accentColor);
            // Ditempel tepat di bawah gambar AI (di koordinat y=1080)
            $finalImage->place($accentLine, 'top-left', 0, 1080);

            // C. Tulis JUDUL UTAMA (Dari Input n8n)
            $fontPathBold = public_path('fonts/Roboto_Condensed-SemiBold.ttf'); // Sesuaikan nama file font-mu
            
            if (file_exists($fontPathBold)) {
                // Koordinat x=540 (tengah), y=1200 (area footer)
                $finalImage->text($request->title, 540, 1200, function ($font) use ($fontPathBold, $textColor) {
                    $font->file($fontPathBold);
                    $font->size(58);       // Ukuran font besar
                    $font->color($textColor);
                    $font->align('center'); // Rata tengah secara horizontal
                    $font->valign('middle'); // Rata tengah secara vertikal di titik koordinat
                    $font->wrap(1000);     // Bungkus teks otomatis jika melebihi lebar 1000px
                    $font->lineHeight(1.3); // Jarak antar baris
                });
            } else {
                 // Opsional: Error log jika font tidak ditemukan
                 // error_log("Font file not found at: " . $fontPathBold);
            }

            // D. Tulis SUB-JUDUL (Branding statis)
            // Menggunakan font yang sama tapi lebih kecil, ditaruh di bawah judul utama
            if (file_exists($fontPathBold)) {
                $finalImage->text('Solusi Manajemen Stok UKM', 540, 1310, function ($font) use ($fontPathBold, $textColor) {
                    $font->file($fontPathBold);
                    $font->size(24);
                    $font->color($textColor);
                    $font->align('center');
                    $font->valign('top');
                });
            }


            // ==================================================
            // LANGKAH 5: TEMPEL WATERMARK LOGO (OVERLAY)
            // ==================================================
            $logoPath = public_path('watermark.png');
            if (file_exists($logoPath)) {
                $watermark = $manager->read($logoPath);
                
                // Resize proporsional logo agar tidak terlalu besar (misal lebar 250px)
                $watermark->scaleDown(width: 250);

                // Tempel di Pojok Kanan Atas dengan sedikit jarak (margin 40px)
                $finalImage->place($watermark, 'top-right', 40, 40);
            }


            // ==================================================
            // LANGKAH 6: OUTPUT
            // ==================================================
            // Kembalikan hasil sebagai file JPEG kualitas 90
            return response($finalImage->toJpeg(90)->toString())
                ->header('Content-Type', 'image/jpeg');

        } catch (\Exception $e) {
            // Tangkap error dan kembalikan detailnya (untuk debugging)
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}