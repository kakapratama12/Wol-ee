"""Keuangan Bot - Main Telegram Bot Handler"""
import logging
from datetime import datetime, timedelta
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import (
    Application, CommandHandler, MessageHandler, 
    CallbackQueryHandler, ContextTypes, filters
)
from sqlalchemy import func
from models import User, Transaction, UsageLog, Partner, SessionLocal, init_db
from ai_parser import parse_transaction, format_amount, format_transaction
from ocr_handler import process_receipt_photo, parse_receipt_with_ai
from config import config, now_wib
from bot_response import BotResponse
from wol_ee_bridge import try_handle, handle_wolee_message, handle_callback_query, is_wolee_user
from conversation_memory import ConversationMemory

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Temporary storage for pending transactions
pending_transactions = {}
memory = ConversationMemory()


async def _send_text_safely(target, text, *, parse_mode="HTML", reply_markup=None):
    from html import escape
    from telegram.error import BadRequest

    text = text or "Gagal memproses."
    try:
        await target.reply_text(text, parse_mode=parse_mode, reply_markup=reply_markup)
    except BadRequest as exc:
        if "Can't parse entities" not in str(exc):
            raise
        await target.reply_text(escape(text), parse_mode="HTML", reply_markup=reply_markup)


async def _edit_text_safely(query, text, *, parse_mode="HTML"):
    from html import escape
    from telegram.error import BadRequest

    text = text or "Selesai."
    try:
        await query.edit_message_text(text, parse_mode=parse_mode)
    except BadRequest as exc:
        if "Can't parse entities" not in str(exc):
            raise
        await query.edit_message_text(escape(text), parse_mode="HTML")


async def _send_wolee_reply(message_or_query, reply):
    if isinstance(reply, BotResponse):
        await _send_text_safely(
            message_or_query,
            reply.text,
            parse_mode=reply.parse_mode,
            reply_markup=reply.reply_markup,
        )
    else:
        await _send_text_safely(message_or_query, reply or "Gagal memproses.", parse_mode="HTML")


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
    await _edit_text_safely(
        query,
        reply.text if isinstance(reply, BotResponse) else (reply or "Selesai."),
        parse_mode=reply.parse_mode if isinstance(reply, BotResponse) else "HTML",
    )

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /start command"""
    user = update.effective_user

    if context.args:
        reply = try_handle(user.id, "/start " + " ".join(context.args))
        await update.message.reply_text(reply)
        return
    
    # Create or get user
    db = SessionLocal()
    try:
        db_user = db.query(User).filter(User.telegram_id == user.id).first()
        if not db_user:
            db_user = User(
                telegram_id=user.id,
                username=user.username,
                full_name=user.full_name
            )
            db.add(db_user)
            db.commit()
        
        await update.message.reply_text(
            f"👋 Halo {user.first_name}!\n\n"
            f"Aku <b>Wol-ee</b>, asisten keuangan kamu.\n\n"
            f"Kirim pesan untuk catat transaksi:\n"
            f"• \"Beli bahan Rp 200 ribu\" → Pengeluaran\n"
            f"• \"Dapet klien Budi Rp 5 juta\" → Pemasukan\n"
            f"• \"Klien Budi hutang Rp 5 juta\" → Piutang (AR)\n"
            f"• \"Hutang ke supplier Ani Rp 3 juta\" → Hutang (AP)\n"
            f"• \"Budi bayar Rp 2 juta\" → Pembayaran\n\n"
            f"📸 Foto struk juga bisa!\n\n"
            f"📊 <b>Perintah:</b>\n"
            f"• /summary - Ringkasan bulan ini\n"
            f"• /profit - Lihat profit/loss\n"
            f"• /history - Riwayat transaksi\n"
            f"• /partners - Daftar partner\n"
            f"• /help - Bantuan",
            parse_mode="HTML"
        )
    finally:
        db.close()

async def help_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /help command"""
    await update.message.reply_text(
        "📖 <b>Cara Pakai:</b>\n\n"
        "Ketik pesan untuk catat transaksi:\n"
        "• \"Beli bahan Rp 200 ribu\" → Pengeluaran\n"
        "• \"Dapet klien Rp 5 juta\" → Pemasukan\n"
        "• \"Klien Budi hutang Rp 5 juta\" → Piutang (AR)\n"
        "• \"Hutang ke supplier Ani Rp 3 juta\" → Hutang (AP)\n"
        "• \"Budi bayar Rp 2 juta\" → Pembayaran\n\n"
        "📸 Kirim foto struk untuk auto-detect\n\n"
        "📊 <b>Perintah:</b>\n"
        "• /summary - Ringkasan bulan ini\n"
        "• /profit - Lihat profit/loss\n"
        "• /history - Riwayat transaksi\n"
        "• /partners - Daftar partner\n"
        "• /addpartner - Tambah partner\n"
        "• /start - Mulai dari awal",
        parse_mode="HTML"
    )

async def partners_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /partners command - list all partners"""
    user_id = update.effective_user.id
    if is_wolee_user(user_id):
        reply = try_handle(user_id, "partners")
        await _send_wolee_reply(update.message, reply)
        return
    user_id = update.effective_user.id
    db = SessionLocal()
    try:
        partners = db.query(Partner).filter(Partner.telegram_id == user_id).all()
        
        if not partners:
            await update.message.reply_text(
                "👥 <b>Belum ada partner</b>\n\n"
                "Partner otomatis dibuat saat kamu catat transaksi dengan nama:\n"
                "• \"Klien <b>Budi</b> hutang Rp 5 juta\"\n"
                "• \"Hutang ke supplier <b>Ani</b> Rp 3 juta\"\n\n"
                "Atau tambah manual: /addpartner",
                parse_mode="HTML"
            )
            return
        
        msg = "👥 <b>Daftar Partner:</b>\n\n"
        for p in partners:
            # Count AR/AP for this partner (defensive: always include user_id)
            ar_total = db.query(func.sum(Transaction.amount)).filter(
                Transaction.user_id == user_id,
                Transaction.partner_id == p.id,
                Transaction.is_receivable == True,
                Transaction.status == "pending"
            ).scalar() or 0
            
            ap_total = db.query(func.sum(Transaction.amount)).filter(
                Transaction.user_id == user_id,
                Transaction.partner_id == p.id,
                Transaction.is_payable == True,
                Transaction.status == "pending"
            ).scalar() or 0
            
            emoji = "👤" if p.type == "customer" else "🏭" if p.type == "supplier" else "🤝"
            contact = f" | 📱 {p.phone}" if p.phone else ""
            
            msg += f"{emoji} <b>{p.name}</b> ({p.type}){contact}\n"
            if ar_total > 0:
                msg += f"   💰 Piutang: {format_amount(ar_total)}\n"
            if ap_total > 0:
                msg += f"   💳 Hutang: {format_amount(ap_total)}\n"
            msg += "\n"
        
        await update.message.reply_text(msg, parse_mode="HTML")
    finally:
        db.close()

async def addpartner_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /addpartner command — create or update partner"""
    user_id = update.effective_user.id
    
    # Check if user provided partner info
    if context.args:
        # Parse: /addpartner Budi customer 0812xxxx
        name = context.args[0]
        p_type = context.args[1] if len(context.args) > 1 else None
        phone = context.args[2] if len(context.args) > 2 else None
        
        # Validate type
        if p_type and p_type not in ["customer", "supplier", "both"]:
            await update.message.reply_text(
                "⚠️ Tipe harus: customer / supplier / both",
                parse_mode="HTML"
            )
            return
        
        db = SessionLocal()
        try:
            # Check if partner already exists
            existing = db.query(Partner).filter(
                Partner.telegram_id == user_id,
                Partner.name.ilike(name)
            ).first()
            
            if existing:
                # Update existing partner
                updated_fields = []
                if p_type:
                    existing.type = p_type
                    updated_fields.append(f"Tipe: {p_type}")
                if phone:
                    existing.phone = phone
                    updated_fields.append(f"WA: {phone}")
                
                if updated_fields:
                    db.commit()
                    await update.message.reply_text(
                        f"✅ <b>Partner diperbarui!</b>\n\n"
                        f"👤 {existing.name}\n"
                        + "\n".join(updated_fields),
                        parse_mode="HTML"
                    )
                else:
                    await update.message.reply_text(
                        f"ℹ️ Partner <b>{name}</b> udah ada. "
                        f"Tambah WA: /addpartner {name} {existing.type} 0812xxxx",
                        parse_mode="HTML"
                    )
                return
            
            # Create new partner
            partner = Partner(
                telegram_id=user_id,
                name=name,
                type=p_type or "customer",
                phone=phone
            )
            db.add(partner)
            db.commit()
            
            await update.message.reply_text(
                f"✅ <b>Partner ditambahkan!</b>\n\n"
                f"👤 Nama: {name}\n"
                f"💼 Tipe: {p_type or 'customer'}\n"
                f"📱 WA: {phone or '-'}",
                parse_mode="HTML"
            )
        finally:
            db.close()
    else:
        await update.message.reply_text(
            "👤 <b>Tambah/Update Partner:</b>\n\n"
            "Format: /addpartner [nama] [tipe] [WA]\n\n"
            "Contoh:\n"
            "• /addpartner Budi customer 0812xxxx\n"
            "• /addpartner Ani supplier\n\n"
            "Tipe: customer / supplier / both\n\n"
            "💡 Kalau partner udah ada, tinggal tambah WA:",
            parse_mode="HTML"
        )

async def partner_detail_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /partner [nama] command - show partner detail"""
    user_id = update.effective_user.id
    
    if not context.args:
        await update.message.reply_text(
            "👤 <b>Detail Partner:</b>\n\n"
            "Format: /partner [nama]\n\n"
            "Contoh:\n"
            "• /partner Mariot\n"
            "• /partner Ani",
            parse_mode="HTML"
        )
        return
    
    partner_name = " ".join(context.args)
    
    db = SessionLocal()
    try:
        # Find partner (case-insensitive)
        partner = db.query(Partner).filter(
            Partner.telegram_id == user_id,
            Partner.name.ilike(partner_name)
        ).first()
        
        if not partner:
            await update.message.reply_text(
                f"❌ Partner <b>{partner_name}</b> gak ditemukan.\n\n"
                f"Cek daftar: /partners",
                parse_mode="HTML"
            )
            return
        
        # Get AR (pending piutang)
        ar_transactions = db.query(Transaction).filter(
            Transaction.user_id == user_id,
            Transaction.partner_id == partner.id,
            Transaction.is_receivable == True,
            Transaction.status == "pending"
        ).all()
        ar_total = sum(tx.amount - tx.paid_amount for tx in ar_transactions)
        
        # Get AP (pending hutang)
        ap_transactions = db.query(Transaction).filter(
            Transaction.user_id == user_id,
            Transaction.partner_id == partner.id,
            Transaction.is_payable == True,
            Transaction.status == "pending"
        ).all()
        ap_total = sum(tx.amount - tx.paid_amount for tx in ap_transactions)
        
        # Get last transaction
        last_tx = db.query(Transaction).filter(
            Transaction.user_id == user_id,
            Transaction.partner_id == partner.id
        ).order_by(Transaction.created_at.desc()).first()
        
        # Count total transactions
        tx_count = db.query(Transaction).filter(
            Transaction.user_id == user_id,
            Transaction.partner_id == partner.id
        ).count()
        
        # Build message
        emoji = "👤" if partner.type == "customer" else "🏭" if partner.type == "supplier" else "🤝"
        
        msg = f"{emoji} <b>{partner.name.upper()}</b>\n"
        msg += f"Type: {partner.type.title()}\n"
        
        if partner.phone:
            msg += f"📱 WA: {partner.phone}\n"
        
        msg += "\n"
        
        if ar_total > 0:
            msg += f"💰 Piutang: <b>{format_amount(ar_total)}</b> ({len(ar_transactions)} transaksi)\n"
        if ap_total > 0:
            msg += f"💳 Hutang: <b>{format_amount(ap_total)}</b> ({len(ap_transactions)} transaksi)\n"
        
        if ar_total == 0 and ap_total == 0:
            msg += "💰 Piutang: Rp 0\n💳 Hutang: Rp 0\n"
        
        msg += f"📊 Total transaksi: {tx_count}\n"
        
        if last_tx:
            date_str = last_tx.created_at.strftime("%d %b %Y")
            type_emoji = "📈" if last_tx.type == "income" else "📉"
            msg += f"\n📝 Transaksi terakhir:\n"
            msg += f"  {type_emoji} {date_str} — {last_tx.description} ({format_amount(last_tx.amount)})"
        
        await update.message.reply_text(msg, parse_mode="HTML")
        
    finally:
        db.close()

async def delete_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /delete [id] command — delete a transaction"""
    user_id = update.effective_user.id
    
    if not context.args:
        await update.message.reply_text(
            "🗑️ <b>Hapus Transaksi:</b>\n\n"
            "Format: /delete [id]\n\n"
            "Lihat ID transaksi: /history\n"
            "Contoh: /delete 42",
            parse_mode="HTML"
        )
        return
    
    try:
        tx_id = int(context.args[0])
    except ValueError:
        await update.message.reply_text(
            "⚠️ ID harus angka. Lihat ID: /history",
            parse_mode="HTML"
        )
        return
    
    db = SessionLocal()
    try:
        # Find transaction (must belong to this user)
        tx = db.query(Transaction).filter(
            Transaction.id == tx_id,
            Transaction.user_id == user_id
        ).first()
        
        if not tx:
            await update.message.reply_text(
                f"❌ Transaksi #{tx_id} gak ditemukan.\n\n"
                f"Cek riwayat: /history",
                parse_mode="HTML"
            )
            return
        
        # Store info before delete
        type_emoji = "📈" if tx.type == "income" else "📉"
        desc = tx.description
        amount = format_amount(tx.amount)
        date = tx.created_at.strftime("%d/%m/%Y")
        
        # Delete
        db.delete(tx)
        db.commit()
        
        await update.message.reply_text(
            f"🗑️ <b>Transaksi dihapus!</b>\n\n"
            f"{type_emoji} {date} | {amount}\n"
            f"📝 {desc}",
            parse_mode="HTML"
        )
        
    except Exception as e:
        db.rollback()
        await update.message.reply_text(
            f"❌ Error menghapus: {str(e)}",
            parse_mode="HTML"
        )
    finally:
        db.close()

async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle regular text messages - parse as transaction"""
    user_id = update.effective_user.id
    user_input = update.message.text

    wol_ee_reply = try_handle(user_id, user_input)
    if wol_ee_reply is not None:
        # Store in memory
        memory.store(user_id, "user", user_input)
        reply_text = wol_ee_reply.text if hasattr(wol_ee_reply, 'text') else str(wol_ee_reply)
        memory.store(user_id, "bot", reply_text[:200])
        await _send_wolee_reply(update.message, wol_ee_reply)
        return

    if is_wolee_user(user_id):
        await update.message.reply_text("🔄 Memproses...")
        is_pro_user = False
        db_check = SessionLocal()
        try:
            db_user_check = db_check.query(User).filter(User.telegram_id == user_id).first()
            is_pro_user = bool(db_user_check and db_user_check.plan == "pro")
        finally:
            db_check.close()
        wol_ee_nl = await handle_wolee_message(user_id, user_input, is_pro=is_pro_user)
        # Store in memory
        memory.store(user_id, "user", user_input)
        reply_text = wol_ee_nl.text if hasattr(wol_ee_nl, 'text') else str(wol_ee_nl) if wol_ee_nl else "Gagal memproses."
        memory.store(user_id, "bot", reply_text[:200])
        await _send_wolee_reply(update.message, wol_ee_nl or "Gagal memproses.")
        return
    
    # === ABUSE MITIGATION ===
    
    # 1. Message length limit (prevent token abuse)
    MAX_MESSAGE_LENGTH = 500
    if len(user_input) > MAX_MESSAGE_LENGTH:
        await update.message.reply_text(
            f"⚠️ Pesan terlalu panjang (max {MAX_MESSAGE_LENGTH} karakter)\n\n"
            f"Contoh: \"Beli bahan Rp 200 ribu\"",
            parse_mode="HTML"
        )
        return
    
    # 2. Cooldown between messages (prevent flood) - 3 seconds
    import time
    last_message_key = f"last_message_{user_id}"
    if not hasattr(handle_message, '_cooldowns'):
        handle_message._cooldowns = {}
    
    last_time = handle_message._cooldowns.get(last_message_key, 0)
    if time.time() - last_time < 3:
        await update.message.reply_text(
            "⏳ Tunggu sebentar sebelum kirim pesan lagi",
            parse_mode="HTML"
        )
        return
    handle_message._cooldowns[last_message_key] = time.time()
    
    # 3. Block common spam/gibberish patterns
    spam_patterns = [
        r'^(.)\1{10,}',  # Repeated characters: "aaaaaaaaaaa"
        r'^[\W_]+$',  # Only symbols/emojis
        r'^(test|spam|abc|123|asdf)+$',  # Common spam
    ]
    import re
    for pattern in spam_patterns:
        if re.match(pattern, user_input.lower().strip()):
            await update.message.reply_text(
                "🤔 Input gak dikenali. Tulis transaksi dengan nominal, contoh:\n\n"
                "\"Beli bahan Rp 200 ribu\"",
                parse_mode="HTML"
            )
            return
    
    # === END ABUSE MITIGATION ===
    
    # Check usage limit
    db = SessionLocal()
    try:
        # Count messages today (for free) or this month (for pro)
        db_user = db.query(User).filter(User.telegram_id == user_id).first()
        
        if db_user and db_user.plan == "pro":
            # Pro: monthly limit
            period_start = now_wib().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
            limit = config.PRO_MONTHLY_MESSAGES
        else:
            # Free: daily limit
            period_start = now_wib().replace(hour=0, minute=0, second=0, microsecond=0)
            limit = config.FREE_DAILY_MESSAGES
        
        message_count = db.query(UsageLog).filter(
            UsageLog.user_id == user_id,
            UsageLog.action == "message",
            UsageLog.created_at >= period_start
        ).count()
        
        if message_count >= limit:
            period = "harian" if not (db_user and db_user.plan == "pro") else "bulanan"
            await update.message.reply_text(
                f"⚠️ Limit {period} tercapai ({limit} pesan)\n\n"
                f"Upgrade ke Pro untuk unlimited! /upgrade",
                parse_mode="HTML"
            )
            return
    finally:
        db.close()
    
    # Parse transaction
    await update.message.reply_text("🔄 Memproses...")
    
    # Check if user is pro
    is_pro = db_user and db_user.plan == "pro"
    
    try:
        result = await parse_transaction(user_input, is_pro=is_pro)
    except Exception as e:
        logger.error(f"AI parse error: {e}")
        await update.message.reply_text(
            "❌ Gak bisa proses pesan ini.\n\n"
            "Coba format lebih jelas:\n"
            "• \"Beli bahan Rp 200 ribu\"\n"
            "• \"Jual produk A Rp 500 ribu\"\n"
            "• \"Klien Budi hutang Rp 1 juta\"",
            parse_mode="HTML"
        )
        return
    
    if "error" in result:
        await update.message.reply_text(
            f"❌ {result['error']}\n\n"
            f"Coba format:\n"
            f"• \"Beli bahan Rp 200 ribu\"\n"
            f"• \"Jual produk A Rp 500 ribu\"\n"
            f"• \"Klien Budi hutang Rp 1 juta\"",
            parse_mode="HTML"
        )
        return
    
    # Store pending transaction
    pending_transactions[user_id] = {
        "result": result,
        "raw_input": user_input,
        "timestamp": now_wib()
    }
    
    # Send confirmation with inline buttons
    keyboard = [
        [
            InlineKeyboardButton("✅ Konfirmasi", callback_data=f"confirm_{user_id}"),
            InlineKeyboardButton("❌ Batal", callback_data=f"cancel_{user_id}")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    await update.message.reply_text(
        format_transaction(result),
        parse_mode="HTML",
        reply_markup=reply_markup
    )

async def handle_photo(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle photo messages - OCR receipt"""
    user_id = update.effective_user.id
    
    # Check usage limit
    db = SessionLocal()
    try:
        db_user = db.query(User).filter(User.telegram_id == user_id).first()
        
        if db_user and db_user.plan == "pro":
            period_start = now_wib().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
            limit = config.PRO_MONTHLY_PHOTOS
        else:
            period_start = now_wib().replace(hour=0, minute=0, second=0, microsecond=0)
            limit = config.FREE_DAILY_PHOTOS
        
        photo_count = db.query(UsageLog).filter(
            UsageLog.user_id == user_id,
            UsageLog.action == "photo",
            UsageLog.created_at >= period_start
        ).count()
        
        if photo_count >= limit:
            period = "harian" if not (db_user and db_user.plan == "pro") else "bulanan"
            await update.message.reply_text(
                f"⚠️ Limit foto {period} tercapai ({limit} foto)\n\n"
                f"Upgrade ke Pro untuk lebih banyak! /upgrade",
                parse_mode="HTML"
            )
            return
    finally:
        db.close()
    
    await update.message.reply_text("📸 Memproses struk...")
    
    # Get photo
    photo = update.message.photo[-1]
    file = await context.bot.get_file(photo.file_id)
    photo_bytes = await file.download_as_bytearray()
    
    # OCR
    ocr_text = await process_receipt_photo(bytes(photo_bytes))
    
    if "Error" in ocr_text or "Gak bisa" in ocr_text:
        await update.message.reply_text(ocr_text)
        return
    
    # Parse with AI
    result = await parse_receipt_with_ai(ocr_text)
    
    if "error" in result:
        await update.message.reply_text(
            f"📝 Teks terbaca:\n```\n{ocr_text[:500]}\n```\n\n"
            f"❌ Gak bisa parse otomatis. Coba ketik manual:\n"
            f"\"Beli [item] Rp [jumlah]\"",
            parse_mode="HTML"
        )
        return
    
    # Store pending
    pending_transactions[user_id] = {
        "result": result,
        "raw_input": f"[Foto] {ocr_text[:100]}",
        "timestamp": now_wib()
    }
    
    # Send confirmation
    keyboard = [
        [
            InlineKeyboardButton("✅ Konfirmasi", callback_data=f"confirm_{user_id}"),
            InlineKeyboardButton("❌ Batal", callback_data=f"cancel_{user_id}")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    await update.message.reply_text(
        f"📝 <b>Teks dari struk:</b>\n```\n{ocr_text[:300]}\n```\n\n"
        f"{format_transaction(result)}",
        parse_mode="HTML",
        reply_markup=reply_markup
    )

async def callback_handler(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle inline button callbacks"""
    query = update.callback_query
    await query.answer()
    
    data = query.data
    user_id = update.effective_user.id
    
    if data.startswith("confirm_"):
        # Extract intended user_id from callback data
        try:
            intended_user_id = int(data.split("_")[1])
        except (IndexError, ValueError):
            await query.edit_message_text("❌ Invalid callback")
            return
        
        # Security: only the original user can confirm their own transaction
        if user_id != intended_user_id:
            await query.answer("❌ Ini bukan transaksi kamu!", show_alert=True)
            return
        
        # Save transaction
        if user_id in pending_transactions:
            tx = pending_transactions[user_id]
            result = tx["result"]
            
            db = SessionLocal()
            try:
                # Handle partner if exists
                partner_id = None
                partner_name = result.get("partner_name")
                partner_type = result.get("partner_type", "customer")
                
                if partner_name:
                    # Find or create partner
                    partner = db.query(Partner).filter(
                        Partner.telegram_id == user_id,
                        Partner.name.ilike(partner_name)
                    ).first()
                    
                    if not partner:
                        # Create new partner
                        partner = Partner(
                            telegram_id=user_id,
                            name=partner_name,
                            type=partner_type
                        )
                        db.add(partner)
                        db.flush()  # Get ID
                    partner_id = partner.id
                
                # Calculate due date (30 days for AR, 14 days for AP)
                due_date = None
                is_receivable = result.get("is_receivable", False)
                is_payable = result.get("is_payable", False)
                is_payment = result.get("is_payment", False)
                
                if is_receivable:
                    due_date = now_wib() + timedelta(days=30)
                elif is_payable:
                    due_date = now_wib() + timedelta(days=14)
                
                # Handle payment
                status = "pending"
                paid_amount = 0
                paid_at = None
                
                if is_payment:
                    payment_amount = result.get("payment_amount", 0)
                    # Find existing AR/AP to pay
                    existing_tx = db.query(Transaction).filter(
                        Transaction.user_id == user_id,
                        Transaction.partner_id == partner_id,
                        Transaction.status == "pending",
                        (Transaction.is_receivable == True) | (Transaction.is_payable == True)
                    ).first()
                    
                    if existing_tx:
                        # Update existing transaction
                        existing_tx.paid_amount += payment_amount
                        existing_tx.paid_at = now_wib()
                        
                        # Check if fully paid
                        if existing_tx.paid_amount >= existing_tx.amount:
                            existing_tx.status = "paid"
                            status = "paid"
                        paid_amount = payment_amount
                    else:
                        # No existing AR/AP, treat as regular income
                        is_payment = False
                
                # Save transaction
                new_tx = Transaction(
                    user_id=user_id,
                    partner_id=partner_id,
                    type=result["type"],
                    amount=result["amount"],
                    category=result["category"],
                    description=result["description"],
                    is_receivable=is_receivable,
                    is_payable=is_payable,
                    due_date=due_date,
                    status=status if not is_payment else "paid",
                    paid_amount=paid_amount,
                    paid_at=paid_at
                )
                db.add(new_tx)
                
                # Log usage
                log = UsageLog(user_id=user_id, action="message")
                db.add(log)
                
                db.commit()
                
                # Build confirmation message
                if is_payment:
                    msg = f"✅ <b>Pembayaran Dicatat!</b>\n\n"
                    msg += f"👤 {partner_name}\n"
                    msg += f"💰 Pembayaran: {format_amount(payment_amount)}\n"
                    if existing_tx:
                        sisa = existing_tx.amount - existing_tx.paid_amount
                        msg += f"💳 Sisa: {format_amount(sisa)}"
                elif is_receivable:
                    msg = f"✅ <b>Piutang Dicatat!</b>\n\n"
                    msg += f"👤 {partner_name}\n"
                    msg += f"💰 Piutang: {format_amount(result['amount'])}\n"
                    msg += f"📅 Jatuh tempo: {due_date.strftime('%d %b %Y')}"
                elif is_payable:
                    msg = f"✅ <b>Hutang Dicatat!</b>\n\n"
                    msg += f"👤 {partner_name}\n"
                    msg += f"💰 Hutang: {format_amount(result['amount'])}\n"
                    msg += f"📅 Jatuh tempo: {due_date.strftime('%d %b %Y')}"
                else:
                    type_emoji = "📈" if result["type"] == "income" else "📉"
                    msg = f"✅ <b>Tersimpan!</b>\n\n"
                    msg += f"{type_emoji} {result['type'].upper()}\n"
                    msg += f"💰 {format_amount(result['amount'])}\n"
                    msg += f"📂 {result['category']}\n"
                    msg += f"📝 {result['description']}"
                
                await query.edit_message_text(msg, parse_mode="HTML")
                
                del pending_transactions[user_id]
                
            except Exception as e:
                logger.error(f"Error saving transaction: {e}")
                db.rollback()
                await query.edit_message_text(
                    "❌ Gak bisa menyimpan transaksi.\n\n"
                    "Coba lagi atau hubungi support.",
                    parse_mode="HTML"
                )
            finally:
                db.close()
    
    elif data.startswith("cancel_"):
        if user_id in pending_transactions:
            del pending_transactions[user_id]
        await query.edit_message_text("❌ Dibatalkan")
    
    elif data.startswith("delhist_"):
        # Handle delete from history buttons
        try:
            tx_id = int(data.split("_")[1])
        except (IndexError, ValueError):
            await query.answer("❌ Invalid", show_alert=True)
            return
        
        db = SessionLocal()
        try:
            tx = db.query(Transaction).filter(
                Transaction.id == tx_id,
                Transaction.user_id == user_id
            ).first()
            
            if not tx:
                await query.answer("❌ Transaksi gak ditemukan", show_alert=True)
                return
            
            # Store tx info for confirmation
            type_emoji = "📈" if tx.type == "income" else "📉"
            desc = tx.description
            amount = format_amount(tx.amount)
            date = tx.created_at.strftime("%d/%m/%Y")
            
            # Show confirmation with inline buttons
            keyboard = [[
                InlineKeyboardButton("✅ Ya, Hapus", callback_data=f"delconfirm_{tx_id}"),
                InlineKeyboardButton("❌ Batal", callback_data=f"delcancel_{tx_id}")
            ]]
            reply_markup = InlineKeyboardMarkup(keyboard)
            
            await query.edit_message_text(
                f"🗑️ <b>Konfirmasi Hapus?</b>\n\n"
                f"#{tx_id} {type_emoji} {date} | {amount}\n"
                f"📝 {desc}",
                parse_mode="HTML",
                reply_markup=reply_markup
            )
            
        finally:
            db.close()
    
    elif data.startswith("delconfirm_"):
        # Confirm delete
        try:
            tx_id = int(data.split("_")[1])
        except (IndexError, ValueError):
            await query.answer("❌ Invalid", show_alert=True)
            return
        
        db = SessionLocal()
        try:
            tx = db.query(Transaction).filter(
                Transaction.id == tx_id,
                Transaction.user_id == user_id
            ).first()
            
            if not tx:
                await query.edit_message_text("❌ Transaksi gak ditemukan")
                return
            
            type_emoji = "📈" if tx.type == "income" else "📉"
            desc = tx.description
            amount = format_amount(tx.amount)
            date = tx.created_at.strftime("%d/%m/%Y")
            
            db.delete(tx)
            db.commit()
            
            await query.edit_message_text(
                f"✅ <b>Transaksi dihapus!</b>\n\n"
                f"#{tx_id} {type_emoji} {date} | {amount}\n"
                f"📝 {desc}",
                parse_mode="HTML"
            )
            
        except Exception as e:
            db.rollback()
            await query.edit_message_text("❌ Error menghapus transaksi")
        finally:
            db.close()
    
    elif data.startswith("delcancel_"):
        await query.edit_message_text("❌ Dibatalkan")

async def summary(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /summary command - monthly summary"""
    user_id = update.effective_user.id
    if is_wolee_user(user_id):
        reply = try_handle(user_id, "summary")
        await _send_wolee_reply(update.message, reply)
        return
    user_id = update.effective_user.id
    
    db = SessionLocal()
    try:
        month_start = now_wib().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
        
        # Get transactions this month
        transactions = db.query(Transaction).filter(
            Transaction.user_id == user_id,
            Transaction.created_at >= month_start,
            Transaction.is_confirmed == True
        ).all()
        
        if not transactions:
            await update.message.reply_text("📊 Belum ada transaksi bulan ini")
            return
        
        # Calculate totals
        total_income = sum(tx.amount for tx in transactions if tx.type == "income")
        total_expense = sum(tx.amount for tx in transactions if tx.type == "expense")
        profit = total_income - total_expense
        
        # Category breakdown
        categories = {}
        for tx in transactions:
            cat = tx.category
            if cat not in categories:
                categories[cat] = {"income": 0, "expense": 0}
            categories[cat][tx.type] += tx.amount
        
        # Format message
        msg = f"📊 <b>Ringkasan {now_wib().strftime('%B %Y')}</b>\n\n"
        msg += f"📈 Pemasukan: <b>{format_amount(total_income)}</b>\n"
        msg += f"📉 Pengeluaran: <b>{format_amount(total_expense)}</b>\n"
        msg += f"💰 Profit: <b>{format_amount(profit)}</b>\n\n"
        
        msg += "📂 <b>Per Kategori:</b>\n"
        for cat, amounts in categories.items():
            if amounts["income"] > 0:
                msg += f"  • {cat}: +{format_amount(amounts['income'])}\n"
            if amounts["expense"] > 0:
                msg += f"  • {cat}: -{format_amount(amounts['expense'])}\n"
        
        await update.message.reply_text(msg, parse_mode="HTML")
        
    finally:
        db.close()

async def profit(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /profit command"""
    user_id = update.effective_user.id
    if is_wolee_user(user_id):
        reply = try_handle(user_id, "profit")
        await _send_wolee_reply(update.message, reply)
        return
    user_id = update.effective_user.id
    
    db = SessionLocal()
    try:
        month_start = now_wib().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
        
        transactions = db.query(Transaction).filter(
            Transaction.user_id == user_id,
            Transaction.created_at >= month_start,
            Transaction.is_confirmed == True
        ).all()
        
        if not transactions:
            await update.message.reply_text("📊 Belum ada transaksi bulan ini")
            return
        
        total_income = sum(tx.amount for tx in transactions if tx.type == "income")
        total_expense = sum(tx.amount for tx in transactions if tx.type == "expense")
        profit = total_income - total_expense
        margin = (profit / total_income * 100) if total_income > 0 else 0
        
        # Profit indicator
        if profit > 0:
            indicator = "🟢 UNTUNG"
        elif profit < 0:
            indicator = "🔴 RUGI"
        else:
            indicator = "🟡 IMPAS"
        
        msg = f"📊 <b>Profit Report {now_wib().strftime('%B %Y')}</b>\n\n"
        msg += f"{indicator}\n\n"
        msg += f"💰 Revenue: <b>{format_amount(total_income)}</b>\n"
        msg += f"💸 Expense: <b>{format_amount(total_expense)}</b>\n"
        msg += f"📈 Profit: <b>{format_amount(profit)}</b>\n"
        msg += f"📊 Margin: <b>{margin:.1f}%</b>\n"
        
        if margin < 10 and total_income > 0:
            msg += "\n⚠️ <b>Margin rendah!</b>\nCek pengeluaran kamu."
        
        await update.message.reply_text(msg, parse_mode="HTML")
        
    finally:
        db.close()

async def history(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Handle /history command - last 10 transactions"""
    user_id = update.effective_user.id
    if is_wolee_user(user_id):
        reply = try_handle(user_id, "history")
        await _send_wolee_reply(update.message, reply)
        return
    user_id = update.effective_user.id
    
    db = SessionLocal()
    try:
        transactions = db.query(Transaction).filter(
            Transaction.user_id == user_id,
            Transaction.is_confirmed == True
        ).order_by(Transaction.created_at.desc()).limit(10).all()
        
        if not transactions:
            await update.message.reply_text("📝 Belum ada riwayat transaksi")
            return
        
        msg = "📝 <b>10 Transaksi Terakhir:</b>\n\n"
        
        # Build inline keyboard with delete buttons
        keyboard = []
        
        for tx in transactions:
            emoji = "📈" if tx.type == "income" else "📉"
            date = tx.created_at.strftime("%d/%m")
            msg += f"#{tx.id} {emoji} {date} | {format_amount(tx.amount)} | {tx.description}\n"
            
            # Add delete button for each transaction
            keyboard.append([
                InlineKeyboardButton(
                    f"🗑️ #{tx.id} Hapus",
                    callback_data=f"delhist_{tx.id}"
                )
            ])
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        await update.message.reply_text(msg, parse_mode="HTML", reply_markup=reply_markup)
        
    finally:
        db.close()


async def wol_ee_stok_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    text = update.message.text or "stok"
    reply = try_handle(user_id, text)
    await update.message.reply_text(reply or "Gagal memproses perintah stok")


async def wol_ee_aging_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_id = update.effective_user.id
    reply = try_handle(user_id, "aging")
    await update.message.reply_text(reply or "Gagal memproses laporan aging")


def main():
    """Main function to run the bot"""
    # Initialize database
    init_db()
    
    # Create bot application
    application = Application.builder().token(config.TELEGRAM_TOKEN).build()
    
    # Set command menu (shows when user types "/")
    from telegram import BotCommand
    commands = [
        BotCommand("start", "Mulai dari awal"),
        BotCommand("help", "Bantuan cara pakai"),
        BotCommand("summary", "Ringkasan bulan ini"),
        BotCommand("profit", "Lihat profit/loss"),
        BotCommand("history", "Riwayat transaksi"),
        BotCommand("delete", "Hapus transaksi"),
        BotCommand("partners", "Daftar partner"),
        BotCommand("partner", "Detail partner"),
        BotCommand("addpartner", "Tambah partner baru"),
    ]
    
    # We'll set commands after bot starts
    async def post_init(application):
        await application.bot.set_my_commands(commands)
    
    application.post_init = post_init
    
    # Add handlers
    application.add_handler(CommandHandler("start", start))
    application.add_handler(CommandHandler("help", help_command))
    application.add_handler(CommandHandler("summary", summary))
    application.add_handler(CommandHandler("profit", profit))
    application.add_handler(CommandHandler("history", history))
    application.add_handler(CommandHandler("delete", delete_command))
    application.add_handler(CommandHandler("partners", partners_command))
    application.add_handler(CommandHandler("partner", partner_detail_command))
    application.add_handler(CommandHandler("addpartner", addpartner_command))
    application.add_handler(CommandHandler("stok", wol_ee_stok_command))
    application.add_handler(CommandHandler("aging", wol_ee_aging_command))
    
    # Message handlers
    application.add_handler(MessageHandler(filters.PHOTO, handle_photo))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_message))
    
    # Callback handler for inline buttons
    application.add_handler(CallbackQueryHandler(callback_handler))
    
    # Run bot
    print("🤖 Bot berjalan...")
    application.run_polling()

if __name__ == "__main__":
    main()
