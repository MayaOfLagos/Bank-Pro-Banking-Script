import { access, readFile, readdir } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const portalDirectory = resolve(scriptDirectory, '..')
const projectDirectory = resolve(portalDirectory, '../..')

const canonicalRoutes = [
  '/login',
  '/register',
  '/pin',
  '/reset-password',
  '/update-password',
  '/dashboard',
  '/transactions',
  '/deposits',
  '/wire-transfer',
  '/domestic-transfer',
  '/withdrawals',
  '/cards',
  '/loans',
  '/tickets',
  '/profile',
  '/transfer-verify',
  '/transfer-cot',
  '/transfer-tax',
  '/transfer-imf',
  '/transfer-success'
]

const retiredFiles = [
  'login.php',
  'pin.php',
  'reset-password.php',
  'updatePassword.php',
  'dashboard.php',
  'transactions.php',
  'wire-transfer.php',
  'domestic-transfer.php',
  'withdrawals.php',
  'cards.php',
  'loans.php',
  'tickets.php',
  'profile.php',
  'profile-center.php',
  'portal-page.php',
  'p/forgot-password.php',
  'include/Regfunction.php',
  'include/process-file.php'
]

const retiredDirectories = [
  'layout',
  'signup',
  'bootstrap',
  'plugins',
  'assets/auth',
  'assets/user',
  'assets/css',
  'assets/js'
]

async function exists(path) {
  try {
    await access(path)
    return true
  } catch {
    return false
  }
}

const reintroducedFiles = []
for (const filename of retiredFiles) {
  if (await exists(resolve(projectDirectory, filename))) reintroducedFiles.push(filename)
}

if (reintroducedFiles.length) {
  throw new Error(`Retired PHP entry files must not exist:\n${reintroducedFiles.join('\n')}`)
}

const reintroducedDirectories = []
for (const directory of retiredDirectories) {
  if (await exists(resolve(projectDirectory, directory))) reintroducedDirectories.push(directory)
}

if (reintroducedDirectories.length) {
  throw new Error(`Retired directories must not exist:\n${reintroducedDirectories.join('\n')}`)
}

if (!(await exists(resolve(projectDirectory, 'user')))) {
  throw new Error('The source-only user/ migration reference must remain available')
}

const legacyImageEntries = await readdir(resolve(projectDirectory, 'assets/img'))
const unexpectedLegacyImages = legacyImageEntries.filter((entry) => entry !== 'favicon.ico')
if (unexpectedLegacyImages.length) {
  throw new Error(`Only the shared favicon may remain in assets/img:\n${unexpectedLegacyImages.join('\n')}`)
}

if (!(await exists(resolve(projectDirectory, 'assets/img/favicon.ico')))) {
  throw new Error('The shared favicon must remain at assets/img/favicon.ico')
}

const userAppSource = await readFile(resolve(projectDirectory, 'user-app.php'), 'utf8')
const developmentRouterSource = await readFile(resolve(projectDirectory, 'router.php'), 'utf8')
const apacheSource = await readFile(resolve(projectDirectory, '.htaccess'), 'utf8')
const vueRouterSource = await readFile(resolve(portalDirectory, 'src/router/index.js'), 'utf8')

const missingRoutes = []
for (const route of canonicalRoutes) {
  if (!userAppSource.includes(`'${route}' =>`)) missingRoutes.push(`user-app.php: ${route}`)
  if (!developmentRouterSource.includes(`'${route}'`)) missingRoutes.push(`router.php: ${route}`)
  if (!vueRouterSource.includes(`path: '${route}'`)) missingRoutes.push(`Vue router: ${route}`)
}

if (missingRoutes.length) {
  throw new Error(`Canonical customer routes are out of sync:\n${missingRoutes.join('\n')}`)
}

if (!apacheSource.includes('user-app.php') || !apacheSource.includes('R=301')) {
  throw new Error('.htaccess must contain the Vue handoff and permanent legacy redirects')
}

if (!apacheSource.includes('^signup(?:/.*)?$') || !apacheSource.includes('^user(?:/.*)?$')) {
  throw new Error('.htaccess must keep signup and the source-only user tree outside the PHP runtime')
}

// Accept either str_starts_with (PHP 8+) or strncmp (PHP 7 compatible) —
// what matters is that /user/ and /signup/ prefix guards exist.
const userGuard =
  developmentRouterSource.includes("str_starts_with($normalizedPath, '/user/')") ||
  developmentRouterSource.includes("strncmp($normalizedPath, '/user/', 6) === 0")
const signupGuard =
  developmentRouterSource.includes("str_starts_with($normalizedPath, '/signup/')") ||
  developmentRouterSource.includes("strncmp($normalizedPath, '/signup/', 8) === 0")
if (!userGuard || !signupGuard) {
  throw new Error('router.php must keep signup and the source-only user tree outside the PHP runtime')
}

if (/alias\s*:\s*['"][^'"]+\.php/.test(vueRouterSource)) {
  throw new Error('Vue routes must not expose legacy .php aliases')
}

if (vueRouterSource.includes('/profile-center')) {
  throw new Error('Use /profile as the canonical customer profile route')
}

console.log(
  `Verified ${canonicalRoutes.length} canonical Vue routes, ` +
  `${retiredFiles.length} retired files, ${retiredDirectories.length} retired directories, ` +
  'and the preserved source-only user/ reference'
)
