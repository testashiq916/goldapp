"""Port of App\\Http\\Controllers\\AccountLedgerController — the per-account
statement (opening balance, transactions in a date range, running balance).

Scoped down from the original: ported faithfully are loadAccount,
openingBalance, and the core of ledgerRows (join daybook+daybookpart+the
opposite account name, search/amount filters, running balance). Not
ported: the opposite-account multi-leg splitting for combined vouchers
(oppositeAccountSplitsForRows), document drill-down URLs, orphan-posting
detection/clearing, and the missing-sales-ledger auto-repair routine —
those are report/maintenance conveniences layered on top of this core
statement rather than the statement itself.
"""
from django.db import connection


def _dictfetchall(cursor):
    columns = [col[0] for col in cursor.description]
    return [dict(zip(columns, row)) for row in cursor.fetchall()]


def _table_exists(table):
    return table in connection.introspection.table_names()


def load_account(code, gilevel):
    if not _table_exists("AccountM"):
        return None
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT TRIM(accode), TRIM(name), TRIM(actype2), opbal, opbalb FROM AccountM WHERE TRIM(accode) = %s",
            [code],
        )
        row = cursor.fetchone()
    if not row:
        return None
    accode, name, actype2, opbal, opbalb = row
    return {
        "accode": accode,
        "name": name,
        "actype2": actype2 or "",
        "opbal": float((opbal if gilevel == 1 else opbalb) or 0),
    }


def opening_balance(code, date_from, gilevel, account):
    total = 0.0
    if _table_exists("daybook"):
        with connection.cursor() as cursor:
            cursor.execute(
                """
                SELECT COALESCE(SUM(daybook.amount), 0) FROM daybook
                WHERE TRIM(daybook.accode) = %s AND daybook.tdate < %s AND daybook.control <= %s
                """,
                [code, date_from, gilevel],
            )
            total = float(cursor.fetchone()[0] or 0)
    return account["opbal"] + total


def ledger_rows(code, date_from, date_to, search, amount_search, opening_bal):
    if not _table_exists("daybook") or not _table_exists("daybookpart"):
        return []

    sql = """
        SELECT daybook.slno, daybook.tdate, TRIM(daybook.accode) AS accode, daybook.amount,
               TRIM(COALESCE(daybookpart.vchno, '')) AS vchno,
               TRIM(COALESCE(daybookpart.staff, '')) AS staff,
               TRIM(COALESCE(daybookpart.particular, '')) AS particular,
               TRIM(COALESCE(daybook.opaccode, '')) AS opaccode,
               TRIM(COALESCE(oth.name, '')) AS othacname
        FROM daybook
        JOIN daybookpart ON daybook.slno = daybookpart.slno
        LEFT JOIN AccountM oth ON TRIM(daybook.opaccode) = TRIM(oth.accode)
        WHERE TRIM(daybook.accode) = %s AND daybook.tdate BETWEEN %s AND %s
    """
    params = [code, date_from, date_to]

    if search:
        like = f"%{search.upper()}%"
        sql += """ AND (UPPER(TRIM(COALESCE(daybookpart.particular, ''))) LIKE %s
                    OR UPPER(TRIM(COALESCE(oth.name, ''))) LIKE %s
                    OR UPPER(TRIM(COALESCE(daybookpart.vchno, ''))) LIKE %s)"""
        params += [like, like, like]

    if amount_search:
        try:
            amt = float(amount_search)
            sql += " AND ABS(COALESCE(daybook.amount, 0)) = %s"
            params.append(amt)
        except ValueError:
            pass

    sql += " ORDER BY daybook.tdate, daybook.slno"

    with connection.cursor() as cursor:
        cursor.execute(sql, params)
        rows = _dictfetchall(cursor)

    running = opening_bal
    result = []
    for row in rows:
        amount = float(row["amount"] or 0)
        part = (row["particular"] or "").strip()
        staff = (row["staff"] or "").strip()
        if staff:
            part += (" ->Staff-> " if part else "Staff-> ") + staff

        running += amount
        result.append({
            "slno": row["slno"],
            "date": row["tdate"],
            "vchno": row["vchno"],
            "othacname": row["othacname"],
            "part": part,
            "debit": abs(amount) if amount < 0 else 0.0,
            "credit": amount if amount > 0 else 0.0,
            "running_balance": running,
            "running_side": "Dr" if running < 0 else "Cr",
        })

    return result


def build_ledger(code, date_from, date_to, gilevel, search="", amount_search=""):
    account = load_account(code, gilevel)
    if not account:
        return None

    opbal = opening_balance(code, date_from, gilevel, account)
    rows = ledger_rows(code, date_from, date_to, search, amount_search, opbal)

    totals = {"debit": sum(r["debit"] for r in rows), "credit": sum(r["credit"] for r in rows)}
    closing = rows[-1]["running_balance"] if rows else opbal

    return {
        "account": account,
        "rows": rows,
        "totals": totals,
        "opening_balance": opbal,
        "closing_balance": closing,
    }


def search_accounts(query, limit=30):
    if not _table_exists("AccountM"):
        return []
    like = f"%{query}%"
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT TRIM(accode), TRIM(name) FROM AccountM WHERE accode LIKE %s OR name LIKE %s ORDER BY name LIMIT %s",
            [like, like, limit],
        )
        return [{"code": c, "name": n} for c, n in cursor.fetchall()]
