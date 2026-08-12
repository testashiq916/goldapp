from django.apps import apps
from django.utils.text import slugify

GROUP_LABELS = {
    "accounts": "Accounts & Daybook",
    "masters": "Masters",
    "clients": "Customers",
    "purchase": "Purchase",
    "sales": "Sales",
    "orders": "Orders",
    "stock": "Stock & Barcode",
    "smith": "Goldsmith & Refinery",
    "repair": "Repairs",
    "kuri": "Kuri & Loans",
    "staff": "Staff & Payroll",
    "misc": "Misc / System",
    "auth": "Users & Access",
    "native": "Billing (New)",
}

GROUP_ORDER = list(GROUP_LABELS.keys())


def model_group(model):
    mod = model.__module__  # e.g. "core.models.purchase"
    return mod.rsplit(".", 1)[-1]


def model_slug(model):
    return slugify(model.__name__)


def all_models_by_group():
    grouped = {g: [] for g in GROUP_ORDER}
    for model in apps.get_app_config("core").get_models():
        grouped.setdefault(model_group(model), []).append(model)
    for g in grouped:
        grouped[g].sort(key=lambda m: m.__name__)
    return grouped


def is_composite_pk(model):
    pk = model._meta.pk
    return pk.name == "pk" and hasattr(pk, "field_names")


def find_model(group, slug):
    grouped = all_models_by_group()
    for model in grouped.get(group, []):
        if model_slug(model) == slug:
            return model
    return None
