<?php

require_once __DIR__ . "/../config/config.php";

function encryptEmail(string $email): string
{
    $cipher = 'AES-256-CBC';

    $key = hash('sha256', EMAIL_ENCRYPTION_KEY, true);
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLength);

    $ciphertext = openssl_encrypt(
        $email,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    $hmac = hash_hmac('sha256', $ciphertext, $key, true);

    return 'v2:' . base64_encode($iv . $hmac . $ciphertext);
}


function decryptEmail(string $encryptedEmail): ?string
{
    $cipher = 'AES-256-CBC';

    // ✅ NEW FORMAT
    if (str_starts_with($encryptedEmail, 'v2:')) {

        $key = hash('sha256', EMAIL_ENCRYPTION_KEY, true);
        $data = base64_decode(substr($encryptedEmail, 3), true);

        if ($data === false) return null;

        $ivLength = openssl_cipher_iv_length($cipher);

        $iv = substr($data, 0, $ivLength);
        $hmac = substr($data, $ivLength, 32);
        $ciphertext = substr($data, $ivLength + 32);

        $calculatedHmac = hash_hmac('sha256', $ciphertext, $key, true);

        if (!hash_equals($hmac, $calculatedHmac)) {
            return null;
        }

        return openssl_decrypt(
            $ciphertext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    // ✅ LEGACY FORMAT (your current production data)
    return openssl_decrypt(
        $encryptedEmail,
        $cipher,
        EMAIL_ENCRYPTION_KEY,
        0,
        EMAIL_ENCRYPTION_IV
    );
}

function encryptData($data)
{
    $cipher = 'AES-256-CBC';
    $key = hash('sha256', DATA_ENCRYPTION_KEY, true);

    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLength);

    $ciphertext = openssl_encrypt(
        $data,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    $hmac = hash_hmac('sha256', $ciphertext, $key, true);

    return base64_encode($iv . $hmac . $ciphertext);
}

function decryptData($data)
{
    $cipher = 'AES-256-CBC';
    $key = hash('sha256', DATA_ENCRYPTION_KEY, true);

    $data = base64_decode($data, true);
    if ($data === false) return null;

    $ivLength = openssl_cipher_iv_length($cipher);

    $iv = substr($data, 0, $ivLength);
    $hmac = substr($data, $ivLength, 32);
    $ciphertext = substr($data, $ivLength + 32);

    $calculatedHmac = hash_hmac('sha256', $ciphertext, $key, true);

    if (!hash_equals($hmac, $calculatedHmac)) {
        return null; // tampered
    }

    return openssl_decrypt(
        $ciphertext,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
}

function hashEmail(string $email): string
{
    return hash('sha256', strtolower(trim($email)));
}
