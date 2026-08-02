"""adb driver: resize the device, walk the app by on-screen text, capture shots."""
import os, re, subprocess, sys, time, xml.etree.ElementTree as ET

ADB = os.path.expanduser('~/AppData/Local/Android/Sdk/platform-tools/adb.exe')
SP = os.path.dirname(os.path.abspath(__file__))
PKG = 'com.haraan.app'


def sh(*args, binary=False, timeout=120):
    p = subprocess.run([ADB] + list(args), capture_output=True, timeout=timeout)
    return p.stdout if binary else p.stdout.decode('utf-8', 'replace')


def shell(cmd, **kw):
    return sh('shell', cmd, **kw)


def size(w, h, density):
    shell(f'wm size {w}x{h}')
    shell(f'wm density {density}')
    time.sleep(2)


def reset_size():
    shell('wm size reset')
    shell('wm density reset')


def relaunch(wait=18):
    shell(f'am force-stop {PKG}')
    time.sleep(1)
    shell(f'monkey -p {PKG} -c android.intent.category.LAUNCHER 1')
    time.sleep(wait)


def dump():
    """UI hierarchy as an ElementTree, or None."""
    shell('rm -f /sdcard/ui.xml')
    shell('uiautomator dump /sdcard/ui.xml')
    xml = sh('exec-out', 'cat', '/sdcard/ui.xml', binary=True)
    try:
        return ET.fromstring(xml)
    except Exception:
        return None


def nodes(root):
    out = []
    for n in root.iter('node'):
        b = n.get('bounds') or ''
        m = re.match(r'\[(\d+),(\d+)\]\[(\d+),(\d+)\]', b)
        if not m:
            continue
        x1, y1, x2, y2 = map(int, m.groups())
        out.append({
            'text': n.get('text') or '',
            'desc': n.get('content-desc') or '',
            'cls': n.get('class') or '',
            'box': (x1, y1, x2, y2),
            'mid': ((x1 + x2) // 2, (y1 + y2) // 2),
        })
    return out


def find(text, root=None, exact=False):
    root = root if root is not None else dump()
    if root is None:
        return None
    t = text.lower()
    for n in nodes(root):
        for field in (n['text'], n['desc']):
            f = field.lower()
            if (f == t) if exact else (t in f and f):
                return n
    return None


def tap(x, y, wait=2.5):
    shell(f'input tap {x} {y}')
    time.sleep(wait)


def tap_text(text, wait=4, exact=False):
    n = find(text, exact=exact)
    if not n:
        print('  ! not found:', text)
        return False
    tap(*n['mid'], wait=wait)
    print('  tapped:', text, n['mid'])
    return True


def texts():
    root = dump()
    if root is None:
        return []
    return [f"{n['text'] or n['desc']} @{n['mid']}" for n in nodes(root)
            if (n['text'] or n['desc'])]


def cap(name):
    png = sh('exec-out', 'screencap', '-p', binary=True)
    path = os.path.join(SP, 'shots', name + '.png')
    os.makedirs(os.path.dirname(path), exist_ok=True)
    open(path, 'wb').write(png)
    print('  captured', name, len(png), 'bytes')
    return path


def dismiss_anr():
    """Tap 'Wait' if the emulator's ANR dialog is up."""
    for _ in range(3):
        n = find('Wait')
        if not n:
            return
        tap(*n['mid'], wait=3)
        print('  dismissed ANR')


def wait_stable(tries=12, pause=6):
    """Block until no ANR dialog is on screen — never shoot through a dialog."""
    for i in range(tries):
        root = dump()
        if root is None:
            time.sleep(pause)
            continue
        anr = any("isn't responding" in n['text'] or n['text'] == 'Wait'
                  for n in nodes(root))
        if not anr:
            return True
        n = find('Wait', root=root)
        if n:
            tap(*n['mid'], wait=3)
            print(f'  dismissed ANR ({i + 1})')
        time.sleep(pause)
    print('  ! still unstable')
    return False


if __name__ == '__main__':
    if sys.argv[1:] and sys.argv[1] == 'texts':
        dismiss_anr()
        for t in texts():
            print(t)
