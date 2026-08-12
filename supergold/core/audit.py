"""Port of App\\Http\\Controllers\\Concerns\\LogsDelpartAudit — every master
add/edit/delete/rename writes a row to `delpart` for the app's built-in
audit trail. Kept as a plain function so any module view can call it.
"""
import datetime

from django.db import connection


def _table_columns(table):
    with connection.cursor() as cursor:
        return [c.name.lower() for c in connection.introspection.get_table_description(cursor, table)]


def _table_exists(table):
    return table in connection.introspection.table_names()


def log_delpart(request, part, *, utype="E", ttype="M", control=None, slno=None, uid=None, ic=None):
    if not _table_exists("delpart"):
        return

    part = (part or "").strip()
    if not part:
        return

    control = control if control is not None else request.session.get("semi")
    control = control if control is not None else request.session.get("control")
    control = control if control is not None else request.session.get("gilevel")
    try:
        control = int(control)
    except (TypeError, ValueError):
        control = 1

    legacy_user = getattr(request, "legacy_user", None) or {}
    uid = (uid or request.session.get("user_id") or legacy_user.get("code") or "").strip()
    ic = (ic or legacy_user.get("code") or uid).strip()

    now = datetime.datetime.now()
    payload = {
        "tdate": now.date(),
        "part": part[:60],
        "control": control,
        "slno": int(slno) if slno is not None else None,
        "utype": (utype or "E").upper()[:1],
        "ttype": (ttype or "M").upper()[:2],
        "updtdate": now.date(),
        "updttime": now.time(),
        "uid": uid,
        "ic": ic,
    }

    cols = set(_table_columns("delpart"))
    filtered = {k: v for k, v in payload.items() if v is not None and k in cols}
    if not filtered:
        return

    columns = ", ".join(f"`{c}`" for c in filtered)
    placeholders = ", ".join(["%s"] * len(filtered))
    with connection.cursor() as cursor:
        cursor.execute(f"INSERT INTO `delpart` ({columns}) VALUES ({placeholders})", list(filtered.values()))
