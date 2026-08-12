from django.urls import path

from core import views
from core.modules import purchase_bill_views

urlpatterns = [
    path("login", views.login_view, name="login"),
    path("logout", views.logout_view, name="logout"),
    path("dashboard", views.dashboard_view, name="dashboard"),
    path("modules/", views.module_home, name="module_home"),
    path("modules/masters/items/rename-code/", views.item_rename_code, name="item_rename_code"),
    path("daybook/", views.daybook_view, name="daybook"),
    path("account-ledger/", views.account_ledger_view, name="account_ledger"),
    path("account-ledger/api/search", views.account_search_api, name="account_search_api"),
    path("purchase-bill/", purchase_bill_views.purchase_bill_view, name="purchase_bill"),
    path("purchase-bill/api/item-search", purchase_bill_views.purchase_item_search, name="purchase_item_search"),
    path("purchase-bill/api/supplier-search", purchase_bill_views.purchase_supplier_search, name="purchase_supplier_search"),
    path("purchase-bill/api/save", purchase_bill_views.purchase_bill_save, name="purchase_bill_save"),
    path("modules/<str:group>/<str:slug>/", views.module_list, name="module_list"),
    path("modules/<str:group>/<str:slug>/add/", views.module_add, name="module_add"),
    path("modules/<str:group>/<str:slug>/<str:pk>/edit/", views.module_edit, name="module_edit"),
    path("modules/<str:group>/<str:slug>/<str:pk>/delete/", views.module_delete, name="module_delete"),
]
