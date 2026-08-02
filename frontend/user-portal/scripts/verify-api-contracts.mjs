import { access, readdir, readFile } from 'node:fs/promises'
import { dirname, extname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const portalDirectory = resolve(scriptDirectory, '..')
const sourceDirectory = resolve(portalDirectory, 'src')
const projectDirectory = resolve(portalDirectory, '../..')
const sourceExtensions = new Set(['.js', '.vue'])
let allSource = ''

async function collectSourceFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true })
  const files = []

  for (const entry of entries) {
    const path = resolve(directory, entry.name)
    if (entry.isDirectory()) files.push(...await collectSourceFiles(path))
    else if (sourceExtensions.has(extname(entry.name))) files.push(path)
  }

  return files
}

const endpointPattern = /['"`](\/api\/[A-Za-z0-9_./-]+\.php)/g
const endpoints = new Set()

for (const file of await collectSourceFiles(sourceDirectory)) {
  const source = await readFile(file, 'utf8')
  allSource += `\n${source}`
  for (const match of source.matchAll(endpointPattern)) endpoints.add(match[1])
}

const missing = []
for (const endpoint of endpoints) {
  try {
    await access(resolve(projectDirectory, endpoint.slice(1)))
  } catch {
    missing.push(endpoint)
  }
}

if (missing.length) {
  throw new Error(`Vue references missing PHP endpoints:\n${missing.sort().join('\n')}`)
}

const resetApi = await readFile(resolve(projectDirectory, 'api/auth/reset-password.php'), 'utf8')
const dashboardApi = await readFile(resolve(projectDirectory, 'api/user/dashboard.php'), 'utf8')
const transferVerifyApi = await readFile(resolve(projectDirectory, 'api/user/transfer-verify-pin.php'), 'utf8')
const contractChecks = [
  ['password reset uses new_password on both sides', allSource.includes('new_password: form.password') && resetApi.includes("['email', 'reset_token', 'new_password']")],
  ['dashboard does not select or return a full card number', !dashboardApi.includes('card_number')],
  ['transfer verification consumes a specific pending transfer', transferVerifyApi.includes('pending_transfer_id') && transferVerifyApi.includes('DELETE FROM temp_trans') && transferVerifyApi.includes('FOR UPDATE')],
]
const failedChecks = contractChecks.filter(([, passed]) => !passed).map(([name]) => name)
if (failedChecks.length) {
  throw new Error(`Failed API contract checks:\n${failedChecks.join('\n')}`)
}

console.log(`Verified ${endpoints.size} Vue-to-PHP API targets`)
