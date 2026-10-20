const https = require('node:https');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const port = Number(process.env.MOCK_INGEST_PORT || 9911);
const events = [];

const certDir = fs.mkdtempSync(path.join(os.tmpdir(), 'takt-mock-ingest-'));
let key;
let cert;
try {
  const keyPath = path.join(certDir, 'key.pem');
  const certPath = path.join(certDir, 'cert.pem');
  execFileSync(
    'openssl',
    [
      'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
      '-subj', '/CN=localhost',
      '-addext', 'subjectAltName=DNS:localhost,DNS:host.docker.internal',
      '-keyout', keyPath, '-out', certPath,
    ],
    { stdio: 'ignore' },
  );
  key = fs.readFileSync(keyPath);
  cert = fs.readFileSync(certPath);
} finally {
  fs.rmSync(certDir, { recursive: true, force: true });
}

const server = https.createServer({ key, cert }, (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Headers', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  if (req.method === 'OPTIONS') {
    res.writeHead(204).end();
    return;
  }
  if (req.method === 'POST' && req.url.startsWith('/api/event')) {
    let body = '';
    req.on('data', (chunk) => {
      body += chunk;
    });
    req.on('end', () => {
      try {
        events.push(JSON.parse(body));
      } catch {
        events.push({ raw: body });
      }
      res.writeHead(202).end();
    });
    return;
  }

  if (req.method === 'GET' && req.url.startsWith('/__events')) {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(events));
    return;
  }

  if (req.method === 'POST' && req.url.startsWith('/__reset')) {
    events.length = 0;
    res.writeHead(200).end();
    return;
  }

  res.writeHead(404).end();
});

server.listen(port, () => {
  console.log(`mock ingest on :${port}`);
});
