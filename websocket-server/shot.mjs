import WebSocket from 'ws'
import { readFileSync, writeFileSync, mkdirSync } from 'fs'

const CDP = 'http://127.0.0.1:9333'
const OUT = 'C:/Users/denia/AppData/Local/Temp/opencode/pkl/shots'
mkdirSync(OUT, { recursive: true })

const toks = readFileSync('C:/Users/denia/AppData/Local/Temp/opencode/pkl/tokens.txt', 'utf8').trim().split('\n').map((l) => {
  const [token, user] = l.split('|||')
  return { token, user }
})
const byId = {}
for (const t of toks) {
  const u = JSON.parse(t.user)
  byId[u.username] = t
}

function seed(role) {
  if (!role) return ''
  const t = byId[role]
  return `localStorage.setItem('token',${JSON.stringify(t.token)});localStorage.setItem('user',${JSON.stringify(t.user)});`
}

let msgId = 0
function makeConn(url) {
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
      waitEvent(method, timeout = 15000) {
        return new Promise((res, rej) => {
          const i = events.findIndex((e) => e.method === method)
          if (i >= 0) return res(events.splice(i, 1)[0])
          const to = setTimeout(() => rej(new Error('timeout ' + method)), timeout)
          const check = () => {
            const j = events.findIndex((e) => e.method === method)
            if (j >= 0) { clearTimeout(to); res(events.splice(j, 1)[0]) }
            else setTimeout(check, 100)
          }
          check()
        })
      },
    }))
    ws.on('message', (d) => {
      const m = JSON.parse(d.toString())
      if (m.id && pending.has(m.id)) {
        const p = pending.get(m.id)
        pending.delete(m.id)
        if (m.error) p.rej(new Error(JSON.stringify(m.error)))
        else p.res(m.result)
      } else if (m.method) events.push(m)
    })
    ws.on('error', reject)
  })
}

async function newPage() {
  const r = await fetch(CDP + '/json/new?about:blank', { method: 'PUT' })
  const t = await r.json()
  const c = await makeConn(t.webSocketDebuggerUrl)
  return { targetId: t.id, conn: c }
}

async function shot({ name, url, role = null, wait = 2500, evalJs = null, immediate = false }) {
  const { targetId, conn } = await newPage()
  try {
    await conn.send('Page.enable')
    await conn.send('Runtime.enable')
    const s = seed(role)
    if (s) await conn.send('Page.addScriptToEvaluateOnNewDocument', { source: s })
    await conn.send('Page.navigate', { url })
    await conn.waitEvent('Page.loadEventFired')
    if (!immediate) await new Promise((r) => setTimeout(r, wait))
    if (evalJs) {
      await conn.send('Runtime.evaluate', { expression: evalJs, awaitPromise: true })
      await new Promise((r) => setTimeout(r, 1200))
    }
    const snap = await conn.send('Page.captureScreenshot', { format: 'png' })
    writeFileSync(`${OUT}/${name}.png`, Buffer.from(snap.data, 'base64'))
    console.log('saved', name)
  } finally {
    conn.ws.close()
    await fetch(CDP + '/json/close/' + targetId).catch(() => {})
  }
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

// 1. login page
await shot({ name: 'login', url: 'http://localhost:5173/login', wait: 2500 })
// 2. login error: type wrong creds + submit
await shot({
  name: 'login-error', url: 'http://localhost:5173/login', wait: 800,
  evalJs: `(async () => {
    const setVal = (sel, v) => {
      const el = document.querySelector(sel);
      el.focus();
      const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value').set
        || Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
      setter.call(el, v);
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    };
    setVal('#username', 'salahuser');
    setVal('#password', 'salahpass');
    document.querySelector('form').requestSubmit();
  })()`,
})
// 3. register filled
await shot({
  name: 'register', url: 'http://localhost:5173/register', wait: 800,
  evalJs: `(async () => {
    const setVal = (sel, v) => {
      const el = document.querySelector(sel);
      if (!el) return;
      el.focus();
      const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
      setter.call(el, v);
      el.dispatchEvent(new Event('input', { bubbles: true }));
    };
    setVal('#companyName', 'PT Maju Bersama');
    setVal('#name', 'Admin Maju');
    setVal('#username', 'adminmaju2');
  })()`,
})
// 4. loading state (capture right at load event)
await shot({ name: 'loading', url: 'http://localhost:5173/track/loading-demo-1', immediate: true })
// 5. kiosk
await shot({ name: 'kiosk', url: 'http://localhost:5173/kiosk', role: 'kioskulpsubang', wait: 3000 })
// 6. ticket G-001 (called)
await shot({ name: 'ticket', url: 'http://localhost:5173/track/91', wait: 2500 })
// 7. monitor TV (dismiss unlock overlay if present)
await shot({
  name: 'monitor', url: 'http://localhost:5173/monitor', role: 'tvulpsubang', wait: 3500,
  evalJs: `(async () => {
    const btns = [...document.querySelectorAll('button')];
    const b = btns.find(x => /sentuh|mulai|aktifkan|izinkan/i.test(x.textContent || ''));
    if (b) b.click();
  })()`,
})
// 8. petugas
await shot({ name: 'petugas', url: 'http://localhost:5173/petugas', role: 'petugasulpsubang', wait: 3500 })
// 9. admin dashboard
await shot({ name: 'admin', url: 'http://localhost:5173/admin', role: 'adminulpsubang', wait: 3500 })
// 10. reports
await shot({ name: 'reports', url: 'http://localhost:5173/admin/reports', role: 'adminulpsubang', wait: 3500 })
// 11. services
await shot({ name: 'services', url: 'http://localhost:5173/admin/services', role: 'adminulpsubang', wait: 3000 })
// 12. accounts
await shot({ name: 'accounts', url: 'http://localhost:5173/admin/accounts', role: 'adminulpsubang', wait: 3000 })
// 13. settings identitas tab
await shot({
  name: 'settings-identitas', url: 'http://localhost:5173/admin/settings', role: 'adminulpsubang', wait: 2500,
  evalJs: `(async () => {
    const tabs = [...document.querySelectorAll('[role="tab"], button')];
    const b = tabs.find(x => /identitas/i.test(x.textContent || ''));
    if (b) b.click();
  })()`,
})
// 14. settings media tab
await shot({
  name: 'settings-media', url: 'http://localhost:5173/admin/settings', role: 'adminulpsubang', wait: 2500,
  evalJs: `(async () => {
    const tabs = [...document.querySelectorAll('[role="tab"], button')];
    const b = tabs.find(x => /media/i.test(x.textContent || ''));
    if (b) b.click();
  })()`,
})
// 15. tenants debug
await shot({ name: 'tenants', url: 'http://localhost:5173/admin/tenants', role: 'adminulpsubang', wait: 3000 })

console.log('done')
process.exit(0)
