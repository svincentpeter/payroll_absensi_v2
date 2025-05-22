<?php
/**
 * Proses upload file & bangun URL
 */

/**
 * Simpan file upload dan kembalikan nama file baru.
 */
function saveUploadedFile(string $inputName, string $targetDir): string
{
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmpName = $_FILES[$inputName]['tmp_name'];
    $ext     = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        return '';
    }
    $newName = time() . '_' . bin2hex(random_bytes(4)) . ".$ext";
    $dest    = $targetDir . $newName;
    if (move_uploaded_file($tmpName, $dest)) {
        return $newName;
    }
    return '';
}

/**
 * Bangun URL Foto KTP
 */
function getKtpPhotoUrl(string $filename): string
{
    return getBaseUrl() . '/uploads/ktp_pics/' . ($filename ?: 'ktp_placeholder.png');
}