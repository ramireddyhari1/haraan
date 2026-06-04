<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$count = DB::table('events')->where('status', 'PUBLISHED')->orWhere('status','PUBLISHED')->update(['status' => 'published']);
echo "Updated $count rows\n";