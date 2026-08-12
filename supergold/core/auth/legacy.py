"""Legacy password cipher compatible with the original SQL-Anywhere app and
the Laravel port's App\\Models\\UserM::fpCrypt / fpencrypt.

Ported byte-for-byte from app/Models/UserM.php so existing `userm.pcode`
values keep working without a password reset/migration step.
"""
from django.db import connection

_TRIM_BYTES = b" \t\n\r\x00\x0b"


def _fp_crypt(password: str, mode: int = 1) -> str:
    raw = password.encode("latin-1", errors="replace")
    trimmed = raw.strip(_TRIM_BYTES)
    length = len(trimmed)

    result = bytearray(b" ")
    for index in range(length):
        char = raw[index] if index < len(raw) else 0
        offset = (index + 1) * (length + 2)
        value = (char + offset) if mode == 1 else (char - offset)
        result.append(value % 256)

    result = bytes(result).strip(_TRIM_BYTES)
    if not result:
        return ""
    return result.decode("cp1252", errors="replace")


def fp_encrypt(password: str) -> str:
    return _fp_crypt(password, mode=1)


def _hex_of_utf8(value: str) -> str:
    return value.encode("utf-8").hex().upper()


def authenticate_legacy(password: str):
    """Password-only login: matches App\\Models\\UserM::authenticateLegacy.

    Returns a dict with code/name, or None.
    """
    password = (password or "").strip()
    if not password:
        return None

    hex_code = _hex_of_utf8(fp_encrypt(password))
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT code, name FROM userm WHERE UPPER(HEX(pcode)) = %s LIMIT 1",
            [hex_code],
        )
        row = cursor.fetchone()
    if not row:
        return None
    return {"code": (row[0] or "").strip(), "name": (row[1] or "").strip()}


def get_user_by_code(code: str):
    code = (code or "").strip()
    if not code:
        return None
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT code, name FROM userm WHERE TRIM(code) = %s LIMIT 1",
            [code],
        )
        row = cursor.fetchone()
    if not row:
        return None
    return {"code": (row[0] or "").strip(), "name": (row[1] or "").strip()}
