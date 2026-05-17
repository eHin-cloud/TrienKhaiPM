<?php
$content = file_get_contents('d:/Sever/htdocs/PMPBDT/core/lang/vi.php');
preg_match_all("/'([^']+)'\s*=>/", $content, $matches);
$keys = $matches[1];
$counts = array_count_values($keys);
foreach ($counts as $key => $count) {
    if ($count > 1) echo "Duplicate key: $key ($count times)\n";
}
