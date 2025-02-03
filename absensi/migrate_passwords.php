<?php
// migrate_passwords.php

require_once '../../koneksi.php'; // Menghubungkan ke database

// Query untuk mengambil semua guru dengan password plaintext
$query = "SELECT nip, password FROM anggota_sekolah WHERE LENGTH(password) < 60"; // Asumsi hash bcrypt lebih panjang dari 60 karakter
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $nip = $row['nip'];
        $plaintext_password = $row['password'];

        // Hash password plaintext menggunakan bcrypt
        $hashed_password = password_hash($plaintext_password, PASSWORD_DEFAULT);

        // Update password di database dengan yang telah di-hash
        $stmt = $conn->prepare("UPDATE anggota_sekolah SET password = ? WHERE nip = ?");
        $stmt->bind_param("ss", $hashed_password, $nip);
        if ($stmt->execute()) {
            echo "Password untuk NIP $nip telah berhasil di-hash.<br>";
        } else {
            echo "Gagal meng-hash password untuk NIP $nip.<br>";
        }
        $stmt->close();
    }
} else {
    echo "Tidak ada password plaintext yang ditemukan.";
}

$conn->close();
?>
