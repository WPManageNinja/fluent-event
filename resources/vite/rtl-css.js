#!/usr/bin/env node

/**
 * Writes an RTL twin (foo.css -> foo.rtl.css) for every stylesheet in assets/.
 *
 * The enqueuer (app/Utils/Enqueuer/Vite.php) swaps .css for .rtl.css on RTL sites without
 * checking that the file exists, and `vite build` empties assets/ first. So every build has
 * to regenerate them, or Arabic and Hebrew sites get a 404 for every stylesheet.
 *
 *   node resources/vite/rtl-css.js           generate
 *   node resources/vite/rtl-css.js --check   only verify; exit 1 if a twin is missing, stale or orphaned
 */

const fs = require('fs');
const path = require('path');

const assetsDir = path.resolve(__dirname, '../../assets');
const checkOnly = process.argv.includes('--check');

function walk(dir) {
    return fs.readdirSync(dir, {withFileTypes: true}).flatMap((entry) => {
        const full = path.join(dir, entry.name);
        return entry.isDirectory() ? walk(full) : [full];
    });
}

if (!fs.existsSync(assetsDir)) {
    console.error('assets/ does not exist. Run npm run build first.');
    process.exit(1);
}

const files = walk(assetsDir);
// Skip *.rtl.css, or a second run flips the flipped output into foo.rtl.rtl.css.
const sources = files.filter((f) => f.endsWith('.css') && !f.endsWith('.rtl.css'));
const problems = [];

for (const source of sources) {
    const target = source.replace(/\.css$/, '.rtl.css');
    const rel = path.relative(assetsDir, target);

    if (checkOnly) {
        if (!fs.existsSync(target)) {
            problems.push(`missing ${rel}`);
        } else if (fs.statSync(target).mtimeMs < fs.statSync(source).mtimeMs) {
            problems.push(`stale ${rel} (older than its .css)`);
        }
        continue;
    }

    // Loaded here so --check runs without node_modules (build.sh calls it on a plain checkout).
    fs.writeFileSync(target, require('rtlcss').process(fs.readFileSync(source, 'utf8')));
}

// A twin whose source is gone would otherwise ship forever, since nothing references it.
for (const file of files.filter((f) => f.endsWith('.rtl.css'))) {
    if (fs.existsSync(file.replace(/\.rtl\.css$/, '.css'))) {
        continue;
    }
    if (checkOnly) {
        problems.push(`orphan ${path.relative(assetsDir, file)}`);
    } else {
        fs.unlinkSync(file);
    }
}

if (checkOnly) {
    if (problems.length) {
        console.error(`RTL stylesheets out of date:\n  ${problems.join('\n  ')}\nRun: node resources/vite/rtl-css.js`);
        process.exit(1);
    }
    console.log(`${sources.length} RTL stylesheets present and current`);
} else {
    console.log(`Generated ${sources.length} RTL stylesheets`);
}
