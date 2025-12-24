#!/usr/bin/env python3
import os
import json
import hashlib
import math
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SUMMARIES_FILE = ROOT / 'file_summaries.json'
EMBEDDINGS_FILE = ROOT / 'embeddings.json'

if not SUMMARIES_FILE.exists():
    print(f"Summaries file not found: {SUMMARIES_FILE}")
    raise SystemExit(1)

with open(SUMMARIES_FILE, 'r', encoding='utf-8') as f:
    summaries = json.load(f)

# Deterministic offline embedding function (safe fallback)
# Produces a fixed-length float vector (512 dims) derived from SHA256(text + counter)

def deterministic_embedding(text, dim=512):
    vec = []
    for i in range(dim):
        h = hashlib.sha256((text + '||' + str(i)).encode('utf-8')).hexdigest()
        # take 8 hex chars -> 32-bit unsigned int
        part = h[:8]
        intval = int(part, 16)
        # map to [-1,1]
        val = (intval / 0xFFFFFFFF) * 2.0 - 1.0
        vec.append(round(val, 6))
    return vec

# If OPENAI_API_KEY exists, user may prefer real embeddings; this script currently
# avoids network calls and uses deterministic embeddings. You can modify to call
# a provider using your key.

items = []
for s in summaries:
    text = s.get('concise_summary') or s.get('content') or ''
    if not text:
        text = s.get('path', '')
    emb = deterministic_embedding(text)
    items.append({'path': s.get('path'), 'sha1': s.get('sha1'), 'kind': s.get('kind'), 'embedding': emb})

with open(EMBEDDINGS_FILE, 'w', encoding='utf-8') as f:
    json.dump({'generated_at': None, 'model': 'deterministic-sha256-512', 'items': items}, f, ensure_ascii=False)

print(f"Embeddings written: {EMBEDDINGS_FILE} (items: {len(items)})")

# Simple nearest-neighbor function for quick local testing

def cosine(a, b):
    dot = sum(x*y for x,y in zip(a,b))
    na = math.sqrt(sum(x*x for x in a))
    nb = math.sqrt(sum(x*x for x in b))
    if na == 0 or nb == 0:
        return 0.0
    return dot / (na * nb)

# Quick smoke test using first summary
if items:
    q_text = summaries[0].get('concise_summary')[:200]
    q_emb = deterministic_embedding(q_text)
    sims = [(itm['path'], cosine(q_emb, itm['embedding'])) for itm in items]
    sims.sort(key=lambda x: x[1], reverse=True)
    print('\nTop 3 matches for first summary:')
    for p,score in sims[:3]:
        print(f"- {p}  score={score:.6f}")
