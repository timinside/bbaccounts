#!/usr/bin/env python3
"""bb-unaux.py v1.0 — Solve ProFreeHost/unaux.com __test anti-bot challenge and call bbaccounts API.
Usage:
  python bb-unaux.py URL [GET|POST] [JSON_BODY]
No pip dependencies.
"""
__version__ = '1.0'
import re, sys, urllib.request, urllib.error

SBOX=list(bytes.fromhex(
"637c777bf26b6fc53001672bfed7ab76ca82c97dfa5947f0add4a2af9ca472c0"
"b7fd9326363ff7cc34a5e5f171d8311504c723c31896059a071280e2eb27b275"
"09832c1a1b6e5aa0523bd6b329e32f8453d100ed20fcb15b6acbbe394a4c58cf"
"d0efaafb434d338545f9027f503c9fa851a3408f929d38f5bcb6da2110fff3d2"
"cd0c13ec5f974417c4a77e3d645d197360814fdc222a908846eeb814de5e0bdb"
"e0323a0a4906245cc2d3ac629195e479e7c8376d8dd54ea96c56f4ea657aae08"
"ba78252e1ca6b4c6e8dd741f4bbd8b8a703eb5664803f60e613557b986c11d9e"
"e1f8981169d98e949b1e87e9ce5528df8ca1890dbfe6426841992d0fb054bb16"))
INV_SBOX=[0]*256
for i,v in enumerate(SBOX): INV_SBOX[v]=i
RCON=[0,1,2,4,8,16,32,64,128,27,54]

def _mul(a,b):
    p=0
    for _ in range(8):
        if b&1: p^=a
        hi=a&0x80; a=(a<<1)&0xFF
        if hi: a^=0x1b
        b>>=1
    return p

def _key_expansion(key):
    w=[[0]*4 for _ in range(44)]
    for i in range(4): w[i]=list(key[4*i:4*i+4])
    for i in range(4,44):
        t=w[i-1][:]
        if i%4==0: t=t[1:]+t[:1]; t=[SBOX[x] for x in t]; t[0]^=RCON[i//4]
        w[i]=[w[i-4][j]^t[j] for j in range(4)]
    return w

def _inv_cipher(block,w):
    s=[[block[r+4*c] for c in range(4)] for r in range(4)]
    for c in range(4):
        for r in range(4): s[r][c]^=w[40+c][r]
    for rnd in range(9,0,-1):
        s[1]=s[1][-1:]+s[1][:-1]; s[2]=s[2][-2:]+s[2][:-2]; s[3]=s[3][-3:]+s[3][:-3]
        for r in range(4):
            for c in range(4): s[r][c]=INV_SBOX[s[r][c]]
        for c in range(4):
            for r in range(4): s[r][c]^=w[rnd*4+c][r]
        for c in range(4):
            a=[s[r][c] for r in range(4)]
            s[0][c]=_mul(a[0],14)^_mul(a[1],11)^_mul(a[2],13)^_mul(a[3],9)
            s[1][c]=_mul(a[0],9)^_mul(a[1],14)^_mul(a[2],11)^_mul(a[3],13)
            s[2][c]=_mul(a[0],13)^_mul(a[1],9)^_mul(a[2],14)^_mul(a[3],11)
            s[3][c]=_mul(a[0],11)^_mul(a[1],13)^_mul(a[2],9)^_mul(a[3],14)
    s[1]=s[1][-1:]+s[1][:-1]; s[2]=s[2][-2:]+s[2][:-2]; s[3]=s[3][-3:]+s[3][:-3]
    for r in range(4):
        for c in range(4): s[r][c]=INV_SBOX[s[r][c]]
    for c in range(4):
        for r in range(4): s[r][c]^=w[c][r]
    return [s[r][c] for c in range(4) for r in range(4)]

def _decrypt(ct,key,iv):
    w=_key_expansion(key); out=[]; prev=list(iv)
    for i in range(0,len(ct),16):
        blk=list(ct[i:i+16]); dec=_inv_cipher(blk,w)
        out.extend([dec[j]^prev[j] for j in range(16)]); prev=blk
    return bytes(out)

def _solve(html):
    m=lambda k: re.search(rf'{k}=toNumbers\("([0-9a-f]+)"\)',html)
    a,b,c=m('a'),m('b'),m('c')
    return _decrypt(bytes.fromhex(c.group(1)),bytes.fromhex(a.group(1)),bytes.fromhex(b.group(1))).hex() if a and b and c else None

def _req(url, method='GET', body=None, headers=None):
    h={'User-Agent':'Mozilla/5.0', **(headers or {})}
    data=body.encode() if isinstance(body,str) else body
    req=urllib.request.Request(url,data=data,headers=h,method=method)
    try:
        r=urllib.request.urlopen(req,timeout=30)
        return r.status, r.read().decode('utf-8','replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8','replace')

def main():
    if len(sys.argv)<2:
        print('Usage: python bb-unaux.py URL [GET|POST] [JSON_BODY]'); raise SystemExit(1)
    url,method,body=sys.argv[1],(sys.argv[2] if len(sys.argv)>2 else 'GET').upper(),(sys.argv[3] if len(sys.argv)>3 else None)
    hdrs={'Content-Type':'application/json'} if body else {}
    status,text=_req(url,method,body,hdrs)
    if '__test' in text and 'slowAES.decrypt' in text:
        cookie=_solve(text)
        if cookie: hdrs['Cookie']='__test='+cookie; status,text=_req(url,method,body,hdrs)
    print(status)
    print(text)

if __name__ == '__main__':
    main()
