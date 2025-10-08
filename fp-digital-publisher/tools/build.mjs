#!/usr/bin/env node
import { build, context } from 'esbuild';
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = fileURLToPath(new URL('..', import.meta.url));
const entryFile = path.join(projectRoot, 'assets/admin/index.tsx');
const outDir = path.join(projectRoot, 'assets/dist/admin');
const cssSource = path.join(projectRoot, 'assets/admin/styles/index.css');
const cssSourceDir = path.join(projectRoot, 'assets/admin/styles');
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

const isProduction = process.env.NODE_ENV === 'production';

const buildOptions = {
  entryPoints: [entryFile],
  bundle: true,
  format: 'iife',
  target: ['es2019'],
  sourcemap: isWatch ? true : false,
  minify: !isWatch || isProduction,
  outdir: outDir,
  entryNames: 'index',
  legalComments: 'none',
  logLevel: 'info',
  drop: isProduction ? ['console', 'debugger'] : [],
  treeShaking: true,
  plugins: [wpI18nPlugin],
  loader: {
    '.css': 'css',
  },
};

async function ensureOutDir() {
  await fsp.mkdir(outDir, { recursive: true });
}

async function resolveImports(cssPath, basePath) {
  const content = await fsp.readFile(cssPath, 'utf8');
  const dir = path.dirname(cssPath);
  
  // Match @import statements
  const importRegex = /@import\s+['"]([^'"]+)['"]\s*;/g;
  const matches = Array.from(content.matchAll(importRegex));
  
  let resolved = content;
  
  for (const match of matches) {
    const importPath = match[1];
    const fullPath = path.resolve(dir, importPath);
    
    try {
      const importedContent = await resolveImports(fullPath, basePath);
      const relativePath = path.relative(basePath, fullPath);
      resolved = resolved.replace(match[0], `/* Imported from ${relativePath} */\n${importedContent}\n`);
    } catch (err) {
      console.warn(`[build] Warning: Could not resolve import ${importPath}`);
    }
  }
  
  return resolved;
}

async function copyCss() {
  await ensureOutDir();
  let css = await resolveImports(cssSource, cssSourceDir);
  
  if (isProduction) {
    // Minify CSS for production
    css = minifyCss(css);
    console.log('[build] Minified and bundled modular CSS');
  } else {
    console.log('[build] Bundled modular CSS');
  }
  
  await fsp.writeFile(cssTarget, css);
}

function minifyCss(css) {
  return css
    .replace(/\/\*[\s\S]*?\*\//g, '') // Remove comments
    .replace(/\s+/g, ' ') // Replace multiple spaces with single space
    .replace(/\s*([{}:;,>+~])\s*/g, '$1') // Remove spaces around special characters
    .replace(/;}/g, '}') // Remove last semicolon in block
    .trim();
}

function watchCss() {
  const debounced = debounce(copyCss, 100);
  debounced();
  // Watch the entire styles directory for changes
  const watcher = fs.watch(cssSourceDir, { recursive: true }, debounced);
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
