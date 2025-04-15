<?php
// fonnte_helper.php
require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

/**
 * Kirim pesan WhatsApp via Fonnte
 * @param string $target  Nomor tujuan format 628xxx (atau id grup)
 * @param string $message Isi pesan
 * @param string $token   API‑token (device token)
 * @return bool
 */
function sendFonnteNotification(string $target, string $message, string $token): bool
{
    $client  = new Client(['timeout' => 10]);
    $attempt = 0;
    $max     = 3;

    while ($attempt < $max) {
        try {
            $res = $client->post(
                'https://api.fonnte.com/send',
                [
                    'headers'     => ['Authorization' => $token], // ← TANPA “Bearer”
                    'multipart'   => [
                        ['name' => 'target',  'contents' => $target],
                        ['name' => 'message', 'contents' => $message],
                        // optional → ['name' => 'countryCode','contents'=>'62'],
                    ],
                ]
            );
            return $res->getStatusCode() === 200;
        } catch (\Throwable $e) {
            $attempt++;
            error_log("[FONNTE] try $attempt : " . $e->getMessage());
            sleep(1);
        }
    }
    return false;
}
