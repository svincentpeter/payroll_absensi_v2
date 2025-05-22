<?php
/**
 * Utility tanggal untuk kontrak & masa kerja
 */

function addMonths(string $date, int $months): string
{
    try {
        $d = new DateTime($date);
        // kontrak berakhir H-1
        $d->modify("+{$months} months")->modify('-1 day');
        return $d->format('Y-m-d');
    } catch (Exception $e) {
        return '0000-00-00';
    }
}

function calcMasaKerja(string $join_start): array
{
    if (!$join_start || $join_start === '0000-00-00') {
        return [0, 0, 0.00];
    }
    try {
        $start = new DateTime($join_start);
        $now   = new DateTime();
        $diff  = $now->diff($start);
        $tahun = (int)$diff->y;
        $bulan = (int)$diff->m;
        $efektif = round($tahun + $bulan / 12, 2);
        return [$tahun, $bulan, $efektif];
    } catch (Exception $e) {
        return [0, 0, 0.00];
    }
}
