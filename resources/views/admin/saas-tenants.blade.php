<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gold Plus SaaS Admin</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: #f4f6fb; color: #1f2937; }
        .wrap { max-width: 1080px; margin: 20px auto; padding: 0 14px; }
        .panel { background: #fff; border: 1px solid #d8e2ef; border-radius: 8px; padding: 16px; box-shadow: 0 8px 24px rgba(31,41,55,.06); }
        h1 { margin: 0 0 6px; font-size: 24px; color: #1f3f66; }
        .muted { color: #64748b; font-size: 13px; margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px; }
        input[type=text], input[type=password] { width: 100%; height: 36px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        input:focus { outline: none; border-color: #2a6398; box-shadow: 0 0 0 3px rgba(42,99,152,.12); }
        .full { grid-column: 1 / -1; }
        .actions { display: flex; gap: 8px; align-items: center; margin-top: 14px; flex-wrap: wrap; }
        button, .btn { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.danger { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .msg, .err { margin: 0 0 12px; padding: 9px 12px; border-radius: 7px; font-size: 13px; }
        .msg { background: #e6f6ec; border: 1px solid #9fd5af; color: #12522d; }
        .err { background: #fdeaea; border: 1px solid #e8a0a0; color: #7a2020; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { background: #edf4fc; color: #2d4f74; }
        .badge { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge.on { background: #dcfce7; color: #166534; }
        .badge.off { background: #fee2e2; color: #991b1b; }
        .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .inline { display: inline; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } .wrap { margin: 8px auto; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="panel">
        <h1>Gold Plus SaaS Admin</h1>
        <div class="muted">Hidden tenant setup page for owner app domain based login.</div>

        @if($message)
            <div class="msg">{{ $message }}</div>
        @endif
        @if($error)
            <div class="err">{{ $error }}</div>
        @endif

        @if(!$unlocked)
            <form method="post" action="{{ url('/goldplus-admin/unlock') }}">
                @csrf
                <div class="grid">
                    <div>
                        <label for="admin_password">Admin Password</label>
                        <input id="admin_password" name="admin_password" type="password" placeholder="GoldplusAdmin" autofocus required>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="primary">Open Admin</button>
                    <a class="btn" href="{{ url('/login') }}">Back to Login</a>
                </div>
            </form>
        @else
            <form method="post" action="{{ url('/goldplus-admin/tenants') }}">
                @csrf
                <input type="hidden" id="tenant_id" name="id" value="">
                <div class="grid">
                    <div>
                        <label for="domain">Domain Address</label>
                        <input id="domain" name="domain" type="text" placeholder="shop.example.com" required>
                    </div>
                    <div>
                        <label for="shop_name">Shop Name</label>
                        <input id="shop_name" name="shop_name" type="text" placeholder="My Jewellery" required>
                    </div>
                    <div>
                        <label for="database_name">Database Name</label>
                        <input id="database_name" name="database_name" type="text" placeholder="gold_shop_db" required>
                    </div>
                    <div>
                        <label for="password">Login Password</label>
                        <input id="password" name="password" type="password" placeholder="Required for new, blank keeps old password">
                    </div>
                    <div class="full">
                        <label><input id="is_active" name="is_active" type="checkbox" value="1" checked> Active tenant</label>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="primary">Save Tenant</button>
                    <button type="button" onclick="resetForm()">New Tenant</button>
                    <a class="btn" href="{{ url('/login') }}">Exit</a>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>Domain</th>
                        <th>Database</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tenants as $tenant)
                    <tr>
                        <td><strong>{{ $tenant['shop_name'] }}</strong></td>
                        <td>{{ $tenant['domain'] }}</td>
                        <td>{{ $tenant['database_name'] }}</td>
                        <td><span class="badge {{ $tenant['is_active'] ? 'on' : 'off' }}">{{ $tenant['is_active'] ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ $tenant['created_at'] }}</td>
                        <td>
                            <div class="row-actions">
                                <button type="button" onclick='editTenant(@json($tenant))'>Edit</button>
                                <form class="inline" method="post" action="{{ url('/goldplus-admin/tenants/' . $tenant['id'] . '/delete') }}" onsubmit="return confirm('Delete {{ $tenant['shop_name'] }}?')">
                                    @csrf
                                    <button type="submit" class="danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">No tenants created yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>

<script>
function editTenant(tenant) {
    document.getElementById('tenant_id').value = tenant.id || '';
    document.getElementById('domain').value = tenant.domain || '';
    document.getElementById('shop_name').value = tenant.shop_name || '';
    document.getElementById('database_name').value = tenant.database_name || '';
    document.getElementById('password').value = '';
    document.getElementById('is_active').checked = !!tenant.is_active;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('tenant_id').value = '';
    document.getElementById('domain').value = '';
    document.getElementById('shop_name').value = '';
    document.getElementById('database_name').value = '';
    document.getElementById('password').value = '';
    document.getElementById('is_active').checked = true;
}
</script>
</body>
</html>
