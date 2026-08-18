{{--
    Styling for the card payment source — Petty Cash, My Own Pocket, Bank Account.
    Prefixed .exp- and included once from the expense list page, which is the only
    place the modals live.

    How many cards actually render depends on what the user may use, so the grid
    columns are set inline by the partial; the 1fr 1fr below is only the fallback.
--}}
<style>
    .exp-source-cards{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:14px;}
    .exp-source-card{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;
        padding:12px 14px;background:#fff;border:0;cursor:pointer;text-align:center;transition:background .15s,color .15s;}
    .exp-source-card + .exp-source-card{border-left:1px solid #e5e7eb;}
    .exp-source-title{font-size:13.5px;font-weight:700;color:#374151;display:inline-flex;align-items:center;gap:7px;}
    .exp-source-sub{font-size:11px;color:#9ca3af;}
    .exp-source-card:hover{background:#f9fafb;}
    /* One accent per card, so the branch below is never a surprise. Each accent
       matches the panel that card reveals — teal for the pot, violet for the
       claim, blue for the bank.

       "own" was missing here while the JS had been setting is-active on it all
       along: clicking My Own Pocket switched the form correctly but left the card
       looking untouched, so the one card whose meaning is "no company cash moves"
       was also the one that gave no sign it had been chosen. Keyed by data-source
       like the other two rather than on .is-active alone, because the colour is
       what says WHICH branch is open. */
    .exp-source-card[data-source="cash"].is-active{background:#f0fdfa;box-shadow:inset 0 -3px 0 #0d9488;}
    .exp-source-card[data-source="cash"].is-active .exp-source-title{color:#0f766e;}
    .exp-source-card[data-source="own"].is-active{background:#f5f3ff;box-shadow:inset 0 -3px 0 #7c3aed;}
    .exp-source-card[data-source="own"].is-active .exp-source-title{color:#6d28d9;}
    .exp-source-card[data-source="bank"].is-active{background:#eff6ff;box-shadow:inset 0 -3px 0 #2563eb;}
    .exp-source-card[data-source="bank"].is-active .exp-source-title{color:#1d4ed8;}

    .exp-source-branch{display:flex;flex-direction:column;gap:12px;}
    /* This block is included after Tailwind, so a bare `display:flex` here beat
       Tailwind's `.hidden{display:none}` on equal specificity — the cash panel
       stayed on screen under the Bank card. Re-state it. */
    .exp-source-branch.hidden{display:none;}

    .exp-fund{border:1px solid #99f6e4;background:#f0fdfa;border-radius:10px;padding:12px 14px;}
    .exp-fund-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .exp-fund-label{font-size:12.5px;font-weight:800;color:#0f766e;}
    .exp-fund-company{font-size:10.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;
        color:#1d4ed8;background:#eff6ff;border:1px solid #bfdbfe;border-radius:20px;padding:2px 9px;white-space:nowrap;}
    .exp-fund-company.hidden{display:none;}
    .exp-fund-note{font-size:11.5px;color:#475569;margin-top:3px;line-height:1.5;}
    .exp-fund-figure{margin-top:7px;display:flex;align-items:baseline;gap:7px;flex-wrap:wrap;}
    .exp-fund-amount{font-size:21px;font-weight:800;color:#0f766e;line-height:1;}
    .exp-fund-cap{font-size:11.5px;color:#64748b;}
    /* Nothing in the pocket is a stop sign, not a detail. */
    .exp-fund.is-empty{border-color:#fecaca;background:#fef2f2;}
    .exp-fund.is-empty .exp-fund-label,
    .exp-fund.is-empty .exp-fund-amount{color:#b91c1c;}

    /* The daily cost fund meter — a LIMIT, sitting under the pot panel that shows
       real money. Neutral on purpose: the panel above it is the cash, and this is
       only an allowance, so it must not compete. Colour arrives here only when the
       limit is actually in danger, which matches the Petty Cash page exactly. */
    .exp-dfund{margin-top:8px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:10px 12px;}
    .exp-dfund.hidden{display:none;}
    .exp-dfund-head{display:flex;align-items:baseline;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .exp-dfund-label{font-size:11.5px;font-weight:800;color:#334155;}
    .exp-dfund-fig{font-size:11.5px;font-weight:700;color:#0f172a;white-space:nowrap;}
    .exp-dfund-track{height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:7px;}
    .exp-dfund-fill{height:100%;border-radius:inherit;background:#94a3b8;width:0;transition:width .2s ease;}
    .exp-dfund-note{font-size:11px;color:#64748b;margin-top:5px;line-height:1.5;}

    /* Near the limit, and past it. Past it is amber rather than red on purpose —
       red is what an empty pocket uses above, and that one actually stops the save
       while this one never does. Two different reds would flatten the difference. */
    .exp-dfund.is-near{border-color:#fde68a;background:#fffbeb;}
    .exp-dfund.is-near .exp-dfund-label,
    .exp-dfund.is-near .exp-dfund-fig,
    .exp-dfund.is-near .exp-dfund-note{color:#92400e;}
    .exp-dfund.is-near .exp-dfund-track{background:#fef3c7;}
    .exp-dfund.is-near .exp-dfund-fill{background:#f59e0b;}

    .exp-dfund.is-over{border-color:#fdba74;background:#fff7ed;}
    .exp-dfund.is-over .exp-dfund-label,
    .exp-dfund.is-over .exp-dfund-fig,
    .exp-dfund.is-over .exp-dfund-note{color:#9a3412;}
    .exp-dfund.is-over .exp-dfund-track{background:#ffedd5;}
    .exp-dfund.is-over .exp-dfund-fill{background:#ea580c;}

    @media(max-width:640px){
        .exp-source-cards{grid-template-columns:1fr;}
        .exp-source-card + .exp-source-card{border-left:0;border-top:1px solid #e5e7eb;}
    }
</style>
