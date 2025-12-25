const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const exts = ['.jsx', '.tsx', '.js', '.html', '.php', '.blade.php'];
let changedFiles = [];

function walk(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      // skip node_modules, vendor, dist
      if (['node_modules', 'vendor', 'dist', 'public'].includes(entry.name)) continue;
      walk(full);
    } else {
      const ext = path.extname(entry.name).toLowerCase();
      // also match blade.php specifically
      const isBlade = entry.name.endsWith('.blade.php');
      if (exts.includes(ext) || isBlade) {
        processFile(full, isBlade);
      }
    }
  }
}

function processFile(filePath, isBlade) {
  let content = fs.readFileSync(filePath, 'utf8');
  const original = content;
  let changed = false;

  // Regex to match opening <button ... className="btn"> across newlines
  const re = /<button\b([\s\S]*?) className="btn">/gi;
  content = content.replace(re, (full, attrs) => {
    // if class or className already present, skip
    if (/\bclassName\s*=|\bclass\s*=/i.test(attrs)) return full;

    // determine attr insertion based on file type
    const ext = path.extname(filePath).toLowerCase();
    const useClassName = ext === '.jsx' || ext === '.tsx' || ext === '.js';
    const insert = useClassName ? ' className="btn"' : ' class="btn"';

    changed = true;
    // place before closing > ensuring spacing
    return `<button${attrs}${insert} className="btn">`;
  });

  if (changed && content !== original) {
    fs.writeFileSync(filePath, content, 'utf8');
    changedFiles.push(filePath);
  }
}

console.log('Scanning from', ROOT);
walk(ROOT);
console.log('Modified files:', changedFiles.length);
changedFiles.forEach(f => console.log(' -', path.relative(ROOT, f)));

if (changedFiles.length === 0) process.exit(0);
else process.exit(0);
