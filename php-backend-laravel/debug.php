<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/gamehub/actionboard/match/3/control', 'POST', [
    '_method' => 'PUT',
    'home_score' => '13',
    'away_score' => '0',
    'overs' => '1.1',
    'status' => 'Live',
    'striker_runs' => '0',
    'striker_balls' => '0',
    'non_striker_runs' => '0',
    'non_striker_balls' => '0',
    'bowler_figures' => '0-0',
    'bowler_overs' => '0.0',
]);
$request->headers->set('X-Requested-With', 'XMLHttpRequest');
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
