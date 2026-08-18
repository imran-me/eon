<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VisaRequiredDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            // Tourist
            ['visa_type' => 'tourist', 'document_name' => 'Valid Passport (min 6 months)', 'is_mandatory' => true,  'sort_order' => 1],
            ['visa_type' => 'tourist', 'document_name' => 'Passport Size Photo',            'is_mandatory' => true,  'sort_order' => 2],
            ['visa_type' => 'tourist', 'document_name' => 'Bank Statement (3 months)',       'is_mandatory' => true,  'sort_order' => 3],
            ['visa_type' => 'tourist', 'document_name' => 'Hotel Booking Confirmation',      'is_mandatory' => true,  'sort_order' => 4],
            ['visa_type' => 'tourist', 'document_name' => 'Flight Itinerary',                'is_mandatory' => true,  'sort_order' => 5],
            ['visa_type' => 'tourist', 'document_name' => 'Travel Insurance',                'is_mandatory' => true,  'sort_order' => 6],
            ['visa_type' => 'tourist', 'document_name' => 'National ID / NID',               'is_mandatory' => false, 'sort_order' => 7],

            // Business
            ['visa_type' => 'business', 'document_name' => 'Valid Passport (min 6 months)',  'is_mandatory' => true,  'sort_order' => 1],
            ['visa_type' => 'business', 'document_name' => 'Passport Size Photo',            'is_mandatory' => true,  'sort_order' => 2],
            ['visa_type' => 'business', 'document_name' => 'Business Card',                  'is_mandatory' => true,  'sort_order' => 3],
            ['visa_type' => 'business', 'document_name' => 'Invitation Letter from Host',    'is_mandatory' => true,  'sort_order' => 4],
            ['visa_type' => 'business', 'document_name' => 'Company Trade License',          'is_mandatory' => true,  'sort_order' => 5],
            ['visa_type' => 'business', 'document_name' => 'Bank Statement (3 months)',       'is_mandatory' => true,  'sort_order' => 6],
            ['visa_type' => 'business', 'document_name' => 'Chamber of Commerce Certificate','is_mandatory' => false, 'sort_order' => 7],
            ['visa_type' => 'business', 'document_name' => 'Travel Insurance',               'is_mandatory' => false, 'sort_order' => 8],

            // Student
            ['visa_type' => 'student', 'document_name' => 'Valid Passport (min 6 months)',   'is_mandatory' => true,  'sort_order' => 1],
            ['visa_type' => 'student', 'document_name' => 'Passport Size Photo',             'is_mandatory' => true,  'sort_order' => 2],
            ['visa_type' => 'student', 'document_name' => 'Admission / Offer Letter',        'is_mandatory' => true,  'sort_order' => 3],
            ['visa_type' => 'student', 'document_name' => 'Academic Certificates',           'is_mandatory' => true,  'sort_order' => 4],
            ['visa_type' => 'student', 'document_name' => 'Bank Statement / Sponsorship',    'is_mandatory' => true,  'sort_order' => 5],
            ['visa_type' => 'student', 'document_name' => 'Language Proficiency Certificate','is_mandatory' => false, 'sort_order' => 6],
            ['visa_type' => 'student', 'document_name' => 'National ID / NID',               'is_mandatory' => false, 'sort_order' => 7],

            // Work
            ['visa_type' => 'work', 'document_name' => 'Valid Passport (min 6 months)',      'is_mandatory' => true,  'sort_order' => 1],
            ['visa_type' => 'work', 'document_name' => 'Passport Size Photo',                'is_mandatory' => true,  'sort_order' => 2],
            ['visa_type' => 'work', 'document_name' => 'Work Permit / Employment Visa',      'is_mandatory' => true,  'sort_order' => 3],
            ['visa_type' => 'work', 'document_name' => 'Offer Letter from Employer',         'is_mandatory' => true,  'sort_order' => 4],
            ['visa_type' => 'work', 'document_name' => 'Employment Contract',                'is_mandatory' => true,  'sort_order' => 5],
            ['visa_type' => 'work', 'document_name' => 'Educational Certificate (Attested)', 'is_mandatory' => true,  'sort_order' => 6],
            ['visa_type' => 'work', 'document_name' => 'Bank Statement (3 months)',          'is_mandatory' => false, 'sort_order' => 7],
            ['visa_type' => 'work', 'document_name' => 'Medical Fitness Certificate',        'is_mandatory' => false, 'sort_order' => 8],

            // Medical
            ['visa_type' => 'medical', 'document_name' => 'Valid Passport (min 6 months)',   'is_mandatory' => true,  'sort_order' => 1],
            ['visa_type' => 'medical', 'document_name' => 'Passport Size Photo',             'is_mandatory' => true,  'sort_order' => 2],
            ['visa_type' => 'medical', 'document_name' => 'Hospital Appointment Letter',     'is_mandatory' => true,  'sort_order' => 3],
            ['visa_type' => 'medical', 'document_name' => 'Medical Report / Referral',       'is_mandatory' => true,  'sort_order' => 4],
            ['visa_type' => 'medical', 'document_name' => 'Bank Statement (3 months)',       'is_mandatory' => true,  'sort_order' => 5],
            ['visa_type' => 'medical', 'document_name' => 'Travel Insurance',                'is_mandatory' => false, 'sort_order' => 6],
            ['visa_type' => 'medical', 'document_name' => 'National ID / NID',               'is_mandatory' => false, 'sort_order' => 7],

            // Other
            ['visa_type' => 'other', 'document_name' => 'Valid Passport (min 6 months)',     'is_mandatory' => true,  'sort_order' => 1],
            ['visa_type' => 'other', 'document_name' => 'Passport Size Photo',               'is_mandatory' => true,  'sort_order' => 2],
            ['visa_type' => 'other', 'document_name' => 'Bank Statement (3 months)',         'is_mandatory' => false, 'sort_order' => 3],
        ];

        foreach ($documents as $doc) {
            DB::table('visa_required_documents')->updateOrInsert(
                ['visa_type' => $doc['visa_type'], 'document_name' => $doc['document_name']],
                array_merge($doc, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
