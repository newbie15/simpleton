from contextlib import contextmanager

import pymysql
from pymysql.cursors import DictCursor

import config


@contextmanager
def connect(params=None):
    conn = pymysql.connect(
        **(params or config.DB), charset="utf8mb4", cursorclass=DictCursor, autocommit=False
    )
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def query(sql, args=()):
    with connect() as conn, conn.cursor() as cur:
        cur.execute(sql, args)
        return cur.fetchall()
