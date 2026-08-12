from django.db import DatabaseError
from django.urls import reverse

from core.models.accounts import Generals
from core.nav import GROUP_LABELS, GROUP_ORDER, all_models_by_group, model_slug


def _setting(code, default=""):
    try:
        row = Generals.objects.filter(code=code).values_list("cvalue", flat=True).first()
        return row if row else default
    except DatabaseError:
        return default


def shop_context(request):
    nav_groups = {}
    if getattr(request, "legacy_user", None):
        grouped = all_models_by_group()
        for g in GROUP_ORDER:
            models = grouped.get(g, [])
            if not models:
                continue
            label = GROUP_LABELS.get(g, g)
            nav_groups[label] = [
                (m._meta.verbose_name.title(), reverse("module_list", args=[g, model_slug(m)]))
                for m in models
            ]

    return {
        "shop_name": _setting("SHOPNM", "SuperGold"),
        "shop_addr": _setting("SHOPADDR"),
        "shop_phone": _setting("SHOPPHONE"),
        "shop_gst": _setting("GSTIN") or _setting("GSTNO"),
        "legacy_user": getattr(request, "legacy_user", None),
        "nav_groups": nav_groups,
    }
