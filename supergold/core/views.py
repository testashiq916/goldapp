from django.contrib import messages
from django.core.paginator import Paginator
from django.forms import modelform_factory
from django.shortcuts import get_object_or_404, redirect, render
from django.urls import reverse
from django.views.decorators.http import require_http_methods

from django.db import transaction

from core.audit import log_delpart
from core.auth.legacy import authenticate_legacy
from core.modules.item_master import can_delete_item, rename_item_code
from core.nav import (
    GROUP_LABELS,
    GROUP_ORDER,
    all_models_by_group,
    find_model,
    is_composite_pk,
    model_slug,
)
from core.models.masters import Items
from core.models.purchase import Purchasem
from core.models.sales import Salesm
from core.models.accounts import Daybook
from core.models.clients import Clients
from core.models.native import TaskReminder


def _login_required(view):
    def wrapped(request, *args, **kwargs):
        if not request.session.get("user_code"):
            return redirect(f"{reverse('login')}?next={request.path}")
        return view(request, *args, **kwargs)

    return wrapped


def login_view(request):
    if request.session.get("user_code"):
        return redirect("dashboard")

    error = None
    if request.method == "POST":
        password = request.POST.get("password", "")
        user = authenticate_legacy(password)
        if user:
            request.session["user_code"] = user["code"]
            request.session["user_name"] = user["name"]
            return redirect(request.GET.get("next") or "dashboard")
        error = "Invalid password"

    return render(request, "native/login.html", {"error": error})


def logout_view(request):
    request.session.flush()
    return redirect("login")


@_login_required
def dashboard_view(request):
    stats = {}
    try:
        stats["items"] = Items.objects.count()
    except Exception:
        stats["items"] = "—"
    try:
        stats["customers"] = Clients.objects.count()
    except Exception:
        stats["customers"] = "—"
    try:
        stats["purchase_bills"] = Purchasem.objects.count()
    except Exception:
        stats["purchase_bills"] = "—"
    try:
        stats["sales_bills"] = Salesm.objects.count()
    except Exception:
        stats["sales_bills"] = "—"
    try:
        stats["pending_reminders"] = TaskReminder.objects.filter(is_done=False).count()
    except Exception:
        stats["pending_reminders"] = "—"

    grouped = all_models_by_group()
    module_counts = {GROUP_LABELS.get(g, g): len(models) for g, models in grouped.items() if models}

    return render(
        request,
        "native/dashboard.html",
        {"stats": stats, "module_counts": module_counts},
    )


@_login_required
def module_home(request):
    grouped = all_models_by_group()
    sections = []
    for g in GROUP_ORDER:
        models = grouped.get(g, [])
        if not models:
            continue
        sections.append(
            {
                "label": GROUP_LABELS.get(g, g),
                "group": g,
                "models": [
                    {
                        "name": m._meta.verbose_name.title(),
                        "slug": model_slug(m),
                        "readonly": is_composite_pk(m),
                        "url": reverse("module_list", args=[g, model_slug(m)]),
                    }
                    for m in models
                ],
            }
        )
    return render(request, "native/module_home.html", {"sections": sections})


@_login_required
def module_list(request, group, slug):
    model = find_model(group, slug)
    if model is None:
        messages.error(request, "Unknown module.")
        return redirect("module_home")

    qs = model.objects.all()
    q = request.GET.get("q", "").strip()
    if q:
        char_fields = [
            f.name
            for f in model._meta.fields
            if f.get_internal_type() in ("CharField", "TextField")
        ]
        if char_fields:
            from django.db.models import Q

            cond = Q()
            for f in char_fields[:6]:
                cond |= Q(**{f"{f}__icontains": q})
            qs = qs.filter(cond)

    field_names = [f.name for f in model._meta.fields][:10]
    paginator = Paginator(qs.order_by(model._meta.pk.name if not is_composite_pk(model) else field_names[0]), 50)
    page = paginator.get_page(request.GET.get("page"))

    return render(
        request,
        "native/module_list.html",
        {
            "model": model,
            "group": group,
            "slug": slug,
            "verbose_name": model._meta.verbose_name.title(),
            "field_names": field_names,
            "page": page,
            "q": q,
            "readonly": is_composite_pk(model),
        },
    )


def _build_form_class(model, editing_pk_field=None):
    exclude = []
    if editing_pk_field:
        exclude.append(editing_pk_field)
    FormClass = modelform_factory(model, fields="__all__", exclude=exclude)
    return FormClass


@_login_required
@require_http_methods(["GET", "POST"])
def module_add(request, group, slug):
    model = find_model(group, slug)
    if model is None or is_composite_pk(model):
        messages.error(request, "This module cannot be edited here.")
        return redirect("module_home")

    FormClass = _build_form_class(model)
    if request.method == "POST":
        form = FormClass(request.POST)
        if form.is_valid():
            obj = form.save()
            log_delpart(request, f"{model._meta.verbose_name.title()}({obj.pk}) Added", utype="A")
            messages.success(request, f"{model._meta.verbose_name.title()} created.")
            return redirect("module_list", group=group, slug=slug)
    else:
        form = FormClass()

    return render(
        request,
        "native/module_form.html",
        {"form": form, "verbose_name": model._meta.verbose_name.title(), "group": group, "slug": slug, "is_new": True},
    )


@_login_required
@require_http_methods(["GET", "POST"])
def module_edit(request, group, slug, pk):
    model = find_model(group, slug)
    if model is None or is_composite_pk(model):
        messages.error(request, "This module cannot be edited here.")
        return redirect("module_home")

    obj = get_object_or_404(model, pk=pk)
    pk_field = model._meta.pk.name
    FormClass = _build_form_class(model, editing_pk_field=pk_field)
    if request.method == "POST":
        form = FormClass(request.POST, instance=obj)
        if form.is_valid():
            form.save()
            log_delpart(request, f"{model._meta.verbose_name.title()}({pk}) Updated", utype="E")
            messages.success(request, f"{model._meta.verbose_name.title()} updated.")
            return redirect("module_list", group=group, slug=slug)
    else:
        form = FormClass(instance=obj)

    return render(
        request,
        "native/module_form.html",
        {
            "form": form,
            "verbose_name": model._meta.verbose_name.title(),
            "group": group,
            "slug": slug,
            "is_new": False,
            "pk_value": pk,
            "pk_field": pk_field,
        },
    )


@_login_required
@require_http_methods(["GET", "POST"])
def module_delete(request, group, slug, pk):
    model = find_model(group, slug)
    if model is None or is_composite_pk(model):
        messages.error(request, "This module cannot be edited here.")
        return redirect("module_home")

    obj = get_object_or_404(model, pk=pk)

    if request.method == "POST":
        # Item Master carries the same delete-safety rule as
        # ItemMasterController::delete(): reserved items, and items with any
        # non-zero net weight in a transaction table, cannot be removed.
        if group == "masters" and slug == "items":
            reserve = (getattr(obj, "reserve", "") or "").strip().upper()
            if reserve == "Y":
                messages.error(request, "This item is reserved. You cannot delete it.")
                return redirect("module_list", group=group, slug=slug)
            if not can_delete_item(str(pk).strip().upper()):
                messages.error(request, "Some entries exist with this item. You cannot delete this item.")
                return redirect("module_list", group=group, slug=slug)

        obj.delete()
        log_delpart(request, f"{model._meta.verbose_name.title()}({pk}) Deleted", utype="D")
        messages.success(request, f"{model._meta.verbose_name.title()} deleted.")
        return redirect("module_list", group=group, slug=slug)

    return render(
        request,
        "native/module_confirm_delete.html",
        {"object": obj, "verbose_name": model._meta.verbose_name.title(), "group": group, "slug": slug},
    )


@_login_required
@require_http_methods(["GET", "POST"])
def item_rename_code(request):
    """Port of ItemMasterController::renameCode — cascades an item code
    change across every table that references it (purchase/sales/order/
    smith/refinery lines, barcodes, item adjustments, etc.)."""
    result = None
    if request.method == "POST":
        old_code = request.POST.get("old_code", "")
        new_code = request.POST.get("new_code", "")
        merge_existing = request.POST.get("merge_existing") == "on"
        with transaction.atomic():
            result = rename_item_code(old_code, new_code, merge_existing)
            if result.get("success"):
                log_delpart(
                    request,
                    f"Item Code Renamed {result['old_code']} to {result['new_code']}",
                    utype="E",
                )
        if result.get("success"):
            messages.success(request, result["message"])
        else:
            messages.error(request, result["message"])
        if result and result.get("counts"):
            result = {**result, "counts_list": list(result["counts"].items())}

    return render(request, "native/item_rename.html", {"result": result})
