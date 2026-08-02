import { readFile, stat } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const outputDirectory = resolve(scriptDirectory, '../../../assets/user-app')
const requiredFiles = ['index.html', 'app.js', 'app.css']

for (const filename of requiredFiles) {
  const file = resolve(outputDirectory, filename)
  const details = await stat(file)
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
if (!indexHtml.includes('/assets/user-app/app.js') || !indexHtml.includes('/assets/user-app/app.css')) {
  throw new Error('Vue build index.html does not reference the deployable application assets')
}

console.log(`Verified Vue build output in ${outputDirectory}`)
