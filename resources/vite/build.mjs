#!/usr/bin/env node

/**
 * Builds assets/ one app at a time (see APPS in apps.mjs), then merges the per-run
 * manifests into assets/manifest.json, the one file PHP reads.
 *
 * A JS app builds with cssCodeSplit off, so Vite writes all of its CSS to one stylesheet and
 * records it in the manifest as "style.css", attached to nothing. That record is hung on the app's
 * first entry as its `css`, so the enqueuer loads it with the script (and swaps in the .rtl.css
 * twin on RTL sites). The fcom portal app is the exception: it keeps its CSS per lazy chunk (see
 * LAZY_CSS_APPS in vite.config.js), and its chunks pick the .rtl.css twin at runtime.
 */

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { APPS } from './apps.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const assetsDir = path.join(root, 'assets');
const names = Object.keys(APPS);

function fail(message) {
    console.error(`\n${message}`);
    process.exit(1);
}

names.forEach((name, index) => {
    console.log(`\n[${index + 1}/${names.length}] Building ${name}`);
    const result = spawnSync('npx', ['vite', 'build'], {
        cwd: root,
        stdio: 'inherit',
        env: { ...process.env, VITE_APP_NAME: name, VITE_EMPTY_OUT_DIR: index === 0 ? 'yes' : 'no' }
    });
    if (result.status !== 0) {
        fail(`vite build failed for ${name}.`);
    }
});

const merged = {};

for (const name of names) {
    const partFile = path.join(assetsDir, `manifest-${name}.json`);
    if (!fs.existsSync(partFile)) {
        fail(`${path.relative(root, partFile)} is missing.`);
    }
    const part = JSON.parse(fs.readFileSync(partFile, 'utf8'));
    fs.unlinkSync(partFile);

    if (name !== 'styles' && part['style.css']) {
        const entry = part[APPS[name][0]];
        if (!entry) {
            fail(`The ${name} manifest has no record for ${APPS[name][0]}.`);
        }
        entry.css = [part['style.css'].file, ...(entry.css || [])];
        delete part['style.css'];
    }

    for (const [key, record] of Object.entries(part)) {
        // Apps build separately, so a shared module can appear in two of them. Its file name carries a
        // content hash, so the same key means the same file; a differing record is a real clash.
        if (merged[key] && JSON.stringify(merged[key]) !== JSON.stringify(record)) {
            fail(`Manifest key "${key}" differs between builds.`);
        }
        merged[key] = record;
    }
}

// An entry is loaded with a ?ver= (PHP's, or fcom's for the portal entry); a chunk that imports it
// by its bare name gets a second copy, and the entry's code (router, route registration) runs twice.
const entryFiles = Object.values(merged).filter((record) => record.isEntry && record.file.endsWith('.js')).map((record) => record.file);
for (const chunk of fs.readdirSync(assetsDir).filter((file) => file.endsWith('.js'))) {
    const code = fs.readFileSync(path.join(assetsDir, chunk), 'utf8');
    for (const entry of entryFiles) {
        if (entry !== chunk && (code.includes(`"./${entry}"`) || code.includes(`'./${entry}'`))) {
            fail(`${chunk} imports the entry ${entry}; its code would run twice. Keep shared code out of entries.`);
        }
    }
}

fs.writeFileSync(path.join(assetsDir, 'manifest.json'), JSON.stringify(merged, null, 2) + '\n');
console.log(`\nMerged ${Object.keys(merged).length} manifest records from ${names.length} builds.`);
