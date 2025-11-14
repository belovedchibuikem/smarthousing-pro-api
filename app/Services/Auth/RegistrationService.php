<?php

namespace App\Services\Auth;

use App\Models\Tenant\User;
use App\Models\Tenant\Member;
use App\Mail\VerificationEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistrationService
{
    public function generateMemberNumber(): string
    {
        $prefix = 'FRSC';
        $year = date('Y');
        $month = date('m');
        
        // Get the last member number for this year/month
        $lastMember = Member::where('member_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('member_number', 'desc')
            ->first();
        
        if ($lastMember) {
            $lastNumber = (int) substr($lastMember->member_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function sendVerificationEmail(User $user): void
    {
        $token = $this->generateVerificationToken();
        Mail::to($user->email)->send(new VerificationEmail($user, $token));
    }

    public function generateVerificationToken(): string
    {
        return Str::random(64);
    }

    public function createMemberProfile(User $user, array $data): Member
    {
        return Member::create([
            'user_id' => $user->id,
            'member_number' => $this->generateMemberNumber(),
            'staff_id' => $data['staff_id'] ?? null,
            'ippis_number' => $data['ippis_number'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'nationality' => $data['nationality'] ?? 'Nigerian',
            'state_of_origin' => $data['state_of_origin'] ?? null,
            'lga' => $data['lga'] ?? null,
            'residential_address' => $data['residential_address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'rank' => $data['rank'] ?? null,
            'department' => $data['department'] ?? null,
            'command_state' => $data['command_state'] ?? null,
            'employment_date' => $data['employment_date'] ?? null,
            'years_of_service' => $data['years_of_service'] ?? null,
            'membership_type' => 'regular',
            'kyc_status' => 'pending',
        ]);
    }
}