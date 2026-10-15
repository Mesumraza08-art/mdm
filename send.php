<?php
/**
 * MDM Auto Detail – form handler for the contact and quote forms.
 * Runs on Hostinger shared hosting (PHP 8+, built-in mail()).
 */

// ---- Settings -------------------------------------------------------------
$TO_EMAIL   = 'hassanpourarwin@gmail.com';          // where inquiries are delivered
$FROM_EMAIL = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
// Tip: create this mailbox in hPanel (Emails) so messages are less likely to land in spam.
$MAX_FILES      = 5;
$MAX_FILE_BYTES = 8 * 1024 * 1024;   // per photo
$MAX_TOTAL      = 20 * 1024 * 1024;  // total attachments (Gmail limit is 25 MB)
// ---------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out($ok, $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode($error ? ['ok' => $ok, 'error' => $error] : ['ok' => $ok]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Method not allowed.', 405);

// Honeypot: bots fill the hidden "website" field. Pretend success.
if (!empty($_POST['website'])) out(true);

function field($k, $max = 2000) {
    $v = isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : '';
    return mb_substr(str_replace("\0", '', $v), 0, $max);
}
function clean_header($v) { return trim(preg_replace('/[\r\n]+/', ' ', $v)); }

$form    = field('form', 20) === 'quote' ? 'quote' : 'contact';
$name    = clean_header(field('name', 120));
$email   = clean_header(field('email', 200));
$phone   = clean_header(field('phone', 40));

if ($name === '' || $email === '' || $phone === '') out(false, 'Please fill in your name, email and phone.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'Please enter a valid email address.', 422);
if (empty($_POST['consent'])) out(false, 'Please confirm consent to be contacted.', 422);

$rows = [
    'Name'  => $name,
    'Email' => $email,
    'Phone' => $phone,
];

if ($form === 'quote') {
    $services = isset($_POST['services']) && is_array($_POST['services'])
        ? array_slice(array_map(fn($s) => mb_substr(trim((string)$s), 0, 60), $_POST['services']), 0, 12) : [];
    if (!$services) out(false, 'Please choose at least one service.', 422);
    $rows['Vehicle']  = trim(field('year', 4) . ' ' . field('make', 60) . ' ' . field('model', 60));
    $rows['Services'] = implode(', ', $services);
    $rows['Details']  = field('details', 5000) ?: '(none)';
    $subject = "Quote request: {$rows['Vehicle']} – $name";
} else {
    $message = field('message', 5000);
    if ($message === '') out(false, 'Please add a message.', 422);
    $rows['Service'] = field('service', 60) ?: '(not chosen)';
    $rows['Vehicle'] = trim(field('year', 4) . ' ' . field('make', 60) . ' ' . field('model', 60)) ?: '(not given)';
    $rows['Message'] = $message;
    $subject = "Website inquiry – $name";
}

// ---- Attachments ----------------------------------------------------------
$attachments = [];
$skipped = 0;
if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $total = 0;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($_FILES['photos']['name'] as $i => $orig) {
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp  = $_FILES['photos']['tmp_name'][$i];
        $size = (int)$_FILES['photos']['size'][$i];
        $mime = $finfo->file($tmp);
        if (count($attachments) >= $MAX_FILES || $size > $MAX_FILE_BYTES || $total + $size > $MAX_TOTAL
            || !preg_match('#^image/(jpeg|png|gif|webp|heic|heif)$#', $mime)) { $skipped++; continue; }
        $total += $size;
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($orig)) ?: "photo$i.jpg";
        $attachments[] = ['name' => $safe, 'mime' => $mime, 'data' => file_get_contents($tmp)];
    }
}
$rows['Photos'] = count($attachments) . ' attached' . ($skipped ? " ($skipped skipped: too large or not an image)" : '');

// ---- Build email ----------------------------------------------------------
$body = ($form === 'quote' ? "New quote request" : "New inquiry") . " from the MDM Auto Detail website\n\n";
foreach ($rows as $k => $v) $body .= str_pad($k . ':', 10) . " $v\n";
$body .= "\nSent " . date('Y-m-d H:i') . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";

$boundary = 'mdm_' . bin2hex(random_bytes(12));
$headers  = [
    'From: MDM Website <' . $FROM_EMAIL . '>',
    'Reply-To: ' . mb_encode_mimeheader($name) . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
];

$msg  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$body\r\n";
foreach ($attachments as $a) {
    $msg .= "--$boundary\r\nContent-Type: {$a['mime']}; name=\"{$a['name']}\"\r\n"
          . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$a['name']}\"\r\n\r\n"
          . chunk_split(base64_encode($a['data'])) . "\r\n";
}
$msg .= "--$boundary--";

$sent = mail($TO_EMAIL, mb_encode_mimeheader(clean_header($subject)), $msg, implode("\r\n", $headers), '-f' . $FROM_EMAIL);

if (!$sent) out(false, 'We could not send your message right now. Please call or email us directly.', 500);
out(true);
