@extends('layout.app')

@section('meta-information')
    <title>Invoice Templates</title>
@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/fontawesome.min.css">
<style>
    .template-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s;
    }
    .template-card:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .template-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-default {
        background: #10b981;
        color: white;
    }
    .badge-type {
        background: #3b82f6;
        color: white;
    }
    .template-actions {
        display: flex;
        gap: 8px;
    }
    .btn {
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
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
    .btn-secondary:hover {
        background: #4b5563;
    }
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    .btn-danger:hover {
        background: #dc2626;
    }
    .btn-success {
        background: #10b981;
        color: white;
    }
    .alert {
        padding: 12px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #ef4444;
    }
</style>
@endsection

@section('main-content')
<div class="container" style="max-width: 100%; margin: 0 auto; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 700; margin: 0;">Invoice Templates</h1>
        <a href="{{ route('role.invoice-templates.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Template
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="templates-list">
        @forelse($templates as $template)
            <div class="template-card">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <h3 style="margin: 0; font-size: 20px; font-weight: 600;">{{ $template->name }}</h3>
                            @if($template->is_default)
                                <span class="template-badge badge-default">Default</span>
                            @endif
                            <span class="template-badge badge-type">{{ ucfirst(str_replace('_', ' ', $template->type)) }}</span>
                        </div>
                        <div style="color: #6b7280; font-size: 14px;">
                            <span><strong>Paper:</strong> {{ $template->paper_size }} ({{ ucfirst($template->orientation) }})</span> • 
                            <span><strong>Fields:</strong> {{ $template->fields->count() }}</span> • 
                            <span><strong>Updated:</strong> {{ $template->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="template-actions">
                        {{-- <a href="{{ route('role.invoice-templates.preview', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'template' => $template]) }}" 
                           class="btn btn-secondary" target="_blank" title="Preview">
                            <i class="fas fa-eye"></i> Preview
                        </a> --}}
                        <a href="{{ route('role.invoice-templates.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'invoice_template' => $template]) }}" 
                           class="btn btn-primary" title="Edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="{{ route('role.invoice-templates.duplicate', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'template' => $template]) }}" 
                              method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success" title="Duplicate">
                                <i class="fas fa-copy"></i> Duplicate
                            </button>
                        </form>
                        <form action="{{ route('role.invoice-templates.destroy', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'invoice_template' => $template]) }}" 
                              method="POST" style="display: inline;"
                              onsubmit="return confirm('Are you sure you want to delete this template?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 8px;">
                <i class="fas fa-file-invoice" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px;"></i>
                <p style="color: #6b7280; font-size: 16px; margin-bottom: 16px">No templates found. Create your first template to get started.</p>
                <a href="{{ route('role.invoice-templates.create', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="btn btn-primary" style="margin-top: 16px;">
                    Create Template
                </a>
            </div>
        @endforelse
    </div>
</div>
@endsection

@section('js')
<script>
    // 
</script>
@endsection