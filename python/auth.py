import hmac

import config
import db


def get_member(chat_id: int):
    rows = db.query(
        "SELECT a.id, a.npk, a.nama FROM telegram_links l "
        "JOIN v_bot_anggota a ON a.id = l.karyawan_id "
        "WHERE l.chat_id = %s AND l.revoked = 0",
        (chat_id,),
    )
    return rows[0] if rows else None


def login(chat_id: int, npk: str, pin: str) -> str:
    """Return 'ok', 'locked', atau 'invalid'. Gagal beruntun mengunci NPK sementara."""
    with db.connect() as conn, conn.cursor() as cur:
        cur.execute("SELECT karyawan_id, pin FROM v_bot_login WHERE npk = %s", (npk,))
        row = cur.fetchone()
        if not row:
            return "invalid"
        kid = row["karyawan_id"]
        cur.execute(
            "SELECT fails, locked_until > NOW() AS locked FROM telegram_login_fail "
            "WHERE karyawan_id = %s FOR UPDATE",
            (kid,),
        )
        state = cur.fetchone()
        if state and state["locked"]:
            return "locked"
        if not hmac.compare_digest(str(row["pin"]), pin):
            fails = (state["fails"] if state else 0) + 1
            lock = fails >= config.PIN_MAX_FAILS
            cur.execute(
                "INSERT INTO telegram_login_fail (karyawan_id, fails, locked_until) "
                "VALUES (%s, %s, IF(%s, NOW() + INTERVAL %s MINUTE, NULL)) "
                "ON DUPLICATE KEY UPDATE fails = VALUES(fails), locked_until = VALUES(locked_until)",
                (kid, 0 if lock else fails, lock, config.PIN_LOCK_MINUTES),
            )
            return "locked" if lock else "invalid"
        cur.execute(
            "UPDATE telegram_login_fail SET fails = 0, locked_until = NULL WHERE karyawan_id = %s",
            (kid,),
        )
        cur.execute(
            "INSERT INTO telegram_links (chat_id, karyawan_id, linked_at, revoked) "
            "VALUES (%s, %s, NOW(), 0) "
            "ON DUPLICATE KEY UPDATE karyawan_id = VALUES(karyawan_id), linked_at = NOW(), revoked = 0",
            (chat_id, kid),
        )
        return "ok"


def unlink(chat_id: int):
    with db.connect() as conn, conn.cursor() as cur:
        cur.execute("UPDATE telegram_links SET revoked = 1 WHERE chat_id = %s", (chat_id,))
