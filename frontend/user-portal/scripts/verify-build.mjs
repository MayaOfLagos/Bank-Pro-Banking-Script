import { readdir, readFile, stat } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const outputDirectory = resolve(scriptDirectory, '../../../assets/user-app')

const manifest = JSON.parse(await readFile(resolve(outputDirectory, 'manifest.json'), 'utf8'))
const records = Object.values(manifest).filter((record) => record?.file)
const entryFile = records.find((record) => record.isEntry && record.file.endsWith('.js'))?.file
const stylesheetFile = records.find((record) => record.file.endsWith('.css'))?.file
if (!entryFile || !stylesheetFile) {
  throw new Error('Vue build manifest does not describe both the JS entry and the stylesheet')
}

const requiredFiles = ['index.html', 'manifest.json', entryFile, stylesheetFile]
for (const filename of requiredFiles) {
  const details = await stat(resolve(outputDirectory, filename))
  if (!details.isFile() || details.size === 0) {
    throw new Error(`Vue build output is missing or empty: ${filename}`)
  }
}

const indexHtml = await readFile(resolve(outputDirectory, 'index.html'), 'utf8')
if (indexHtml.includes('\0')) {
  throw new Error('Vue build index.html contains null bytes')
}
if (!indexHtml.includes('id="app"')) {
  throw new Error('Vue build index.html does not contain the application mount point')
}
if (!indexHtml.includes(`/assets/user-app/${entryFile}`) || !indexHtml.includes(`/assets/user-app/${stylesheetFile}`)) {
  throw new Error('Vue build index.html does not reference the deployable application assets')
}

// The duplicate-bundle bug: a chunk importing a different spelling of the
// entry than the one user-app.php serves makes the browser evaluate the
// whole bundle twice, giving two Vue apps and two Pinia instances.
const chunkDirectory = resolve(outputDirectory, 'chunks')
for (const chunk of await readdir(chunkDirectory)) {
  if (!chunk.endsWith('.js')) continue
  const source = await readFile(resolve(chunkDirectory, chunk), 'utf8')
  for (const [, imported] of source.matchAll(/from"\.\.\/(app[^"]*\.js)"/g)) {
    if (imported !== entryFile) {
      throw new Error(`Chunk ${chunk} imports "${imported}" but the served entry is "${entryFile}"`)
    }
  }
}

console.log(`Verified Vue build output in ${outputDirectory} (entry ${entryFile})`)
