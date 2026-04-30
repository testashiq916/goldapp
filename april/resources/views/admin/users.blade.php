<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 980px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 12px; }
        button, .btn { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        button.primary, .btn.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        button.warn, .btn.warn { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .msg { margin-bottom: 10px; padding: 8px 12px; border-radius: 7px; font-size: 13px; background: #e6f6ec; border: 1px solid #9fd5af; color: #12522d; }
        .table-wrap { border: 1px solid #d8e2ef; border-radius: 8px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 8px 10px; text-align: left; }
        th { background: #edf4fc; color: #2d4f74; font-weight: 700; position: sticky; top: 0; z-index: 2; }
        tr:hover { background: #f0f6ff; }
        .actions { display: flex; gap: 4px; }
        .actions a { font-size: 11px; padding: 3px 8px; border-radius: 5px; text-decoration: none; border: 1px solid; }
        .actions .edit { border-color: #2a6398; background: #e8f2ff; color: #17456e; }
        .actions .perm { border-color: #7c3aed; background: #f3e8ff; color: #5b21b6; }
        .actions .hist { border-color: #0d9488; background: #e6fffa; color: #0f766e; }
        .actions .del { border-color: #a23b3b; background: #fdeaea; color: #8e1f1f; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.inactive { background: #fee2e2; color: #991b1b; }
        @media (max-width: 768px) { .wrap { margin: 8px; padding: 10px; } h1 { font-size: 18px; } .toolbar { flex-wrap: wrap; } }
        @media (max-width: 540px) { .wrap { margin: 4px; border-radius: 8px; } th, td { padding: 5px 6px; font-size: 11px; } .actions { flex-wrap: wrap; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>User Management</h1>

    @if($message)
        <div class="msg">{{ $message }}</div>
    @endif

    <div class="toolbar">
        <a href="{{ url('/admin/users/add') }}" class="btn primary">Add New User</a>
        <a href="{{ url('/dashboard') }}" class="btn">Exit</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Code</th>
                <th>Name</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $user->code }}</strong></td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email ?? '-' }}</td>
                    <td>{{ $user->mobile ?? '-' }}</td>
                    <td>
                        @php $st = $user->status ?? 'active'; @endphp
                        <span class="badge {{ $st === 'active' ? 'active' : 'inactive' }}">{{ ucfirst($st) }}</span>
                    </td>
                    <td class="actions">
                        <a href="{{ url('/admin/users/' . urlencode($user->code) . '/edit') }}" class="edit">Edit</a>
                        <a href="{{ url('/admin/users/' . urlencode($user->code) . '/permissions') }}" class="perm">Permissions</a>
                        <a href="{{ url('/admin/users/' . urlencode($user->code) . '/history') }}" class="hist">History</a>
                        @if($user->code !== 'admin')
                            <form method="POST" action="{{ url('/admin/users/' . urlencode($user->code) . '/delete') }}" style="display:inline" onsubmit="return confirm('Delete user {{ $user->code }}?')">
                                @csrf
                                <button type="submit" class="del" style="height:auto;font-size:11px;padding:3px 8px;border-radius:5px;">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
