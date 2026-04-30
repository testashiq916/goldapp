<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permissions: {{ $user->code }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 800px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; }
        h1 { margin: 0 0 4px; font-size: 24px; color: #1f3f66; }
        .sub { font-size: 13px; color: #64748b; margin-bottom: 14px; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 14px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 14px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        .section { margin-bottom: 14px; border: 1px solid #e3eaf4; border-radius: 8px; overflow: hidden; }
        .section-head { background: #edf4fc; padding: 8px 12px; font-size: 13px; font-weight: 700; color: #2d4f74; }
        .section-body { padding: 10px 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .perm-item { display: flex; align-items: center; gap: 8px; font-size: 12px; }
        .perm-item input { width: 16px; height: 16px; }
        .perm-item .label { font-weight: 600; color: #1f3f66; }
        .perm-item .desc { color: #64748b; font-size: 11px; }
        .note { margin-top: 10px; font-size: 11px; color: #94a3b8; }
        @media (max-width: 600px) { .section-body { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>User Permissions</h1>
    <div class="sub">User: <strong>{{ $user->code }}</strong> - {{ $user->name }} | Checked items are <strong>restricted</strong> (blocked).</div>

    <form method="POST" action="{{ url('/admin/users/' . urlencode($user->code) . '/permissions') }}">
        @csrf

        <div class="toolbar">
            <button type="submit" class="primary">Save Permissions</button>
            <button type="button" onclick="window.location.href='{{ url('/admin/users') }}'">Cancel</button>
            <button type="button" onclick="toggleAll(true)">Check All</button>
            <button type="button" onclick="toggleAll(false)">Uncheck All</button>
        </div>

        @foreach($availablePermissions as $category => $perms)
            <div class="section">
                <div class="section-head">{{ $category }}</div>
                <div class="section-body">
                    @foreach($perms as $code => $label)
                        <label class="perm-item">
                            <input type="checkbox" name="permissions[]" value="{{ $code }}"
                                   {{ in_array($code, $userPermissions) ? 'checked' : '' }}>
                            <span>
                                <span class="label">{{ $code }}</span><br>
                                <span class="desc">{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="note">Note: Checked permissions are RESTRICTIONS. The user will be BLOCKED from these actions.</div>
    </form>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = checked);
}
</script>
</body>
</html>
