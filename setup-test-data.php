<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Tenant\User;
use App\Models\Tenant\Member;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Setting up test data...\n";
    
    // Create a test user
    $user = User::firstOrCreate(
        ['email' => 'test@example.com'],
        [
            'first_name' => 'Test',
            'last_name' => 'Member',
            'password' => bcrypt('password'),
            'role' => 'member',
            'status' => 'active',
            'phone' => '+234 803 123 4567'
        ]
    );
    
    echo "Created user: {$user->email}\n";
    
    // Create a test member
    $member = Member::firstOrCreate(
        ['user_id' => $user->id],
        [
            'member_number' => 'TEST-001',
            'staff_id' => 'STAFF-001',
            'ippis_number' => 'IPPIS-001',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'marital_status' => 'single',
            'nationality' => 'Nigerian',
            'state_of_origin' => 'Lagos',
            'lga' => 'Ikeja',
            'residential_address' => '123 Test Street, Lagos',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'rank' => 'Inspector',
            'department' => 'Traffic',
            'command_state' => 'Lagos',
            'employment_date' => '2020-01-01',
            'years_of_service' => 4,
            'membership_type' => 'regular',
            'kyc_status' => 'pending',
            'status' => 'active'
        ]
    );
    
    echo "Created member: {$member->member_number}\n";
    echo "Member ID: {$member->id}\n";
    
    // Create a test document
    $document = \App\Models\Tenant\Document::firstOrCreate(
        [
            'member_id' => $member->id,
            'type' => 'national_id'
        ],
        [
            'title' => 'National ID Card',
            'description' => 'Test national ID document',
            'file_path' => 'documents/test/national_id.pdf',
            'file_size' => 1024000,
            'mime_type' => 'application/pdf',
            'status' => 'pending',
            'uploaded_by' => $user->id
        ]
    );
    
    echo "Created document: {$document->title}\n";
    
    echo "Test data setup complete!\n";
    echo "You can now test with member ID: {$member->id}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}























