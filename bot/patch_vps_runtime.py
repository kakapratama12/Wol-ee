#!/usr/bin/env python3
"""Patch keuangan-bot runtime on VPS for Wol-ee integration."""

from __future__ import annotations

from pathlib import Path

ROOT = Path("/home/ubuntu/keuangan-bot")

# 1) config.py
config_path = ROOT / "config.py"
text = config_path.read_text()
if "WOL_EE_API_URL" not in text:
    text = text.replace(
        "    # Database\n",
        "    # Wol-ee Laravel API\n"
        '    WOL_EE_API_URL: str = os.getenv("WOL_EE_API_URL", "http://127.0.0.1/api")\n'
        '    WOL_EE_API_TOKEN: str = os.getenv("WOL_EE_API_TOKEN", "")\n\n'
        "    # Database\n",
    )
    config_path.write_text(text)
    print("config.py updated")

# 2) .env
env_path = ROOT / ".env"
env = env_path.read_text()
for key, value in {
    "WOL_EE_API_URL": "http://127.0.0.1/api",
    "WOL_EE_API_TOKEN": "1:W2NjpaZMF5ZmvyMf3sFhQuIcmLWorFRD",
}.items():
    if f"{key}=" not in env:
        env += f"\n{key}={value}\n"
env_path.write_text(env)
print(".env updated")

# 3) bot.py
bot_path = ROOT / "bot.py"
bot = bot_path.read_text()

if "from wol_ee_bridge import try_handle" not in bot:
    bot = bot.replace(
        "from config import config, now_wib\n",
        "from config import config, now_wib\nfrom wol_ee_bridge import try_handle\n",
    )

start_block = bot.split("async def start", 1)[1].split("async def help_command", 1)[0]
if "context.args" not in start_block:
    bot = bot.replace(
        'async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):\n    """Handle /start command"""\n    user = update.effective_user\n    \n    # Create or get user',
        'async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):\n    """Handle /start command"""\n    user = update.effective_user\n\n    if context.args:\n        reply = try_handle(user.id, "/start " + " ".join(context.args))\n        await update.message.reply_text(reply)\n        return\n    \n    # Create or get user',
    )

if "wol_ee_reply = try_handle" not in bot:
    bot = bot.replace(
        "    user_input = update.message.text\n    \n    # === ABUSE MITIGATION ===",
        "    user_input = update.message.text\n\n    wol_ee_reply = try_handle(user_id, user_input)\n    if wol_ee_reply is not None:\n        await update.message.reply_text(wol_ee_reply)\n        return\n    \n    # === ABUSE MITIGATION ===",
    )

if "async def wol_ee_stok_command" not in bot:
    insert = """

async def wol_ee_stok_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    text = update.message.text or "stok"
    reply = try_handle(user_id, text)
    await update.message.reply_text(reply or "Gagal memproses perintah stok")


async def wol_ee_aging_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    reply = try_handle(user_id, "aging")
    await update.message.reply_text(reply or "Gagal memproses laporan aging")

"""
    bot = bot.replace("\ndef main():", insert + "\ndef main():")

if 'CommandHandler("stok", wol_ee_stok_command)' not in bot:
    bot = bot.replace(
        '    application.add_handler(CommandHandler("addpartner", addpartner_command))\n',
        '    application.add_handler(CommandHandler("addpartner", addpartner_command))\n'
        '    application.add_handler(CommandHandler("stok", wol_ee_stok_command))\n'
        '    application.add_handler(CommandHandler("aging", wol_ee_aging_command))\n',
    )

bot_path.write_text(bot)
print("bot.py patched")

(ROOT / "logs").mkdir(exist_ok=True)
print("logs dir ready")
