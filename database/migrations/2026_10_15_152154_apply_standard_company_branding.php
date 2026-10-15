<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $oldFooter = 'Thank you for choosing Air Courier. Please keep this receipt for your records.';
            foreach (DB::table('company_settings')->where('name', 'Air Courier')->orWhere('receipt_footer', $oldFooter)->get() as $company) {
                $before = ['name' => $company->name, 'receipt_footer' => $company->receipt_footer];
                $after = [
                    'name' => $company->name === 'Air Courier' ? 'Standard' : $company->name,
                    'receipt_footer' => $company->receipt_footer === $oldFooter
                        ? 'Thank you for choosing Standard. Please keep this receipt for your records.'
                        : $company->receipt_footer,
                ];
                DB::table('company_settings')->where('id', $company->id)->update($after + ['updated_at' => now()]);
                DB::table('audit_logs')->insert([
                    'staff_id' => null,
                    'action' => 'company.branding_updated',
                    'subject_type' => 'CompanySetting',
                    'subject_id' => $company->id,
                    'before' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode($after, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Retain the business identity and its audit history when reverting code.
    }
};
