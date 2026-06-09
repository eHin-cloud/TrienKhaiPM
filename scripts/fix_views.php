<?php
function processDirectory($dir) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            processDirectory($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($path);
            
            // Remove session_start()
            $content = preg_replace('/session_start\s*\(\s*\)\s*;/i', '// session_start() removed by Router', $content);
            
            // Remove require_once 'database.php'
            $content = preg_replace('/require_once\s+[\'"]database\.php[\'"]\s*;/i', '// database.php is auto-loaded by Router', $content);
            $content = preg_replace('/require_once\s+[\'"]test_db_functions\.php[\'"]\s*;/i', '// test_db_functions.php removed', $content);
            
            // Fix references to header and footer (for files in pages directory mostly)
            $content = preg_replace('/require_once\s+[\'"]header\.php[\'"]\s*;/i', 'require_once __DIR__ . \'/../partials/header.php\';', $content);
            $content = preg_replace('/require_once\s+[\'"]footer\.php[\'"]\s*;/i', 'require_once __DIR__ . \'/../partials/footer.php\';', $content);
            
            // Fix mail_helper
            $content = preg_replace('/require_once\s+[\'"]mail_helper\.php[\'"]\s*;/i', 'require_once __DIR__ . \'/../../core/mail_helper.php\';', $content);

            // Fix image uploads paths (since uploads was moved to public/uploads, scripts that run via public/index.php will naturally resolve "uploads/xxx". But admin logic in App\Service doesn't need upload rewriting unless absolute path is required.)
            
            file_put_contents($path, $content);
            echo "Processed: $path\n";
        }
    }
}

// Chạy trực tiếp từ d:\Sever\htdocs\PMSuaCode
processDirectory('views');
echo "Done replacing paths in views.\n";
