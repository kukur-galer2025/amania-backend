<?php

namespace Database\Seeders;

use App\Models\EventBankAccount;
use Illuminate\Database\Seeder;

class EventBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Assign 2 bank account default ke Event ID 1 sampai 10
        for ($i = 1; $i <= 10; $i++) {
            EventBankAccount::create([
                'event_id' => $i,
                'bank_code' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'PT Amania Edukasi',
            ]);
            
            EventBankAccount::create([
                'event_id' => $i,
                'bank_code' => 'MANDIRI',
                'account_number' => '0987654321',
                'account_holder' => 'PT Amania Edukasi',
            ]);
        }
    }
}