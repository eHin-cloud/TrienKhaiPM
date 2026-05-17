<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../src/Service/AdminService.php';

$adminService = new \App\Service\AdminService($db);
$data = $adminService->getDashboardData();

echo "STATS: " . json_encode($data['stats']) . PHP_EOL;
echo "CHART DATA: " . json_encode($data['chartData']) . PHP_EOL;
