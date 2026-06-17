#!/usr/bin/env bash
# Deploy bot modules ke VPS keuangan-bot
set -euo pipefail

VPS="${VPS_HOST:-ubuntu@43.157.240.175}"
REMOTE_DIR="/home/ubuntu/keuangan-bot"
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)"

FILES=(
  ai_parser.py
  handlers.py
  wol_ee_bridge.py
  wol_ee_client.py
  bot_storage.py
  offline_queue.py
  patch_vps_runtime.py
  requirements.txt
)

echo "Deploying to ${VPS}:${REMOTE_DIR}..."
for f in "${FILES[@]}"; do
  scp "${LOCAL_DIR}/${f}" "${VPS}:${REMOTE_DIR}/${f}"
done

ssh "${VPS}" "cd ${REMOTE_DIR} && source venv/bin/activate && pip install -q -r requirements.txt && python3 patch_vps_runtime.py && supervisorctl restart keuangan-bot"
echo "Deploy selesai. Cek: ssh ${VPS} supervisorctl status keuangan-bot"
