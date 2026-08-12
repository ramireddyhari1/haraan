"""Walk the app by on-screen text and capture a store-screenshot set.

Usage: python run_flow.py <prefix> <width> <height> <density> [rotate]
Text-based navigation, so the same walk works at any screen size.
"""
import sys, time

import driver as d


def demo_mode():
    d.shell('settings put global sysui_demo_allowed 1')
    for c in ('enter', 'clock -e hhmm 0930', 'battery -e level 100 -e plugged false',
              'network -e wifi show -e level 4', 'network -e mobile hide',
              'notifications -e visible false', 'status -e volume hide -e bluetooth hide'):
        d.shell('am broadcast -a com.android.systemui.demo -e command ' + c)


def step(label, fn, prefix, shots):
    try:
        fn()
    except Exception as e:                                   # keep walking
        print('  step failed:', label, e)
    d.wait_stable()
    time.sleep(3)
    shots.append(d.cap(f'{prefix}_{label}'))


def main():
    prefix, w, h, dens = sys.argv[1], int(sys.argv[2]), int(sys.argv[3]), int(sys.argv[4])
    rotate = len(sys.argv) > 5 and sys.argv[5] == 'rotate'

    d.shell('settings put system accelerometer_rotation 0')
    d.shell(f'settings put system user_rotation {1 if rotate else 0}')
    d.size(w, h, dens)
    demo_mode()
    d.relaunch(wait=30)
    d.wait_stable()
    if d.find('Skip'):
        d.tap_text('Skip', wait=16)
    d.wait_stable()
    time.sleep(6)

    shots = []
    step('1_home', lambda: None, prefix, shots)

    def open_event():
        for name in ('Gaurav Gupta Live', 'Resin Art', 'Brunch Ka Scene'):
            n = d.find(name)
            if n:
                d.tap(*n['mid'], wait=16)
                return
    step('2_event', open_event, prefix, shots)
    step('3_tickets', lambda: (d.tap_text('Book tickets', wait=10),
                               d.tap_text('Add', wait=6)), prefix, shots)

    def to_gamehub():
        d.shell('input keyevent KEYCODE_BACK'); time.sleep(3)
        d.shell('input keyevent KEYCODE_BACK'); time.sleep(6)
        d.wait_stable()
        d.tap_text('GameHub', wait=14)
    step('4_gamehub', to_gamehub, prefix, shots)

    def open_venue():
        for name in ('Badminton Club Pro', 'Riverside Football Turf'):
            n = d.find(name)
            if n:
                d.tap(*n['mid'], wait=16)
                return
    step('5_venue', open_venue, prefix, shots)

    print('\n'.join(shots))


if __name__ == '__main__':
    main()
