#!/usr/bin/env node
import { build, context } from 'esbuild';
import { promises as fs } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const args = process.argv.slice(2);
const watchMode = args.includes('--watch');
const cleanOnly = args.includes('--clean') && ! watchMode;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const pluginRoot = path.resolve(__dirname, '..');
const adminDir = path.join(pluginRoot, 'admin');
const distDir = path.join(adminDir, 'dist');
const manifestPath = path.join(distDir, 'manifest.json');

async function ensureCleanOutput() {
    await fs.rm(distDir, { recursive: true, force: true });
    await fs.mkdir(distDir, { recursive: true });
}

function toPosix(value) {
    return value.replace(/\\+/g, '/').replace(/^\.\//, '');
}

async function discoverEntryPoints() {
    const entryPoints = [];

    const jsDir = path.join(adminDir, 'js');
    const cssDir = path.join(adminDir, 'css');

    try {
        const jsFiles = await fs.readdir(jsDir, { withFileTypes: true });
        jsFiles
            .filter((file) => file.isFile() && file.name.endsWith('.js'))
            .forEach((file) => {
                entryPoints.push(toPosix(path.join('admin/js', file.name)));
            });
    } catch (error) {
        if (error.code !== 'ENOENT') {
            throw error;
        }
    }

    try {
        const cssFiles = await fs.readdir(cssDir, { withFileTypes: true });
        cssFiles
            .filter((file) => file.isFile() && file.name.endsWith('.css'))
            .forEach((file) => {
                entryPoints.push(toPosix(path.join('admin/css', file.name)));
            });
    } catch (error) {
        if (error.code !== 'ENOENT') {
            throw error;
        }
    }

    return entryPoints;
}

async function writeManifest(metafile) {
    if (!metafile || !metafile.outputs) {
        return;
    }

    const manifest = {};
    for (const [outputPath, outputMeta] of Object.entries(metafile.outputs)) {
        if (!outputMeta.entryPoint) {
            continue;
        }

        const entry = toPosix(outputMeta.entryPoint);
        const output = toPosix(outputPath);
        manifest[entry] = output;
    }

    await fs.writeFile(manifestPath, JSON.stringify(manifest, null, 2));
}

function manifestPlugin() {
    return {
        name: 'tts-manifest-writer',
        setup(buildApi) {
            buildApi.onEnd(async (result) => {
                if (result.errors && result.errors.length > 0) {
                    console.error('[tts-assets] Build completed with errors, manifest not updated.');
                    return;
                }

                try {
                    await writeManifest(result.metafile);
                    console.log(`[tts-assets] Manifest written to ${path.relative(pluginRoot, manifestPath)}`);
                } catch (error) {
                    console.error('[tts-assets] Failed to write manifest:', error);
                }
            });
        },
    };
}

async function runBuild() {
    const entryPoints = await discoverEntryPoints();

    if (entryPoints.length === 0) {
        console.warn('[tts-assets] No entry points found under admin/js or admin/css.');
        return;
    }

    const buildOptions = {
        absWorkingDir: pluginRoot,
        entryPoints,
        bundle: true,
        format: 'iife',
        sourcemap: true,
        minify: true,
        metafile: true,
        outdir: 'admin/dist',
        outbase: 'admin',
        entryNames: '[dir]/[name]-[hash]',
        assetNames: 'assets/[name]-[hash]',
        logLevel: 'info',
        target: 'es2018',
        loader: {
            '.css': 'css',
        },
        plugins: [manifestPlugin()],
    };

    if (watchMode) {
        const ctx = await context(buildOptions);
        await ctx.watch();
        console.log('[tts-assets] Watching admin assets for changes...');
        process.stdin.resume();
        return;
    }

    const result = await build(buildOptions);
    await writeManifest(result.metafile);
    console.log('[tts-assets] Build complete.');
}

(async function main() {
    if (cleanOnly) {
        await ensureCleanOutput();
        console.log('[tts-assets] Cleaned admin/dist.');
        return;
    }

    await ensureCleanOutput();
    await runBuild();
})();
