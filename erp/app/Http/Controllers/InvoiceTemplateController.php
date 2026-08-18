<?php
// app/Http/Controllers/InvoiceTemplateController.php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use App\Models\InvoiceTemplateField;
use App\Models\InvoiceTemplateStyle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceTemplateController extends Controller
{
    public function index()
    {
        $templates = InvoiceTemplate::with(['fields', 'style', 'branch'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('invoice-templates.index', compact('templates'));
    }

    public function create()
    {
        $types = [
            'sale' => 'Sales Invoice',
            'purchase' => 'Purchase Invoice',
            'ticket_sale' => 'Ticket Sale',
            'salary' => 'Salary Slip',
            'payment' => 'Payment Receipt'
        ];

        return view('invoice-templates.create', compact('types'));
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'paper_size' => 'required|string',
            'orientation' => 'required|string',
            'is_default' => 'boolean',
            'layout_config' => 'nullable|json',
            'branch_id' => 'nullable|exists:branches,id',
            'fields' => 'nullable|string',
            'style' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // If this template is set as default, unset other defaults of same type
            if ($request->is_default) {
                InvoiceTemplate::where('type', $request->type)
                    ->update(['is_default' => false]);
            }

            $template = InvoiceTemplate::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'paper_size' => $validated['paper_size'],
                'orientation' => $validated['orientation'],
                'is_default' => $request->is_default ?? false,
                'layout_config' => $request->layout_config,
                'branch_id' => $validated['branch_id'] ?? null,
            ]);

            // Create fields
            if ($request->has('fields')) {
                $all_fields = gettype($request->fields) == 'string' ? json_decode($request->fields, true) : $request->fields;
                foreach ($all_fields as $field) {                       
                    InvoiceTemplateField::create([
                        'invoice_template_id' => $template->id,
                        'label' => $field['label'],
                        'key' => $field['key'],
                        'type' => $field['type'],
                        'section' => $field['section'],
                        'sort_order' => $field['sort_order'] ?? 0,
                        'is_required' => $field['is_required'] ?? false,
                        'is_visible' => $field['is_visible'] ?? true,
                    ]);
                }
            }

            // Create style
            InvoiceTemplateStyle::create([
                'invoice_template_id' => $template->id,
                'show_border' => $request->input('style.show_border', true),
                'striped_table' => $request->input('style.striped_table', false),
                // 'font_family' => $request->input('style.font_family', 'Inter'),
                // 'primary_color' => $request->input('style.primary_color', '#000000'),
                // 'secondary_color' => $request->input('style.secondary_color'),
                'title_color' => $request->input('style.title_color'),
                'title_bg' => $request->input('style.title_bg'),
                'tabler_header_bg' => $request->input('style.tabler_header_bg'),
                'text_color' => $request->input('style.text_color'),
                'title_font' => $request->input('style.title_font'),
                'text_font' => $request->input('style.text_font'),
                'number_font' => $request->input('style.number_font'),
            ]);

            DB::commit();
            return redirect()->route('role.invoice-templates.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])->with('success', 'Template created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create template: ' . $e->getMessage());
        }
    }

    public function edit($role, $id)
    {
        $invoiceTemplate = InvoiceTemplate::findOrFail($id);
        $invoiceTemplate->load(['fields', 'style']);

        $types = [
            'sale' => 'Sales Invoice',
            'purchase' => 'Purchase Invoice',
            'ticket_sale' => 'Ticket Sale',
            'salary' => 'Salary Slip',
            'payment' => 'Payment Receipt'
        ];

        return view('invoice-templates.edit', compact('invoiceTemplate', 'types'));
    }

    public function update(Request $request, $role, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'paper_size' => 'required|string',
            'orientation' => 'required|string',
            'is_default' => 'boolean',
            'layout_config' => 'nullable|json',
            'branch_id' => 'nullable|exists:branches,id',
            'fields' => 'nullable|string',
            'style' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $invoiceTemplate = InvoiceTemplate::findOrFail($id);
            if ($request->is_default) {
                InvoiceTemplate::where('type', $request->type)
                    ->where('id', '!=', $invoiceTemplate->id)
                    ->update(['is_default' => false]);
            }

            $invoiceTemplate->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'paper_size' => $validated['paper_size'],
                'orientation' => $validated['orientation'],
                'is_default' => $request->is_default ?? false,
                'layout_config' => $request->layout_config,
                'branch_id' => $validated['branch_id'] ?? null,
            ]);

            // Update fields
            $invoiceTemplate->fields()->delete();
            if ($request->has('fields')) {
                $all_fields = gettype($request->fields) == 'string' ? json_decode($request->fields, true) : $request->fields;
                foreach ($all_fields as $field) {
                    InvoiceTemplateField::create([
                        'invoice_template_id' => $invoiceTemplate->id,
                        'label' => $field['label'],
                        'key' => $field['key'],
                        'type' => $field['type'],
                        'section' => $field['section'],
                        'sort_order' => $field['sort_order'] ?? 0,
                        'is_required' => $field['is_required'] ?? false,
                        'is_visible' => $field['is_visible'] ?? true,
                    ]);
                }
            }

            // Update style
            $invoiceTemplate->style()->updateOrCreate(
                ['invoice_template_id' => $invoiceTemplate->id],
                [
                    'show_border' => $request->input('style.show_border', true),
                    'striped_table' => $request->input('style.striped_table', false),
                    // 'font_family' => $request->input('style.font_family', 'Inter'),
                    // 'primary_color' => $request->input('style.primary_color', '#000000'),
                    // 'secondary_color' => $request->input('style.secondary_color'),
                    'title_color' => $request->input('style.title_color'),
                    'title_bg' => $request->input('style.title_bg'),
                    'tabler_header_bg' => $request->input('style.tabler_header_bg'),
                    'text_color' => $request->input('style.text_color'),
                    'title_font' => $request->input('style.title_font'),
                    'text_font' => $request->input('style.text_font'),
                    'number_font' => $request->input('style.number_font'),
                ]
            );

            DB::commit();
            return redirect()->route('role.invoice-templates.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])->with('success', 'Template updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update template: ' . $e->getMessage());
        }
    }

    public function destroy($role, $id)
    {
        try {
            $invoiceTemplate = InvoiceTemplate::findOrFail($id);
            $invoiceTemplate->delete();
            return redirect()->route('role.invoice-templates.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])->with('success', 'Template deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete template: ' . $e->getMessage());
        }
    }

    public function duplicate($role, $id)
    {        
        DB::beginTransaction();
        try {
            $template = InvoiceTemplate::findOrFail($id);
            $newTemplate = $template->replicate();
            $newTemplate->name = $template->name . ' (Copy)';
            $newTemplate->is_default = false;
            $newTemplate->save();

            foreach ($template->fields as $field) {
                $newField = $field->replicate();
                $newField->invoice_template_id = $newTemplate->id;
                $newField->save();
            }

            if ($template->style) {
                $newStyle = $template->style->replicate();
                $newStyle->invoice_template_id = $newTemplate->id;
                $newStyle->save();
            }

            DB::commit();
            return redirect()->route('role.invoice-templates.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'invoice_template' => $newTemplate])->with('success', 'Template duplicated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to duplicate template: ' . $e->getMessage());
        }
    }

    public function preview($role, $id)
    {
        $template = InvoiceTemplate::findOrFail($id);
        $template->load(['fields', 'style']);
        return view('invoice-templates.preview', compact('template'));
    }
}
