#!/usr/bin/env python3
"""Patch keuangan-bot runtime on VPS for Wol-ee NL + API integration."""

from __future__ import annotations

from pathlib import Path

ROOT = Path("/home/ubuntu/keuangan-bot")

# 1) config.py — Wol-ee API vars
config_path = ROOT / "config.py"
if config_path.exists():
    text = config_path.read_text()
    if "WOL_EE_API_URL" not in text:
        text = text.replace(
            "    # Database\n",
            "    # Wol-ee Laravel API\n"
            '    WOL_EE_API_URL: str = os.getenv("WOL_EE_API_URL", "https://wolee.my.id/api")\n'
            '    WOL_EE_API_TOKEN: str = os.getenv("WOL_EE_API_TOKEN", "")\n\n'
            "    # Database\n",
        )
        config_path.write_text(text)
        print("config.py updated")

# 2) .env — no hardcoded secrets
env_path = ROOT / ".env"
if env_path.exists():
    env = env_path.read_text()
    for key, default in {
        "WOL_EE_API_URL": "https://wolee.my.id/api",
    }.items():
        if f"{key}=" not in env:
            env += f"\n{key}={default}\n"
    env_path.write_text(env)
    print(".env updated")

# 3) bot.py patches
bot_path = ROOT / "bot.py"
bot = bot_path.read_text()

if "from wol_ee_bridge import try_handle, handle_wolee_message, is_wolee_user" not in bot:
    bot = bot.replace(
        "from wol_ee_bridge import try_handle\n",
        "from wol_ee_bridge import try_handle, handle_wolee_message, is_wolee_user\n",
    )

# handle_message: Wol-ee NL path for registered users
old_handle = (
    "    wol_ee_reply = try_handle(user_id, user_input)\n"
    "    if wol_ee_reply is not None:\n"
    "        await update.message.reply_text(wol_ee_reply)\n"
    "        return\n"
)
new_handle = (
    "    wol_ee_reply = try_handle(user_id, user_input)\n"
    "    if wol_ee_reply is not None:\n"
    "        await update.message.reply_text(wol_ee_reply, parse_mode=\"HTML\")\n"
    "        return\n\n"
    "    if is_wolee_user(user_id):\n"
    "        await update.message.reply_text(\"🔄 Memproses...\")\n"
    "        is_pro_user = False\n"
    "        db_check = SessionLocal()\n"
    "        try:\n"
    "            db_user_check = db_check.query(User).filter(User.telegram_id == user_id).first()\n"
    "            is_pro_user = bool(db_user_check and db_user_check.plan == \"pro\")\n"
    "        finally:\n"
    "            db_check.close()\n"
    "        wol_ee_nl = await handle_wolee_message(user_id, user_input, is_pro=is_pro_user)\n"
    "        await update.message.reply_text(wol_ee_nl or \"Gagal memproses.\", parse_mode=\"HTML\")\n"
    "        return\n"
)
if old_handle in bot:
    bot = bot.replace(old_handle, new_handle)
    print("handle_message patched")

# summary command
summary_hook = (
    'async def summary(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /summary command - monthly summary"""\n'
)
summary_new = (
    'async def summary(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /summary command - monthly summary"""\n'
    '    user_id = update.effective_user.id\n'
    '    if is_wolee_user(user_id):\n'
    '        reply = try_handle(user_id, "summary")\n'
    '        await update.message.reply_text(reply, parse_mode="HTML")\n'
    '        return\n'
)
if summary_hook in bot and "if is_wolee_user(user_id):" not in bot.split("async def summary")[1].split("async def profit")[0]:
    bot = bot.replace(summary_hook, summary_new, 1)
    print("summary patched")

# profit command
profit_hook = (
    'async def profit(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /profit command"""\n'
)
profit_new = (
    'async def profit(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /profit command"""\n'
    '    user_id = update.effective_user.id\n'
    '    if is_wolee_user(user_id):\n'
    '        reply = try_handle(user_id, "profit")\n'
    '        await update.message.reply_text(reply, parse_mode="HTML")\n'
    '        return\n'
)
if profit_hook in bot and "if is_wolee_user(user_id):" not in bot.split("async def profit")[1].split("async def history")[0]:
    bot = bot.replace(profit_hook, profit_new, 1)
    print("profit patched")

# history command
history_hook = (
    'async def history(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /history command - last 10 transactions"""\n'
)
history_new = (
    'async def history(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /history command - last 10 transactions"""\n'
    '    user_id = update.effective_user.id\n'
    '    if is_wolee_user(user_id):\n'
    '        reply = try_handle(user_id, "history")\n'
    '        await update.message.reply_text(reply, parse_mode="HTML")\n'
    '        return\n'
)
if history_hook in bot and "if is_wolee_user(user_id):" not in bot.split("async def history")[1].split("async def wol_ee_stok")[0]:
    bot = bot.replace(history_hook, history_new, 1)
    print("history patched")

# partners command
partners_hook = (
    'async def partners_command(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /partners command - list all partners"""\n'
)
partners_new = (
    'async def partners_command(update: Update, context: ContextTypes.DEFAULT_TYPE):\n'
    '    """Handle /partners command - list all partners"""\n'
    '    user_id = update.effective_user.id\n'
    '    if is_wolee_user(user_id):\n'
    '        reply = try_handle(user_id, "partners")\n'
    '        await update.message.reply_text(reply, parse_mode="HTML")\n'
    '        return\n'
)
if partners_hook in bot and "if is_wolee_user(user_id):" not in bot.split("async def partners_command")[1].split("async def addpartner")[0]:
    bot = bot.replace(partners_hook, partners_new, 1)
    print("partners patched")

bot_path.write_text(bot)
print("bot.py saved")

(ROOT / "logs").mkdir(exist_ok=True)
print("done")


# 4) WOL_EE_APP_URL
if config_path.exists():
    text = config_path.read_text()
    if "WOL_EE_APP_URL" not in text:
        text = text.replace(
            '    WOL_EE_API_TOKEN: str = os.getenv("WOL_EE_API_TOKEN", "")\n',
            '    WOL_EE_API_TOKEN: str = os.getenv("WOL_EE_API_TOKEN", "")\n'
            '    WOL_EE_APP_URL: str = os.getenv("WOL_EE_APP_URL", "")\n',
        )
        config_path.write_text(text)
        print("WOL_EE_APP_URL added to config.py")

if env_path.exists():
    env = env_path.read_text()
    if "WOL_EE_APP_URL=" not in env:
        env += "\nWOL_EE_APP_URL=https://your-domain.com\n"
        env_path.write_text(env)
        print(".env WOL_EE_APP_URL added")

# 5) BotResponse + callback query support
bot = bot_path.read_text()

if "from bot_response import BotResponse" not in bot:
    bot = bot.replace(
        "from telegram.ext import Application",
        "from bot_response import BotResponse\nfrom telegram.ext import Application",
    )

if "handle_callback_query" not in bot:
    bot = bot.replace(
        "from wol_ee_bridge import try_handle, handle_wolee_message, is_wolee_user\n",
        "from wol_ee_bridge import try_handle, handle_wolee_message, handle_callback_query, is_wolee_user\n",
    )

helper = """
async def _send_wolee_reply(message_or_query, reply):
    if isinstance(reply, BotResponse):
        await message_or_query.reply_text(
            reply.text,
            parse_mode=reply.parse_mode,
            reply_markup=reply.reply_markup,
        )
    else:
        await message_or_query.reply_text(reply or "Gagal memproses.", parse_mode="HTML")


async def wolee_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    if not query or not query.data or not query.data.startswith("wolee:batch:"):
        return
    await query.answer()
    user_id = update.effective_user.id
    if not is_wolee_user(user_id):
        await query.edit_message_text("Sesi tidak valid.")
        return
    reply = handle_callback_query(user_id, query.data)
    await query.edit_message_text(
        reply.text if isinstance(reply, BotResponse) else (reply or "Selesai."),
        parse_mode=reply.parse_mode if isinstance(reply, BotResponse) else "HTML",
    )

"""

if "async def wolee_callback" not in bot:
    # insert helper before first async def handler if possible
    marker = "async def start(update: Update"
    if marker in bot:
        bot = bot.replace(marker, helper + marker)
        print("wolee callback helper added")

if 'CallbackQueryHandler(wolee_callback' not in bot:
    bot = bot.replace(
        "app.run_polling",
        "    app.add_handler(CallbackQueryHandler(wolee_callback, pattern=r'^wolee:batch:'))\n\n    app.run_polling",
    )
    print("CallbackQueryHandler registered")

bot_path.write_text(bot)
print("bot.py callback patch saved")
