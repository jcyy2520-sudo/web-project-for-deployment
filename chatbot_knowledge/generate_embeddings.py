#!/usr/bin/env python3
# pyre-ignore-all-errors
import os
import json
import urllib.request
import urllib.error
import math
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SUMMARIES_FILE = ROOT / 'file_summaries.json'
EMBEDDINGS_FILE = ROOT / 'embeddings.json'
ENV_FILE = ROOT.parent / 'web-backend' / '.env'

def load_env_file(filepath):
    if not filepath.exists():
        return
    with open(filepath, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if '=' in line:
                key, val = line.split('=', 1)
                key = key.strip()
                val = val.strip()
                # strip outer quotes if present
                if val.startswith(('"', "'")) and val.endswith(('"', "'")) and len(val) >= 2 and val[0:1] == val[-1:]:
                    val = val[1:-1]
                if key not in os.environ:
                    os.environ[key] = val

# Load .env file
load_env_file(ENV_FILE)
# Try dotenv in current dir as fallback
load_env_file(Path('.env'))

VOYAGE_API_KEY = os.environ.get('VOYAGE_API_KEY')
VOYAGE_MODEL = os.environ.get('VOYAGE_EMBEDDING_MODEL', 'voyage-4-large')

if not VOYAGE_API_KEY:
    print("Error: VOYAGE_API_KEY not found in environment or .env file.")
    print(f"Checked .env path: {ENV_FILE}")
    raise SystemExit(1)

if not SUMMARIES_FILE.exists():
    print(f"Summaries file not found: {SUMMARIES_FILE}")
    raise SystemExit(1)

with open(SUMMARIES_FILE, 'r', encoding='utf-8') as f:
    summaries = json.load(f)

def get_voyage_embeddings(texts, model=VOYAGE_MODEL):
    url = "https://api.voyageai.com/v1/embeddings"
    headers = {
        "Authorization": f"Bearer {VOYAGE_API_KEY}",
        "Content-Type": "application/json"
    }
    payload = {
        "input": texts,
        "model": model
    }
    
    data = json.dumps(payload).encode('utf-8')
    req = urllib.request.Request(url, data=data, headers=headers, method='POST')
    
    try:
        with urllib.request.urlopen(req, timeout=30.0) as response:
            resp_data = json.loads(response.read().decode('utf-8'))
            return [item['embedding'] for item in resp_data['data']]
    except urllib.error.HTTPError as e:
        print(f"Error calling Voyage AI API: HTTP {e.code}")
        print(f"Response: {e.read().decode('utf-8')}")
        return None
    except Exception as e:
        print(f"Error calling Voyage AI API: {e}")
        return None

items = []
texts_to_embed: list[str] = []
valid_summaries = []

# Prepare batch
for s in summaries:
    text = s.get('concise_summary') or s.get('content') or ''
    if not text:
        text = s.get('path', '')
    texts_to_embed.append(text)
    valid_summaries.append(s)

# Batch process (Voyage AI supports max 128 items per request for most models, let's use 100)
BATCH_SIZE = 100
all_embeddings = []

print(f"Generating embeddings using {VOYAGE_MODEL}...")
for i in range(0, len(texts_to_embed), BATCH_SIZE):
    batch = texts_to_embed[i:i + BATCH_SIZE]
    print(f"Processing batch {i//BATCH_SIZE + 1} ({len(batch)} items)...")
    
    embs = get_voyage_embeddings(batch)
    if not embs:
        print("Failed to get embeddings. Aborting.")
        raise SystemExit(1)
        
    all_embeddings.extend(embs)
    time.sleep(0.5) # Slight pause to respect rate limits

for s, emb in zip(valid_summaries, all_embeddings):
    items.append({
        'path': s.get('path'), 
        'sha1': s.get('sha1'), 
        'kind': s.get('kind'), 
        'embedding': emb
    })

with open(EMBEDDINGS_FILE, 'w', encoding='utf-8') as f:
    json.dump({
        'generated_at': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()), 
        'model': VOYAGE_MODEL, 
        'items': items
    }, f, ensure_ascii=False)

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
    q_text = texts_to_embed[0][:200]
    print(f"\nRunning test embedding query for: '{q_text}'...")
    
    # Get embedding for the test query
    q_emb = get_voyage_embeddings([q_text])
    
    if q_emb:
        sims: list[tuple[str, float]] = [(str(itm['path']), cosine(q_emb[0], itm['embedding'])) for itm in items]
        sims.sort(key=lambda x: x[1], reverse=True)
        print('\nTop 3 matches for first summary:')
        for p,score in sims[:3]:
            print(f"- {p}  score={score:.6f}")
    else:
        print("Test embedding query failed.")
