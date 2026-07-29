import { spawn } from 'node:child_process'
import { networkInterfaces } from 'node:os'
import { resolve } from 'node:path'

const port = process.env.DEV_PORT || '8888'
const ignoredAdapter = /vmware|virtual|vethernet|sstap|loopback|bluetooth/i

const addresses = Object.entries(networkInterfaces())
  .filter(([name]) => !ignoredAdapter.test(name))
  .flatMap(([, entries]) => entries || [])
  .filter(entry => entry.family === 'IPv4' && !entry.internal)
  .map(entry => entry.address)

const host = process.env.DEV_HOST || addresses[0] || '127.0.0.1'
const nuxtEntry = resolve('node_modules/nuxt/bin/nuxt.mjs')

console.log(`Local:   http://localhost:${port}/app/`)
console.log(`Network: http://${host}:${port}/app/`)

const child = spawn(
  process.execPath,
  [nuxtEntry, 'dev', '--host', host, '--port', port],
  {
    env: {
      ...process.env,
      NODE_OPTIONS: '--no-deprecation'
    },
    stdio: 'inherit'
  }
)

child.on('exit', code => {
  process.exit(code ?? 0)
})
