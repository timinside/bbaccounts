# Examples

All examples use Authorization Code + PKCE against `https://bbaccounts.unaux.com` and need no client secret.

1. Open the developer panel on the hosted domain, create a project and add the callback printed by the example.
2. Create a credential with **Public client** enabled and copy its client ID.
3. Set `BBA_CLIENT_ID` and run the example.
4. Approve the requested scopes in the browser; the example prints the profile it receives.

| Example | Provider | Run | Stack |
|---|---|---|---|
| PHP | `https://bbaccounts.unaux.com` | `php -S localhost:8100 examples/php-web/index.php` | Plain PHP |
| Python | `https://bbaccounts.unaux.com` | `python examples/python/app.py` | Python standard library |
| Node.js | `https://bbaccounts.unaux.com` | `node examples/nodejs/app.js` | Node.js 18+, built-ins only |
| Go | `https://bbaccounts.unaux.com` | `go run examples/go/main.go` | Go standard library |

Each example prints the address to open and the exact redirect URI to register. `BBA_BASE`, `BBA_REDIRECT` and
`PORT` are optional overrides when a different callback is needed.

## ProFreeHost / unaux.com bypass

Some hosts behind ProFreeHost can show an anti-bot `__test` cookie challenge to plain non-browser HTTP clients.
If an example gets an HTML challenge page instead of JSON, solve the challenge and retry with `Cookie: __test=<value>`.

`examples/python/bb-unaux.py` is a small Python stdlib helper (no pip) that solves the challenge and makes the request:

```bash
# GET /api or /oauth endpoint through the bypass
python examples/python/bb-unaux.py "https://bbaccounts.unaux.com/oauth/userinfo" GET

# POST with JSON body
python examples/python/bb-unaux.py "https://bbaccounts.unaux.com/oauth/token" POST "grant_type=authorization_code&..."
```

You can also download it from `https://timinside.github.io/bbaccounts/downloads/bb-unaux.py`.

For your own clients, the flow is: request URL -> if response contains `__test` + `slowAES.decrypt`, solve it (see `bb-unaux.py`) -> retry same request with `Cookie: __test=<solved>`.

## GitHub Pages

`examples/github-pages` is a static copy of the site home page, not an OAuth client. Change `unavailable` at the
end of `index.html` to switch off links to the live site.
