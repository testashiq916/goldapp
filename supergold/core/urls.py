from django.urls import path

from core import views

urlpatterns = [
    path("login", views.login_view, name="login"),
    path("logout", views.logout_view, name="logout"),
    path("dashboard", views.dashboard_view, name="dashboard"),
    path("modules/", views.module_home, name="module_home"),
    path("modules/masters/items/rename-code/", views.item_rename_code, name="item_rename_code"),
    path("modules/<str:group>/<str:slug>/", views.module_list, name="module_list"),
    path("modules/<str:group>/<str:slug>/add/", views.module_add, name="module_add"),
    path("modules/<str:group>/<str:slug>/<str:pk>/edit/", views.module_edit, name="module_edit"),
    path("modules/<str:group>/<str:slug>/<str:pk>/delete/", views.module_delete, name="module_delete"),
]
