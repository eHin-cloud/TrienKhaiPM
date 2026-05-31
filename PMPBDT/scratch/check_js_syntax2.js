const fs = require('fs');
const html = fs.readFileSync('views/admin/admin.php', 'utf8');

// We want to extract just the JS blocks
const scriptRegex = /<script>([\s\S]*?)<\/script>/g;
let match;
while ((match = scriptRegex.exec(html)) !== null) {
    let scriptContent = match[1];
    
    // Replace all <?php ... ?> and <?= ... ?> with empty strings or mocked variables
    scriptContent = scriptContent.replace(/<\?php[\s\S]*?\?>/g, '');
    scriptContent = scriptContent.replace(/<\?=[\s\S]*?\?>/g, '[]');
    
    try {
        new Function(scriptContent);
    } catch (e) {
        console.error('JS Syntax error found in script block at index ' + match.index);
        console.error(e.message);
        
        // Print the block with line numbers to help find the issue
        const lines = scriptContent.split('\n');
        lines.forEach((line, i) => console.log(`${i + 1}: ${line}`));
    }
}
