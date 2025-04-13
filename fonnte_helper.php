<?php
// fonnte_helper.php

/**
 * Fungsi untuk mengirim notifikasi WhatsApp menggunakan Fonnte API.
 *
 * @param string $phone Nomor tujuan, misalnya "08222786232" (akan dikonversi jika perlu)
 * @param string $message Pesan yang akan dikirim
 * @return mixed Response dari API Fonnte
 */
function send_whatsapp_notification($phone, $message)
{
    $token = 'gQdGzG1sU2kobqDYQC7T'; // Pastikan token API Anda valid.
    $api_url = 'https://api.fonnte.com/send';

    // Pastikan nomor hanya berisi digit (hapus spasi, tanda hubung, dll.)
    $phone = preg_replace('/\D/', '', $phone);

    // Jika nomor tidak diawali dengan "62" dan dimulai dengan "0", lakukan konversi
    if (substr($phone, 0, 2) !== '62' && substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    // Sekarang $phone harus dalam format internasional, misalnya "628222786232"
  
    // Susun data payload sebagai array
    $data = [
        'target'      => $phone,        // Pastikan field ini sesuai dengan dokumentasi Fonnte
        'message'     => $message,
        'countryCode' => '62'           // Jika API mengharuskan, masukkan kode negara.  
    ];

    // Encode payload sebagai JSON
    $payload = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    // Kirim payload JSON:
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        error_log("cURL error in send_whatsapp_notification(): " . $error_msg);
        return json_encode(['success' => false, 'error' => $error_msg]);
    }

    curl_close($ch);

    // Untuk debugging: tulis log respons
    error_log("Response from Fonnte API: " . $result);

    return $result;
}
