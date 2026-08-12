"""Port of App\\Http\\Controllers\\DayBookController — the daily cash/ledger
report with its 5 report forms. Read-only, so ported as a set of plain
functions operating on raw SQL (kept close to the original queries rather
than rewritten in the ORM, to stay verifiably equivalent).
"""
from django.db import connection


def _dictfetchall(cursor):
    columns = [col[0] for col in cursor.description]
    return [dict(zip(columns, row)) for row in cursor.fetchall()]


def _table_exists(table):
    return table in connection.introspection.table_names()


def _column_exists(table, column):
    with connection.cursor() as cursor:
        cols = {c.name.lower() for c in connection.introspection.get_table_description(cursor, table)}
    return column.lower() in cols


GILEVEL = 1  # DayBookController::resolveLevel() is hard-pinned to 1


def _get_opening_balance():
    with connection.cursor() as cursor:
        cursor.execute("SELECT opbal FROM AccountM WHERE accode = 'CASH'")
        row = cursor.fetchone()
    return float(row[0]) if row and row[0] is not None else 0.0


def _get_sum(op, date):
    with connection.cursor() as cursor:
        cursor.execute(
            f"SELECT COALESCE(SUM(amount), 0) FROM daybook WHERE accode = 'CASH' AND control <= %s AND tdate {op} %s",
            [GILEVEL, date],
        )
        return float(cursor.fetchone()[0] or 0)


def get_cash_balances(date_from, date_to):
    base = _get_opening_balance()
    opbal = base + _get_sum("<", date_from)
    clbal = base + _get_sum("<=", date_to)
    return opbal, clbal


def _sales_range(tdate):
    if not _table_exists("salesm"):
        return ""
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT MIN(billno), MAX(billno) FROM salesm WHERE tdate = %s AND control <= %s", [tdate, GILEVEL]
        )
        mn, mx = cursor.fetchone()
    return f"{mn} - {mx}" if mn else ""


def _purchase_range(tdate):
    if not _table_exists("purchasem"):
        return ""
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT MIN(docno), MAX(docno) FROM purchasem WHERE tdate = %s AND control <= %s", [tdate, GILEVEL]
        )
        mn, mx = cursor.fetchone()
    return f"{mn} - {mx}" if mn else ""


def _general_particular(accode, tdate, vchno):
    vchno = vchno or ""
    with connection.cursor() as cursor:
        cursor.execute(
            """
            SELECT COUNT(DISTINCT db.slno) FROM daybook db
            JOIN daybookpart dbp ON db.slno = dbp.slno
            WHERE db.tdate = %s AND db.accode = %s AND dbp.vchno = %s AND db.control <= %s
            """,
            [tdate, accode, vchno, GILEVEL],
        )
        cnt = cursor.fetchone()[0]

        if cnt == 1:
            cursor.execute(
                """
                SELECT MAX(dbp.particular) FROM daybook db
                JOIN daybookpart dbp ON db.slno = dbp.slno
                WHERE db.tdate = %s AND db.accode = %s AND dbp.vchno = %s AND db.control <= %s
                """,
                [tdate, accode, vchno, GILEVEL],
            )
            return cursor.fetchone()[0] or ""
    return "Day Total"


def _part_description(accode, tdate, vchno):
    if accode == "RS":
        return _sales_range(tdate)
    if accode == "EP":
        return _purchase_range(tdate)
    return _general_particular(accode, tdate, vchno)


def _form4_part_description(accode, tdate, vchno):
    sales_codes = {"RS", "DISC", "TAX", "VA"}
    purch_codes = {"EP", "DISC", "TAX", "VA"}
    if accode in sales_codes and not vchno:
        return "S.No." + _sales_range(tdate)
    if accode in purch_codes and not vchno:
        return "S.No." + _purchase_range(tdate)
    return _general_particular(accode, tdate, vchno)


def get_form1_data(date_from, date_to):
    with connection.cursor() as cursor:
        cursor.execute(
            """
            SELECT DISTINCT
                daybook.tdate, daybook.accode, AccountM.name AS accountm_name, daybookpart.vchno,
                (SELECT SUM(db2.amount) FROM daybook db2
                 JOIN daybookpart dbp2 ON db2.slno = dbp2.slno
                 WHERE db2.tdate = daybook.tdate AND db2.accode = daybook.accode
                   AND dbp2.vchno = daybookpart.vchno AND db2.control <= %s
                ) AS daybook_amount
            FROM AccountM
            JOIN daybook ON AccountM.accode = daybook.accode
            JOIN daybookpart ON daybook.slno = daybookpart.slno
            WHERE daybook.control <= %s AND daybook.tdate >= %s AND daybook.tdate <= %s
              AND AccountM.accode <> 'CASH'
            ORDER BY daybook.tdate ASC
            """,
            [GILEVEL, GILEVEL, date_from, date_to],
        )
        rows = _dictfetchall(cursor)

    for row in rows:
        row["part"] = _part_description(row["accode"], str(row["tdate"]), row.get("vchno"))

    rows.sort(
        key=lambda r: (
            str(r["tdate"]),
            r.get("vchno") or "",
            -(float(r["daybook_amount"] or 0)),
        )
    )
    return rows


def get_form2_data(date_from, date_to):
    with connection.cursor() as cursor:
        cursor.execute(
            """
            SELECT daybook.slno AS daybook_slno, daybook.tdate AS daybook_tdate,
                   daybook.accode AS daybook_accode, daybook.amount AS daybook_amount,
                   AccountM.name AS accountm_name,
                   daybookpart.particular AS daybookpart_particular,
                   daybookpart.vchno AS daybookpart_vchno
            FROM AccountM
            JOIN daybook ON AccountM.accode = daybook.accode
            JOIN daybookpart ON daybook.slno = daybookpart.slno
            WHERE daybook.control <= %s AND daybook.tdate BETWEEN %s AND %s
            ORDER BY daybook.tdate ASC, daybook.slno ASC
            """,
            [GILEVEL, date_from, date_to],
        )
        return _dictfetchall(cursor)


def group_form2_by_slno(rows, with_diff=False):
    groups = {}
    for row in rows:
        slno = row["daybook_slno"]
        g = groups.setdefault(slno, {"rows": [], "db": 0.0, "cr": 0.0, "particular": "", "vchno": ""})
        g["rows"].append(row)
        amt = float(row["daybook_amount"] or 0)
        if amt < 0:
            g["db"] += abs(amt)
        else:
            g["cr"] += amt
        part = row.get("daybookpart_particular") or ""
        if part > g["particular"]:
            g["particular"] = part
        vch = row.get("daybookpart_vchno") or ""
        if vch > g["vchno"]:
            g["vchno"] = vch

    if with_diff:
        groups = {k: v for k, v in groups.items() if round(v["db"], 2) != round(v["cr"], 2)}
    return groups


def get_form3_data(date_from, date_to):
    with connection.cursor() as cursor:
        cursor.execute(
            """
            SELECT daybook.slno AS daybook_slno, daybook.tdate AS daybook_tdate,
                   daybook.accode AS daybook_accode, daybook.amount AS daybook_amount,
                   AccountM.name AS accountm_name,
                   daybookpart.particular AS daybookpart_particular,
                   daybookpart.vchno AS daybookpart_vchno
            FROM AccountM
            JOIN daybook ON AccountM.accode = daybook.accode
            JOIN daybookpart ON daybook.slno = daybookpart.slno
            WHERE daybook.control <= %s AND daybook.tdate >= %s AND daybook.tdate <= %s
            ORDER BY daybook.tdate ASC, daybook.slno ASC
            """,
            [GILEVEL, date_from, date_to],
        )
        return _dictfetchall(cursor)


def get_form4_data(date_from, date_to):
    dopbal, _ = get_cash_balances(date_from, date_to)

    with connection.cursor() as cursor:
        cursor.execute(
            """
            SELECT db.tdate, db.accode, am.name AS acname, dbp.vchno, SUM(db.amount) AS amount
            FROM daybook db
            JOIN daybookpart dbp ON db.slno = dbp.slno
            JOIN AccountM am ON db.accode = am.accode
            WHERE db.tdate >= %s AND db.tdate <= %s AND db.accode <> 'CASH'
            GROUP BY db.tdate, db.accode, am.name, dbp.vchno
            ORDER BY db.tdate, am.name
            """,
            [date_from, date_to],
        )
        cursor_rows = _dictfetchall(cursor)

    result = []
    sno = 0
    prev_date = None

    for crow in cursor_rows:
        dtdate = crow["tdate"]
        saccode = crow["accode"]
        sacname = crow["acname"]
        svchno = crow["vchno"]
        damt = -float(crow["amount"] or 0)

        if prev_date != dtdate:
            sno += 1
            result.append(
                {
                    "tdate": dtdate,
                    "sno": sno,
                    "acname": "By Balance B/D" if dopbal < 0 else "To Balance B/D",
                    "dbamt": abs(dopbal) if dopbal < 0 else 0,
                    "cramt": abs(dopbal) if dopbal >= 0 else 0,
                    "particular": "",
                }
            )
            prev_date = dtdate

        if damt != 0:
            sno += 1
            particular = _form4_part_description(saccode, str(dtdate), svchno)
            result.append(
                {
                    "tdate": dtdate,
                    "sno": sno,
                    "acname": f"By {sacname}" if damt < 0 else f"To {sacname}",
                    "dbamt": abs(damt) if damt < 0 else 0,
                    "cramt": abs(damt) if damt >= 0 else 0,
                    "particular": particular,
                }
            )
            dopbal += damt

    result.sort(key=lambda r: (str(r["tdate"]), r["sno"]))
    return result


def group_form4_by_date(rows):
    groups = {}
    for row in rows:
        dt = row["tdate"]
        g = groups.setdefault(dt, {"rows": [], "totdb": 0.0, "totcr": 0.0})
        g["rows"].append(row)
        g["totdb"] += float(row["dbamt"] or 0)
        g["totcr"] += float(row["cramt"] or 0)
    return groups


def get_form5_data(date_from, date_to):
    gl = GILEVEL

    def fetch(sql, params):
        with connection.cursor() as cursor:
            cursor.execute(sql, params)
            return _dictfetchall(cursor)

    sales = []
    if _table_exists("salesm"):
        sales = fetch(
            """
            SELECT slno, billno, tdate, custcode, custname, billamt, netamt, ramt FROM salesm
            WHERE tdate BETWEEN %s AND %s AND control <= %s AND (sr <> 'R' OR sr IS NULL)
            ORDER BY tdate, slno
            """,
            [date_from, date_to, gl],
        )

    sales_returns = []
    if _table_exists("salesrm"):
        extra = ", pamt" if _column_exists("salesrm", "pamt") else ""
        sales_returns = fetch(
            f"""
            SELECT slno, billno, tdate, custcode, custname, billamt, netamt{extra} FROM salesrm
            WHERE tdate BETWEEN %s AND %s AND control <= %s AND sr = 'R'
            ORDER BY tdate, slno
            """,
            [date_from, date_to, gl],
        )

    purchases = []
    if _table_exists("purchasem"):
        extra = ", pamt" if _column_exists("purchasem", "pamt") else ""
        purchases = fetch(
            f"""
            SELECT slno, docno, tdate, billno, suppcode, name, billamt, netamt{extra} FROM purchasem
            WHERE tdate BETWEEN %s AND %s AND control <= %s AND (pr <> 'R' OR pr IS NULL)
            ORDER BY tdate, slno
            """,
            [date_from, date_to, gl],
        )

    purchase_returns = []
    if _table_exists("purchaserm"):
        extra = ", pamt" if _column_exists("purchaserm", "pamt") else ""
        purchase_returns = fetch(
            f"""
            SELECT slno, docno, tdate, billno, suppcode, name, billamt, netamt{extra} FROM purchaserm
            WHERE tdate BETWEEN %s AND %s AND control <= %s AND pr = 'R'
            ORDER BY tdate, slno
            """,
            [date_from, date_to, gl],
        )

    receipts = fetch(
        """
        SELECT db.slno, db.tdate, db.accode, am.name AS acname, dbp.vchno, dbp.particular, ABS(db.amount) AS amount
        FROM daybook db
        JOIN daybookpart dbp ON db.slno = dbp.slno
        JOIN AccountM am ON db.accode = am.accode
        WHERE db.tdate BETWEEN %s AND %s AND db.control <= %s AND db.accode <> 'CASH' AND db.amount < 0
          AND EXISTS (SELECT 1 FROM daybook db2 WHERE db2.slno = db.slno AND db2.accode = 'CASH' AND db2.amount > 0)
        ORDER BY db.tdate, db.slno
        """,
        [date_from, date_to, gl],
    )

    payments = fetch(
        """
        SELECT db.slno, db.tdate, db.accode, am.name AS acname, dbp.vchno, dbp.particular, ABS(db.amount) AS amount
        FROM daybook db
        JOIN daybookpart dbp ON db.slno = dbp.slno
        JOIN AccountM am ON db.accode = am.accode
        WHERE db.tdate BETWEEN %s AND %s AND db.control <= %s AND db.accode <> 'CASH' AND db.amount > 0
          AND EXISTS (SELECT 1 FROM daybook db2 WHERE db2.slno = db.slno AND db2.accode = 'CASH' AND db2.amount < 0)
        ORDER BY db.tdate, db.slno
        """,
        [date_from, date_to, gl],
    )

    def total(rows, key):
        return sum(float(r.get(key) or 0) for r in rows)

    totals = {
        "sales_count": len(sales), "sales_amt": total(sales, "netamt"),
        "sret_count": len(sales_returns), "sret_amt": total(sales_returns, "netamt"),
        "purch_count": len(purchases), "purch_amt": total(purchases, "netamt"),
        "pret_count": len(purchase_returns), "pret_amt": total(purchase_returns, "netamt"),
        "receipts_count": len(receipts), "receipts_amt": total(receipts, "amount"),
        "payments_count": len(payments), "payments_amt": total(payments, "amount"),
    }

    return {
        "sales": sales, "sales_returns": sales_returns,
        "purchases": purchases, "purchase_returns": purchase_returns,
        "receipts": receipts, "payments": payments, "totals": totals,
    }


def build_report(form_type, date_from, date_to, with_diff):
    rows, groups, form5 = [], {}, None
    opbal = clbal = 0.0

    if form_type == "form1":
        opbal, clbal = get_cash_balances(date_from, date_to)
        rows = get_form1_data(date_from, date_to)
    elif form_type == "form2":
        rows = get_form2_data(date_from, date_to)
        groups = group_form2_by_slno(rows, with_diff)
    elif form_type == "form3":
        rows = get_form3_data(date_from, date_to)
    elif form_type == "form4":
        rows = get_form4_data(date_from, date_to)
        groups = group_form4_by_date(rows)
    elif form_type == "form5":
        opbal, clbal = get_cash_balances(date_from, date_to)
        form5 = get_form5_data(date_from, date_to)

    return {"rows": rows, "groups": groups, "opbal": opbal, "clbal": clbal, "form5": form5}
