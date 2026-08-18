<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PassportHolder;
use App\Models\VisaCategory;
use App\Models\VisaProcess;
use App\Models\VisaAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VisaProcessingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = VisaProcess::with(['passportHolder', 'country', 'visaCategory', 'visaAttachments'])
            ->orderByDesc('id');

        if ($request->filled('passport_holder_id')) {
            $query->where('passport_holder_id', $request->passport_holder_id);
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->filled('visa_category_id')) {
            $query->where('visa_category_id', $request->visa_category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $datas = $query->paginate(20);
        // $passportHolders = PassportHolder::orderBy('name')->get();
        // $countries = Country::orderBy('name')->get();
        // $visaCategories = VisaCategory::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Visa processing data retrieved successfully.',
            'data' => $datas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'passport_holder_id' => 'required|exists:passport_holders,id',
            'country_id' => 'required|exists:countries,id',
            'visa_category_id' => 'required|exists:visa_categories,id',
            'status' => 'required|in:pending,received,in_embassy,approved,delivered,rejected',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
        ]);

        $data = VisaProcess::create([
            'passport_holder_id' => $request->passport_holder_id,
            'country_id' => $request->country_id,
            'visa_category_id' => $request->visa_category_id,
            'status' => $request->status,
            'remarks' => $request->remarks,
        ]);

        // Attach uploaded files if any
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                if ($attachment->isValid()) {
                    $path = $this->storePublicFile(
                        $attachment,
                        'uploads/visa-attachments/' . $data->id
                    );

                    VisaAttachment::create([
                        'visa_process_id' => $data->id,
                        'file_path' => $path,
                        'file_name' => $attachment->getClientOriginalName(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data created successfully.',
            'data' => $data,
        ]);
    }
    

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $role, string $id)
    {
        $data = VisaProcess::findOrFail($request->id ?? $id);

        $validator = Validator::make($request->all(), [
            'passport_holder_id' => 'required|exists:passport_holders,id',
            'country_id' => 'required|exists:countries,id',
            'visa_category_id' => 'required|exists:visa_categories,id',
            'status' => 'required|in:pending,received,in_embassy,approved,delivered,rejected',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data->update([
            'passport_holder_id' => $request->passport_holder_id,
            'country_id' => $request->country_id,
            'visa_category_id' => $request->visa_category_id,
            'status' => $request->status,
            'remarks' => $request->remarks,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                if ($attachment->isValid()) {
                    $path = $this->storePublicFile(
                        $attachment,
                        'uploads/visa-attachments/' . $data->id
                    );

                    VisaAttachment::create([
                        'visa_process_id' => $data->id,
                        'file_path' => $path,
                        'file_name' => $attachment->getClientOriginalName(),
                    ]);
                }
            }
        }

        if($request->status != $data->getOriginal('status') && $request->status == 'approved') {
            // Send notification to the passport holder about visa approval
            $passportHolder = $data->passportHolder;
            $country = $data->country;
            $visaCategory = $data->visaCategory;

            $message = "Dear {$passportHolder->name}, your visa application for {$country->name} under the category of {$visaCategory->name} has been approved.";

            // Send SMS
            sendSms($passportHolder->phone, $message);

            // Send Email
            sendBrevoMail($passportHolder->email, $passportHolder->name, 'Visa Application Approved', "<p>{$message}</p>");
        }


        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Delete a specific attachment.
     */
    public function deleteAttachment(Request $request, $role, $visaId, $attachmentId)
    {
        $attachment = VisaAttachment::where('id', $attachmentId)
            ->where('visa_process_id', $visaId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ]);
        }

        // Delete the file from storage
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
        ]);
    }

    /**
     * Get attachments for a visa process.
     */
    public function getAttachments($role, $visaId)
    {
        $attachments = VisaAttachment::where('visa_process_id', $visaId)->get();

        return response()->json([
            'success' => true,
            'attachments' => $attachments,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $role, string $id)
    {
        $data = VisaProcess::find($request->item_id ?? $id);
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data Info Not Found!',
            ]);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully.',
        ]);
    }

    private function storePublicFile(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        $fileName = uniqid() . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());

        if (! file_exists(public_path($directory))) {
            mkdir(public_path($directory), 0777, true);
        }

        if (! empty($oldPath) && file_exists(public_path($oldPath))) {
            unlink(public_path($oldPath));
        }

        $file->move(public_path($directory), $fileName);

        return trim($directory, '/') . '/' . $fileName;
    }
}
