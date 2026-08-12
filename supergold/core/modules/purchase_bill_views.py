import json

from django.http import JsonResponse
from django.shortcuts import redirect, render
from django.views.decorators.http import require_http_methods

from core.audit import log_delpart
from core.models.clients import Clients
from core.models.masters import Items
from core.modules.purchase_bill import PurchaseBillError, gen_dec, save_purchase_bill


def _login_required(view):
    def wrapped(request, *args, **kwargs):
        if not request.session.get("user_code"):
            return redirect("login")
        return view(request, *args, **kwargs)

    return wrapped


@_login_required
def purchase_bill_view(request):
    return render(
        request,
        "native/purchase_bill.html",
        {"gold_rate": gen_dec("GRATE")},
    )


@_login_required
def purchase_item_search(request):
    q = request.GET.get("q", "").strip()
    qs = Items.objects.all()
    if q:
        from django.db.models import Q

        qs = qs.filter(Q(code__icontains=q) | Q(name__icontains=q))
    rows = list(qs.order_by("code").values("code", "name")[:30])
    return JsonResponse({"ok": True, "results": rows})


@_login_required
def purchase_supplier_search(request):
    q = request.GET.get("q", "").strip()
    qs = Clients.objects.filter(ctype="S")
    if q:
        from django.db.models import Q

        qs = qs.filter(Q(code__icontains=q) | Q(name__icontains=q) | Q(mobile__icontains=q))
    rows = list(qs.order_by("name").values("code", "name", "addr1", "mobile")[:30])
    return JsonResponse({"ok": True, "results": rows})


@_login_required
@require_http_methods(["POST"])
def purchase_bill_save(request):
    try:
        payload = json.loads(request.body.decode("utf-8"))
    except (ValueError, UnicodeDecodeError):
        return JsonResponse({"ok": False, "message": "Invalid request body"}, status=400)

    user_code = request.session.get("user_code", "")
    try:
        result = save_purchase_bill(payload, user_code)
    except PurchaseBillError as exc:
        return JsonResponse({"ok": False, "message": exc.message}, status=exc.status)

    log_delpart(
        request,
        f"Purchase Bill({result['doc_no']}) Saved",
        utype="E" if result["existing"] else "A",
        ttype="T",
        slno=result["slno"],
    )

    return JsonResponse({
        "ok": True,
        "message": "Purchase bill saved successfully",
        "doc_no": result["doc_no"],
        "slno": result["slno"],
    })
