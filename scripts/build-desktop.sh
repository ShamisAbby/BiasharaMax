#!/usr/bin/env bash
# Builds the Flutter desktop client for the current platform. Windows builds
# require an actual Windows machine (or the build-desktop-windows.yml CI
# workflow) — Flutter has no Linux-to-Windows cross-compile path.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESKTOP_DIR="$ROOT_DIR/desktop-app"

PLATFORM="${1:-}"
if [ -z "$PLATFORM" ]; then
    case "$(uname -s)" in
        Darwin) PLATFORM="macos" ;;
        Linux)  PLATFORM="linux" ;;
        *)      echo "Usage: $0 [windows|macos|linux] (could not auto-detect)"; exit 1 ;;
    esac
    echo "==> No platform given, detected: $PLATFORM"
fi

if ! command -v flutter >/dev/null 2>&1; then
    echo "flutter not found on PATH. Install it first: https://docs.flutter.dev/get-started/install"
    exit 1
fi

cd "$DESKTOP_DIR"

echo "==> Generating native runner shell for $PLATFORM (safe on an existing pubspec.yaml/lib/)"
flutter create --platforms="$PLATFORM" .

echo "==> Fetching packages"
flutter pub get

echo "==> Running Drift codegen (lib/data/local/database.g.dart)"
dart run build_runner build --delete-conflicting-outputs

echo "==> Building release for $PLATFORM"
flutter build "$PLATFORM" --release

echo "==> Done. Output under desktop-app/build/$PLATFORM/"
