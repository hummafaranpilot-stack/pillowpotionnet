#!/bin/bash
# Kinzeno prelander - local preview
cd "$(dirname "$0")"
PORT="${1:-8777}"
echo "Kinzeno prelander -> http://localhost:$PORT"
open "http://localhost:$PORT" 2>/dev/null &
python3 -m http.server "$PORT"
