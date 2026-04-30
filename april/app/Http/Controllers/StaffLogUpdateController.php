<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rats\Zkteco\Lib\ZKTeco;

class StaffLogUpdateController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('staff-log-update.index', [
            'title' => 'Staff Log Update',
        ]);
    }

    // Connect to device and get info
    public function connectDevice(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $ip   = trim($request->input('ip', '192.168.1.201'));
        $port = (int) ($request->input('port', 4370));

        if ($ip === '') return response()->json(['ok' => false, 'message' => 'IP address required']);

        try {
            $zk = new ZKTeco($ip, $port);
            $connected = $zk->connect();

            if (!$connected) {
                return response()->json(['ok' => false, 'message' => 'Cannot connect to device at ' . $ip . ':' . $port]);
            }

            $deviceName = $zk->deviceName() ?: 'Unknown';
            $serialNo   = $zk->serialNumber() ?: '';

            $zk->disconnect();

            return response()->json([
                'ok'         => true,
                'message'    => 'Connected to ' . $ip,
                'deviceName' => $deviceName,
                'serialNo'   => $serialNo,
            ]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Connection error: ' . $e->getMessage()]);
        }
    }

    // Download user list from device
    public function downloadUsers(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $ip   = trim($request->input('ip', '192.168.1.201'));
        $port = (int) ($request->input('port', 4370));

        try {
            $zk = new ZKTeco($ip, $port);
            if (!$zk->connect()) {
                return response()->json(['ok' => false, 'message' => 'Cannot connect to device']);
            }

            $users = $zk->getUser();
            $zk->disconnect();

            if (!is_array($users)) {
                return response()->json(['ok' => false, 'message' => 'Failed to read user list']);
            }

            // Build idno-to-code mapping from clients
            $idMap = DB::table('clients')
                ->where('ctype', 'S')
                ->whereNotNull('idno')
                ->where('idno', '!=', '')
                ->select('code', 'name', 'idno')
                ->get()
                ->keyBy('idno');

            $rows = [];
            foreach ($users as $u) {
                $enrollNo = trim($u['userid'] ?? $u['uid'] ?? '');
                $name     = trim($u['name'] ?? '');
                $role     = (int) ($u['role'] ?? 0);
                $client   = $idMap[$enrollNo] ?? null;

                $rows[] = [
                    'idno'      => $enrollNo,
                    'name'      => $name,
                    'privilege' => $role,
                    'code'      => $client ? trim($client->code) : '',
                    'staffname' => $client ? trim($client->name) : '',
                ];
            }

            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Download attendance logs from device
    public function downloadLogs(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $ip     = trim($request->input('ip', '192.168.1.201'));
        $port   = (int) ($request->input('port', 4370));
        $date1  = $request->input('date1', '');
        $date2  = $request->input('date2', '');

        try {
            $zk = new ZKTeco($ip, $port);
            if (!$zk->connect()) {
                return response()->json(['ok' => false, 'message' => 'Cannot connect to device']);
            }

            $attendance = $zk->getAttendance();
            $zk->disconnect();

            if (!is_array($attendance)) {
                return response()->json(['ok' => false, 'message' => 'Failed to read attendance logs']);
            }

            // Build idno-to-code mapping
            $idMap = DB::table('clients')
                ->where('ctype', 'S')
                ->whereNotNull('idno')
                ->where('idno', '!=', '')
                ->select('code', 'name', 'idno')
                ->get()
                ->keyBy('idno');

            $rows = [];
            foreach ($attendance as $a) {
                $enrollNo  = trim($a['id'] ?? $a['uid'] ?? '');
                $timestamp = $a['timestamp'] ?? '';
                $state     = (int) ($a['state'] ?? 0);

                // Parse timestamp "Y-m-d H:i:s"
                $dt = strtotime($timestamp);
                if (!$dt) continue;

                $tdate = date('Y-m-d', $dt);
                $ttime = date('H:i:s', $dt);

                // Date range filter
                if ($date1 && $tdate < $date1) continue;
                if ($date2 && $tdate > $date2) continue;

                $client = $idMap[$enrollNo] ?? null;

                $rows[] = [
                    'idno'      => $enrollNo,
                    'scode'     => $client ? trim($client->code) : '',
                    'staffname' => $client ? trim($client->name) : '',
                    'tdate'     => $tdate,
                    'ttime'     => $ttime,
                    'attstate'  => $state,
                    'status'    => '?', // Will be determined during update
                ];
            }

            // Sort by date then time
            usort($rows, function ($a, $b) {
                $c = strcmp($a['tdate'], $b['tdate']);
                return $c !== 0 ? $c : strcmp($a['ttime'], $b['ttime']);
            });

            return response()->json(['ok' => true, 'rows' => $rows, 'total' => count($rows)]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Clear attendance logs on device
    public function clearDeviceLogs(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $ip   = trim($request->input('ip', '192.168.1.201'));
        $port = (int) ($request->input('port', 4370));

        try {
            $zk = new ZKTeco($ip, $port);
            if (!$zk->connect()) {
                return response()->json(['ok' => false, 'message' => 'Cannot connect to device']);
            }

            $result = $zk->clearAttendance();
            $zk->disconnect();

            if ($result) {
                return response()->json(['ok' => true, 'message' => 'Device logs cleared']);
            }
            return response()->json(['ok' => false, 'message' => 'Failed to clear device logs']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Load staff list (users with idno mapping)
    public function loadUsers(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $rows = DB::table('clients')
            ->where('ctype', 'S')
            ->select('code', 'name', 'idno')
            ->orderBy('code')
            ->get()
            ->map(function ($r) {
                return [
                    'code' => trim($r->code ?? ''),
                    'name' => trim($r->name ?? ''),
                    'idno' => trim($r->idno ?? ''),
                ];
            })->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Load existing logs from staff_log for a date range
    public function loadLogs(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $date1 = $request->query('date1', date('Y-m-d'));
        $date2 = $request->query('date2', date('Y-m-d'));

        if (!$this->hasTable('staff_log')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = DB::table('staff_log')
            ->leftJoin('clients', function ($j) {
                $j->on('staff_log.scode', '=', 'clients.code')
                  ->where('clients.ctype', '=', 'S');
            })
            ->where('staff_log.tdate', '>=', $date1)
            ->where('staff_log.tdate', '<=', $date2)
            ->select(
                'staff_log.scode', 'staff_log.idno', 'staff_log.tdate',
                'staff_log.ttime', 'staff_log.status',
                'clients.name as staffname'
            )
            ->orderBy('staff_log.tdate')
            ->orderBy('staff_log.ttime')
            ->get()
            ->map(function ($r) {
                return [
                    'scode'    => trim($r->scode ?? ''),
                    'idno'     => trim($r->idno ?? ''),
                    'staffname' => trim($r->staffname ?? ''),
                    'tdate'    => $r->tdate ?? '',
                    'ttime'    => $r->ttime ?? '',
                    'status'   => trim($r->status ?? ''),
                ];
            })->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Update logs — insert new log entries (with duplicate check and I/O status)
    public function updateLogs(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $entries = (array) $request->input('entries', []);
        if (empty($entries)) return response()->json(['ok' => false, 'message' => 'No entries to update']);

        if (!$this->hasTable('staff_log')) {
            return response()->json(['ok' => false, 'message' => 'staff_log table missing']);
        }

        // Build idno-to-code mapping from clients
        $idMap = DB::table('clients')
            ->where('ctype', 'S')
            ->whereNotNull('idno')
            ->where('idno', '!=', '')
            ->pluck('code', 'idno')
            ->toArray();

        $inserted = 0;
        $skipped  = 0;

        DB::beginTransaction();
        try {
            foreach ($entries as $entry) {
                $idno  = trim($entry['idno'] ?? '');
                $scode = trim($entry['scode'] ?? '');
                $tdate = $entry['tdate'] ?? '';
                $ttime = $entry['ttime'] ?? '';

                if ($idno === '' && $scode === '') continue;
                if ($tdate === '' || $ttime === '') continue;

                // Resolve scode from idno if not provided
                if ($scode === '' && $idno !== '' && isset($idMap[$idno])) {
                    $scode = $idMap[$idno];
                }

                // Check for duplicate
                $exists = DB::table('staff_log')
                    ->where('idno', $idno)
                    ->where('tdate', $tdate)
                    ->where('ttime', $ttime)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Determine status: I (In) or O (Out)
                $status = 'I';
                $earlier = DB::table('staff_log')
                    ->where('idno', $idno)
                    ->where('tdate', $tdate)
                    ->where('ttime', '<', $ttime)
                    ->exists();

                if ($earlier) {
                    $status = 'O';
                }

                DB::table('staff_log')->insert([
                    'scode'  => $scode,
                    'idno'   => $idno,
                    'tdate'  => $tdate,
                    'ttime'  => $ttime,
                    'status' => $status,
                ]);
                $inserted++;
            }

            DB::commit();
            return response()->json([
                'ok'      => true,
                'message' => "Inserted {$inserted} entries, skipped {$skipped} duplicates",
                'inserted' => $inserted,
                'skipped'  => $skipped,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Delete a specific log entry
    public function deleteLog(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $idno  = trim($request->input('idno', ''));
        $tdate = trim($request->input('tdate', ''));
        $ttime = trim($request->input('ttime', ''));

        if ($idno === '' || $tdate === '' || $ttime === '') {
            return response()->json(['ok' => false, 'message' => 'Missing fields']);
        }

        DB::table('staff_log')
            ->where('idno', $idno)
            ->where('tdate', $tdate)
            ->where('ttime', $ttime)
            ->delete();

        return response()->json(['ok' => true, 'message' => 'Log entry deleted']);
    }

    // Clear all logs in a date range
    public function clearLogs(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $date1 = trim($request->input('date1', ''));
        $date2 = trim($request->input('date2', ''));

        if ($date1 === '' || $date2 === '') {
            return response()->json(['ok' => false, 'message' => 'Date range required']);
        }

        $deleted = DB::table('staff_log')
            ->where('tdate', '>=', $date1)
            ->where('tdate', '<=', $date2)
            ->delete();

        return response()->json(['ok' => true, 'message' => "Deleted {$deleted} entries"]);
    }
}
