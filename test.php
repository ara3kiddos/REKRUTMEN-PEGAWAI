<?php
// test.php
echo "Testing config.php include...\n";

require_once 'includes/config.php';
echo "Pertama kali include - OK\n";

require_once 'includes/config.php';
echo "Kedua kali include - OK (tidak error)\n";

echo "Fungsi _schemaTableExists tersedia: " . (function_exists('_schemaTableExists') ? 'Ya' : 'Tidak') . "\n";
echo "Config loaded: " . (defined('CONFIG_LOADED') ? 'Ya' : 'Tidak') . "\n";
?>