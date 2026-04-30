<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $mode === 'add' ? 'Add User' : 'Edit User' }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 560px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 18px; }
        h1 { margin: 0 0 14px; font-size: 24px; color: #1f3f66; }
        .err { margin-bottom: 10px; padding: 8px 12px; border-radius: 7px; font-size: 13px; background: #fdeaea; border: 1px solid #f2b1b1; color: #8e1f1f; }
        .field { margin-bottom: 12px; }
        .field label { display: block; font-size: 12px; font-weight: 700; color: #375b84; margin-bottom: 4px; }
        .field input, .field select { width: 100%; height: 36px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 10px; font-size: 13px; box-sizing: border-box; }
        .field input:focus, .field select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        .toolbar { display: flex; gap: 8px; margin-top: 14px; }
        button { height: 36px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $mode === 'add' ? 'Add New User' : 'Edit User: ' . $user->code }}</h1>

    @if(!empty($error))
        <div class="err">{{ $error }}</div>
    @endif

    <form method="POST" action="{{ $mode === 'add' ? url('/admin/users/add') : url('/admin/users/' . urlencode($user->code) . '/edit') }}">
        @csrf

        @if($mode === 'add')
            <div class="field">
                <label>User Code *</label>
                <input type="text" name="code" maxlength="20" required value="{{ old('code') }}" style="text-transform:uppercase" autofocus>
            </div>
        @endif

        <div class="field">
            <label>Name *</label>
            <input type="text" name="name" maxlength="50" required value="{{ $user->name ?? old('name') }}" {{ $mode === 'edit' ? 'autofocus' : '' }}>
        </div>

        <div class="field">
            <label>Password {{ $mode === 'edit' ? '(leave blank to keep current)' : '*' }}</label>
            <input type="password" name="password" minlength="6" {{ $mode === 'add' ? 'required' : '' }}>
        </div>

        <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="{{ $user->email ?? old('email') }}">
        </div>

        <div class="field">
            <label>Mobile</label>
            <input type="text" name="mobile" maxlength="15" value="{{ $user->mobile ?? old('mobile') }}">
        </div>

        @if($mode === 'edit')
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <option value="active" {{ ($user->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($user->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        @endif

        <div class="toolbar">
            <button type="submit" class="primary">{{ $mode === 'add' ? 'Create User' : 'Update User' }}</button>
            <button type="button" onclick="window.location.href='{{ url('/admin/users') }}'">Cancel</button>
        </div>
    </form>
</div>
</body>
</html>
