#!/usr/bin/env node
import { build, context } from 'esbuild';
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = fileURLToPath(new URL('..', import.meta.url));
const entryFile = path.join(projectRoot, 'assets/admin/index.tsx');
const outDir = path.join(projectRoot, 'assets/dist/admin');
const cssSource = path.join(projectRoot, 'assets/admin/index.css');
const cssTarget = path.join(outDir, 'index.css');

const isWatch = process.argv.includes('--watch');

const wpI18nPlugin = {
  name: 'wp-i18n-shim',
  setup(buildApi) {
    buildApi.onResolve({ filter: /^@wordpress\/i18n$/ }, () => ({
      path: path.join(projectRoot, 'assets/shims/wp-i18n.ts'),
    }));
  },
};

const buildOptions = {
  entryPoints: [entryFile],
  bundle: true,
  format: 'iife',
  target: ['es2019'],
  sourcemap: true,
  minify: !isWatch,
  outdir: outDir,
  entryNames: 'index',
  legalComments: 'none',
  logLevel: 'info',
  plugins: [wpI18nPlugin],
  loader: {
    '.css': 'css',
  },
};

async function ensureOutDir() {
  await fsp.mkdir(outDir, { recursive: true });
}

async function copyCss() {
  await ensureOutDir();
  const css = await fsp.readFile(cssSource, 'utf8');
  await fsp.writeFile(cssTarget, css);
  console.log('[build] Copied admin CSS');
}

function watchCss() {
  const debounced = debounce(copyCss, 100);
  debounced();
  const watcher = fs.watch(cssSource, debounced);
  return () => watcher.close();
}

function debounce(fn, delay) {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      fn(...args);
    }, delay);
  };
}

async function run() {
  if (isWatch) {
    const ctx = await context({ ...buildOptions, minify: false });
    await ctx.watch();
    console.log('[build] Watching JS changes...');
    const stopWatchingCss = watchCss();
    console.log('[build] Watching CSS changes...');

    const shutdown = async () => {
      stopWatchingCss();
      await ctx.dispose();
      process.exit(0);
    };

    process.on('SIGINT', shutdown);
    process.on('SIGTERM', shutdown);

    await new Promise(() => {});
  } else {
    await Promise.all([
      build({ ...buildOptions, minify: true }),
      copyCss(),
    ]);
    console.log('[build] Build completed');
  }
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
