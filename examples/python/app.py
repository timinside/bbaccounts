"""Minimal bbaccounts example: Authorization Code + PKCE for a public client.

1. Register a project and add http://localhost:8101/callback as a redirect URI.
2. Fill in CLIENT_ID below.
3. Run: python examples/python/app.py

ProFreeHost/unaux bypass: if requests get the anti-bot __test challenge, this
example retries automatically using examples/python/bb-unaux.py (stdlib only).
You can also call it manually: python examples/python/bb-unaux.py URL [GET|POST] [BODY]
"""

import base64
import hashlib
import html
import importlib.util
import json
import os
import secrets
import urllib.parse
import urllib.request
from http.server import BaseHTTPRequestHandler, HTTPServer

BASE = os.environ.get("BBA_BASE", "https://bbaccounts.unaux.com").rstrip("/")
CLIENT_ID = os.environ.get("BBA_CLIENT_ID", "YOUR_CLIENT_ID")
REDIRECT = os.environ.get("BBA_REDIRECT", "http://localhost:8101/callback")
SCOPE = "profile email"

pending = {}


def base64url(raw: bytes) -> str:
    return base64.urlsafe_b64encode(raw).rstrip(b"=").decode()


def _unaux_cookie(html_text: str) -> str | None:
    helper = os.path.join(os.path.dirname(__file__), "bb-unaux.py")
    if not os.path.isfile(helper):
        return None
    spec = importlib.util.spec_from_file_location("bb_unaux", helper)
    if spec is None or spec.loader is None:
        return None
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod._solve(html_text)


def http_json(url: str, data: dict | None = None, token: str | None = None) -> dict:
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    body = None
    if data is not None:
        body = urllib.parse.urlencode(data).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"

    def _send(hdrs: dict) -> str:
        request = urllib.request.Request(url, data=body, headers=hdrs)
        with urllib.request.urlopen(request, timeout=15) as response:
            return response.read().decode() or "{}"

    text = _send(headers)
    if "__test" in text and "slowAES.decrypt" in text:
        cookie = _unaux_cookie(text)
        if cookie:
            text = _send({**headers, "Cookie": "__test=" + cookie})
    return json.loads(text)


class Handler(BaseHTTPRequestHandler):
    def do_GET(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        query = urllib.parse.parse_qs(parsed.query)

        if parsed.path == "/callback":
            self.callback(query)
            return

        verifier = base64url(secrets.token_bytes(48))
        challenge = base64url(hashlib.sha256(verifier.encode()).digest())
        state = base64url(secrets.token_bytes(24))
        pending[state] = verifier
        url = BASE + "/oauth/authorize?" + urllib.parse.urlencode({
            "response_type": "code",
            "client_id": CLIENT_ID,
            "redirect_uri": REDIRECT,
            "scope": SCOPE,
            "state": state,
            "code_challenge": challenge,
            "code_challenge_method": "S256",
        })
        self.page(
            "<h1>bbaccounts Python example</h1>"
            "<p>This page uses OAuth 2.0 with PKCE and no client secret.</p>"
            f'<p><a href="{html.escape(url)}">Sign in with bbaccounts</a></p>'
        )

    def callback(self, query: dict) -> None:
        state = (query.get("state") or [""])[0]
        verifier = pending.pop(state, None)
        if verifier is None:
            self.page("<h1>State mismatch</h1><p>Start again from the home page.</p>", 400)
            return

        try:
            token = http_json(BASE + "/oauth/token", {
                "grant_type": "authorization_code",
                "client_id": CLIENT_ID,
                "redirect_uri": REDIRECT,
                "code": (query.get("code") or [""])[0],
                "code_verifier": verifier,
            })
            me = http_json(BASE + "/oauth/userinfo", token=token["access_token"])
        except Exception as error:  # example: show provider errors directly
            self.page(f"<h1>Request failed</h1><pre>{html.escape(str(error))}</pre>", 400)
            return

        self.page("<h1>Signed in</h1><pre>" + html.escape(json.dumps(me, indent=2)) + '</pre><p><a href="/">Start over</a></p>')

    def page(self, body: str, status: int = 200) -> None:
        content = body.encode()
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(content)))
        self.end_headers()
        self.wfile.write(content)


if __name__ == "__main__":
    print(f"Open {REDIRECT.rsplit('/callback', 1)[0]}")
    HTTPServer(("localhost", 8101), Handler).serve_forever()
