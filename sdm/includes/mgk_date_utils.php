<?php
/**
 *  mgk_date_utils.php
 *  --------------------------------------------------------------------
 *  Kumpulan fungsi utilitas tanggal/waktu khusus halaman
 *  “manage_guru_karyawan.php”.
 *
 *  – addMonths()             : tambah n bulan ke sebuah tanggal (akhir H-1)
 *  – calcMasaKerja()         : hitung lama bekerja (thn, bln, efektif desimal)
 *  – calcAge()               : hitung umur dari tanggal lahir
 *  – translateBulan()        : terjemahkan nomor/bahasa-Inggris bulan → Indonesia
 *  – translateHari()         : terjemahkan nama hari bahasa-Inggris → Indonesia
 *
 *  Semua fungsi dibungkus `if (!function_exists())` agar tidak bentrok
 *  bila dipakai di tempat lain.
 *  --------------------------------------------------------------------
 */

 /* ================================================================
  * 1. Tambah n bulan ke tanggal (YYYY-MM-DD), kontrak berakhir H-1
  * ================================================================ */
if (!function_exists('addMonths')) {
    /**
     * @param string $date   Format `Y-m-d`
     * @param int    $months Jumlah bulan (+)
     * @return string        Tanggal baru `Y-m-d`  (atau `'0000-00-00'` bila error)
     */
    function addMonths(string $date, int $months): string
    {
        try {
            $d = new DateTime($date);
            $d->modify("+{$months} months")->modify('-1 day');
            return $d->format('Y-m-d');
        } catch (Exception $e) {
            return '0000-00-00';
        }
    }
}

/* ================================================================
 * 2. Hitung masa kerja dari tanggal bergabung
 *    – return [tahun, bulan, efektifDesimal]
 * ================================================================ */
if (!function_exists('calcMasaKerja')) {
    function calcMasaKerja(string $join_start): array
    {
        if (!$join_start || $join_start === '0000-00-00') {
            return [0, 0, 0.00];
        }
        try {
            $start = new DateTime($join_start);
            $now   = new DateTime();
            $diff  = $now->diff($start);

            $tahun   = (int)$diff->y;
            $bulan   = (int)$diff->m;
            $efektif = round($tahun + $bulan / 12, 2);

            return [$tahun, $bulan, $efektif];
        } catch (Exception $e) {
            return [0, 0, 0.00];
        }
    }
}

/* ================================================================
 * 3. Hitung umur dari tanggal lahir
 * ================================================================ */
if (!function_exists('calcAge')) {
    function calcAge(string $dob): int
    {
        if (!$dob || $dob === '0000-00-00') {
            return 0;
        }
        try {
            $birth = new DateTime($dob);
            $today = new DateTime();
            return $birth->diff($today)->y;
        } catch (Exception $e) {
            return 0;
        }
    }
}

/* ================================================================
 * 4. Terjemahan bulan (1‒12 / english) ➜ Bahasa Indonesia
 * ================================================================ */
if (!function_exists('translateBulan')) {
    /**
     * @param string|int $month  "01"-"12" / 1-12 / "January"
     * @return string            "Januari"… dst.  ('' bila salah)
     */
    function translateBulan($month): string
    {
        $map = [
            1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
            5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
            9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
        ];

        // Jika input string english
        $eng = [
            'january'=>1, 'february'=>2, 'march'=>3, 'april'=>4,
            'may'=>5, 'june'=>6, 'july'=>7, 'august'=>8,
            'september'=>9, 'october'=>10, 'november'=>11, 'december'=>12
        ];

        if (is_numeric($month)) {
            $i = intval($month);
            return $map[$i] ?? '';
        }
        $lower = strtolower($month);
        if (isset($eng[$lower])) {
            return $map[$eng[$lower]];
        }
        return '';
    }
}

/* ================================================================
 * 5. Terjemahan hari bahasa-Inggris ➜ Bahasa Indonesia
 * ================================================================ */
if (!function_exists('translateHari')) {
    function translateHari(string $dayEn): string
    {
        $map = [
            'monday'    => 'Senin',
            'tuesday'   => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday'  => 'Kamis',
            'friday'    => 'Jumat',
            'saturday'  => 'Sabtu',
            'sunday'    => 'Minggu',
        ];
        return $map[strtolower($dayEn)] ?? '';
    }
}
