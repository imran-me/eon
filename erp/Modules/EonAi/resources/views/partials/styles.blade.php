{{-- EON AI styles — inlined, every selector eai- prefixed, nothing global. --}}
<style>
    .eai-wrap { max-width: 980px; margin: 0 auto; }
    .eai-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 16px; flex-wrap: wrap; }
    .eai-title { display: flex; align-items: center; gap: 12px; }
    .eai-logo { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 18px;
        background: linear-gradient(135deg, #4f46e5, #7c3aed); box-shadow: 0 6px 16px rgba(79, 70, 229, .25); }
    .eai-title h1 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1.2; }
    .eai-title p { font-size: 12.5px; color: #64748b; margin: 2px 0 0; }
    .eai-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 2px 12px rgba(15, 23, 42, .05); margin-bottom: 14px; overflow: hidden; }
    .eai-card-h { padding: 12px 16px; border-bottom: 1px solid #eef2f7; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .eai-card-h h2 { font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
    .eai-card-b { padding: 16px; }
    .eai-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 760px) { .eai-grid { grid-template-columns: 1fr; } }
    .eai-label { display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 5px; }
    .eai-input { width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #dce2eb; border-radius: 9px; padding: 0 11px; font-size: 13px; color: #0f172a; background: #fff; }
    .eai-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #6366f11a; }
    .eai-hint { font-size: 11.5px; color: #64748b; margin-top: 5px; line-height: 1.45; }
    .eai-btn { display: inline-flex; align-items: center; gap: 7px; height: 36px; padding: 0 14px; border-radius: 9px; border: 1px solid #dce2eb; background: #fff;
        color: #334155; font-size: 12.5px; font-weight: 700; cursor: pointer; text-decoration: none; white-space: nowrap; }
    .eai-btn:hover { background: #f8fafc; color: #0f172a; text-decoration: none; }
    .eai-btn.primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .eai-btn.primary:hover { background: #4338ca; color: #fff; }
    .eai-btn:disabled { opacity: .5; cursor: not-allowed; }
    .eai-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .eai-toggle { display: inline-flex; align-items: center; gap: 9px; font-size: 13px; font-weight: 600; color: #0f172a; cursor: pointer; }
    .eai-toggle input { width: 18px; height: 18px; accent-color: #4f46e5; }
    .eai-alert { border-radius: 10px; padding: 10px 13px; font-size: 13px; margin-bottom: 14px; display: flex; gap: 9px; align-items: flex-start; line-height: 1.45; }
    .eai-alert.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .eai-alert.err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .eai-alert.warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .eai-pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
    .eai-pill.ok { background: #dcfce7; color: #166534; }
    .eai-pill.off { background: #f1f5f9; color: #475569; }
    .eai-pill.err { background: #fee2e2; color: #991b1b; }
    .eai-pill.wait { background: #fef3c7; color: #92400e; }
    .eai-kv { display: grid; grid-template-columns: 160px 1fr; gap: 8px 14px; font-size: 13px; }
    .eai-kv dt { color: #64748b; }
    .eai-kv dd { margin: 0; color: #0f172a; font-weight: 600; word-break: break-all; }
    .eai-key { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; background: #0f172a; color: #e2e8f0;
        padding: 10px 12px; border-radius: 9px; word-break: break-all; }
    .eai-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11.5px; background: #f8fafc; border: 1px solid #e2e8f0;
        color: #0f172a; padding: 10px 12px; border-radius: 9px; white-space: pre-wrap; word-break: break-word; line-height: 1.55; margin: 6px 0 0; }
    .eai-steps { margin: 0; padding-left: 18px; font-size: 13px; color: #334155; line-height: 1.7; }
    html.dark .eai-card { background: #1e293b; border-color: #334155; }
    html.dark .eai-card-h { border-bottom-color: #334155; }
    html.dark .eai-title h1, html.dark .eai-card-h h2, html.dark .eai-kv dd, html.dark .eai-toggle { color: #e2e8f0; }
    html.dark .eai-input, html.dark .eai-btn { background: #0f172a; border-color: #475569; color: #e2e8f0; }
    html.dark .eai-code { background: #0f172a; border-color: #334155; color: #e2e8f0; }
    html.dark .eai-steps { color: #cbd5e1; }
</style>
