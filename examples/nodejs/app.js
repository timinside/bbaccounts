'use strict';

// Minimal bbaccounts example: Authorization Code + PKCE for a public client.
// 1. Register a project and add http://localhost:8102/callback as a redirect URI.
// 2. Set BBA_CLIENT_ID (environment variable or below).
// 3. Run: node examples/nodejs/app.js
// Override BBA_BASE, BBA_CLIENT_ID, BBA_REDIRECT and PORT with environment variables.
//
// ProFreeHost/unaux bypass: if you get an anti-bot __test challenge (HTML with
// slowAES.decrypt) instead of JSON, solve it and retry with Cookie: __test=<value>.
// Helper: python examples/python/bb-unaux.py URL [GET|POST] [BODY]
// See examples/README.md.

const http = require('node:http');
const crypto = require('node:crypto');

const BASE = (process.env.BBA_BASE || 'https://bbaccounts.unaux.com').replace(/\/+$/, '');
const CLIENT_ID = process.env.BBA_CLIENT_ID || 'YOUR_CLIENT_ID';
const REDIRECT = process.env.BBA_REDIRECT || 'http://localhost:8102/callback';
const PORT = Number(process.env.PORT || 8102);
const SCOPE = 'profile email';

const pending = new Map();

function escapeHtml(value) {
  return String(value).replace(/[<>&"]/g, (char) => ({
    '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;',
  })[char]);
}

function page(title, body) {
  return `<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>${escapeHtml(title)}</title></head>
<body style="font-family:system-ui;max-width:720px;margin:48px auto;padding:0 20px">
${body}
</body>
</html>`;
}

async function tokenRequest(code, verifier) {
  const response = await fetch(`${BASE}/oauth/token`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      grant_type: 'authorization_code',
      client_id: CLIENT_ID,
      redirect_uri: REDIRECT,
      code,
      code_verifier: verifier,
    }),
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.access_token) {
    throw new Error(JSON.stringify(payload));
  }
  return payload.access_token;
}

async function userInfo(accessToken) {
  const response = await fetch(`${BASE}/oauth/userinfo`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(JSON.stringify(payload));
  }
  return payload;
}

const server = http.createServer(async (request, response) => {
  const url = new URL(request.url || '/', `http://localhost:${PORT}`);
  response.setHeader('Content-Type', 'text/html; charset=utf-8');

  try {
    if (url.pathname === '/callback') {
      const state = url.searchParams.get('state') || '';
      const verifier = pending.get(state);
      pending.delete(state);
      if (!verifier) {
        response.writeHead(400);
        response.end(page('State mismatch', '<h1>State mismatch</h1><p>Start again.</p>'));
        return;
      }

      const token = await tokenRequest(url.searchParams.get('code') || '', verifier);
      const profile = await userInfo(token);
      response.end(page('Signed in', `<h1>Signed in</h1><pre>${escapeHtml(JSON.stringify(profile, null, 2))}</pre><p><a href="/">Start over</a></p>`));
      return;
    }

    const verifier = crypto.randomBytes(48).toString('base64url');
    const challenge = crypto.createHash('sha256').update(verifier).digest('base64url');
    const state = crypto.randomBytes(24).toString('base64url');
    pending.set(state, verifier);

    const authorize = new URL(`${BASE}/oauth/authorize`);
    authorize.search = new URLSearchParams({
      response_type: 'code',
      client_id: CLIENT_ID,
      redirect_uri: REDIRECT,
      scope: SCOPE,
      state,
      code_challenge: challenge,
      code_challenge_method: 'S256',
    }).toString();

    response.end(page('Node.js example', `<h1>bbaccounts Node.js example</h1>
<p>Authorization Code + PKCE with no client secret.</p>
<p><a href="${escapeHtml(authorize.toString())}">Sign in with bbaccounts</a></p>`));
  } catch (error) {
    response.writeHead(400);
    response.end(page('Error', `<h1>Error</h1><pre>${escapeHtml(error.message)}</pre><p><a href="/">Start over</a></p>`));
  }
});

server.listen(PORT, 'localhost', () => {
  console.log(`bbaccounts Node.js example: http://localhost:${PORT}`);
  console.log(`Identity provider: ${BASE}`);
  console.log(`Redirect URI: ${REDIRECT}`);
});
