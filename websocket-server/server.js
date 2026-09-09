import { createServer } from 'node:http'
import { WebSocket, WebSocketServer } from 'ws'

const WS_PORT = parseInt(process.env.WS_PORT || '3001', 10)
const HTTP_PORT = parseInt(process.env.HTTP_PORT || '3002', 10)
const BROADCAST_SECRET = process.env.BROADCAST_SECRET || ''

const clients = new Set()

function broadcast(message, tenantId = null) {
  const data = typeof message === 'string' ? message : JSON.stringify(message)
  for (const ws of clients) {
    if (ws.readyState !== WebSocket.OPEN) continue
    // Per-tenant isolation: if broadcast has tenantId, only send to clients with same tenantId (or to clients without tenantId yet — broadcast to all for backward compat)
    if (tenantId != null && ws.tenantId != null && ws.tenantId !== tenantId) continue
    ws.send(data)
  }
}

const wss = new WebSocketServer({ port: WS_PORT })

wss.on('connection', (ws, req) => {
  // Try to get tenantId from token query param via BE lookup is done client-side via register message; also try URL token for initial
  ws.tenantId = null
  // Parse ?token= or ?tenantId= from URL for early assignment (best-effort)
  try {
    const url = new URL(req.url, `http://${req.headers.host}`)
    const token = url.searchParams.get('token')
    // We don't verify token here; client will send register with tenantId shortly
    if (url.searchParams.get('tenantId')) ws.tenantId = parseInt(url.searchParams.get('tenantId'), 10) || null
    if (url.searchParams.get('tenant_id')) ws.tenantId = parseInt(url.searchParams.get('tenant_id'), 10) || ws.tenantId
  } catch {}

  clients.add(ws)

  ws.on('message', (raw) => {
    try {
      const msg = JSON.parse(raw.toString())
      if (msg.type === 'ping') {
        ws.send(JSON.stringify({ type: 'pong' }))
        return
      }
      if (msg.type === 'register' && msg.tenantId != null) {
        ws.tenantId = parseInt(msg.tenantId, 10) || null
        ws.send(JSON.stringify({ type: 'registered', tenantId: ws.tenantId }))
        return
      }
      if (msg.type === 'register' && msg.tenant_id != null) {
        ws.tenantId = parseInt(msg.tenant_id, 10) || null
        return
      }
    } catch {
      // ignore invalid messages
    }
  })

  ws.on('close', () => {
    clients.delete(ws)
  })

  ws.on('error', () => {
    clients.delete(ws)
  })
})

const httpServer = createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/broadcast') {
    const secret = req.headers['x-broadcast-secret']
    if (secret !== BROADCAST_SECRET) {
      res.writeHead(401, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ success: false, message: 'unauthorized' }))
      return
    }

    let body = ''
    req.on('data', (chunk) => { body += chunk })
    req.on('end', () => {
      try {
        const event = JSON.parse(body)
        const tenantId = event.tenantId ?? event.payload?.tenantId ?? event.payload?.tenant_id ?? null
        const msg = JSON.stringify({ type: event.type, payload: event.payload, tenantId })
        broadcast(msg, tenantId != null ? parseInt(tenantId, 10) : null)
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ success: true }))
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ success: false, message: 'invalid JSON' }))
      }
    })
  } else {
    res.writeHead(404)
    res.end()
  }
})

httpServer.listen(HTTP_PORT, () => {
  console.log(`WS server  → ws://0.0.0.0:${WS_PORT}`)
  console.log(`HTTP relay → http://0.0.0.0:${HTTP_PORT}/broadcast`)
})
