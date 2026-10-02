import WebSocket from 'ws'
import { readFileSync, writeFileSync } from 'fs'

const CDP = 'http://127.0.0.1:9333'
const OUT = 'C:/Users/denia/AppData/Local/Temp/opencode/pkl/shots'
const toks = readFileSync('C:/Users/denia/AppData/Local/Temp/opencode/pkl/tokens.txt', 'utf8').trim().split('\n').map((l) => {
  const [token, user] = l.split('|||')
  return { token, user }
})
const byId = {}
for (const t of toks) byId[JSON.parse(t.user).username] = t

let msgId = 0
async function conn(url) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(url)
    const pending = new Map()
    const events = []
    ws.on('open', () => resolve({
      ws,
      send(method, params = {}) {
        return new Promise((res, rej) => {
          const id = ++msgId
          pending.set(id, { res, rej })
          ws.send(JSON.stringify({ id, method, params }))
        })
      },
      async waitLoad(timeout = 15000) {
        const t0 = Date.now()
        for (;;) {
          const i = events.findIndex((e) => e.method === 'Page.loadEventFired')
          if (i >= 0) { events.splice(i, 1); return }
          if (Date.now() - t0 > timeout) throw new Error('load timeout')
          await new Promise((r) => setTimeout(r, 100))
        }
      },
    }))
    ws.on('message', (d) => {
      const m = JSON.parse(d.toString())
      if (m.id && pending.has(m.id)) { const p = pending.get(m.id); pending.delete(m.id); if (m.error) p.rej(new Error(JSON.stringify(m.error))); else p.res(m.result) }
      else if (m.method) events.push(m)
    })
    ws.on('error', reject)
  })
}

async function shot({ name, url, role = null, wait = 4000, evalJs = null, clickXY = null }) {
  const r = await fetch(CDP + '/json/new?about:blank', { method: 'PUT' })
  const t = await r.json()
  const c = await conn(t.webSocketDebuggerUrl)
  try {
    await c.send('Page.enable')
    await c.send('Runtime.enable')
    if (role) {
      const s = byId[role]
      await c.send('Page.addScriptToEvaluateOnNewDocument', {
        source: `localStorage.setItem('token',${JSON.stringify(s.token)});localStorage.setItem('user',${JSON.stringify(s.user)});`,
      })
    }
    await c.send('Page.navigate', { url })
    await c.waitLoad()
    await new Promise((r) => setTimeout(r, wait))
    if (evalJs) {
      await c.send('Runtime.evaluate', { expression: evalJs, awaitPromise: true })
      await new Promise((r) => setTimeout(r, 1500))
    }
    if (clickXY) {
      await c.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: clickXY[0], y: clickXY[1], button: 'left', clickCount: 1 })
      await c.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: clickXY[0], y: clickXY[1], button: 'left', clickCount: 1 })
      await new Promise((r) => setTimeout(r, 2500))
    }
    const snap = await c.send('Page.captureScreenshot', { format: 'png' })
    writeFileSync(`${OUT}/${name}.png`, Buffer.from(snap.data, 'base64'))
    console.log('saved', name)
  } finally {
    c.ws.close()
    await fetch(CDP + '/json/close/' + t.id).catch(() => {})
  }
}

// tenants with longer wait
await shot({ name: 'tenants', url: 'http://localhost:5173/admin/tenants', role: 'adminulpsubang', wait: 5000 })
// monitor: click center to dismiss overlay
await shot({ name: 'monitor', url: 'http://localhost:5173/monitor', role: 'tvulpsubang', wait: 3000, clickXY: [720, 450] })
console.log('done')
process.exit(0)
