<?php
// fonnte_helper.php

/**
 * Fungsi untuk mengirim notifikasi WhatsApp menggunakan Fonnte API.
 *
 * @param string $phone Nomor tujuan (contoh: "08123456789")
 * @param string $message Pesan yang akan dikirim
 * @return mixed Response dari API Fonnte
 */
function send_whatsapp_notification($phone, $message)
{
    $token = 'gQdGzG1sU2kobqDYQC7T'; // Ganti dengan token API Anda
    $api_url = 'https://api.fonnte.com/send';

    // Data yang dikirimkan ke API
    $data = [
        'target'      => $phone,
        'message'     => $message,
        'countryCode' => '62' // Pastikan sesuai dengan kode negara
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $token
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
