from django.apps import apps
from django.contrib import admin
from django.db import models as dj_models


def _build_admin_class(model):
    field_names = [f.name for f in model._meta.fields]
    char_fields = [
        f.name
        for f in model._meta.fields
        if isinstance(f, (dj_models.CharField, dj_models.TextField))
    ]

    attrs = {
        "list_display": field_names[:8],
        "search_fields": char_fields[:5],
        "list_per_page": 50,
    }
    return type(f"{model.__name__}Admin", (admin.ModelAdmin,), attrs)


for model in apps.get_app_config("core").get_models():
    if model._meta.pk.name == "pk" and hasattr(model._meta.pk, "columns"):
        # Composite-primary-key models aren't supported by Django admin yet;
        # they still get browse/detail coverage via core's generic views.
        continue
    try:
        admin.site.register(model, _build_admin_class(model))
    except admin.sites.AlreadyRegistered:
        pass

admin.site.site_header = "SuperGold Administration"
admin.site.site_title = "SuperGold Admin"
admin.site.index_title = "All Modules"
