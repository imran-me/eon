<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendenceSetting;
use App\Models\Company;
use App\Models\DeviceSetting;
use App\Models\DeviceUser;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;



class AttendanceController extends Controller
{
    public function serverStatus()
    {
        try {
            $status = 'Ok';
            Log::info($status);
            return response()->json(['status' => $status]);
        } catch (\Exception $err) {
            return response()->json(['error' => $err->getMessage()], 500);
        }
    }

    public function device($data)
    {
        // try {
        Log::info($data);
        return response()->json(['data' => $data]);
        // } catch (\Exception $err) {
        //     return response()->json(['error' => $err->getMessage()], 500);
        // }
    }


    public function attendanceLog($data)
    {
        $payload = json_decode($data, true);
        // logger('Attendance Payload:', $payload);
        if (!$payload || !isset($payload['logs'])) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $deviceSerial = $payload['device_sn'] ?? $payload['sn'] ?? null;
        // logger('Device Serial: ' . $deviceSerial);
        if (!$deviceSerial) {
            logger('Missing device serial', $payload);
            return response()->json(['error' => 'Device serial missing'], 400);
        }

        // 🔹 Attendance setting (grace period etc.)
        $attendanceSetting = AttendenceSetting::find(1);

        // 🔹 Validate device ONCE
        $device = DeviceSetting::where('device_serial_no', $deviceSerial)
            ->where('is_active', 1)
            ->first();
        // logger('Device Lookup:', ['device' => $deviceSerial, 'found' => (bool)$device]);
        if (!$device) {
            // logger("Unauthorized device: {$deviceSerial}");
            return response()->json(['error' => 'Unauthorized device'], 403);
        }

        foreach ($payload['logs'] as $log) {

            if (empty($log['enrollid']) || empty($log['time'])) {
                continue;
            }

            $deviceUserId = $log['enrollid'];
            $timeStamp    = strtotime($log['time']);

            if (!$timeStamp) continue;

            $date = date('Y-m-d', $timeStamp);
            $time = date('H:i:s', $timeStamp);

            $user = DeviceUser::where('device_user_id', $deviceUserId)
                ->where('device_id', $device->id)
                ->with('user')
                ->first()
                ?->user;

            // logger('User Lookup:', ['user' => $user]);
            if (!$user) continue;

            // 🔹 Is first punch of the day?
            $isFirstCheckIn = !AttendanceLog::where('user_id', $user->id)
                ->where('date', $date)
                ->exists();

            // 🔹 Find open log (checked in but not out)
            $openLog = AttendanceLog::where('user_id', $user->id)
                ->where('date', $date)
                ->whereNotNull('check_in')
                ->whereNull('check_out')
                ->latest()
                ->first();

            if ($openLog) {
                // ✅ CHECK-OUT
                $openLog->update([
                    'check_out' => $time,
                    'note'      => 'Exited from ' . ($device->company->name ?? null),
                ]);
            } else {

                // 🔐 Duplicate check-in protection
                $exists = AttendanceLog::where('user_id', $user->id)
                    ->where('date', $date)
                    ->where('check_in', $time)
                    ->exists();

                if ($exists) continue;

                // ✅ CHECK-IN
                AttendanceLog::create([
                    'user_id'  => $user->id,
                    'date'     => $date,
                    'check_in' => $time,
                    'shift_id' => $user->shift_id,
                    'note'     => 'Entered in ' . ($device->company->name ?? null),
                    'source'   => 'machine',
                ]);

                // 🔔 Late notification (ONLY first check-in)
                if ($isFirstCheckIn && $user->shift && $attendanceSetting) {

                    $graceMinutes = (int) $attendanceSetting->time_after_checkin;

                    $shiftStart = Carbon::parse($date . ' ' . $user->shift->start_time);
                    $lateThreshold = $shiftStart->copy()->addMinutes($graceMinutes);
                    $checkInTime = Carbon::parse($date . ' ' . $time);

                    if ($checkInTime->gt($lateThreshold)) {

                        $lateMinutes = (int) $lateThreshold->diffInMinutes($checkInTime);

                        if (!empty($user->email) && $lateMinutes > 0) {
                            $this->sendMail($lateMinutes, $user->email, $user->name);
                        }

                        if (!empty($user->phone) && $lateMinutes > 0) {
                            $smsMessage = "Dear {$user->name}, today you arrived at the office {$lateMinutes} minutes late. Please try to adhere to office hours in the future.";
                            sendSms($user->phone, $smsMessage);
                        }
                    }
                }
            }


            // 🔹 Approved Early Leave check — waives the generic device note in favor of a clear message
            $approvedEarlyLeave = Leave::findApprovedEarlyLeaveForDate($user->id, $date);

            // 🔹 DAILY ATTENDANCE SUMMARY
            $attendance = Attendance::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'date'    => $date,
                ],
                [
                    'company_id'               => $user->company_id,
                    'shift_id'                 => $user->shift_id,
                    'status'                   => 'present',
                    'check_in'                 => $time, // only set on first punch
                    'attendence_setting_id'    => $attendanceSetting->id ?? null,
                    'note'                     => $approvedEarlyLeave
                        ? 'Approved Early Leave (until ' . Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')'
                        : 'Now entered in ' . ($device->company->name ?? null),
                ]
            );

            // 🔹 Update summary checkout only on checkout punch
            if ($openLog) {
                $attendance->update([
                    'check_out' => $time,
                    'note'      => $approvedEarlyLeave
                        ? 'Approved Early Leave (from ' . Carbon::parse($approvedEarlyLeave->leave_time)->format('h:i A') . ')'
                        : 'Exited from ' . ($device->company->name ?? null),
                ]);
            }
        }

        return response()->json(['message' => 'Attendance processed'], 200);
    }

    public function sendMail($lateMinutes, $email, $name){
        $subject = 'দেরিতে অফিসে উপস্থিতির নোটিশ';
        $htmlContent = '<p>প্রিয় ' . $name . ',</p>
                        <p>আজ আপনি অফিসে ' . $lateMinutes . ' মিনিট দেরিতে উপস্থিত হয়েছেন। অনুগ্রহ করে ভবিষ্যতে অফিস সময় মেনে চলবেন।</p>';
        sendBrevoMail(
            $email,
            $name,
            $subject,
            $htmlContent
        );
    }


    // public function senddMail($lateMinutes, $email, $name)
    // {
    //     $response = Http::withOptions([
    //         'verify' => false,
    //     ])->withHeaders([
    //         'api-key'      => config('services.brevo.key'),
    //         'Content-Type' => 'application/json',
    //     ])->post('https://api.brevo.com/v3/smtp/email', [
    //         'to' => [
    //             [
    //                 'email' => $email,
    //                 'name'  => $name,
    //             ],
    //         ],
    //         'subject' => 'দেরিতে অফিসে উপস্থিতির নোটিশ',
    //         'htmlContent' => '<p>প্রিয় ' . $name . ',</p>
    //                                     <p>
    //                                         আজ আপনি অফিসে ' . $lateMinutes . ' মিনিট দেরিতে উপস্থিত হয়েছেন।
    //                                         অনুগ্রহ করে ভবিষ্যতে অফিস সময় মেনে চলবেন।</p>',
    //         'sender' => [
    //             'email' => 'no-reply@epaltravels.com',
    //             'name'  => 'Epal Group',
    //         ],
    //     ]);
    // }
}
