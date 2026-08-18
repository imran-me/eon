<style>
    .cfm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        z-index: 9000;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 34px 16px;
        overflow-y: auto
    }

    .cfm-overlay.hidden {
        display: none
    }

    .cfm-box {
        background: #fff;
        border-radius: 16px;
        width: 100%;
        max-width: 1180px;
        box-shadow: 0 22px 70px rgba(15, 23, 42, .24);
        overflow: hidden;
        font-family: 'DM Sans', sans-serif
    }

    .cfm-head {
        padding: 18px 22px;
        background: linear-gradient(135deg, #2563eb, #6d28d9);
        display: flex;
        align-items: center;
        gap: 12px
    }

    .cfm-head-ic {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        background: rgba(255, 255, 255, .2);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .cfm-title {
        font-size: 16px;
        font-weight: 900;
        color: #fff
    }

    .cfm-sub {
        font-size: 11.5px;
        color: #dbeafe;
        margin-top: 1px
    }

    .cfm-close {
        margin-left: auto;
        background: rgba(255, 255, 255, .22);
        border: 0;
        color: #fff;
        width: 31px;
        height: 31px;
        border-radius: 999px;
        cursor: pointer;
        font-size: 18px
    }

    .cfm-body {
        padding: 22px
    }

    .cfm-section-title {
        font-size: 13px;
        font-weight: 900;
        color: #1f2937;
        display: flex;
        gap: 7px;
        align-items: center;
        margin: 4px 0 13px
    }

    .cfm-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-bottom: 18px
    }

    .cfm-full {
        grid-column: 1/-1
    }

    .cfm-lbl {
        display: block;
        font-size: 11px;
        font-weight: 900;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 7px
    }

    .cfm-lbl sup {
        color: #ef4444
    }

    .cfm-input,
    .cfm-select,
    .cfm-textarea {
        width: 100%;
        border: 1px solid #d7deea;
        border-radius: 9px;
        padding: 10px 12px;
        font-size: 13px;
        outline: none;
        background: #fff;
        color: #1a2035;
        font-family: 'DM Sans', sans-serif
    }

    .cfm-textarea {
        min-height: 72px;
        resize: vertical
    }

    .cfm-input:focus,
    .cfm-select:focus,
    .cfm-textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .08)
    }

    .cfm-doc-grid {
        border: 1px solid #d7deea;
        border-radius: 10px;
        background: #f8fafc;
        padding: 12px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        min-height: 80px
    }

    .cfm-doc {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #475569
    }

    .cfm-doc-empty {
        grid-column: 1/-1;
        color: #94a3b8;
        font-size: 12px;
        padding: 18px;
        text-align: center
    }

    .cfm-doc-add {
        display: grid;
        grid-template-columns: 260px auto;
        gap: 8px;
        margin-top: 10px;
        justify-content: start
    }

    .cfm-foot {
        padding: 14px 22px;
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 8px
    }

    .cfm-btn {
        height: 38px;
        padding: 0 16px;
        border-radius: 9px;
        border: 0;
        font-size: 13px;
        font-weight: 900;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 7px
    }

    .cfm-btn-ghost {
        background: #f3f4f6;
        color: #475569;
        border: 1px solid #e5e7eb
    }

    .cfm-btn-save {
        background: #0b73e8;
        color: #fff
    }

    .cfm-btn-small {
        background: #eef2ff;
        color: #475569;
        border: 1px solid #dbe4f0
    }

    .cfm-view-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px
    }

    .cfm-view-item {
        background: #f8fafc;
        border: 1px solid #eef2f7;
        border-radius: 10px;
        padding: 12px
    }

    .cfm-view-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 4px
    }

    .cfm-view-value {
        font-size: 13px;
        font-weight: 800;
        color: #1f2937
    }

    .cfm-view-doc {
        display: inline-flex;
        margin: 0 6px 6px 0;
        padding: 5px 10px;
        border-radius: 999px;
        background: #fee2e2;
        color: #991b1b;
        font-size: 11px;
        font-weight: 900
    }

    .cfm-view-doc.done {
        background: #dcfce7;
        color: #166534
    }

    @media(max-width:900px) {

        .cfm-grid,
        .cfm-view-grid,
        .cfm-doc-grid {
            grid-template-columns: 1fr 1fr
        }
    }

    @media(max-width:640px) {

        .cfm-grid,
        .cfm-view-grid,
        .cfm-doc-grid,
        .cfm-doc-add {
            grid-template-columns: 1fr
        }
    }

    .cfm-holder-row {
        display: grid;
        grid-template-columns: 1fr 84px;
        gap: 8px
    }

    .cfm-file-holder-row {
        display: grid;
        grid-template-columns: 220px 1fr;
        gap: 14px;
        align-items: start
    }

    .cfm-holder-info {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        background: #eef6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #0f4fa8;
        padding: 10px 12px;
        font-size: 12px;
        font-weight: 700
    }

    .cfm-holder-info b {
        color: #07509f;
        font-weight: 900
    }

    .cfm-holder-info.hidden {
        display: none
    }

    .cfm-add-holder {
        height: 40px;
        border-radius: 9px;
        border: 1px solid #3b82f6;
        background: #eff6ff;
        color: #2563eb;
        font-size: 12px;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        cursor: pointer
    }

    .cfm-add-holder:hover {
        background: #2563eb;
        color: #fff
    }

    @media(max-width:640px) {
        .cfm-holder-row {
            grid-template-columns: 1fr
        }

        .cfm-file-holder-row {
            grid-template-columns: 1fr
        }

        .cfm-holder-info {
            grid-template-columns: 1fr
        }
    }
</style>
<div id="createModal" class="fixed inset-0 z-[9000] hidden flex items-center justify-center bg-slate-950/50 p-4 modal-backdrop">
    <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" onclick="event.stopPropagation()">
        <header class="flex items-center gap-3 bg-gradient-to-r from-indigo-600 to-blue-500 px-5 py-4 text-white"><span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/20"><i class="fas fa-plus"></i></span><div><h2 class="text-sm font-bold">New Work Permit File</h2><p class="text-[11px] text-blue-100">Register applicant, category, vendor, visa rate and required documents</p></div><button type="button" class="modal-close-create ml-auto flex h-8 w-8 items-center justify-center rounded-full bg-white/20"><i class="fas fa-times text-xs"></i></button></header>
        <div class="cfm-body overflow-y-auto p-5">
            <form id="createForm" action="{{ route('role.contract-files.store', ['role' => $role]) }}" method="POST">
                @csrf
                @include('contract-files.form-fields', ['prefix' => 'create'])
            </form>
        </div>
        <footer class="flex justify-end gap-2 bg-slate-50 px-5 py-3"><button type="button" class="modal-close-create rounded-lg border px-4 py-2 text-xs font-semibold">Cancel</button><button id="createSubmit" type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white"><i class="fas fa-save mr-1"></i>Save File</button></footer>
    </div>
</div>
