<style>
    .fi-sidebar { border-right: 1px solid rgba(148,163,184,.15); }
    .fi-sidebar-header { box-shadow: none !important; }
    .fi-sidebar-nav { gap: 1.5rem; }
    .fi-main { padding-top: 2rem; }
    .fi-section, .fi-ta-ctn { border-radius: 1rem; }
    .visible-welcome { display:flex; align-items:center; justify-content:space-between; gap:2rem; padding:.5rem; }
    .visible-eyebrow { color:#0d9488; font-size:.7rem; letter-spacing:.16em; font-weight:700; margin-bottom:.75rem; }
    .visible-welcome h2 { font-size:1.9rem; line-height:1.2; letter-spacing:-.035em; font-weight:700; margin-bottom:.75rem; }
    .visible-welcome p:not(.visible-eyebrow) { color:#64748b; max-width:42rem; line-height:1.7; font-size:.95rem; }
    .visible-cta { display:flex; gap:1rem; align-items:center; white-space:nowrap; border-radius:.75rem; background:#0f766e; color:white; padding:.85rem 1.15rem; font-size:.875rem; font-weight:600; }
    .visible-cta:hover { background:#115e59; }
    .visible-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1.5rem; margin-top:1.75rem; padding:.5rem; border-top:1px solid rgba(148,163,184,.2); }
    .visible-steps div { padding-top:1rem; }
    .visible-steps b { display:block; font-size:.8rem; margin-bottom:.4rem; }
    .visible-steps span { color:#64748b; font-size:.8rem; line-height:1.6; }
    .dark .visible-welcome p:not(.visible-eyebrow), .dark .visible-steps span { color:#94a3b8; }
    .dark .visible-eyebrow { color:#5eead4; }
    @media(max-width:768px) {
        .visible-welcome { flex-direction:column; align-items:flex-start; gap:1.25rem; }
        .visible-welcome h2 { font-size:1.5rem; }
        .visible-steps { grid-template-columns:1fr; gap:.5rem; }
    }
</style>

