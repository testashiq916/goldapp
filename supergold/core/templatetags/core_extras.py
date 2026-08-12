from django import template

register = template.Library()


@register.filter(name="getattr")
def getattr_filter(obj, field_name):
    if obj is None or obj == "":
        return ""
    try:
        value = getattr(obj, field_name)
    except AttributeError:
        return ""
    return value if value is not None else ""
