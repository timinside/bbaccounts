// Minimal bbaccounts example: Authorization Code + PKCE for a public client.
// 1. Register a project and add http://localhost:8103/callback as a redirect URI.
// 2. Set BBA_CLIENT_ID before running.
// 3. Run: go run examples/go/main.go
// Override BBA_BASE, BBA_CLIENT_ID, BBA_REDIRECT and PORT with environment variables.
//
// ProFreeHost/unaux bypass: if you get an anti-bot __test challenge (HTML with
// slowAES.decrypt) instead of JSON, solve it and retry with Cookie: __test=<value>.
// Helper: python examples/python/bb-unaux.py URL [GET|POST] [BODY]
// See examples/README.md.

package main

import (
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"html"
	"io"
	"log"
	"net/http"
	"net/url"
	"os"
	"strings"
	"sync"
	"time"
)

var (
	baseURL   = strings.TrimRight(env("BBA_BASE", "https://bbaccounts.unaux.com"), "/")
	clientID  = env("BBA_CLIENT_ID", "YOUR_CLIENT_ID")
	redirect  = env("BBA_REDIRECT", "http://localhost:8103/callback")
	port      = env("PORT", "8103")
	client    = &http.Client{Timeout: 15 * time.Second}
	pending   = map[string]string{}
	pendingMu sync.Mutex
)

func env(key, fallback string) string {
	if value := strings.TrimSpace(os.Getenv(key)); value != "" {
		return value
	}

	return fallback
}

func randomBytes(size int) []byte {
	buf := make([]byte, size)
	if _, err := rand.Read(buf); err != nil {
		panic(err)
	}

	return buf
}

func base64url(raw []byte) string {
	return base64.RawURLEncoding.EncodeToString(raw)
}

func page(title, body string) string {
	return "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>" +
		html.EscapeString(title) + "</title></head>" +
		"<body style=\"font-family:system-ui;max-width:720px;margin:48px auto;padding:0 20px\">" +
		body + "</body></html>"
}

func main() {
	http.HandleFunc("/", handler)
	log.Printf("bbaccounts Go example: http://localhost:%s", port)
	log.Printf("Identity provider: %s", baseURL)
	log.Printf("Redirect URI: %s", redirect)
	log.Fatal(http.ListenAndServe("localhost:"+port, nil))
}

func handler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if r.URL.Path == "/callback" {
		callback(w, r)
		return
	}

	verifier := base64url(randomBytes(48))
	digest := sha256.Sum256([]byte(verifier))
	challenge := base64url(digest[:])
	state := base64url(randomBytes(24))

	pendingMu.Lock()
	pending[state] = verifier
	pendingMu.Unlock()

	query := url.Values{
		"response_type":         {"code"},
		"client_id":             {clientID},
		"redirect_uri":          {redirect},
		"scope":                 {"profile email"},
		"state":                 {state},
		"code_challenge":        {challenge},
		"code_challenge_method": {"S256"},
	}
	authorize := baseURL + "/oauth/authorize?" + query.Encode()

	fmt.Fprint(w, page("Go example", "<h1>bbaccounts Go example</h1>"+
		"<p>Authorization Code + PKCE with no client secret.</p>"+
		"<p><a href=\""+html.EscapeString(authorize)+"\">Sign in with bbaccounts</a></p>"))
}

func callback(w http.ResponseWriter, r *http.Request) {
	state := r.URL.Query().Get("state")
	pendingMu.Lock()
	verifier, ok := pending[state]
	delete(pending, state)
	pendingMu.Unlock()
	if !ok {
		w.WriteHeader(http.StatusBadRequest)
		fmt.Fprint(w, page("State mismatch", "<h1>State mismatch</h1><p>Start again.</p>"))
		return
	}

	form := url.Values{
		"grant_type":    {"authorization_code"},
		"client_id":     {clientID},
		"redirect_uri":  {redirect},
		"code":          {r.URL.Query().Get("code")},
		"code_verifier": {verifier},
	}
	tokenResponse, err := client.PostForm(baseURL+"/oauth/token", form)
	if err != nil {
		showError(w, err)
		return
	}
	defer tokenResponse.Body.Close()

	var token struct {
		AccessToken string `json:"access_token"`
	}
	if err := json.NewDecoder(tokenResponse.Body).Decode(&token); err != nil {
		showError(w, err)
		return
	}
	if tokenResponse.StatusCode != http.StatusOK || token.AccessToken == "" {
		showError(w, fmt.Errorf("token endpoint returned %d", tokenResponse.StatusCode))
		return
	}

	infoRequest, err := http.NewRequest(http.MethodGet, baseURL+"/oauth/userinfo", nil)
	if err != nil {
		showError(w, err)
		return
	}
	infoRequest.Header.Set("Accept", "application/json")
	infoRequest.Header.Set("Authorization", "Bearer "+token.AccessToken)

	infoResponse, err := client.Do(infoRequest)
	if err != nil {
		showError(w, err)
		return
	}
	defer infoResponse.Body.Close()

	body, err := io.ReadAll(infoResponse.Body)
	if err != nil {
		showError(w, err)
		return
	}
	if infoResponse.StatusCode != http.StatusOK {
		showError(w, fmt.Errorf("userinfo returned %d: %s", infoResponse.StatusCode, string(body)))
		return
	}

	var profile any
	if err := json.Unmarshal(body, &profile); err != nil {
		showError(w, err)
		return
	}
	pretty, err := json.MarshalIndent(profile, "", "  ")
	if err != nil {
		showError(w, err)
		return
	}

	fmt.Fprint(w, page("Signed in", "<h1>Signed in</h1><pre>"+
		html.EscapeString(string(pretty))+"</pre><p><a href=\"/\">Start over</a></p>"))
}

func showError(w http.ResponseWriter, err error) {
	w.WriteHeader(http.StatusBadRequest)
	fmt.Fprint(w, page("Error", "<h1>Error</h1><pre>"+
		html.EscapeString(err.Error())+"</pre><p><a href=\"/\">Start over</a></p>"))
}
