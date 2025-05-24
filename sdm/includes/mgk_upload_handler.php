<?php
/**
 *  mgk_upload_handler.php
 *  -------------------------------------------------------------
 *  – Helper upload / konversi gambar  (profil & KTP)
 *  – Generator URL foto & placeholder
 *
 *  Dipakai oleh: mgk_crud_guru.php, halaman lain yang butuh upload
 */

require_once __DIR__.'/mgk_date_utils.php';   // butuh getBaseUrl()
if (!function_exists('send_response')) {
    // fallback minimal jika helper global belum di‐include
    function send_response(int $code, $result = ''): void {
        echo json_encode(['code'=>$code,'result'=>$result],JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* -------------------------------------------------------------
 *  Konstanta folder upload
 * ----------------------------------------------------------- */
const PROF_DIR = __DIR__.'/../../uploads/profile_pics/';
const KTP_DIR  = __DIR__.'/../../uploads/ktp_pics/';

/* Pastikan folder ada */
foreach ([PROF_DIR, KTP_DIR] as $d) {
    if (!is_dir($d)) mkdir($d,0755,true);
}

/* -------------------------------------------------------------
 *  save_image_as_jpg()  – konversi ke JPG kualitas 90 %
 * ----------------------------------------------------------- */
if (!function_exists('save_image_as_jpg')) {
    function save_image_as_jpg(string $tmp, string $destFolder, string $fileName): string
    {
        $info = getimagesize($tmp);
        if (!$info) send_response(1,'File bukan gambar');

        switch ($info['mime']) {
            case 'image/jpeg': $src=imagecreatefromjpeg($tmp); break;
            case 'image/png' : $src=imagecreatefrompng($tmp);  break;
            case 'image/gif' : $src=imagecreatefromgif($tmp);  break;
            default:          send_response(1,'Format gambar tidak didukung');
        }
        if (!is_dir($destFolder)) mkdir($destFolder,0755,true);

        $fullPath = $destFolder.$fileName;
        if (!imagejpeg($src,$fullPath,90)) {
            imagedestroy($src);
            send_response(1,'Gagal menyimpan gambar');
        }
        imagedestroy($src);

        $rel = substr($fullPath, strpos($fullPath,'/uploads'));
        return getBaseUrl().$rel;
    }
}

/* -------------------------------------------------------------
 *  Helper upload khusus (profil & KTP)
 * ----------------------------------------------------------- */
function uploadProfileIfAny(string $field, string $slug): string
{
    if (empty($_FILES[$field]['tmp_name']) ||
        $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return '';

    if ($_FILES[$field]['size'] > 2*1024*1024)
        send_response(1,'Ukuran foto profil maks 2 MB');

    return save_image_as_jpg(
        $_FILES[$field]['tmp_name'],
        PROF_DIR,
        $slug.'.jpg'
    );
}

function uploadKtpIfAny(string $field, string $slug): string
{
    if (empty($_FILES[$field]['tmp_name']) ||
        $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return '';

    if ($_FILES[$field]['size'] > 2*1024*1024)
        send_response(1,'Ukuran foto KTP maks 2 MB');

    return save_image_as_jpg(
        $_FILES[$field]['tmp_name'],
        KTP_DIR,
        $slug.'.jpg'
    );
}

/* -------------------------------------------------------------
 *  URL generator + placeholder
 * ----------------------------------------------------------- */
function getKtpPhotoUrl(string $dbVal=''): string
{
    if (!$dbVal) return getBaseUrl().'/assets/img/ktp_placeholder.png';
    if (strpos($dbVal,'http')===0) return $dbVal;
    $f = basename($dbVal);
    return getBaseUrl()."/uploads/ktp_pics/$f";
}
