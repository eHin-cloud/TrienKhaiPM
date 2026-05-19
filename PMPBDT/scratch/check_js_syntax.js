const fs = require('fs');
const html = fs.readFileSync('views/admin/admin.php', 'utf8');
const scriptRegex = /<script>([\s\S]*?)<\/script>/g;
let match;
while ((match = scriptRegex.exec(html)) !== null) {
    const scriptContent = match[1];
    try {
        // Evaluate the script content. Note: this might fail because of <?= ?> tags.
        // We will mock the PHP tags to valid JS strings to just check the JS syntax.
        let sanitized = scriptContent.replace(/<\?=.*?\?>/g, '"mocked_php_output"');
        new Function(sanitized);
    } catch (e) {
        console.error('Syntax error in script block at index ' + match.index + ':', e);
        // Print context of the error
        // Note: the line number in the error might be relative to the script block.
        console.log(e.stack);
    }
}
