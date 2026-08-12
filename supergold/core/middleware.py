class LegacySessionUserMiddleware:
    """Mirrors Laravel's session()->get('user_code'/'user_name') pattern.

    Attaches request.legacy_user (dict with code/name) when a legacy login
    session exists, so views/templates can read it without touching
    request.session directly.
    """

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        code = request.session.get("user_code")
        request.legacy_user = (
            {"code": code, "name": request.session.get("user_name", "")}
            if code
            else None
        )
        return self.get_response(request)
