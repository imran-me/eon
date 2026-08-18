@extends('layout.app')

@section('meta-information')
    <title>Preview: {{ $template->name }}</title>
@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/fontawesome.min.css">
<style>
    body {
        margin: 0;
        padding: 20px;
        background: #f3f4f6;
    }
    .preview-container {
        max-width: 900px;
        margin: 0 auto;
    }
    .preview-header {
        background: white;
        padding: 20px;
        border-radius: 8px 8px 0 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .preview-paper {
        background: white;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin: 0 auto 40px;
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
        padding: 20px;
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
    @media print {
        body {
            background: white;
            padding: 0;
        }
        .preview-container {
            padding: 0;
            margin: 0 auto;
        }
        .preview-header {
            display: none !important;
        }
        .btn {
            display: none !important;
        }
        .preview-paper {
            box-shadow: none;
            margin: 0;
            page-break-after: always;
        }
    }
</style>
@endsection

@section('main-content')
<div class="preview-container">
    <div class="preview-header no-print" style="margin-bottom: 30px">
        <h2 style="margin: 0;">{{ $template->name }}</h2>
        <div style="display: flex; gap: 10px;">
            <button onclick="printPreview()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                Close
            </button>
        </div>
    </div>

    <div class="preview-paper {{ $template->paper_size }} {{ $template->orientation }}" 
         style="font-family: {{ $template->style->font_family ?? 'Inter' }}; color: {{ $template->style->primary_color ?? '#000000' }};">
        
        <!-- Header Section -->
        <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid {{ $template->style->primary_color ?? '#000000' }};">
            <h1 style="color: {{ $template->style->primary_color ?? '#000000' }}; margin: 0 0 20px 0; font-size: 28px;">
                {{ strtoupper(str_replace('_', ' ', $template->type)) }}
            </h1>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                @foreach($template->fields->where('section', 'header')->where('is_visible', true) as $field)
                    <div style="margin-bottom: 10px;">
                        <strong style="color: {{ $template->style->secondary_color ?? '#6b7280' }}; font-size: 12px;">
                            {{ $field->label }}:
                        </strong>
                        <div style="font-size: 14px;">
                            @switch($field->type)
                                @case('date')
                                    {{ now()->format('Y-m-d') }}
                                    @break
                                @case('currency')
                                    $0.00
                                    @break
                                @case('number')
                                    0
                                    @break
                                @default
                                    Sample {{ $field->label }}
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Body Section -->
        <div style="margin-bottom: 30px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: {{ $template->style->primary_color ?? '#000000' }}; color: white;">
                        <th style="padding: 12px; text-align: left; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">Item</th>
                        <th style="padding: 12px; text-align: center; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">Quantity</th>
                        <th style="padding: 12px; text-align: right; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">Price</th>
                        <th style="padding: 12px; text-align: right; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 1; $i <= 5; $i++)
                        <tr style="background: {{ $template->style->striped_table && $i % 2 == 0 ? '#f9fafb' : 'transparent' }};">
                            <td style="padding: 10px; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">Sample Item {{ $i }}</td>
                            <td style="padding: 10px; text-align: center; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">{{ $i }}</td>
                            <td style="padding: 10px; text-align: right; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">${{ number_format(100 * $i, 2) }}</td>
                            <td style="padding: 10px; text-align: right; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">${{ number_format(100 * $i * $i, 2) }}</td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="padding: 10px; text-align: right; font-weight: bold; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">
                            Subtotal:
                        </td>
                        <td style="padding: 10px; text-align: right; font-weight: bold; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">
                            $5,500.00
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding: 10px; text-align: right; font-weight: bold; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">
                            Tax (10%):
                        </td>
                        <td style="padding: 10px; text-align: right; font-weight: bold; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">
                            $550.00
                        </td>
                    </tr>
                    <tr style="background: {{ $template->style->primary_color ?? '#000000' }}; color: white;">
                        <td colspan="3" style="padding: 12px; text-align: right; font-weight: bold; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">
                            Total:
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: bold; {{ $template->style->show_border ? 'border: 1px solid #ddd;' : '' }}">
                            $6,050.00
                        </td>
                    </tr>
                </tfoot>
            </table>

            @if($template->fields->where('section', 'body')->where('is_visible', true)->count() > 0)
                <div style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    @foreach($template->fields->where('section', 'body')->where('is_visible', true) as $field)
                        <div>
                            <strong style="color: {{ $template->style->secondary_color ?? '#6b7280' }}; font-size: 12px;">
                                {{ $field->label }}:
                            </strong>
                            <div style="font-size: 14px;">
                                @switch($field->type)
                                    @case('date')
                                        {{ now()->format('Y-m-d') }}
                                        @break
                                    @case('currency')
                                        $0.00
                                        @break
                                    @case('number')
                                        0
                                        @break
                                    @default
                                        Sample {{ $field->label }}
                                @endswitch
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Footer Section -->
        <div style="padding-top: 20px; border-top: 1px solid #e5e7eb;">
            @foreach($template->fields->where('section', 'footer')->where('is_visible', true) as $field)
                <div style="margin-bottom: 10px;">
                    <strong style="color: {{ $template->style->secondary_color ?? '#6b7280' }}; font-size: 12px;">
                        {{ $field->label }}:
                    </strong>
                    <div style="font-size: 14px;">
                        @switch($field->type)
                            @case('date')
                                {{ now()->format('Y-m-d') }}
                                @break
                            @case('currency')
                                $0.00
                                @break
                            @case('number')
                                0
                                @break
                            @default
                                Sample {{ $field->label }}
                        @endswitch
                    </div>
                </div>
            @endforeach

            <div style="text-align: center; margin-top: 30px; color: {{ $template->style->secondary_color ?? '#6b7280' }}; font-size: 12px;">
                Thank you for your business!
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery.print/1.6.2/jQuery.print.min.js"></script>
<script>
    function printPreview(){
        $('.preview-container').print({
            globalStyles: true,   // Keep global CSS
            mediaPrint: true,     // Allow @media print styles
            iframe: true,         // Print inside an iframe
            noPrintSelector: ".no-print" // Hide unwanted items
        });        
    }       
</script>
@endsection