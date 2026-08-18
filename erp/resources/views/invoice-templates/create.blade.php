@extends('layout.app')

@section('meta-information')
    <title>Create Invoice Template</title>
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
        <h1>Create Invoice Template</h1>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='{{ route('role.invoice-templates.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <button type="button" class="btn btn-primary" id="save-template">
                <i class="fas fa-save"></i> Save Template
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

    <form id="template-form" method="POST" action="{{ route('role.invoice-templates.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">
        @csrf
        
        <div class="editor-layout">
            <!-- Left Panel - Basic Settings -->
            <div class="panel" style="width: 30%">
                <h3 class="panel-title">Basic Settings</h3>
                
                <div class="form-group">
                    <label>Template Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Sales Invoice A4">
                </div>

                <div class="form-group">
                    <label>Template Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="">Select Type</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Paper Size *</label>
                    <select name="paper_size" id="paper_size" class="form-control" required>
                        <option value="A4">A4 (210 x 297 mm)</option>
                        <option value="A5">A5 (148 x 210 mm)</option>
                        <option value="Thermal">Thermal (80mm)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Orientation *</label>
                    <select name="orientation" id="orientation" class="form-control" required>
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_default" id="is_default" value="1">
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
                    {{-- <div class="form-group">
                        <label>Font Family</label>
                        <select name="style[font_family]" id="font_family" class="form-control">
                            <option value="Inter">Inter</option>
                            <option value="Arial">Arial</option>
                            <option value="Helvetica">Helvetica</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Courier">Courier</option>
                            <option value="Georgia">Georgia</option>
                        </select>
                    </div> --}}
                    <div class="form-group">
                        <label>Font for Texts</label>                        
                        <select name="style[text_font]" id="text_font" class="form-control">
                            <option value="one">Inter (better for text font)</option>
                            <option value="two">Lato (better for text font)</option>
                            <option value="three">Roboto (better for text font)</option>
                            <option value="four">Poppins (better for title font)</option>
                            <option value="five">Courier Prime (better for numbers)</option>
                            <option value="six">DM Sans (better for text font)</option>
                            <option value="seven">Open Sans (better for text font)</option>
                            <option value="eight">Public Sans (better for text font)</option>
                            <option value="nine">IBM Plex Sans (better for text font)</option>
                            <option value="ten">Source Sans 3 (better for text font)</option>
                            <option value="eleven">Merriweather (better for title font)</option>
                            <option value="twelve">Montserrat (better for title font)</option>
                            <option value="thirteen">Crimson Pro (better for title font)</option>
                            <option value="fourteen">VT323 (better for numbers)</option>
                            <option value="fifteen">Space Mono (better for numbers)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Font for Titles</label>                        
                        <select name="style[title_font]" id="title_font" class="form-control">
                            <option value="one">Inter (better for text font)</option>
                            <option value="two">Lato (better for text font)</option>
                            <option value="three">Roboto (better for text font)</option>
                            <option value="four">Poppins (better for title font)</option>
                            <option value="five">Courier Prime (better for numbers)</option>
                            <option value="six">DM Sans (better for text font)</option>
                            <option value="seven">Open Sans (better for text font)</option>
                            <option value="eight">Public Sans (better for text font)</option>
                            <option value="nine">IBM Plex Sans (better for text font)</option>
                            <option value="ten">Source Sans 3 (better for text font)</option>
                            <option value="eleven">Merriweather (better for title font)</option>
                            <option value="twelve">Montserrat (better for title font)</option>
                            <option value="thirteen">Crimson Pro (better for title font)</option>
                            <option value="fourteen">VT323 (better for numbers)</option>
                            <option value="fifteen">Space Mono (better for numbers)</option>
                        </select>
                    </div>                                        
                    <div class="form-group">
                        <label>Font for Numbers</label>                        
                        <select name="style[number_font]" id="number_font" class="form-control">
                            <option value="one">Inter (better for text font)</option>
                            <option value="two">Lato (better for text font)</option>
                            <option value="three">Roboto (better for text font)</option>
                            <option value="four">Poppins (better for title font)</option>
                            <option value="five">Courier Prime (better for numbers)</option>
                            <option value="six">DM Sans (better for text font)</option>
                            <option value="seven">Open Sans (better for text font)</option>
                            <option value="eight">Public Sans (better for text font)</option>
                            <option value="nine">IBM Plex Sans (better for text font)</option>
                            <option value="ten">Source Sans 3 (better for text font)</option>
                            <option value="eleven">Merriweather (better for title font)</option>
                            <option value="twelve">Montserrat (better for title font)</option>
                            <option value="thirteen">Crimson Pro (better for title font)</option>
                            <option value="fourteen">VT323 (better for numbers)</option>
                            <option value="fifteen">Space Mono (better for numbers)</option>
                        </select>
                    </div>   
                </div>

                <div class="tab-content" id="colors">
                    {{-- <div class="form-group">
                        <label>Primary Color</label>
                        <div class="color-picker-group">
                            <input type="color" id="primary_color_picker" value="#000000">
                            <input type="text" name="style[primary_color]" id="primary_color" class="form-control" value="#000000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Secondary Color</label>
                        <div class="color-picker-group">
                            <input type="color" id="secondary_color_picker" value="#6b7280">
                            <input type="text" name="style[secondary_color]" id="secondary_color" class="form-control" value="#6b7280">
                        </div>
                    </div> --}}
                    {{-- title_color --}}
                    <div class="form-group">
                        <label>Title Color</label>
                        <div class="color-picker-group">
                            <input type="color" id="title_color_picker">
                            <input type="text" name="style[title_color]" id="title_color" class="form-control">
                        </div>
                    </div>
                    {{-- title_bg --}}
                    <div class="form-group">
                        <label>Title Background</label>
                        <div class="color-picker-group">
                            <input type="color" id="title_bg_picker">
                            <input type="text" name="style[title_bg]" id="title_bg" class="form-control">
                        </div>
                    </div>
                    {{-- tabler_header_bg --}}
                    <div class="form-group">
                        <label>Tabler Header Background</label>
                        <div class="color-picker-group">
                            <input type="color" id="tabler_header_bg_picker">
                            <input type="text" name="style[tabler_header_bg]" id="tabler_header_bg" class="form-control">
                        </div>
                    </div>
                    {{-- text_color --}}
                    <div class="form-group">
                        <label>Text Color</label>
                        <div class="color-picker-group">
                            <input type="color" id="text_color_picker">
                            <input type="text" name="style[text_color]" id="text_color" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="layout">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="style[show_border]" id="show_border" value="1" checked>
                            <label for="show_border" style="margin: 0;">Show Table Borders</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="style[striped_table]" id="striped_table" value="1">
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
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
@include('invoice-templates.script')
@endsection