<?php
// Amount validation, image uploads, and checkout schema checks.
function submitted_amount_to_cents($value): ?int
{
    if (!is_int($value) && !is_float($value)) {
        return null;
    }

    $amount = (float)$value;
    if (!is_finite($amount) || $amount < 0 || $amount > 99999999.99) {
        return null;
    }

    $scaled = $amount * 100;
    $cents = (int)round($scaled);

    return abs($scaled - $cents) < 0.0001 ? $cents : null;
}

function database_amount_to_cents($value): ?int
{
    if (
        !is_string($value) ||
        !preg_match('/^\\d{1,8}(?:\\.\\d{1,2})?$/D', $value)
    ) {
        return null;
    }

    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

    return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
}

function store_payment_proof(string $field = 'payment_proof', string $folder = 'payment_proofs', string $label = 'Proof of payment'): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException("$label is too large. Please upload an image 5 MB or smaller.");
    }

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("Could not upload $label. Please try again.");
    }

    if ((int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException("$label is too large. Please upload an image 5 MB or smaller.");
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $extensions = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp'
    ];

    if (!$imageInfo || !isset($extensions[$imageInfo[2]])) {
        throw new RuntimeException("$label must be a JPG, PNG, or WebP image.");
    }

    $directory = __DIR__ . '/../uploads/' . $folder;

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Could not save $label. Please try again.");
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$imageInfo[2]];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException("Could not save $label. Please try again.");
    }

    return 'uploads/' . $folder . '/' . $filename;
}

function orders_support_payment_proof(mysqli $conn): bool
{
    return $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'payment_proof'")->num_rows > 0;
}
