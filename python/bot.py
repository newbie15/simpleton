import logging
import time
from collections import defaultdict

from telegram import BotCommand, KeyboardButton, ReplyKeyboardMarkup, ReplyKeyboardRemove, Update
from telegram.error import TelegramError
from telegram.ext import Application, CommandHandler, ContextTypes, MessageHandler, filters

import auth
import config
import db

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logging.getLogger("httpx").setLevel(logging.WARNING)  # URL httpx memuat token bot
log = logging.getLogger("bot")

_attempts = defaultdict(list)

BTN_SALDO, BTN_BELANJA, BTN_TAGIHAN, BTN_SHU = "💰 Saldo", "🛒 Belanja", "🧾 Tagihan", "🎁 SHU"
BTN_LAPORAN, BTN_CARI = "📊 Laporan Keuangan", "🔎 Cari Barang"
LEGACY_BTN_KELUAR = "🚪 Keluar"  # keyboard lama di chat yang belum di-refresh
BTN_LOGIN = "🔐 Masuk sebagai anggota"
BTN_CANCEL_LOGIN = "✖️ Batal"
LOGIN_STATE = "login_step"
LOGIN_KEYBOARD = ReplyKeyboardMarkup(
    [[KeyboardButton(BTN_LOGIN)]], resize_keyboard=True, is_persistent=True
)
LOGIN_CANCEL_KEYBOARD = ReplyKeyboardMarkup(
    [[KeyboardButton(BTN_CANCEL_LOGIN)]], resize_keyboard=True, is_persistent=True
)
MENU = ReplyKeyboardMarkup(
    [[KeyboardButton(BTN_SALDO), KeyboardButton(BTN_BELANJA)],
     [KeyboardButton(BTN_TAGIHAN), KeyboardButton(BTN_SHU)],
     [KeyboardButton(BTN_LAPORAN), KeyboardButton(BTN_CARI)]],
    resize_keyboard=True,
    is_persistent=True,
)
COMMANDS = [
    BotCommand("menu", "Tampilkan tombol menu"),
    BotCommand("masuk", "Masuk dengan NPK dan PIN"),
    BotCommand("batal", "Batalkan proses login"),
    BotCommand("saldo", "Saldo simpanan"),
    BotCommand("belanja", "10 belanja terakhir"),
    BotCommand("tagihan", "Tagihan potong gaji"),
    BotCommand("shu", "Riwayat SHU"),
    BotCommand("laporan", "Ringkasan keuangan koperasi"),
    BotCommand("cari", "Cari barang dan stok"),
    BotCommand("keluar", "Putuskan akun Telegram"),
]


def rp(n):
    return "Rp " + f"{float(n):,.0f}".replace(",", ".")


def rate_limited(chat_id, limit=5, window=300):
    now = time.time()
    _attempts[chat_id] = [t for t in _attempts[chat_id] if now - t < window]
    if len(_attempts[chat_id]) >= limit:
        return True
    _attempts[chat_id].append(now)
    return False


async def delete_sensitive_message(message):
    try:
        await message.delete()
        return True
    except TelegramError as exc:
        log.warning("Tidak dapat menghapus pesan login (%s)", type(exc).__name__)
        return False


async def start(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    ctx.user_data.pop(LOGIN_STATE, None)
    ctx.user_data.pop("login_npk", None)
    ctx.user_data.pop("awaiting_product_search", None)
    member = auth.get_member(update.effective_chat.id)
    if member:
        await update.message.reply_text(f"Halo {member['nama']}, pilih menu di bawah.", reply_markup=MENU)
        return
    await update.message.reply_text(
        "Selamat datang di bot Koperasi Makmur Indah Sejahtera.\n"
        "Untuk membuka layanan anggota, tekan tombol di bawah untuk masuk.",
        reply_markup=LOGIN_KEYBOARD,
    )


async def mulai_login(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    ctx.user_data.pop("awaiting_product_search", None)
    ctx.user_data[LOGIN_STATE] = "npk"
    await update.message.reply_text(
        "Masukkan NPK anggota.\n\nJangan kirim PIN dulu. Ketik /start untuk membatalkan.",
        reply_markup=LOGIN_CANCEL_KEYBOARD,
    )


async def batal_login(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    ctx.user_data.pop(LOGIN_STATE, None)
    ctx.user_data.pop("login_npk", None)
    if auth.get_member(update.effective_chat.id):
        await update.message.reply_text("Proses login dibatalkan.", reply_markup=MENU)
    else:
        await update.message.reply_text("Proses login dibatalkan.", reply_markup=LOGIN_KEYBOARD)


async def masuk(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    ctx.user_data.pop(LOGIN_STATE, None)
    chat_id = update.effective_chat.id
    if rate_limited(chat_id):
        response, markup = "Terlalu banyak percobaan. Coba lagi beberapa menit lagi.", None
    elif len(ctx.args) != 2:
        response, markup = "Format: /masuk <NPK> <PIN>", None
    else:
        result = auth.login(chat_id, ctx.args[0].strip(), ctx.args[1].strip())
        log.info("login attempt: argument_count=%d result=%s", len(ctx.args), result)
        messages = {
            "ok": "Berhasil masuk. Pilih menu di bawah.",
            "locked": "Akun terkunci sementara karena terlalu banyak salah PIN. Coba lagi nanti.",
            "invalid": "NPK atau PIN salah.",
        }
        response, markup = messages[result], MENU if result == "ok" else None
    deleted = await delete_sensitive_message(update.message)
    if len(ctx.args) == 2 and not deleted:
        response += "\nPesan perintah tidak dapat dihapus otomatis. Hapus pesan itu dari chat."
    await update.message.reply_text(response, reply_markup=markup)


def member_only(handler):
    async def wrapper(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
        member = auth.get_member(update.effective_chat.id)
        if not member:
            await update.message.reply_text("Belum terverifikasi. Gunakan /start.")
            return
        try:
            await handler(update, member)
        except Exception:
            log.exception("handler gagal")
            await update.message.reply_text("Terjadi kesalahan, coba lagi nanti.")

    return wrapper


@member_only
async def saldo(update, m):
    rows = db.query(
        "SELECT jenis_simpanan, saldo FROM v_bot_simpanan WHERE karyawan_id = %s", (m["id"],)
    )
    lines = [f"Saldo simpanan {m['nama']}"] + [f"- {r['jenis_simpanan'].title()}: {rp(r['saldo'])}" for r in rows]
    if not rows:
        lines.append("Belum ada data simpanan.")
    else:
        lines.append(f"Total: {rp(sum(r['saldo'] for r in rows))}")
    await update.message.reply_text("\n".join(lines))


@member_only
async def belanja(update, m):
    rows = db.query(
        "SELECT nota, metode_pembayaran, total_tagihan, waktu_transaksi FROM v_bot_belanja "
        "WHERE karyawan_id = %s ORDER BY waktu_transaksi DESC LIMIT 10",
        (m["id"],),
    )
    text = "\n".join(
        f"{r['waktu_transaksi']:%d/%m/%y} {r['nota']} {rp(r['total_tagihan'])} ({r['metode_pembayaran']})"
        for r in rows
    )
    await update.message.reply_text("10 belanja terakhir:\n" + (text or "Belum ada transaksi."))


@member_only
async def tagihan(update, m):
    rows = db.query(
        "SELECT nota, total_tagihan, waktu_transaksi FROM v_bot_tagihan "
        "WHERE karyawan_id = %s ORDER BY waktu_transaksi DESC",
        (m["id"],),
    )
    text = "\n".join(f"{r['waktu_transaksi']:%d/%m/%y} {r['nota']} {rp(r['total_tagihan'])}" for r in rows)
    total = rp(sum(r["total_tagihan"] for r in rows))
    await update.message.reply_text(
        "Tagihan potong gaji (60 hari terakhir):\n" + (text + f"\nTotal: {total}" if rows else "Tidak ada tagihan.")
    )


@member_only
async def shu(update, m):
    rows = db.query(
        "SELECT jenis_simpanan, nominal, tgl_transaksi FROM v_bot_shu "
        "WHERE karyawan_id = %s ORDER BY tgl_transaksi DESC LIMIT 5",
        (m["id"],),
    )
    text = "\n".join(f"{r['tgl_transaksi']:%d/%m/%Y} {rp(r['nominal'])} ({r['jenis_simpanan']})" for r in rows)
    await update.message.reply_text("Riwayat SHU:\n" + (text or "Belum ada SHU tercatat."))


@member_only
async def laporan(update, m):
    rows = db.query(
        "SELECT as_of_date, assets, liabilities, equity, income_ytd, expenses_ytd, cash_change_ytd "
        "FROM v_bot_financial_summary LIMIT 1"
    )
    if not rows:
        raise RuntimeError("v_bot_financial_summary tidak menghasilkan baris")
    row = rows[0]
    as_of = row["as_of_date"]
    year = as_of.year if hasattr(as_of, "year") else int(str(as_of)[:4])
    report = (
        "📊 Ringkasan Keuangan Koperasi\n"
        f"Posisi per {as_of:%d/%m/%Y}\n"
        f"Aset: {rp(row['assets'])}\n"
        f"Kewajiban: {rp(row['liabilities'])}\n"
        f"Ekuitas: {rp(row['equity'])}\n\n"
        f"Tahun berjalan {year}\n"
        f"Pendapatan: {rp(row['income_ytd'])}\n"
        f"Beban: {rp(row['expenses_ytd'])}\n"
        f"Surplus/(defisit): {rp(row['income_ytd'] - row['expenses_ytd'])}\n"
        f"Perubahan kas/bank: {rp(row['cash_change_ytd'])}\n\n"
        "Ringkasan agregat dari jurnal yang sudah diposting."
    )
    await update.message.reply_text(report)


async def cari(update, ctx):
    if not auth.get_member(update.effective_chat.id):
        await update.message.reply_text("Belum terverifikasi. Gunakan /start.")
        return
    ctx.user_data["awaiting_product_search"] = True
    await update.message.reply_text("Kirim nama barang atau barcode yang ingin dicari.")


async def cari_barang(update, query):
    query = query.strip()
    if not query:
        await update.message.reply_text("Ketik nama barang atau barcode.")
        return
    if len(query) > 64:
        await update.message.reply_text("Pencarian maksimal 64 karakter.")
        return
    matches = db.query(
        "SELECT barcode, nama, jumlah FROM v_bot_products "
        "WHERE barcode = %s OR LOCATE(%s, nama) > 0 OR LOCATE(%s, barcode) > 0 "
        "ORDER BY CASE WHEN barcode = %s THEN 0 ELSE 1 END, nama LIMIT 10",
        (query, query, query, query),
    )
    if not matches:
        await update.message.reply_text("Barang tidak ditemukan.")
        return
    lines = [
        f"{row['nama']}\nKode: {row['barcode']} | Stok: {row['jumlah']}"
        for row in matches
    ]
    suffix = "\n\nMaksimal 10 hasil." if len(matches) == 10 else ""
    await update.message.reply_text("\n\n".join(lines) + suffix)


async def handle_text(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    text = update.message.text.strip()
    if text == BTN_LOGIN and not auth.get_member(update.effective_chat.id):
        await mulai_login(update, ctx)
        return
    if text == BTN_CANCEL_LOGIN:
        await batal_login(update, ctx)
        return
    if ctx.user_data.get(LOGIN_STATE) == "npk":
        if not text:
            await update.message.reply_text("NPK tidak boleh kosong. Silakan masukkan NPK.")
            return
        ctx.user_data["login_npk"] = text
        ctx.user_data[LOGIN_STATE] = "pin"
        await update.message.reply_text(
            "NPK diterima. Sekarang masukkan PIN Telegram 6 digit.",
            reply_markup=LOGIN_CANCEL_KEYBOARD,
        )
        return
    if ctx.user_data.get(LOGIN_STATE) == "pin":
        npk = ctx.user_data.pop("login_npk", "")
        ctx.user_data.pop(LOGIN_STATE, None)
        chat_id = update.effective_chat.id
        pin_deleted = await delete_sensitive_message(update.message)
        if not text.isascii() or not text.isdigit() or len(text) != 6:
            ctx.user_data[LOGIN_STATE] = "pin"
            ctx.user_data["login_npk"] = npk
            response = "PIN harus terdiri dari 6 angka. Silakan coba lagi atau tekan Batal."
            if not pin_deleted:
                response += "\nPesan PIN tidak dapat dihapus otomatis. Hapus pesan itu dari chat."
            await update.message.reply_text(
                response,
                reply_markup=LOGIN_CANCEL_KEYBOARD,
            )
            return
        if rate_limited(chat_id):
            ctx.user_data.pop(LOGIN_STATE, None)
            response = "Terlalu banyak percobaan. Coba lagi beberapa menit lagi."
            if not pin_deleted:
                response += "\nPesan PIN tidak dapat dihapus otomatis. Hapus pesan itu dari chat."
            await update.message.reply_text(
                response,
                reply_markup=LOGIN_KEYBOARD,
            )
            return
        result = auth.login(chat_id, npk, text)
        log.info("interactive login attempt: result=%s", result)
        if result == "ok":
            ctx.user_data.pop("login_npk", None)
            response = "Berhasil masuk. Pilih menu di bawah."
            if not pin_deleted:
                response += "\nPesan PIN tidak dapat dihapus otomatis. Hapus pesan itu dari chat."
            await update.message.reply_text(response, reply_markup=MENU)
        elif result == "locked":
            ctx.user_data.pop("login_npk", None)
            response = "Akun terkunci sementara karena terlalu banyak salah PIN. Coba lagi nanti."
            if not pin_deleted:
                response += "\nPesan PIN tidak dapat dihapus otomatis. Hapus pesan itu dari chat."
            await update.message.reply_text(
                response,
                reply_markup=LOGIN_KEYBOARD,
            )
        else:
            ctx.user_data["login_npk"] = npk
            ctx.user_data[LOGIN_STATE] = "pin"
            response = "PIN tidak cocok untuk NPK tersebut. Coba masukkan PIN lagi atau tekan Batal."
            if not pin_deleted:
                response += "\nPesan PIN tidak dapat dihapus otomatis. Hapus pesan itu dari chat."
            await update.message.reply_text(
                response,
                reply_markup=LOGIN_CANCEL_KEYBOARD,
            )
        return
    actions = {
        BTN_SALDO: saldo,
        BTN_BELANJA: belanja,
        BTN_TAGIHAN: tagihan,
        BTN_SHU: shu,
        BTN_LAPORAN: laporan,
        BTN_CARI: cari,
    }
    if text == LEGACY_BTN_KELUAR:
        ctx.user_data.pop("awaiting_product_search", None)
        await update.message.reply_text(
            "Tombol Keluar sudah dihapus. Untuk memutus akun, ketik /keluar.", reply_markup=MENU
        )
        return
    if text in actions:
        ctx.user_data.pop("awaiting_product_search", None)
        await actions[text](update, ctx)
        return
    if not auth.get_member(update.effective_chat.id):
        await update.message.reply_text("Belum terverifikasi. Gunakan /start.")
        return
    if ctx.user_data.pop("awaiting_product_search", False):
        try:
            await cari_barang(update, text)
        except Exception:
            log.exception("pencarian barang gagal")
            await update.message.reply_text("Pencarian gagal, coba lagi nanti.")
        return
    await update.message.reply_text("Pilih salah satu tombol menu di bawah.", reply_markup=MENU)


async def keluar(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    auth.unlink(update.effective_chat.id)
    await update.message.reply_text(
        "Akun Telegram ini sudah diputus dari data anggota.", reply_markup=ReplyKeyboardRemove()
    )


async def post_init(app: Application):
    await app.bot.set_my_commands(COMMANDS)


def main():
    if not config.BOT_TOKEN:
        raise SystemExit("TELEGRAM_BOT_TOKEN belum diisi di .env")
    app = Application.builder().token(config.BOT_TOKEN).post_init(post_init).build()
    for name, fn in [
        ("start", start),         ("menu", start), ("masuk", masuk), ("batal", batal_login),
        ("saldo", saldo), ("belanja", belanja),
        ("tagihan", tagihan), ("shu", shu), ("laporan", laporan), ("cari", cari), ("keluar", keluar),
    ]:
        app.add_handler(CommandHandler(name, fn))
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_text))
    app.run_polling()


if __name__ == "__main__":
    main()
