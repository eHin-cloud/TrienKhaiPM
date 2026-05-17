<?php
require 'core/database.php';
echo "Current Database: " . $db->query("SELECT DATABASE()")->fetchColumn() . "\n";
