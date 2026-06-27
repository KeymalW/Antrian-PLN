import { createServer } from 'node:http'
import { WebSocket, WebSocketServer } from 'ws'

const WS_PORT = parseInt(process.env.WS_PORT || '3001', 10)
const HTTP_PORT = parseInt(process.env.HTTP_PORT || '3002', 10)
const BROADCAST_SECRET = process.env.BROADCAST_SECRET || ''

const clients = new Set()

function broadcast(message) {
  const data = typeof message === 'string' ? message : JSON.stringify(message)
  for (const ws of clients) {
    if (ws.readyState === WebSocket.OPEN) {
      ws.send(data)
    }
  }
}

const wss = new WebSocketServer({ port: WS_PORT })

wss.on('connection', (ws, req) => {
  clients.add(ws)

  ws.on('message', (raw) => {
    try {
      const msg = JSON.parse(raw.toString())
      if (msg.type === 'ping') {
        ws.send(JSON.stringify({ type: 'pong' }))
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
        broadcast(JSON.stringify({ type: event.type, payload: event.payload }))
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
