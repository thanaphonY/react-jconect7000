#!/usr/bin/env python3
"""Resume Thailand OSM.de offline tile cache into frontend/assets/maps/tiles."""
import math
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

OUT = Path(__file__).resolve().parents[1] / 'assets' / 'maps' / 'tiles'
OUT.mkdir(parents=True, exist_ok=True)
(OUT / '.source').write_text('osmde\n')

SOUTH, NORTH, WEST, EAST = 5.55, 20.55, 97.25, 105.75
ZMIN, ZMAX = 5, 12
BASE = 'https://tile.openstreetmap.de/{z}/{x}/{y}.png'
UA = 'Mozilla/5.0 (compatible; JConnectTileSeed/1.0; offline cache for internal app)'
WORKERS = 6
MIN_BYTES = 70
BATCH = 1500


def deg2tile(lat, lon, zoom):
    lat_rad = math.radians(lat)
    n = 2 ** zoom
    x = int((lon + 180.0) / 360.0 * n)
    y = int((1.0 - math.asinh(math.tan(lat_rad)) / math.pi) / 2.0 * n)
    return x, y


def all_jobs():
    jobs = []
    for z in range(ZMIN, ZMAX + 1):
        x0, y1 = deg2tile(SOUTH, WEST, z)
        x1, y0 = deg2tile(NORTH, EAST, z)
        for x in range(x0, x1 + 1):
            for y in range(y0, y1 + 1):
                jobs.append((z, x, y))
    return jobs


def is_good(dest: Path) -> bool:
    if not dest.is_file() or dest.stat().st_size < MIN_BYTES:
        return False
    try:
        with dest.open('rb') as fh:
            return fh.read(4) == b'\x89PNG'
    except Exception:
        return False


def fetch(zxy):
    z, x, y = zxy
    dest = OUT / str(z) / str(x) / f'{y}.png'
    if is_good(dest):
        return 'skip'
    dest.parent.mkdir(parents=True, exist_ok=True)
    url = BASE.format(z=z, x=x, y=y)
    req = urllib.request.Request(url, headers={'User-Agent': UA})
    for attempt in range(5):
        try:
            with urllib.request.urlopen(req, timeout=40) as resp:
                data = resp.read()
            if len(data) < MIN_BYTES or not data.startswith(b'\x89PNG'):
                raise RuntimeError(f'bad tile bytes={len(data)}')
            tmp = dest.with_suffix('.tmp')
            tmp.write_bytes(data)
            tmp.replace(dest)
            return 'ok'
        except Exception as e:
            if attempt == 4:
                return f'fail:{e}'
            time.sleep(1.0 * (attempt + 1))
    return 'fail'


def main():
    jobs = all_jobs()
    pending = [j for j in jobs if not is_good(OUT / str(j[0]) / str(j[1]) / f'{j[2]}.png')]
    print(f'total={len(jobs)} pending={len(pending)} out={OUT}', flush=True)
    ok = skip = fail = 0
    t0 = time.time()
    done = 0
    n = len(pending)
    for start in range(0, n, BATCH):
        batch = pending[start:start + BATCH]
        with ThreadPoolExecutor(max_workers=WORKERS) as ex:
            futs = [ex.submit(fetch, j) for j in batch]
            for fut in as_completed(futs):
                r = fut.result()
                done += 1
                if r == 'ok':
                    ok += 1
                elif r == 'skip':
                    skip += 1
                else:
                    fail += 1
                    if fail <= 40:
                        print('ERR', r, flush=True)
                if done % 250 == 0 or done == n:
                    elapsed = time.time() - t0
                    rate = done / elapsed if elapsed else 0
                    eta = (n - done) / rate if rate else 0
                    print(
                        f'progress {done}/{n} ok={ok} skip={skip} fail={fail} '
                        f'{rate:.1f}/s eta={eta/60:.1f}m',
                        flush=True,
                    )
        print(f'batch complete {min(start + BATCH, n)}/{n}', flush=True)

    print(f'DONE ok={ok} skip={skip} fail={fail} elapsed={time.time()-t0:.0f}s', flush=True)
    for z in range(ZMIN, max(ZMAX, 13) + 1):
        zdir = OUT / str(z)
        c = sum(1 for _ in zdir.rglob('*.png')) if zdir.exists() else 0
        print(f'zoom {z}: {c} png', flush=True)


if __name__ == '__main__':
    main()
