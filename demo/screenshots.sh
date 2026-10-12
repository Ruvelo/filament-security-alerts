#!/usr/bin/env bash
# Regenerates the images in art/ from a live Halyard admin panel. Run it after
# changing the plugin's page or widget, or the demo content, then commit art/.
#
# Filament pages are Livewire components, so this serves the demo app with
# `php -S` (see demo/app.php) instead of rendering static files. Two panels: the
# real one, and the same one after everything was patched, for "All clear".
#
# Needs PHP (with the dev dependencies installed) and a Chrome or Chromium
# binary: set CHROME=/path/to/chrome if it isn't on the PATH.
set -euo pipefail
cd "$(dirname "$0")/.."

CHROME=${CHROME:-$(command -v chromium || command -v chromium-browser || command -v google-chrome || command -v chrome-headless-shell || true)}
[ -x "$CHROME" ] || { echo "Chrome not found; set CHROME=/path/to/chrome" >&2; exit 1; }

rm -rf build/demo build/demo-clear
php demo/app.php seed
DEMO_CLEAR=1 php demo/app.php seed

port=8962
clear_port=8963
php -S "127.0.0.1:$port" -t build/demo/public demo/server.php >/dev/null 2>&1 &
server=$!
DEMO_CLEAR=1 php -S "127.0.0.1:$clear_port" -t build/demo-clear/public demo/server.php >/dev/null 2>&1 &
clear_server=$!
trap 'kill $server $clear_server' EXIT
sleep 1

mkdir -p art
shot() { # name, url, width,height, [extra chrome flags]
    "$CHROME" --headless --no-sandbox --hide-scrollbars --force-device-scale-factor=2 \
        --virtual-time-budget=8000 ${4:-} --window-size="$3" \
        --screenshot="art/$1.png" "$2" 2>/dev/null
    echo "art/$1.png"
}
dark=--blink-settings=preferredColorScheme=0
base="http://127.0.0.1:$port/admin"
filters='tableFilters[exploited][isActive]=1'

shot screenshot-page       "$base/security-alerts"                          1440,1000
shot screenshot-exploited  "$base/security-alerts?$filters"                 1440,640
shot screenshot-dashboard  "$base"                                          1440,720
shot screenshot-clear      "http://127.0.0.1:$clear_port/admin/security-alerts" 1440,640
shot screenshot-dark       "$base/security-alerts"                          1440,1000 $dark
shot screenshot-dashboard-dark "$base"                                      1440,720 $dark
shot screenshot-mobile     "$base/security-alerts"                          390,844

# The banner frames the page screenshot, so it goes last.
mkdir -p build/demo/public/banner
cp demo/banner.html demo/ruvelo-mark.svg art/screenshot-page.png build/demo/public/banner/
shot banner "http://127.0.0.1:$port/banner/banner.html" 1280,640
