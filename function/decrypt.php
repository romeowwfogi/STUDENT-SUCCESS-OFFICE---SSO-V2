<?php
const ENCRYPTION_KEY = 'PLPasig-STUDENT-SUCCESS-OFFICE-2025';

function decryptData($encrypted)
{
    $data = base64_decode($encrypted);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
}