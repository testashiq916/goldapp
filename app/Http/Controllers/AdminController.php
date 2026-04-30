<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\UserM;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        if (!$request->session()->has('user_code')) {
            redirect('/login')->send();
            exit;
        }

        $userCode = strtoupper(trim((string) $request->session()->get('user_code', '')));
        $allowedCodes = ['ADMIN', 'MGR', 'DEVELOPER'];
        $hasAdminPermission = $this->hasTable('userd')
            ? DB::table('userd')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
                ->whereRaw('UPPER(TRIM(menuitem)) = ?', ['ADMINBUTTON'])
                ->exists()
            : false;

        if (!in_array($userCode, $allowedCodes, true) && !$hasAdminPermission) {
            abort(403, 'Access Denied: Only administrators can access this page.');
        }
    }

    public function users(Request $request): View
    {
        $this->requireAdmin($request);
        $users = UserM::getAllUsers();
        $message = $request->session()->pull('admin_message');

        return view('admin.users', compact('users', 'message'));
    }

    public function addUser(Request $request): View|RedirectResponse
    {
        $this->requireAdmin($request);
        $error = null;

        if ($request->isMethod('post')) {
            $code = trim($request->input('code', ''));
            $name = trim($request->input('name', ''));
            $password = $request->input('password', '');
            $email = trim($request->input('email', '')) ?: null;
            $mobile = trim($request->input('mobile', '')) ?: null;

            if ($code === '' || $name === '' || $password === '') {
                $error = 'User Code, Name, and Password are required';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters';
            } elseif (UserM::getUserByCode($code)) {
                $error = 'User code already exists';
            } else {
                $result = UserM::createUser($code, $name, $password, $email, $mobile);
                if ($result) {
                    $request->session()->put('admin_message', 'User created successfully');
                    return redirect('/admin/users');
                }
                $error = 'Failed to create user';
            }
        }

        return view('admin.user-form', [
            'mode' => 'add',
            'user' => null,
            'error' => $error,
        ]);
    }

    public function editUser(Request $request, string $code): View|RedirectResponse
    {
        $this->requireAdmin($request);
        $user = UserM::getUserByCode($code);
        if (!$user) {
            abort(404, 'User not found');
        }

        $error = null;

        if ($request->isMethod('post')) {
            $name = trim($request->input('name', ''));
            $email = trim($request->input('email', '')) ?: null;
            $mobile = trim($request->input('mobile', '')) ?: null;
            $status = $request->input('status', 'active');
            $newPassword = $request->input('password', '');

            if ($name === '') {
                $error = 'Name is required';
            } else {
                UserM::updateUser($code, $name, $email, $mobile, $status);

                if ($newPassword !== '' && strlen($newPassword) >= 6) {
                    UserM::updatePassword($code, $newPassword);
                } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
                    $error = 'Password must be at least 6 characters';
                }

                if (!$error) {
                    $request->session()->put('admin_message', 'User updated successfully');
                    return redirect('/admin/users');
                }
            }

            $user = UserM::getUserByCode($code);
        }

        return view('admin.user-form', [
            'mode' => 'edit',
            'user' => $user,
            'error' => $error,
        ]);
    }

    public function deleteUser(Request $request, string $code): RedirectResponse
    {
        $this->requireAdmin($request);

        if ($code === 'admin') {
            $request->session()->put('admin_message', 'Cannot delete admin user');
        } else {
            $result = UserM::deleteUser($code);
            $request->session()->put('admin_message', $result ? 'User deleted successfully' : 'Failed to delete user');
        }

        return redirect('/admin/users');
    }

    public function userPermissions(Request $request, string $code): View|RedirectResponse
    {
        $this->requireAdmin($request);
        $user = UserM::getUserByCode($code);
        if (!$user) {
            abort(404, 'User not found');
        }

        if ($request->isMethod('post')) {
            $selected = $request->input('permissions', []);
            UserM::clearPermissions($code);
            foreach ($selected as $perm) {
                UserM::addPermission($code, $perm);
            }
            $request->session()->put('admin_message', 'Permissions updated successfully');
            return redirect('/admin/users');
        }

        $userPermissions = UserM::getUserPermissions($code);

        return view('admin.user-permissions', [
            'user' => $user,
            'userPermissions' => $userPermissions,
            'availablePermissions' => $this->getAvailablePermissions(),
        ]);
    }

    public function userHistory(Request $request, string $code): View
    {
        $this->requireAdmin($request);
        $user = UserM::getUserByCode($code);
        if (!$user) {
            abort(404, 'User not found');
        }

        $history = UserM::getLoginHistory($code, 100);

        return view('admin.user-history', [
            'user' => $user,
            'history' => $history,
        ]);
    }

    private function getAvailablePermissions(): array
    {
        return Permission::groupedLabels();
    }
}
