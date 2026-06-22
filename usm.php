<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PasswordService;
use App\Enums\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$isCli = PHP_SAPI === 'cli';
$args = $isCli ? array_slice($argv ?? [], 1) : [];
$action = $isCli ? strtolower((string) ($args[0] ?? 'list')) : strtolower((string) ($_REQUEST['action'] ?? 'screen'));

if (!Schema::hasTable('userm') || !Schema::hasTable('userd')) {
    exit("Required tables not found: userm / userd\n");
}

if (!$isCli) {
    handleWebRequest($action);
    return;
}

header('Content-Type: text/plain; charset=UTF-8');

try {
    match ($action) {
        'list' => listUsers(),
        'add', 'create' => createOrUpdateUser(
            valueArg($args, 1, 'code'),
            valueArg($args, 2, 'name'),
            valueArg($args, 3, 'password'),
            permissionArgs($args, 4),
        ),
        'password', 'pass' => updatePassword(
            valueArg($args, 1, 'code'),
            valueArg($args, 2, 'password'),
        ),
        'grant' => grantPermissions(
            valueArg($args, 1, 'code'),
            permissionArgs($args, 2),
        ),
        'admin' => grantPermissions(
            valueArg($args, 1, 'code'),
            ['ADMINBUTTON', 'MDI_USER_ACCESS'],
        ),
        'help' => usage(),
        default => usage("Unknown action: {$action}"),
    };
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

function handleWebRequest(string $action): void
{
    $message = '';
    $error = '';

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($action === 'save_access') {
                $code = normalizeCode((string) ($_POST['code'] ?? ''));
                $permissions = array_map('normalizePermission', (array) ($_POST['permissions'] ?? []));
                syncPermissions($code, $permissions);
                $message = "Access saved for {$code}.";
            } elseif ($action === 'create_user') {
                $code = (string) ($_POST['new_code'] ?? '');
                $name = (string) ($_POST['new_name'] ?? '');
                $password = (string) ($_POST['new_password'] ?? '');
                createOrUpdateUser($code, $name, $password, []);
                $message = 'User created/updated.';
                $_GET['code'] = normalizeCode($code);
            } elseif ($action === 'change_password') {
                $code = (string) ($_POST['password_code'] ?? '');
                $password = (string) ($_POST['password_value'] ?? '');
                updatePassword($code, $password);
                $message = 'Password updated.';
                $_GET['code'] = normalizeCode($code);
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    renderAccessScreen($message, $error);
}

function renderAccessScreen(string $message = '', string $error = ''): void
{
    header('Content-Type: text/html; charset=UTF-8');

    $users = DB::table('userm')
        ->select(['code', 'name'])
        ->orderBy('code')
        ->get()
        ->map(fn ($row) => [
            'code' => trim((string) $row->code),
            'name' => trim((string) $row->name),
        ])
        ->all();

    $selectedCode = normalizeCode((string) ($_GET['code'] ?? ($users[0]['code'] ?? '')));
    $selectedPermissions = $selectedCode !== '' ? permissionSet($selectedCode) : [];
    $groups = Permission::groupedLabels();
    $allPermissionLabels = Permission::all();

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>User Access Manager</title>';
    echo '<style>
        body{font-family:Segoe UI,Tahoma,sans-serif;margin:0;background:#f4f7fb;color:#1f2937;font-size:14px}
        .wrap{max-width:1260px;margin:18px auto;padding:0 14px}
        .head{display:flex;align-items:center;gap:10px;justify-content:space-between;margin-bottom:12px}
        h1{font-size:22px;margin:0;color:#163b63}
        .panel{background:#fff;border:1px solid #d8e3f0;border-radius:8px;padding:14px;margin-bottom:12px;box-shadow:0 8px 22px rgba(22,59,99,.06)}
        .row{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
        label{display:block;font-size:12px;font-weight:700;color:#426487;margin-bottom:4px}
        select,input{height:36px;border:1px solid #b9cce2;border-radius:6px;padding:0 9px;font-size:13px;background:#fff}
        select{min-width:280px}
        button{height:36px;border:1px solid #28669b;border-radius:6px;padding:0 14px;background:#e7f2ff;color:#17476f;font-weight:700;cursor:pointer}
        button.primary{background:#e7f8ed;border-color:#2b8149;color:#1b6433}
        button.danger{background:#fff0f0;border-color:#c45151;color:#9b2727}
        .msg{padding:9px 12px;border-radius:6px;margin-bottom:12px;font-weight:700}
        .ok{background:#e9f9ef;border:1px solid #9bd1ad;color:#176336}
        .err{background:#fff0f0;border:1px solid #e3a1a1;color:#9b2727}
        .quick{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
        .group{border:1px solid #dce6f2;border-radius:8px;margin-bottom:10px;overflow:hidden}
        .ghead{display:flex;align-items:center;justify-content:space-between;background:#eef5fd;padding:8px 10px;color:#214d78;font-weight:800}
        .gbody{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:0;border-top:1px solid #dce6f2}
        .perm{display:flex;gap:8px;align-items:flex-start;padding:8px 10px;border-right:1px solid #eef2f7;border-bottom:1px solid #eef2f7;min-height:42px}
        .perm input{height:auto;margin-top:2px}
        .perm span{line-height:1.25}
        .perm small{display:block;color:#6b7d90;font-size:11px;margin-top:2px}
        .modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;padding:18px}
        .modal.show{display:flex}
        .box{width:min(460px,100%);background:#fff;border-radius:9px;border:1px solid #cfdae8;padding:16px}
        .box h2{margin:0 0 12px;font-size:18px;color:#163b63}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        @media(max-width:700px){.grid2,.gbody{grid-template-columns:1fr}.head{align-items:flex-start;flex-direction:column}select{min-width:100%;width:100%}}
    </style>';
    echo '</head><body><div class="wrap">';
    echo '<div class="head"><h1>User Access Manager</h1><div><button type="button" onclick="openModal(\'userModal\')">New User</button> <button type="button" onclick="openPasswordModal()">Password</button></div></div>';

    if ($message !== '') {
        echo '<div class="msg ok">' . e($message) . '</div>';
    }
    if ($error !== '') {
        echo '<div class="msg err">' . e($error) . '</div>';
    }

    echo '<div class="panel"><form method="get" class="row">';
    echo '<input type="hidden" name="action" value="screen">';
    echo '<div><label>Select User</label><select name="code" onchange="this.form.submit()">';
    if (!$users) {
        echo '<option value="">No users found</option>';
    }
    foreach ($users as $user) {
        $value = $user['code'];
        $label = $user['code'] . ' - ' . $user['name'];
        echo '<option value="' . e($value) . '"' . ($selectedCode === strtoupper($value) ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></div><button type="submit">Load</button></form>';

    if ($selectedCode !== '') {
        echo '<div class="quick">';
        echo '<button type="button" onclick="setAll(true)">Select All</button>';
        echo '<button type="button" onclick="setAll(false)">Clear All</button>';
        echo '<button type="button" onclick="setAdmin()">Admin Access</button>';
        echo '<button type="button" onclick="filterChecked()">Show Selected</button>';
        echo '<button type="button" onclick="showAll()">Show All</button>';
        echo '</div>';
    }
    echo '</div>';

    if ($selectedCode !== '') {
        echo '<form method="post" action="?action=save_access&code=' . e($selectedCode) . '">';
        echo '<input type="hidden" name="code" value="' . e($selectedCode) . '">';
        echo '<div class="panel">';
        echo '<div class="row" style="justify-content:space-between;margin-bottom:10px"><strong>Access for ' . e($selectedCode) . '</strong><button class="primary" type="submit">Save Access</button></div>';

        foreach ($groups as $groupName => $permissions) {
            echo '<section class="group">';
            echo '<div class="ghead"><span>' . e((string) $groupName) . '</span><button type="button" onclick="toggleGroup(this)">Toggle Group</button></div>';
            echo '<div class="gbody">';
            foreach ($permissions as $key => $label) {
                $permissionKey = is_string($key) ? $key : (string) $label;
                $permissionLabel = is_string($key) ? (string) $label : ($allPermissionLabels[$permissionKey] ?? $permissionKey);
                $checked = isset($selectedPermissions[strtoupper($permissionKey)]) ? ' checked' : '';
                echo '<label class="perm" data-perm="' . e(strtoupper($permissionKey)) . '">';
                echo '<input type="checkbox" name="permissions[]" value="' . e(strtoupper($permissionKey)) . '"' . $checked . '>';
                echo '<span>' . e($permissionLabel) . '<small>' . e(strtoupper($permissionKey)) . '</small></span>';
                echo '</label>';
            }
            echo '</div></section>';
        }

        echo '<button class="primary" type="submit">Save Access</button>';
        echo '</div></form>';
    }

    renderUserModal();
    renderPasswordModal($selectedCode);

    echo '<script>
        function openModal(id){document.getElementById(id).classList.add("show")}
        function closeModal(id){document.getElementById(id).classList.remove("show")}
        function setAll(on){document.querySelectorAll("input[name=\'permissions[]\']").forEach(function(c){c.checked=on})}
        function setAdmin(){setAll(false);["ADMINBUTTON","MDI_USER_ACCESS"].forEach(function(p){var el=document.querySelector("input[value=\'"+p+"\']"); if(el) el.checked=true;})}
        function toggleGroup(btn){btn.closest(".group").querySelectorAll("input[type=checkbox]").forEach(function(c){c.checked=!c.checked})}
        function filterChecked(){document.querySelectorAll(".perm").forEach(function(row){var c=row.querySelector("input"); row.style.display=c.checked?"flex":"none";})}
        function showAll(){document.querySelectorAll(".perm").forEach(function(row){row.style.display="flex";})}
        function openPasswordModal(){var sel=document.querySelector("select[name=code]"); var input=document.getElementById("passwordCode"); if(sel&&input) input.value=sel.value; openModal("passwordModal")}
    </script>';
    echo '</div></body></html>';
}

function renderUserModal(): void
{
    echo '<div class="modal" id="userModal"><div class="box">';
    echo '<h2>Create / Update User</h2>';
    echo '<form method="post" action="?action=create_user&apply=1">';
    echo '<div class="grid2"><div><label>Code</label><input name="new_code" required></div><div><label>Name</label><input name="new_name" required></div></div>';
    echo '<div style="margin-top:10px"><label>Password</label><input name="new_password" type="password" required style="width:100%"></div>';
    echo '<div class="row" style="justify-content:flex-end;margin-top:14px"><button type="button" onclick="closeModal(\'userModal\')">Cancel</button><button class="primary" type="submit">Save User</button></div>';
    echo '</form></div></div>';
}

function renderPasswordModal(string $selectedCode): void
{
    echo '<div class="modal" id="passwordModal"><div class="box">';
    echo '<h2>Change Password</h2>';
    echo '<form method="post" action="?action=change_password&apply=1">';
    echo '<div><label>User Code</label><input id="passwordCode" name="password_code" value="' . e($selectedCode) . '" required style="width:100%"></div>';
    echo '<div style="margin-top:10px"><label>New Password</label><input name="password_value" type="password" required style="width:100%"></div>';
    echo '<div class="row" style="justify-content:flex-end;margin-top:14px"><button type="button" onclick="closeModal(\'passwordModal\')">Cancel</button><button class="primary" type="submit">Save Password</button></div>';
    echo '</form></div></div>';
}

function permissionSet(string $code): array
{
    $rows = DB::table('userd')
        ->whereRaw('UPPER(TRIM(code)) = ?', [normalizeCode($code)])
        ->pluck('menuitem');

    $set = [];
    foreach ($rows as $row) {
        $key = normalizePermission((string) $row);
        if ($key !== '') {
            $set[$key] = true;
        }
    }

    return $set;
}

function syncPermissions(string $code, array $permissions): void
{
    $code = normalizeCode($code);
    if ($code === '') {
        throw new InvalidArgumentException('User code is required.');
    }
    if (!DB::table('userm')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists()) {
        throw new InvalidArgumentException("User not found: {$code}");
    }

    $permissions = array_values(array_unique(array_filter(array_map('normalizePermission', $permissions))));

    DB::transaction(function () use ($code, $permissions): void {
        DB::table('userd')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
        foreach ($permissions as $permission) {
            DB::table('userd')->insert([
                'code' => $code,
                'menuitem' => $permission,
            ]);
        }
    });
}

function usage(?string $message = null): void
{
    if ($message) {
        echo $message . "\n\n";
    }

    echo "User access helper for userm / userd\n\n";
    echo "CLI:\n";
    echo "  php usm.php list\n";
    echo "  php usm.php add CODE NAME PASSWORD [PERMISSION...]\n";
    echo "  php usm.php password CODE PASSWORD\n";
    echo "  php usm.php grant CODE PERMISSION [PERMISSION...]\n";
    echo "  php usm.php admin CODE\n\n";
    echo "Browser examples:\n";
    echo "  usm.php?action=list\n";
    echo "  usm.php?action=admin&code=MGR&apply=1\n";
    echo "  usm.php?action=grant&code=MGR&permissions=ADMINBUTTON,MDI_USER_ACCESS&apply=1\n";
    echo "  usm.php?action=add&code=04&name=USER&password=1234&permissions=MDI_USER_ACCESS&apply=1\n\n";
    echo "Browser write actions require apply=1.\n";
}

function listUsers(): void
{
    $users = DB::table('userm')->orderBy('code')->get();

    if ($users->isEmpty()) {
        echo "No users found.\n";
        return;
    }

    foreach ($users as $user) {
        $code = trim((string) ($user->code ?? ''));
        $name = trim((string) ($user->name ?? ''));
        $permissions = DB::table('userd')
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($code)])
            ->orderBy('menuitem')
            ->pluck('menuitem')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        echo "{$code}\t{$name}";
        if ($permissions) {
            echo "\t" . implode(', ', $permissions);
        }
        echo "\n";
    }
}

function createOrUpdateUser(string $code, string $name, string $password, array $permissions): void
{
    ensureWritable();
    $code = normalizeCode($code);
    $name = trim($name);
    $password = trim($password);

    if ($code === '' || $name === '' || $password === '') {
        throw new InvalidArgumentException('code, name, and password are required.');
    }

    $payload = userPayload($code, $name, $password);
    $exists = DB::table('userm')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists();

    DB::transaction(function () use ($code, $payload, $exists, $permissions): void {
        if ($exists) {
            $update = $payload;
            unset($update['code']);
            DB::table('userm')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->update($update);
            echo "Updated user {$code}.\n";
        } else {
            DB::table('userm')->insert($payload);
            echo "Created user {$code}.\n";
        }

        if ($permissions) {
            grantPermissions($code, $permissions, false);
        }
    });
}

function updatePassword(string $code, string $password): void
{
    ensureWritable();
    $code = normalizeCode($code);
    $password = trim($password);

    if ($code === '' || $password === '') {
        throw new InvalidArgumentException('code and password are required.');
    }

    $updated = DB::table('userm')
        ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
        ->update(['pcode' => PasswordService::encrypt($password)]);

    echo $updated ? "Password updated for {$code}.\n" : "User not found: {$code}\n";
}

function grantPermissions(string $code, array $permissions, bool $checkWritable = true): void
{
    if ($checkWritable) {
        ensureWritable();
    }

    $code = normalizeCode($code);
    $permissions = array_values(array_unique(array_filter(array_map(
        fn ($value) => strtoupper(trim((string) $value)),
        $permissions
    ))));

    if ($code === '' || !$permissions) {
        throw new InvalidArgumentException('code and at least one permission are required.');
    }

    $exists = DB::table('userm')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists();
    if (!$exists) {
        throw new InvalidArgumentException("User not found: {$code}");
    }

    foreach ($permissions as $permission) {
        $hasPermission = DB::table('userd')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->whereRaw('UPPER(TRIM(menuitem)) = ?', [$permission])
            ->exists();

        if ($hasPermission) {
            echo "Already granted {$permission} to {$code}.\n";
            continue;
        }

        DB::table('userd')->insert([
            'code' => $code,
            'menuitem' => $permission,
        ]);
        echo "Granted {$permission} to {$code}.\n";
    }
}

function userPayload(string $code, string $name, string $password): array
{
    $columns = array_map('strtolower', Schema::getColumnListing('userm'));
    $payload = [
        'code' => $code,
        'name' => $name,
        'pcode' => PasswordService::encrypt($password),
    ];

    foreach (['maxcredit', 'maxdisc', 'maxadjwgtbc', 'minvaperc', 'maxdiscperc'] as $column) {
        if (in_array($column, $columns, true)) {
            $payload[$column] = 0;
        }
    }

    return $payload;
}

function ensureWritable(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if ((string) ($_GET['apply'] ?? '') !== '1') {
        throw new RuntimeException('Browser write actions require apply=1.');
    }
}

function normalizePermission(string $permission): string
{
    return strtoupper(trim($permission));
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function valueArg(array $args, int $index, string $name): string
{
    if (PHP_SAPI === 'cli') {
        return (string) ($args[$index] ?? '');
    }

    return (string) ($_GET[$name] ?? '');
}

function permissionArgs(array $args, int $start): array
{
    if (PHP_SAPI === 'cli') {
        return array_slice($args, $start);
    }

    $raw = (string) ($_GET['permissions'] ?? $_GET['permission'] ?? '');
    if ($raw === '') {
        return [];
    }

    return preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function normalizeCode(string $code): string
{
    return strtoupper(trim($code));
}
