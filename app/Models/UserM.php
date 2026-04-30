<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserM
{
    protected string $table = 'userm';

    public static function fpencrypt(string $password): string
    {
        return self::fpCrypt($password, 1);
    }

    public static function encryptPassword(string $password): string
    {
        return self::fpCrypt($password, 1);
    }

    public static function authenticateLegacy(string $password): ?object
    {
        $enc = self::fpencrypt($password);
        $hex = strtoupper(bin2hex($enc));

        return DB::table('userm')
            ->select(['code', 'name'])
            ->whereRaw('UPPER(HEX(pcode)) = ?', [$hex])
            ->first();
    }

    public static function authenticateForCode(string $code, string $password): ?object
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $enc = self::fpencrypt($password);
        $hex = strtoupper(bin2hex($enc));
        $sha = 'SHA256:' . hash('sha256', $password);

        $cols = ['code', 'name'];
        foreach (['email', 'mobile', 'status'] as $c) {
            if (Schema::hasColumn('userm', $c)) {
                $cols[] = $c;
            }
        }

        $query = DB::table('userm')
            ->select($cols)
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($code)])
            ->where(function ($q) use ($enc, $hex, $sha, $password) {
                $q->whereRaw('UPPER(HEX(pcode)) = ?', [$hex])
                    ->orWhere('pcode', $enc)
                    ->orWhere('pcode', $sha)
                    ->orWhere('pcode', $password);
            });

        return $query->first();
    }

    public static function verifyLegacyPasswordForCode(string $code, string $password): bool
    {
        $enc = self::fpencrypt($password);
        $hex = strtoupper(bin2hex($enc));

        return DB::table('userm')
            ->whereRaw('TRIM(code) = ?', [trim($code)])
            ->whereRaw('UPPER(HEX(pcode)) = ?', [$hex])
            ->exists();
    }

    private static function fpCrypt(string $pcode, int $mode): string
    {
        $trimmed = trim($pcode);
        $length = strlen($trimmed);
        $result = ' ';

        for ($index = 0; $index < $length; $index++) {
            $char = substr($pcode, $index, 1);
            $offset = ($index + 1) * ($length + 2);
            $ascii = ord($char);
            $result .= chr($mode === 1 ? $ascii + $offset : $ascii - $offset);
        }

        $result = trim($result);

        if ($result === '') {
            return '';
        }

        return mb_convert_encoding($result, 'UTF-8', 'Windows-1252');
    }

    public static function authenticate(string $password): ?object
    {
        $enc = self::fpencrypt($password);
        $sha = 'SHA256:' . hash('sha256', $password);

        $query = DB::table('userm')
            ->select(['code', 'name', 'email', 'mobile'])
            ->where(function ($q) use ($enc, $sha, $password) {
                $q->where('pcode', $enc)
                    ->orWhere('pcode', $sha)
                    ->orWhere('pcode', $password);
            });

        if (Schema::hasColumn('userm', 'status')) {
            $query->where('status', 'active');
        }

        return $query->first();
    }

    public static function getAllUsers(): array
    {
        $cols = ['code', 'name'];
        foreach (['email', 'mobile', 'status'] as $c) {
            if (Schema::hasColumn('userm', $c)) {
                $cols[] = $c;
            }
        }

        return DB::table('userm')
            ->select($cols)
            ->orderBy('code')
            ->get()
            ->all();
    }

    public static function getUserByCode(string $code): ?object
    {
        $cols = ['code', 'name', 'pcode'];
        foreach (['email', 'mobile', 'status'] as $c) {
            if (Schema::hasColumn('userm', $c)) {
                $cols[] = $c;
            }
        }

        return DB::table('userm')
            ->select($cols)
            ->whereRaw('TRIM(code) = ?', [trim($code)])
            ->first();
    }

    public static function createUser(string $code, string $name, string $password, ?string $email = null, ?string $mobile = null): bool
    {
        $data = [
            'code' => strtoupper(trim($code)),
            'name' => trim($name),
            'pcode' => self::encryptPassword($password),
        ];

        if (Schema::hasColumn('userm', 'email')) {
            $data['email'] = $email;
        }
        if (Schema::hasColumn('userm', 'mobile')) {
            $data['mobile'] = $mobile;
        }
        if (Schema::hasColumn('userm', 'status')) {
            $data['status'] = 'active';
        }

        return DB::table('userm')->insert($data);
    }

    public static function updateUser(string $code, string $name, ?string $email = null, ?string $mobile = null, string $status = 'active'): bool
    {
        $data = ['name' => trim($name)];

        if (Schema::hasColumn('userm', 'email')) {
            $data['email'] = $email;
        }
        if (Schema::hasColumn('userm', 'mobile')) {
            $data['mobile'] = $mobile;
        }
        if (Schema::hasColumn('userm', 'status')) {
            $data['status'] = $status;
        }

        return DB::table('userm')
            ->whereRaw('TRIM(code) = ?', [trim($code)])
            ->update($data) >= 0;
    }

    public static function updatePassword(string $code, string $password): bool
    {
        return DB::table('userm')
            ->whereRaw('TRIM(code) = ?', [trim($code)])
            ->update(['pcode' => self::encryptPassword($password)]) >= 0;
    }

    public static function deleteUser(string $code): bool
    {
        DB::table('userd')->whereRaw('TRIM(code) = ?', [trim($code)])->delete();
        if (Schema::hasTable('userhist')) {
            DB::table('userhist')->whereRaw('TRIM(code) = ?', [trim($code)])->delete();
        }
        return DB::table('userm')->whereRaw('TRIM(code) = ?', [trim($code)])->delete() > 0;
    }

    public static function getUserPermissions(string $code): array
    {
        return DB::table('userd')
            ->whereRaw('TRIM(code) = ?', [trim($code)])
            ->pluck('menuitem')
            ->map(fn ($v) => trim((string) $v))
            ->all();
    }

    public static function clearPermissions(string $code): void
    {
        DB::table('userd')->whereRaw('TRIM(code) = ?', [trim($code)])->delete();
    }

    public static function addPermission(string $code, string $menuitem): void
    {
        DB::table('userd')->insert([
            'code' => trim($code),
            'menuitem' => trim($menuitem),
        ]);
    }

    public static function getLoginHistory(string $code, int $limit = 100): array
    {
        if (!Schema::hasTable('userhist')) {
            return [];
        }

        $cols = ['code'];
        foreach (['tdate', 'ttime', 'ltime', 'ip', 'useragent'] as $c) {
            if (Schema::hasColumn('userhist', $c)) {
                $cols[] = $c;
            }
        }

        $query = DB::table('userhist')
            ->select($cols)
            ->whereRaw('TRIM(code) = ?', [trim($code)])
            ->orderByDesc('tdate');

        if (in_array('ttime', $cols)) {
            $query->orderByDesc('ttime');
        }

        return $query->limit($limit)->get()->all();
    }
}
