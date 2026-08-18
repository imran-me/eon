@extends('layout.app')

@section('meta-information')
    <title>Edit Invoice Template</title>
@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/fontawesome.min.css">
@include('invoice-templates.css')
<style>
    * { box-sizing: border-box; }
    .container { max-width: 100%; margin: 0 auto; padding: 20px; }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }
    .editor-layout {
        display: flex;        
        gap: 20px;
        min-height: calc(100vh - 200px);
    }
    .panel {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        height: fit-content;
    }
    .panel-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 6px;
        color: #374151;
    }
    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .btn {
        padding: 10px 20px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
    }
    .btn-primary {
        background: #3b82f6;
        color: white;
    }
    .btn-primary:hover {
        background: #2563eb;
    }
    .btn-secondary {
        background: #6b7280;
        color: white;
    }
    .btn-success {
        background: #10b981;
        color: white;
    }
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    /* Fields Manager */
    .field-item {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        cursor: move;
    }
    .field-item.dragging {
        opacity: 0.5;
    }
    .field-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .field-label {
        font-weight: 500;
        font-size: 14px;
    }
    .field-actions {
        display: flex;
        gap: 4px;
    }
    .field-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        font-size: 12px;
    }
    .field-body input, .field-body select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 12px;
    }
    
    /* Color Picker */
    .color-picker-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .color-picker-group input[type="color"] {
        width: 50px;
        height: 38px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        cursor: pointer;
    }
    .color-picker-group input[type="text"] {
        flex: 1;
    }
    
    /* Preview */
    #preview-container {
        background: white;
        min-height: 500px;
        overflow-y: auto;
        max-height: calc(100vh - 200px);
    }
    .preview-wrapper {
        transform-origin: top center;
        margin: 0 auto;
    }
    .preview-paper {
        background: white;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin: 20px auto;
        padding: 40px;
    }
    .preview-paper.A4 {
        width: 794px;
        min-height: 1123px;
    }
    .preview-paper.A5 {
        width: 559px;
        min-height: 794px;
    }
    .preview-paper.Thermal {
        width: 302px;
        min-height: 400px;
    }
    .preview-paper.landscape {
        transform: rotate(0deg);
    }
    
    .tabs {
        display: flex;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 20px;
    }
    .tab {
        padding: 10px 20px;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
    }
    .tab.active {
        border-bottom-color: #3b82f6;
        color: #3b82f6;
        font-weight: 500;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
</style>
@endsection

@section('main-content')
<div class="container">
    <div class="page-header">
        <h1>Edit Invoice Template: {{ $invoiceTemplate->name }}</h1>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='{{ route('role.invoice-templates.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <button type="button" class="btn btn-primary" id="save-template">
                <i class="fas fa-save"></i> Update Template
            </button>
        </div>
    </div>

    {{-- Error Display --}}
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
            <strong>Whoops!</strong> Please fix the following errors:
            <ul class="list-disc pl-5 mt-2">
                @foreach ($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="template-form" method="POST" action="{{ route('role.invoice-templates.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'invoice_template' => $invoiceTemplate]) }}">
        @csrf
        @method('PUT')
        
        <div class="editor-layout">
            <!-- Left Panel - Basic Settings -->
            <div class="panel" style="width: 30%">
                <h3 class="panel-title">Basic Settings</h3>
                
                <div class="form-group">
                    <label>Template Name *</label>
                    <input type="text" name="name" class="form-control" required 
                           value="{{ old('name', $invoiceTemplate->name) }}" 
                           placeholder="e.g., Sales Invoice A4">
                </div>

                <div class="form-group">
                    <label>Template Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="">Select Type</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ old('type', $invoiceTemplate->type) == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Paper Size *</label>
                    <select name="paper_size" id="paper_size" class="form-control" required>
                        <option value="A4" {{ old('paper_size', $invoiceTemplate->paper_size) == 'A4' ? 'selected' : '' }}>A4 (210 x 297 mm)</option>
                        <option value="A5" {{ old('paper_size', $invoiceTemplate->paper_size) == 'A5' ? 'selected' : '' }}>A5 (148 x 210 mm)</option>
                        <option value="Thermal" {{ old('paper_size', $invoiceTemplate->paper_size) == 'Thermal' ? 'selected' : '' }}>Thermal (80mm)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Orientation *</label>
                    <select name="orientation" id="orientation" class="form-control" required>
                        <option value="portrait" {{ old('orientation', $invoiceTemplate->orientation) == 'portrait' ? 'selected' : '' }}>Portrait</option>
                        <option value="landscape" {{ old('orientation', $invoiceTemplate->orientation) == 'landscape' ? 'selected' : '' }}>Landscape</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_default" id="is_default" value="1" 
                               {{ old('is_default', $invoiceTemplate->is_default) ? 'checked' : '' }}>
                        <label for="is_default" style="margin: 0;">Set as Default Template</label>
                    </div>
                </div>

                <hr style="margin: 20px 0;">
                
                <h3 class="panel-title">Style Settings</h3>
                
                <div class="tabs">
                    <div class="tab active" data-tab="typography">Typography</div>
                    <div class="tab" data-tab="colors">Colors</div>
                    <div class="tab" data-tab="layout">Layout</div>
                </div>

                <div class="tab-content active" id="typography">
                    <div class="form-group">
                        <label>Font for Texts</label>
                        @php $text_font = old('style.text_font', $invoiceTemplate->style->text_font); @endphp
                        <select name="style[text_font]" id="text_font" class="form-control">
                            <option value="one" {{ $text_font == 'one' ? 'selected' : '' }}>Inter (better for text font)</option>
                            <option value="two" {{ $text_font == 'two' ? 'selected' : '' }}>Lato (better for text font)</option>
                            <option value="three" {{ $text_font == 'three' ? 'selected' : '' }}>Roboto (better for text font)</option>
                            <option value="four" {{ $text_font == 'four' ? 'selected' : '' }}>Poppins (better for title font)</option>
                            <option value="five" {{ $text_font == 'five' ? 'selected' : '' }}>Courier Prime (better for numbers)</option>
                            <option value="six" {{ $text_font == 'six' ? 'selected' : '' }}>DM Sans (better for text font)</option>
                            <option value="seven" {{ $text_font == 'seven' ? 'selected' : '' }}>Open Sans (better for text font)</option>
                            <option value="eight" {{ $text_font == 'eight' ? 'selected' : '' }}>Public Sans (better for text font)</option>
                            <option value="nine" {{ $text_font == 'nine' ? 'selected' : '' }}>IBM Plex Sans (better for text font)</option>
                            <option value="ten" {{ $text_font == 'ten' ? 'selected' : '' }}>Source Sans 3 (better for text font)</option>
                            <option value="eleven" {{ $text_font == 'eleven' ? 'selected' : '' }}>Merriweather (better for title font)</option>
                            <option value="twelve" {{ $text_font == 'twelve' ? 'selected' : '' }}>Montserrat (better for title font)</option>
                            <option value="thirteen" {{ $text_font == 'thirteen' ? 'selected' : '' }}>Crimson Pro (better for title font)</option>
                            <option value="fourteen" {{ $text_font == 'fourteen' ? 'selected' : '' }}>VT323 (better for numbers)</option>
                            <option value="fifteen" {{ $text_font == 'fifteen' ? 'selected' : '' }}>Space Mono (better for numbers)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Font for Titles</label>
                        @php $title_font = old('style.title_font', $invoiceTemplate->style->title_font); @endphp
                        <select name="style[title_font]" id="title_font" class="form-control">
                            <option value="one" {{ $title_font == 'one' ? 'selected' : '' }}>Inter (better for text font)</option>
                            <option value="two" {{ $title_font == 'two' ? 'selected' : '' }}>Lato (better for text font)</option>
                            <option value="three" {{ $title_font == 'three' ? 'selected' : '' }}>Roboto (better for text font)</option>
                            <option value="four" {{ $title_font == 'four' ? 'selected' : '' }}>Poppins (better for title font)</option>
                            <option value="five" {{ $title_font == 'five' ? 'selected' : '' }}>Courier Prime (better for numbers)</option>
                            <option value="six" {{ $title_font == 'six' ? 'selected' : '' }}>DM Sans (better for text font)</option>
                            <option value="seven" {{ $title_font == 'seven' ? 'selected' : '' }}>Open Sans (better for text font)</option>
                            <option value="eight" {{ $title_font == 'eight' ? 'selected' : '' }}>Public Sans (better for text font)</option>
                            <option value="nine" {{ $title_font == 'nine' ? 'selected' : '' }}>IBM Plex Sans (better for text font)</option>
                            <option value="ten" {{ $title_font == 'ten' ? 'selected' : '' }}>Source Sans 3 (better for text font)</option>
                            <option value="eleven" {{ $title_font == 'eleven' ? 'selected' : '' }}>Merriweather (better for title font)</option>
                            <option value="twelve" {{ $title_font == 'twelve' ? 'selected' : '' }}>Montserrat (better for title font)</option>
                            <option value="thirteen" {{ $title_font == 'thirteen' ? 'selected' : '' }}>Crimson Pro (better for title font)</option>
                            <option value="fourteen" {{ $title_font == 'fourteen' ? 'selected' : '' }}>VT323 (better for numbers)</option>
                            <option value="fifteen" {{ $title_font == 'fifteen' ? 'selected' : '' }}>Space Mono (better for numbers)</option>
                        </select>
                    </div>                                        
                    <div class="form-group">
                        <label>Font for Numbers</label>
                        @php $number_font = old('style.number_font', $invoiceTemplate->style->number_font); @endphp
                        <select name="style[number_font]" id="number_font" class="form-control">
                            <option value="one" {{ $number_font == 'one' ? 'selected' : '' }}>Inter (better for text font)</option>
                            <option value="two" {{ $number_font == 'two' ? 'selected' : '' }}>Lato (better for text font)</option>
                            <option value="three" {{ $number_font == 'three' ? 'selected' : '' }}>Roboto (better for text font)</option>
                            <option value="four" {{ $number_font == 'four' ? 'selected' : '' }}>Poppins (better for title font)</option>
                            <option value="five" {{ $number_font == 'five' ? 'selected' : '' }}>Courier Prime (better for numbers)</option>
                            <option value="six" {{ $number_font == 'six' ? 'selected' : '' }}>DM Sans (better for text font)</option>
                            <option value="seven" {{ $number_font == 'seven' ? 'selected' : '' }}>Open Sans (better for text font)</option>
                            <option value="eight" {{ $number_font == 'eight' ? 'selected' : '' }}>Public Sans (better for text font)</option>
                            <option value="nine" {{ $number_font == 'nine' ? 'selected' : '' }}>IBM Plex Sans (better for text font)</option>
                            <option value="ten" {{ $number_font == 'ten' ? 'selected' : '' }}>Source Sans 3 (better for text font)</option>
                            <option value="eleven" {{ $number_font == 'eleven' ? 'selected' : '' }}>Merriweather (better for title font)</option>
                            <option value="twelve" {{ $number_font == 'twelve' ? 'selected' : '' }}>Montserrat (better for title font)</option>
                            <option value="thirteen" {{ $number_font == 'thirteen' ? 'selected' : '' }}>Crimson Pro (better for title font)</option>
                            <option value="fourteen" {{ $number_font == 'fourteen' ? 'selected' : '' }}>VT323 (better for numbers)</option>
                            <option value="fifteen" {{ $number_font == 'fifteen' ? 'selected' : '' }}>Space Mono (better for numbers)</option>
                        </select>
                    </div>                    
                </div>

                <div class="tab-content" id="colors">
                    {{-- <div class="form-group">
                        <label>Primary Color</label>
                        @php $primaryColor = old('style.primary_color', $invoiceTemplate->style->primary_color ?? '#000000'); @endphp
                        <div class="color-picker-group">
                            <input type="color" id="primary_color_picker" value="{{ $primaryColor }}">
                            <input type="text" name="style[primary_color]" id="primary_color" class="form-control" value="{{ $primaryColor }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Secondary Color</label>
                        @php $secondaryColor = old('style.secondary_color', $invoiceTemplate->style->secondary_color ?? '#6b7280'); @endphp
                        <div class="color-picker-group">
                            <input type="color" id="secondary_color_picker" value="{{ $secondaryColor }}">
                            <input type="text" name="style[secondary_color]" id="secondary_color" class="form-control" value="{{ $secondaryColor }}">
                        </div>
                    </div> --}}
                    {{-- title_color --}}
                    <div class="form-group">
                        <label>Title Color</label>
                        @php $title_color = old('style.title_color', $invoiceTemplate->style->title_color); @endphp
                        <div class="color-picker-group">
                            <input type="color" id="title_color_picker" value="{{ $title_color }}">
                            <input type="text" name="style[title_color]" id="title_color" class="form-control" value="{{ $title_color }}">
                        </div>
                    </div>
                    {{-- title_bg --}}
                    <div class="form-group">
                        <label>Title Background</label>
                        @php $title_bg = old('style.title_bg', $invoiceTemplate->style->title_bg); @endphp
                        <div class="color-picker-group">
                            <input type="color" id="title_bg_picker" value="{{ $title_bg }}">
                            <input type="text" name="style[title_bg]" id="title_bg" class="form-control" value="{{ $title_bg }}">
                        </div>
                    </div>
                    {{-- tabler_header_bg --}}
                    <div class="form-group">
                        <label>Tabler Header Background</label>
                        @php $tabler_header_bg = old('style.tabler_header_bg', $invoiceTemplate->style->tabler_header_bg); @endphp
                        <div class="color-picker-group">
                            <input type="color" id="tabler_header_bg_picker" value="{{ $tabler_header_bg }}">
                            <input type="text" name="style[tabler_header_bg]" id="tabler_header_bg" class="form-control" value="{{ $tabler_header_bg }}">
                        </div>
                    </div>
                    {{-- text_color --}}
                    <div class="form-group">
                        <label>Text Color</label>
                        @php $text_color = old('style.text_color', $invoiceTemplate->style->text_color); @endphp
                        <div class="color-picker-group">
                            <input type="color" id="text_color_picker" value="{{ $text_color }}">
                            <input type="text" name="style[text_color]" id="text_color" class="form-control" value="{{ $text_color }}">
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="layout">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="style[show_border]" id="show_border" value="1" 
                                   {{ old('style.show_border', $invoiceTemplate->style->show_border ?? true) ? 'checked' : '' }}>
                            <label for="show_border" style="margin: 0;">Show Table Borders</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="style[striped_table]" id="striped_table" value="1"
                                   {{ old('style.striped_table', $invoiceTemplate->style->striped_table ?? false) ? 'checked' : '' }}>
                            <label for="striped_table" style="margin: 0;">Striped Table Rows</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Panel - Fields Manager -->
            <div class="panel" style="width: 70%">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 class="panel-title" style="margin: 0; padding: 0; border: none;">Custom Fields</h3>
                    <button type="button" class="btn btn-success btn-sm" id="add-field">
                        <i class="fas fa-plus"></i> Add Field
                    </button>
                </div>

                <div class="tabs">
                    <div class="tab active" data-tab="header-fields">Header</div>
                    <div class="tab" data-tab="body-fields">Body</div>
                    <div class="tab" data-tab="footer-fields">Footer</div>
                </div>

                <div class="tab-content active" id="header-fields">
                    <div id="fields-header" class="fields-container"></div>
                </div>

                <div class="tab-content" id="body-fields">
                    <div id="fields-body" class="fields-container"></div>
                </div>

                <div class="tab-content" id="footer-fields">
                    <div id="fields-footer" class="fields-container"></div>
                </div>
            </div>

            <!-- Right Panel - Live Preview -->
            <div class="panel" style="display: none">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 class="panel-title" style="margin: 0; padding: 0; border: none;">Live Preview</h3>
                    <select id="zoom-level" class="form-control" style="width: auto;">
                        <option value="0.5">50%</option>
                        <option value="0.75" selected>75%</option>
                        <option value="1">100%</option>
                    </select>
                </div>
                <div id="preview-container"></div>
            </div>
        </div>
    </form>
</div>

<!-- Field Template -->
<template id="field-template">
    <div class="field-item" draggable="true">
        <div class="field-header">
            <span class="field-label"></span>
            <div class="field-actions">
                <button type="button" class="btn btn-sm btn-danger remove-field" title="Remove">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="field-body">
            <input type="text" placeholder="Label" class="field-label-input">
            <input type="text" placeholder="Value" class="field-key-input">
            <select class="field-type-input">
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="date">Date</option>
                <option value="currency">Currency</option>
            </select>
            <select class="field-section-input">
                <option value="header">Header</option>
                <option value="body">Body</option>
                <option value="footer">Footer</option>
            </select>
            <div style="display: flex; align-items: center;">
                <label style="display: flex; align-items: center; margin-right: 20px;">
                    <input type="checkbox" style="width: auto; margin-right: 5px" class="field-required-input"> 
                    Required
                </label>
                <label style="display: flex; align-items: center;">
                    <input type="checkbox" style="width: auto; margin-right: 5px" class="field-visible-input" checked> 
                    Visible
                </label>
            </div>
        </div>
    </div>
</template>

<script>
    // Pass existing fields to JavaScript
    var existingFields = @json($invoiceTemplate->fields);
</script>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
@include('invoice-templates.script')
@endsection