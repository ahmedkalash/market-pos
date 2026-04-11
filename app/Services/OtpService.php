<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Notifications\SendOtpNotification;
use Illuminate\Support\Facades\Notification;

class OtpService
{
    /**
     * Generate a new OTP and send it.
     *
     * @param  string  $type  ('email', 'phone')
     * @param  int  $valid_for  (minutes)
     */
    public function generate(string $identifier, string $type = 'email', int $valid_for = 10): OtpVerification
    {
        // Delete any existing OTPs for this identifier to avoid clutter
        OtpVerification::where('identifier', $identifier)
            ->where('type', $type)
            ->delete();

        $code = (string) rand(100000, 999999);

        $otp = OtpVerification::create([
            'identifier' => $identifier,
            'type' => $type,
            'code' => $code,
            'expires_at' => now()->addMinutes($valid_for),
        ]);

        $channels = [
            'email' => 'mail',
            //
        ];

        // Send the notification
        Notification::route($channels[$type], $identifier)
            ->notify(new SendOtpNotification($code));

        return $otp;
    }

    /**
     * Verify the OTP code.
     */
    public function verify(string $identifier, string $code, string $type = 'email'): bool
    {
        $otp = OtpVerification::where('identifier', $identifier)
            ->where('type', $type)
            ->where('code', $code)
            ->first();

        if (! $otp || $otp->isExpired()) {
            return false;
        }

        return true;
    }
}
