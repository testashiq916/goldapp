"""Business rules ported from App\\Http\\Controllers\\ItemMasterController
that generic CRUD (core/views.py module_list/add/edit) doesn't provide:
delete-safety checks across every table that references an item code, and
cascading code-rename across ~19 related tables. These are the actual
unique value of the original controller beyond plain field editing.
"""
from django.db import connection

# table -> weight-like column(s) that must net to zero before an item code
# can be deleted (mirrors ItemMasterController::canDeleteItem).
_DELETE_CHECK_TABLES = [
    ("salesd", "weight"),
    ("salesrd", "weight"),
    ("purchased", "weight"),
    ("purchaserd", "weight"),
    ("orderd", "weight"),
    ("repaird", "weight"),
    ("smithd", "weight"),
    ("refineryd", "issuedwgt"),
    ("refineryd", "rcvdwgt"),
]

# table -> columns holding an item code, used when cascading a rename
# (mirrors ItemMasterController::itemRenameReferences).
ITEM_RENAME_REFERENCES = {
    "itemadj": ["fromcode", "tocode"],
    "itemsstk": ["code"],
    "orderd": ["code"],
    "orderdmodel": ["code"],
    "orderdga": ["code"],
    "salesd": ["code"],
    "salesrd": ["code"],
    "purchased": ["code"],
    "purchaserd": ["code"],
    "refineryd": ["code"],
    "repaird": ["code"],
    "smithd": ["code"],
    "smithnewwrk": ["code"],
    "smithsusp": ["code"],
    "wstgtable": ["code"],
    "mctable": ["code"],
    "barcode": ["icode"],
    "itemadjverify": ["code"],
    "itemstmp": ["code"],
    "modelm": ["icode"],
}


def _existing_tables():
    return set(connection.introspection.table_names())


def _table_columns(table):
    with connection.cursor() as cursor:
        return {c.name.lower() for c in connection.introspection.get_table_description(cursor, table)}


def can_delete_item(code):
    """True if no transaction anywhere references this item code with a
    non-zero net weight — i.e. it's safe to delete."""
    tables = _existing_tables()
    total = 0.0

    for table, column in _DELETE_CHECK_TABLES:
        if table not in tables:
            continue
        cols = _table_columns(table)
        if column not in cols or "code" not in cols:
            continue
        with connection.cursor() as cursor:
            cursor.execute(
                f"SELECT COALESCE(SUM(`{column}`), 0) FROM `{table}` WHERE UPPER(TRIM(code)) = %s",
                [code],
            )
            total += float(cursor.fetchone()[0] or 0)

    if "itemadj" in tables:
        cols = _table_columns("itemadj")
        if {"fromcode", "fromwgt"} <= cols:
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT COALESCE(SUM(fromwgt), 0) FROM itemadj WHERE UPPER(TRIM(fromcode)) = %s", [code]
                )
                total += float(cursor.fetchone()[0] or 0)
        if {"tocode", "towgt"} <= cols:
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT COALESCE(SUM(towgt), 0) FROM itemadj WHERE UPPER(TRIM(tocode)) = %s", [code]
                )
                total += float(cursor.fetchone()[0] or 0)

    return abs(total) < 0.0001


def rename_item_code(old_code, new_code, merge_existing=False):
    """Cascades an item code rename across every referencing table, mirroring
    ItemMasterController::renameCode. Returns a dict describing what changed.
    Caller is responsible for wrapping this in a transaction.
    """
    old_code = (old_code or "").strip().upper()
    new_code = (new_code or "").strip().upper()

    if not old_code or not new_code:
        return {"success": False, "message": "Old code and new code are required"}
    if len(old_code) > 10 or len(new_code) > 10:
        return {"success": False, "message": "Item code maximum length is 10"}
    if old_code == new_code:
        return {"success": False, "message": "Old code and new code are same"}

    with connection.cursor() as cursor:
        cursor.execute("SELECT 1 FROM items WHERE UPPER(TRIM(code)) = %s", [old_code])
        if not cursor.fetchone():
            return {"success": False, "message": "Invalid old item code"}

        cursor.execute("SELECT 1 FROM items WHERE UPPER(TRIM(code)) = %s", [new_code])
        new_exists = cursor.fetchone() is not None

    if new_exists and not merge_existing:
        return {"success": False, "message": "New item code already exists", "exists": True}

    counts = {}
    with connection.cursor() as cursor:
        if new_exists:
            cursor.execute("DELETE FROM items WHERE UPPER(TRIM(code)) = %s", [old_code])
            counts["items_deleted"] = cursor.rowcount
        else:
            cursor.execute("UPDATE items SET code = %s WHERE UPPER(TRIM(code)) = %s", [new_code, old_code])
            counts["items"] = cursor.rowcount

        tables = _existing_tables()
        for table, columns in ITEM_RENAME_REFERENCES.items():
            if table not in tables:
                continue
            table_cols = _table_columns(table)
            for column in columns:
                if column not in table_cols:
                    continue
                cursor.execute(
                    f"UPDATE `{table}` SET `{column}` = %s WHERE UPPER(TRIM(`{column}`)) = %s",
                    [new_code, old_code],
                )
                if cursor.rowcount > 0:
                    counts[f"{table}.{column}"] = cursor.rowcount

    return {
        "success": True,
        "message": "Item renamed successfully",
        "old_code": old_code,
        "new_code": new_code,
        "merged": new_exists,
        "counts": counts,
    }
