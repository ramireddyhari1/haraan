<?php
// Downloads the Flaticon cricket player PNG and saves to public/images/cricket-player.png
$url = 'https://cdn-icons-png.flaticon.com/512/2348/2348726.png';
$dir = __DIR__ . '/../public/images';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$out = $dir . '/cricket-player.png';
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: PHP-script\r\n"
    ]
];
$context = stream_context_create($opts);
$data = @file_get_contents($url, false, $context);
if ($data === false) {
    echo "ERROR_FETCH\n";
    exit(1);
}
file_put_contents($out, $data);
echo "WROTE_OK\n";
