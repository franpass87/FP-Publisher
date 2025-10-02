#!/usr/bin/env node
import fs from 'node:fs/promises';
import path from 'node:path';

const AUTHOR = {
  name: 'Francesco Passeri',
  email: 'info@francescopasseri.com',
  uri: 'https://francescopasseri.com',
  pluginUri: 'https://francescopasseri.com',
};

const DESCRIPTION = 'Centralizes scheduling and publishing across WordPress and social channels with queue-driven workflows and SPA tools.';
const COMPOSER_DESCRIPTION = 'Centralizes scheduling and publishing across WordPress and social channels with queue-driven workflows.';

const args = process.argv.slice(2);
const apply = parseApply(args);
const docsMode = args.includes('--docs');

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const pluginFile = path.join(root, 'fp-digital-publisher.php');
const composerFile = path.join(root, 'composer.json');
const packageFile = path.join(root, 'package.json');
const readmeMdFile = path.join(root, 'README.md');
const readmeTxtFile = path.join(root, 'readme.txt');
const overviewFile = path.join(root, 'docs', 'overview.md');
const faqFile = path.join(root, 'docs', 'faq.md');

const pluginHeader = await readPluginHeader(pluginFile);
const version = pluginHeader.version ?? '0.0.0';
const requiresAtLeast = pluginHeader['requires at least'] ?? '6.4';
const requiresPhp = pluginHeader['requires php'] ?? '8.0';
const testedUpTo = '6.6';

const report = [];

await updateFile(pluginFile, updatePluginHeader, ['Description', 'Author', 'Author URI']);
await updateFile(composerFile, updateComposer, ['description', 'authors', 'homepage', 'support', 'scripts']);
await updateFile(packageFile, updatePackage, ['author', 'homepage', 'bugs', 'scripts']);
await updateFile(readmeTxtFile, updateReadmeTxt, ['metadata', 'short-description']);
await updateFile(readmeMdFile, updateReadmeMd, ['table', 'short-description']);
if (docsMode) {
  await updateFile(overviewFile, updateDocsShortDescription, ['short-description']);
}
if (docsMode) {
  await updateFile(faqFile, updateDocsMetadata, ['metadata']);
}

if (report.length === 0) {
  console.log('No changes required.');
} else {
  console.table(report);
}

function parseApply(argv) {
  for (const arg of argv) {
    if (! arg.startsWith('--apply')) {
      continue;
    }

    const [, raw] = arg.split('=');
    if (raw === undefined || raw === '') {
      return true;
    }

    const value = raw.toLowerCase();
    return value !== 'false' && value !== '0' && value !== 'no';
  }

  return false;
}

async function readPluginHeader(filePath) {
  const content = await fs.readFile(filePath, 'utf8');
  const header = {};
  const regex = /^\s*\*\s*([^:]+):\s*(.+)$/gim;
  for (const match of content.matchAll(regex)) {
    header[match[1].trim().toLowerCase()] = match[2].trim();
  }

  return header;
}

async function updateFile(filePath, updater, fields) {
  try {
    const original = await fs.readFile(filePath, 'utf8');
    const updated = await updater(original);
    if (updated === original) {
      return;
    }

    if (apply) {
      await fs.writeFile(filePath, updated, 'utf8');
    } else {
      await fs.writeFile(`${filePath}.bak`, updated, 'utf8');
    }

    report.push({ file: path.relative(root, filePath), fields: fields.join(', ') });
  } catch (error) {
    if (error && error.code === 'ENOENT') {
      return;
    }

    console.error(`Failed to update ${filePath}:`, error);
    process.exitCode = 1;
  }
}

async function updatePluginHeader(content) {
  let updated = content;
  updated = replaceHeaderLine(updated, 'Description', DESCRIPTION);
  updated = replaceHeaderLine(updated, 'Author', AUTHOR.name);
  updated = replaceHeaderLine(updated, 'Author URI', AUTHOR.uri);

  return updated;
}

function replaceHeaderLine(content, label, value) {
  const pattern = new RegExp(`(^\\s*\\*\\s*${escapeRegex(label)}:\\s*)(.*)$`, 'mi');
  if (! pattern.test(content)) {
    return content;
  }

  return content.replace(pattern, `$1${value}`);
}

async function updateComposer(content) {
  const data = JSON.parse(content);
  data.description = COMPOSER_DESCRIPTION;
  data.homepage = AUTHOR.pluginUri;
  data.support = data.support ?? {};
  data.support.issues = AUTHOR.pluginUri;

  data.authors = [
    {
      name: AUTHOR.name,
      email: AUTHOR.email,
      homepage: AUTHOR.uri,
      role: 'Developer',
    },
  ];

  data.scripts = data.scripts ?? {};
  data.scripts['sync:author'] = "node tools/sync-author-metadata.js --apply=${APPLY:-false}";
  data.scripts['sync:docs'] = "node tools/sync-author-metadata.js --docs --apply=${APPLY:-false}";
  data.scripts['changelog:from-git'] = 'conventional-changelog -p angular -i CHANGELOG.md -s || true';

  return `${JSON.stringify(data, null, 4)}\n`;
}

async function updatePackage(content) {
  const data = JSON.parse(content);
  data.author = `${AUTHOR.name} <${AUTHOR.email}> (${AUTHOR.uri})`;
  data.homepage = AUTHOR.pluginUri;
  data.bugs = { url: AUTHOR.pluginUri };

  data.scripts = data.scripts ?? {};
  data.scripts['sync:author'] = "node tools/sync-author-metadata.js --apply=${APPLY:-false}";
  data.scripts['sync:docs'] = "node tools/sync-author-metadata.js --docs --apply=${APPLY:-false}";
  data.scripts['changelog:from-git'] = 'conventional-changelog -p angular -i CHANGELOG.md -s || true';

  if (! data.devDependencies) {
    data.devDependencies = {};
  }
  if (! data.devDependencies['conventional-changelog-cli']) {
    data.devDependencies['conventional-changelog-cli'] = '^3.0.0';
  }

  return `${JSON.stringify(data, null, 2)}\n`;
}

async function updateReadmeTxt(content) {
  let updated = content;
  updated = replaceMetadataLine(updated, 'Contributors', 'francescopasseri');
  updated = replaceMetadataLine(updated, 'Donate link', AUTHOR.pluginUri);
  updated = replaceMetadataLine(updated, 'Plugin Homepage', AUTHOR.pluginUri);
  updated = replaceMetadataLine(updated, 'Author', AUTHOR.name);
  updated = replaceMetadataLine(updated, 'Stable tag', version);
  updated = replaceMetadataLine(updated, 'Requires at least', requiresAtLeast);
  updated = replaceMetadataLine(updated, 'Requires PHP', requiresPhp);
  updated = replaceMetadataLine(updated, 'Tested up to', testedUpTo);
  updated = replaceShortDescription(updated, DESCRIPTION);

  return updated;
}

async function updateReadmeMd(content) {
  let updated = content;
  updated = replaceInlineMarker(updated, 'sync:version', version);
  updated = replaceInlineMarker(updated, 'sync:author', `[${AUTHOR.name}](${AUTHOR.uri}) <${AUTHOR.email}>`);
  updated = replaceInlineMarker(updated, 'sync:author-uri', AUTHOR.uri);
  updated = replaceInlineMarker(updated, 'sync:plugin-uri', AUTHOR.pluginUri);
  updated = replaceInlineMarker(updated, 'sync:wp-requires', requiresAtLeast);
  updated = replaceInlineMarker(updated, 'sync:wp-tested', testedUpTo);
  updated = replaceInlineMarker(updated, 'sync:php-requires', requiresPhp);
  updated = replaceShortDescription(updated, DESCRIPTION);

  return updated;
}

async function updateDocsShortDescription(content) {
  return replaceShortDescription(content, DESCRIPTION);
}

async function updateDocsMetadata(content) {
  return content;
}

function replaceMetadataLine(content, label, value) {
  const pattern = new RegExp(`(^${escapeRegex(label)}:\\s*)(.*)$`, 'mi');
  if (! pattern.test(content)) {
    return content;
  }

  return content.replace(pattern, `$1${value}`);
}

function replaceShortDescription(content, value) {
  const pattern = /(<!--\s*sync:short-description:start\s*-->)([\s\S]*?)(<!--\s*sync:short-description:end\s*-->)/m;
  if (! pattern.test(content)) {
    return content;
  }

  return content.replace(pattern, `$1\n${value}\n$3`);
}

function replaceInlineMarker(content, marker, value) {
  const pattern = new RegExp(`(<!--\\s*${escapeRegex(marker)}\\s*-->)([\\s\\S]*?)(<!--\\s*/${escapeRegex(marker)}\\s*-->)`, 'g');
  if (! pattern.test(content)) {
    return content;
  }

  return content.replace(pattern, `$1${value}$3`);
}

function escapeRegex(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
