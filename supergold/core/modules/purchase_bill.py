"""Port of App\\Http\\Controllers\\PurchaseBillController's core transaction
logic — saving a purchase bill: item lines, stock updates, exchange (old
gold) lines, and the full daybook ledger posting. This is the financially
critical part of the controller; the picker/search/reprint UI helpers are
covered by the generic module browser instead.

Not ported: multi-branch "secondary database sync" (SecondaryDatabaseSync)
and bill-type-wise prefix overrides — both are optional/advanced features
layered on top of the core save path ported here.
"""
import datetime
import re

from django.db import connection, transaction

GILEVEL = 1


def _table_exists(table):
    return table in connection.introspection.table_names()


def _table_columns(table):
    if not _table_exists(table):
        return set()
    with connection.cursor() as cursor:
        return {c.name.lower() for c in connection.introspection.get_table_description(cursor, table)}


def _column_max_length(table, column):
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS "
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            [table, column],
        )
        row = cursor.fetchone()
    return int(row[0]) if row and row[0] else 0


def clamp_to_column_lengths(table, data, columns):
    for col in columns:
        value = data.get(col)
        if not isinstance(value, str) or value == "":
            continue
        limit = _column_max_length(table, col)
        if limit and len(value) > limit:
            data[col] = value[:limit]


def _filtered(table, data):
    cols = _table_columns(table)
    return {k: v for k, v in data.items() if k in cols}


def _insert(table, data):
    data = _filtered(table, data)
    if not data:
        return
    cols = ", ".join(f"`{c}`" for c in data)
    placeholders = ", ".join(["%s"] * len(data))
    with connection.cursor() as cursor:
        cursor.execute(f"INSERT INTO `{table}` ({cols}) VALUES ({placeholders})", list(data.values()))


def parse_date(raw):
    raw = (raw or "").strip()
    if raw in ("", "00/00/0000", "00-00-0000"):
        return None
    m = re.match(r"^(\d{2})/(\d{2})/(\d{4})$", raw)
    if m:
        if m.group(1) == "00" or m.group(2) == "00":
            return None
        return f"{m.group(3)}-{m.group(2)}-{m.group(1)}"
    m = re.match(r"^(\d{2})-(\d{2})-(\d{4})$", raw)
    if m:
        if m.group(1) == "00" or m.group(2) == "00":
            return None
        return f"{m.group(3)}-{m.group(2)}-{m.group(1)}"
    if re.match(r"^\d{4}-\d{2}-\d{2}$", raw):
        return raw
    return None


def gen_int(code):
    if not _table_exists("generali"):
        return 0
    with connection.cursor() as cursor:
        cursor.execute("SELECT cvalue FROM generali WHERE TRIM(code) = %s", [code])
        row = cursor.fetchone()
    return int(row[0]) if row and row[0] is not None else 0


def gen_dec(code):
    if not _table_exists("generald"):
        return 0.0
    with connection.cursor() as cursor:
        cursor.execute("SELECT cvalue FROM generald WHERE code = %s", [code])
        row = cursor.fetchone()
    return float(row[0]) if row and row[0] is not None else 0.0


def gen_str(code):
    if not _table_exists("generals"):
        return ""
    with connection.cursor() as cursor:
        cursor.execute("SELECT cvalue FROM generals WHERE code = %s", [code])
        row = cursor.fetchone()
    return (row[0] or "").strip() if row else ""


def increment_gen_int(code):
    """Ported: keeps the shared SERIALNO/PURCHASEB counter ahead of every
    table's max slno, so legacy DBs that drift never collide."""
    if not _table_exists("generali"):
        return 1
    current = gen_int(code)

    max_used = 0
    tables = (
        ["salesm", "salesrm", "purchasem", "purchaserm", "daybook", "daybookpart", "orderm", "smithm", "refinerym", "repairm"]
        if code == "SERIALNO"
        else ["purchasem", "purchaserm"]
    )
    for table in tables:
        if "slno" in _table_columns(table):
            with connection.cursor() as cursor:
                cursor.execute(f"SELECT MAX(slno) FROM `{table}`")
                row = cursor.fetchone()
            if row and row[0] is not None:
                max_used = max(max_used, int(row[0]))

    next_val = max(current, max_used) + 1
    with connection.cursor() as cursor:
        cursor.execute("UPDATE generali SET cvalue = %s WHERE TRIM(code) = %s", [next_val, code])
        if cursor.rowcount == 0:
            cursor.execute("INSERT INTO generali (code, cvalue) VALUES (%s, %s)", [code, next_val])
    return next_val


def purchase_bill_number_length():
    try:
        length = int(gen_str("PBLEN") or "0")
    except ValueError:
        length = 0
    return length if length > 0 else 4


def next_unique_purchase_doc_no(prefix, start):
    prefix = (prefix or "").strip()
    number = max(1, start)
    length = purchase_bill_number_length()

    while number < 1_000_000_000:
        doc_no = f"{prefix}{str(number).zfill(length)}"
        with connection.cursor() as cursor:
            cursor.execute(
                "SELECT 1 FROM purchasem WHERE pr = 'P' AND UPPER(TRIM(docno)) = %s LIMIT 1",
                [doc_no.upper()],
            )
            exists = cursor.fetchone() is not None
        if not exists:
            return doc_no
        number += 1
    return f"{prefix}{str(number).zfill(length)}"


def generate_bill_number(bill_type_code=""):
    next_val = increment_gen_int("PURCHASEB")
    prefix = gen_str("PBPREF") or "PL/"
    return next_unique_purchase_doc_no(prefix, next_val)


def calc_totals(data):
    bill_total = float(data.get("bill_total", 0))
    exch_amt = float(data.get("exchange_amt", 0))
    disc_perc = float(data.get("disc_perc", 0))
    discount = float(data.get("discount", 0))
    round_amt = float(data.get("round", 0))
    tax_perc = float(data.get("tax_perc", 0))
    hmc = float(data.get("hmc", 0))
    tcs_perc = float(data.get("tcs_perc", 0))
    paid_amt = float(data.get("paid_amt", 0))
    auto_paid = bool(data.get("auto_paid"))
    ob = float(data.get("ob", 0))
    others = float(data.get("others", 0))
    tax_ext = bool(data.get("external"))
    tax_deduct = bool(data.get("tax_deduct_bamt"))
    tax_on_mc_only = bool(data.get("tax_on_mc"))

    if disc_perc > 0 and discount == 0.0:
        discount = round(bill_total * disc_perc / 100, 2)

    net_total = bill_total - discount - exch_amt + hmc

    if tax_ext:
        tax_amt = 0.0
        cess_amt = 0.0
    else:
        tax_base = hmc if tax_on_mc_only else net_total
        tax_amt = round(tax_base * tax_perc / 100, 2)
        cess_amt = 0.0

    if tax_deduct:
        net_total -= tax_amt + cess_amt
    else:
        net_total += tax_amt + cess_amt

    tcs_amt = round(net_total * tcs_perc / 100) if tcs_perc > 0 else 0.0
    net_total += tcs_amt
    net_total += round_amt

    if auto_paid:
        paid_amt = net_total + others

    balance = net_total + others - paid_amt
    cb = ob + balance

    return {
        "bill_total": round(bill_total, 2),
        "exchange_amt": round(exch_amt, 2),
        "discount": round(discount, 2),
        "round": round(round_amt, 2),
        "tax_amt": tax_amt,
        "cess": cess_amt,
        "tcs_amt": tcs_amt,
        "paid_amt": round(paid_amt, 2),
        "net_total": round(net_total, 2),
        "balance": round(balance, 2),
        "cb": round(cb, 2),
    }


def adjust_item_stock(code, qty, wgt, stwgt, stktype, direction):
    if not _table_exists("items"):
        return
    qty_col = "qty" if GILEVEL == 1 else "qtyb"
    wgt_col = "weight" if GILEVEL == 1 else "weightb"
    sign = "+" if direction == "+" else "-"

    with connection.cursor() as cursor:
        cursor.execute(
            f"UPDATE items SET {qty_col} = {qty_col} {sign} %s, {wgt_col} = {wgt_col} {sign} %s WHERE code = %s",
            [qty, wgt, code],
        )

    if stktype and _table_exists("itemsstk"):
        with connection.cursor() as cursor:
            cursor.execute("SELECT 1 FROM itemsstk WHERE code = %s AND stktype = %s", [code, stktype])
            exists = cursor.fetchone() is not None
        if exists:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"UPDATE itemsstk SET weight = weight {sign} %s, qty = qty {sign} %s WHERE code = %s AND stktype = %s",
                    [wgt, qty, code, stktype],
                )
        elif direction == "+":
            _insert("itemsstk", {"code": code, "stktype": stktype, "qty": qty, "weight": wgt})


def reverse_edit_stock(slno):
    if _table_exists("purchased"):
        with connection.cursor() as cursor:
            cursor.execute("SELECT code, qty, weight, stwgt, stktype FROM purchased WHERE slno = %s", [slno])
            rows = cursor.fetchall()
        for code, qty, weight, stwgt, stktype in rows:
            adjust_item_stock(code, int(qty or 0), float(weight or 0), float(stwgt or 0), (stktype or "").strip(), "-")

    if _table_exists("purchaserd"):
        with connection.cursor() as cursor:
            cursor.execute("SELECT code, qty, weight, stwgt, stktype FROM purchaserd WHERE slno = %s", [slno])
            rows = cursor.fetchall()
        for code, qty, weight, stwgt, stktype in rows:
            adjust_item_stock(code, int(qty or 0), float(weight or 0), float(stwgt or 0), (stktype or "").strip(), "+")


def write_purchase_daybook(
    lslno, bill_date, supp_code, supp_name, docno, supp_bill_no,
    bill_total, net_total, exch_amt, paid_amt, chq_amt, chq_bank, chq_pdc,
    discount, tax_amt, cess_amt, hmc, tcs_amt, round_amt, others,
    interstate, tax_ext, control,
):
    """Entry order matches the PowerBuilder-derived original exactly (see
    PurchaseBillController::writePurchaseDaybook docblock)."""
    if not _table_exists("daybook"):
        return

    db_cols = _table_columns("daybook")
    disc_ac = gen_str("PDISCAC") or "DISC"
    ep_ac, cash_ac, cnp_ac, add_ac = "EP", "CASH", "CNP", "ADD"
    hmc_ac, tcs_ac, sgst_ac, cgst_ac, igst_ac, ptaxexp_ac, round_ac = (
        "HMC", "TCSAC", "SGST", "CGST", "IGST", "PTAXEXP", "ROUND",
    )

    sno = 0

    def ins(accode, amount, opaccode):
        nonlocal sno
        if round(amount, 2) == 0.0:
            return
        sno += 1
        row = {}
        if "slno" in db_cols: row["slno"] = lslno
        if "sno" in db_cols: row["sno"] = sno
        if "tdate" in db_cols: row["tdate"] = bill_date
        if "ddate" in db_cols: row["ddate"] = bill_date
        if "accode" in db_cols: row["accode"] = accode[:20]
        if "amount" in db_cols: row["amount"] = round(amount, 2)
        if "control" in db_cols: row["control"] = control
        if "opaccode" in db_cols: row["opaccode"] = opaccode[:20]
        if "vtype" in db_cols: row["vtype"] = "PL"
        if "vno" in db_cols: row["vno"] = lslno
        if "userid" in db_cols: row["userid"] = ""
        _insert("daybook", row)

    if _table_exists("daybookpart"):
        dp_cols = _table_columns("daybookpart")
        particular = f"By Purchase - {docno} - {supp_bill_no} From {supp_name}"
        dp_row = {}
        if "slno" in dp_cols: dp_row["slno"] = lslno
        if "tdate" in dp_cols: dp_row["tdate"] = bill_date
        if "particular" in dp_cols: dp_row["particular"] = particular[:200]
        if "vchno" in dp_cols: dp_row["vchno"] = lslno
        if "control" in dp_cols: dp_row["control"] = control
        if "vtype" in dp_cols: dp_row["vtype"] = "PL"
        if dp_row:
            _insert("daybookpart", dp_row)

    dacamt = net_total + others
    cash_paid = paid_amt - chq_amt

    if cash_paid > 0:
        ins(cash_ac, cash_paid, ep_ac)
    if chq_amt > 0:
        cb_ac = cnp_ac if chq_pdc == "Y" else (chq_bank or cash_ac)
        ins(cb_ac, chq_amt, ep_ac)
    if exch_amt > 0:
        ins(ep_ac, exch_amt, ep_ac)
    if dacamt != 0.0:
        ins(supp_code or ep_ac, dacamt, ep_ac)
    if paid_amt > 0:
        ins(supp_code or ep_ac, -paid_amt, ep_ac)
    if others > 0:
        ins(add_ac, -others, ep_ac)
    if hmc > 0:
        ins(hmc_ac, -hmc, ep_ac)
    if tcs_amt > 0:
        ins(tcs_ac, -tcs_amt, ep_ac)
    if discount > 0:
        ins(disc_ac, discount, ep_ac)

    total_tax = tax_amt + cess_amt
    if total_tax > 0:
        if tax_ext:
            ins(ptaxexp_ac, -total_tax, ep_ac)
        elif interstate:
            ins(igst_ac, -total_tax, ep_ac)
        else:
            half = round(total_tax / 2, 2)
            ins(sgst_ac, -half, ep_ac)
            ins(cgst_ac, -half, ep_ac)

    if bill_total > 0:
        ep_op_ac = ep_ac
        if cash_paid > 0:
            ep_op_ac = cash_ac
        elif chq_amt > 0:
            ep_op_ac = chq_bank or cash_ac
        elif supp_code:
            ep_op_ac = supp_code
        ins(ep_ac, -bill_total, ep_op_ac)

    if round_amt != 0.0:
        ins(round_ac, -round_amt, ep_ac)

    with connection.cursor() as cursor:
        cursor.execute("SELECT COALESCE(SUM(amount), 0) FROM daybook WHERE slno = %s", [lslno])
        total_sum = round(float(cursor.fetchone()[0] or 0), 2)
    if total_sum != 0.0:
        sno += 1
        row = {}
        if "slno" in db_cols: row["slno"] = lslno
        if "sno" in db_cols: row["sno"] = sno
        if "tdate" in db_cols: row["tdate"] = bill_date
        if "accode" in db_cols: row["accode"] = round_ac
        if "amount" in db_cols: row["amount"] = -total_sum
        if "control" in db_cols: row["control"] = control
        if "opaccode" in db_cols: row["opaccode"] = ep_ac
        if "vtype" in db_cols: row["vtype"] = "PL"
        if "vno" in db_cols: row["vno"] = lslno
        _insert("daybook", row)


class PurchaseBillError(Exception):
    def __init__(self, message, status=422):
        super().__init__(message)
        self.message = message
        self.status = status


def _item_name(code):
    with connection.cursor() as cursor:
        cursor.execute("SELECT name FROM items WHERE code = %s", [code])
        row = cursor.fetchone()
    return (row[0] or "").strip() if row else ""


def _known_item_codes(codes):
    if not codes or not _table_exists("items"):
        return set()
    placeholders = ", ".join(["%s"] * len(codes))
    with connection.cursor() as cursor:
        cursor.execute(f"SELECT code FROM items WHERE code IN ({placeholders})", list(codes))
        return {(r[0] or "").strip().upper() for r in cursor.fetchall()}


@transaction.atomic
def save_purchase_bill(payload, user_code):
    """payload is a dict mirroring the JS form POST body from
    PurchaseBillController::save(). Returns {'doc_no', 'slno'} on success or
    raises PurchaseBillError with a user-facing message."""
    gsincharge = user_code

    mode = (payload.get("mode") or "bill").strip()
    posted_slno = int(payload.get("slno") or 0)
    doc_no = (payload.get("doc_no") or "").strip()
    supp_bill_no = (payload.get("supp_bill_no") or "").strip()
    bill_date = parse_date(payload.get("bill_date")) or datetime.date.today().isoformat()
    supp_code = (payload.get("sup_code") or "").strip()
    supp_name = (payload.get("sup_name") or "").strip()
    addr = (payload.get("address") or "").strip()
    mobile = (payload.get("mobile") or "").strip()
    pan = (payload.get("pan") or "").strip()
    state_code = (payload.get("state_code") or "").strip()
    sm_code = (payload.get("sm_code") or "").strip()
    counter = (payload.get("counter") or "").strip()
    bill_type = (payload.get("btype") or "").strip()
    note = (payload.get("note") or "").strip()
    due_date = parse_date(payload.get("due_date"))
    chq_bank = (payload.get("chq_bank") or "").strip()
    chq_no = (payload.get("chq_no") or "").strip()
    chq_date = parse_date(payload.get("chq_date"))
    chq_pdc = "Y" if str(payload.get("chq_pdc", "N")).upper() == "Y" else "N"
    interstate = str(payload.get("interstate", "N")).upper() == "Y"
    tax_ext = str(payload.get("tax_external", "N")).upper() == "Y"
    tax_on_mc_only = str(payload.get("tax_on_mc", "N")).upper() == "Y"
    manual_bno = bool(payload.get("manual_bill_no"))

    gold_rate = float(payload.get("gold_rate") or gen_dec("GRATE"))
    tax_perc = float(payload.get("tax_perc") or 0)
    disc_perc = float(payload.get("disc_perc") or 0)
    discount = float(payload.get("discount") or 0)
    round_amt = float(payload.get("round") or 0)
    hmc = float(payload.get("hmc") or 0)
    tcs_perc = float(payload.get("tcs_perc") or 0)
    chq_amt = float(payload.get("chq_amt") or 0)
    paid_amt = float(payload.get("paid_amt") or 0)
    ob = float(payload.get("ob") or 0)
    others = float(payload.get("others") or 0)

    items = payload.get("items") or []
    exch_items = payload.get("exchange_items") or []

    if not sm_code:
        raise PurchaseBillError("Salesman is required.")

    candidate_codes = {
        (i.get("code") or i.get("item_code") or "").strip().upper() for i in items
    } - {""}
    known_codes = _known_item_codes(list(candidate_codes))

    valid_items = []
    for idx, item in enumerate(items):
        scode = (item.get("code") or item.get("item_code") or "").strip().upper()
        sname = str(item.get("name") or "").strip()
        dwgt = float(item.get("weight") or 0)
        iqty = int(item.get("qty") or 0)
        damt = float(item.get("amount") or 0)

        if not scode and (sname or iqty > 0 or dwgt > 0 or damt > 0):
            raise PurchaseBillError(f"Item code is required for row {idx + 1}" + (f" ({sname})" if sname else ""))
        if not scode:
            continue
        if scode not in known_codes:
            raise PurchaseBillError(f"Item code '{scode}' (row {idx + 1}) is not in the item master. Pick a valid item from the lookup.")
        if iqty <= 0 and dwgt <= 0 and damt <= 0:
            continue
        if float(item.get("rate") or 0) <= 0 and damt <= 0:
            raise PurchaseBillError(f"Check Rate for {scode}")

        item = {**item, "code": scode, "item_code": scode}
        valid_items.append(item)

    if not valid_items:
        raise PurchaseBillError("No valid items to save")

    bill_total = sum(float(i.get("amount") or 0) for i in valid_items)

    exch_candidates = {
        (e.get("code") or e.get("item_code") or "").strip().upper() for e in exch_items
    } - {""}
    known_exch_codes = _known_item_codes(list(exch_candidates))
    for idx, ei in enumerate(exch_items):
        ecode = (ei.get("code") or ei.get("item_code") or "").strip().upper()
        ename = str(ei.get("name") or "").strip()
        eqty = int(ei.get("qty") or 0)
        ewgt = float(ei.get("weight") or 0)
        eamt = float(ei.get("amount") or 0)
        if not ecode and (ename or eqty > 0 or ewgt > 0 or eamt > 0):
            raise PurchaseBillError(f"Item code is required for exchange row {idx + 1}" + (f" ({ename})" if ename else ""))
        if ecode and ecode not in known_exch_codes:
            raise PurchaseBillError(f"Exchange item code '{ecode}' (row {idx + 1}) is not in the item master.")

    exch_amt_calc = sum(float(e.get("amount") or 0) for e in exch_items)

    totals = calc_totals({
        "bill_total": bill_total, "exchange_amt": exch_amt_calc, "tax_perc": tax_perc,
        "disc_perc": disc_perc, "discount": discount, "round": round_amt, "hmc": hmc,
        "tcs_perc": tcs_perc, "paid_amt": paid_amt, "ob": ob, "external": tax_ext,
        "tax_on_mc": tax_on_mc_only, "others": others,
    })

    exch_amt = totals["exchange_amt"]
    tax_amt = totals["tax_amt"]
    cess_amt = totals["cess"]
    tcs_amt = totals["tcs_amt"]
    net_total = totals["net_total"]
    balance = totals["balance"]

    icontrol = 1
    status = 3 if balance == 0 else (2 if paid_amt > 0 else 1)
    ttime = datetime.datetime.now().time().isoformat(timespec="seconds")

    sgst = cgst = igst = 0.0
    if not tax_ext:
        if interstate:
            igst = tax_amt
        else:
            sgst = round(tax_amt / 2, 2)
            cgst = round(tax_amt / 2, 2)

    existing_slno = 0
    if mode == "edit" or posted_slno > 0:
        existing = None
        if posted_slno > 0:
            with connection.cursor() as cursor:
                cursor.execute("SELECT slno, docno FROM purchasem WHERE slno = %s AND pr = 'P'", [posted_slno])
                existing = cursor.fetchone()
        if not existing and doc_no:
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT slno, docno FROM purchasem WHERE UPPER(TRIM(docno)) = %s AND pr = 'P'",
                    [doc_no.upper()],
                )
                existing = cursor.fetchone()
        if existing:
            existing_slno = int(existing[0])
            if not doc_no:
                doc_no = (existing[1] or "").strip()
            reverse_edit_stock(existing_slno)

    if existing_slno > 0:
        lslno = existing_slno
        docno = doc_no
    else:
        lslno = increment_gen_int("SERIALNO")
        docno = doc_no if (manual_bno and doc_no) else generate_bill_number(bill_type)

    purchasem_all = {
        "slno": lslno, "docno": docno, "billno": supp_bill_no, "suppcode": supp_code,
        "name": supp_name, "billamt": bill_total, "eamt": exch_amt, "pamt": paid_amt,
        "addamt": others, "status": status, "pr": "P", "control": icontrol, "tdate": bill_date,
        "ttime": ttime, "duedate": due_date, "rate": gold_rate, "smcode": sm_code,
        "round": round_amt, "taxamt": tax_amt, "taxperc": tax_perc, "netamt": net_total + others,
        "ob": ob, "astamt": cess_amt, "ic": gsincharge, "taxexternal": "Y" if tax_ext else "N",
        "billtype": bill_type, "discperc": disc_perc, "discount": discount, "addr": addr,
        "note": note, "exchslno": 0, "fr": "N", "chqbank": chq_bank, "chqamt": chq_amt,
        "chqno": chq_no, "chqdate": chq_date, "chqpdc": chq_pdc, "pan": pan,
        "statecode": state_code, "sgst": sgst, "cgst": cgst, "igst": igst, "mobile": mobile,
        "counter": counter, "cst": "Y" if interstate else "N", "tcsperc": tcs_perc,
        "tcsamt": tcs_amt, "hmc": hmc, "taxonmconly": "Y" if tax_on_mc_only else "N",
    }
    purchasem_data = _filtered("purchasem", purchasem_all)
    clamp_to_column_lengths("purchasem", purchasem_data, ["name", "addr", "note", "mobile", "pan", "chqbank", "chqno", "statecode", "counter"])

    with connection.cursor() as cursor:
        if existing_slno > 0:
            set_clause = ", ".join(f"`{k}` = %s" for k in purchasem_data)
            cursor.execute(f"UPDATE purchasem SET {set_clause} WHERE slno = %s", list(purchasem_data.values()) + [lslno])
            cursor.execute("DELETE FROM purchased WHERE slno = %s", [lslno])
            cursor.execute("DELETE FROM purchaserm WHERE slno = %s", [lslno])
            cursor.execute("DELETE FROM purchaserd WHERE slno = %s", [lslno])
        else:
            _insert("purchasem", purchasem_data)

    sno = 0
    for item in valid_items:
        sno += 1
        scode = (item.get("code") or "").strip().upper()
        iqty = int(item.get("qty") or 0)
        dwgt = float(item.get("weight") or 0)
        dlesswgt = float(item.get("lesswgt") or item.get("less_wgt") or 0)
        dlessp = float(item.get("lessperc") or item.get("less_perc") or 0)
        dstwgt = float(item.get("stwgt") or item.get("stone_wgt") or 0)
        dstprice = float(item.get("stprice") or item.get("stone_price") or 0)
        drate = float(item.get("rate") or 0)
        damount = float(item.get("amount") or 0)
        dmud = float(item.get("mud") or 0)
        dmc = float(item.get("mcharge") or 0)
        dround = float(item.get("round") or 0)
        dtouch = float(item.get("touch") or 0)
        dstktouch = float(item.get("stktouch") or 0)
        sstktype = str(item.get("stktype") or "").strip()
        siqtype = str(item.get("purity") or item.get("iqtype") or "").strip()
        smark = str(item.get("mark") or "").strip()
        sname = str(item.get("name") or item.get("item_name") or "").strip()
        sbatch = str(item.get("batch") or "").strip()

        dcost = round(damount / dwgt, 2) if dwgt > 0 else 0

        if sname.strip() == _item_name(scode).strip():
            sname = ""

        _insert("purchased", {
            "slno": lslno, "code": scode, "qty": iqty, "weight": dwgt, "rate": drate,
            "lesswgt": dlesswgt, "lessperc": dlessp, "amount": damount, "cost": dcost,
            "stwgt": dstwgt, "stprice": dstprice, "mud": dmud, "name": sname, "sno": sno,
            "mark": smark, "stktype": sstktype, "iqtype": siqtype, "mcharge": dmc,
            "round": dround, "stktouch": dstktouch, "touch": dtouch, "batch": sbatch, "fr": 0,
        })

        adjust_item_stock(scode, iqty, dwgt, dstwgt, sstktype, "+")

    if exch_amt > 0 and exch_items:
        _insert("purchaserm", {
            "slno": lslno, "docno": docno, "billno": supp_bill_no, "suppcode": supp_code,
            "name": supp_name, "billamt": exch_amt, "ramt": exch_amt, "addamt": 0, "lessamt": 0,
            "status": 3, "pr": "E", "control": icontrol, "tdate": bill_date, "ttime": ttime,
            "rate": gold_rate, "smcode": sm_code, "round": 0, "netamt": exch_amt, "ic": gsincharge,
        })

        esno = 0
        for ei in exch_items:
            esc = (ei.get("code") or ei.get("item_code") or "").strip().upper()
            if not esc:
                continue
            esno += 1
            eqty = int(ei.get("qty") or 0)
            ewgt = round(float(ei.get("weight") or 0), 3)
            eless = float(ei.get("lesswgt") or ei.get("less_wgt") or 0)
            elessp = float(ei.get("lessperc") or ei.get("less_perc") or 0)
            estwgt = float(ei.get("stwgt") or ei.get("stone_wgt") or 0)
            estprice = float(ei.get("stprice") or ei.get("stone_price") or 0)
            erate = float(ei.get("rate") or 0)
            eamt = round(float(ei.get("amount") or 0), 2)
            emud = float(ei.get("mud") or 0)
            esstktype = str(ei.get("stktype") or "").strip()
            esname = str(ei.get("name") or ei.get("item_name") or "").strip()
            ecost = float(ei.get("cost") or 0)

            if esname.strip() == _item_name(esc).strip():
                esname = ""

            _insert("purchaserd", {
                "slno": lslno, "code": esc, "qty": eqty, "weight": ewgt, "lesswgt": eless,
                "lessperc": elessp, "rate": erate, "amount": eamt, "cost": ecost,
                "stwgt": estwgt, "stprice": estprice, "name": esname, "sno": esno, "mud": emud,
                "mark": "", "stktype": esstktype, "stktouch": 0,
            })

            adjust_item_stock(esc, eqty, ewgt, estwgt, esstktype, "-")

    with connection.cursor() as cursor:
        if _table_exists("daybook"):
            cursor.execute("DELETE FROM daybook WHERE slno = %s", [lslno])
        if _table_exists("daybookpart"):
            cursor.execute("DELETE FROM daybookpart WHERE slno = %s", [lslno])

    write_purchase_daybook(
        lslno, bill_date, supp_code, supp_name, docno, supp_bill_no,
        bill_total, net_total, exch_amt, paid_amt, chq_amt, chq_bank, chq_pdc,
        totals["discount"], tax_amt, cess_amt, hmc, tcs_amt, round_amt, others,
        interstate, tax_ext, icontrol,
    )

    return {"doc_no": docno, "slno": lslno, "existing": existing_slno > 0}
