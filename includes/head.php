<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DYSL | Scholarship Library</title>
<script nonce="<?= cspNonce() ?>" src="https://cdn.tailwindcss.com/3.4.17"></script>
<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/chart.js@4.6.1/dist/chart.umd.min.js"></script>
<script nonce="<?= cspNonce() ?>">
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                dyci: {
                    blue: '#1A3B9C',
                    blueDark: '#122B78',
                    gold: '#F4B925',
                    goldHover: '#E0A619',
                    goldInk: '#8A6100',
                },
                brand: {
                    50: '#fefce8', 100: '#fef9c3', 200: '#fef08a', 300: '#fde047', 400: '#facc15',
                    500: '#eab308', 600: '#ca8a04', 700: '#a16207', 800: '#854d0e', 900: '#713f12'
                }
            }
        }
    }
}
</script>
<style>
body{font-family:Inter,ui-sans-serif,system-ui,sans-serif}.view{display:none}.view.active{display:block}
#sidebar{transform:translateX(-100%);visibility:hidden;transition:transform .2s ease,visibility 0s linear .2s;max-width:calc(100vw - 2rem)}
#sidebar.open{transform:translateX(0);visibility:visible;transition-delay:0s}
#sidebarEdge{display:none}
@media(min-width:1024px) and (hover:hover) and (pointer:fine){
  #sidebarEdge{display:block;position:fixed;inset:0 auto 0 0;width:12px;z-index:39}
  #sidebarEdge::before{content:"";position:absolute;inset:calc(50% - 22px) auto auto 0;width:3px;height:44px;background:#d5a01d;border-radius:0 3px 3px 0}
}
#sidebar .nav{display:block;width:100%;min-height:46px;padding:.75rem 1.5rem;border-inline-start:3px solid transparent;text-align:left;font-size:.875rem;font-weight:500;transition:background-color .18s ease,color .18s ease,border-color .18s ease}
#sidebar .nav:hover,#sidebar .nav:focus-visible{background:#eef2fb;color:#122B78;border-inline-start-color:#d5a01d}
.dark #sidebar .nav:hover,.dark #sidebar .nav:focus-visible{background:var(--night-hover);color:var(--night-text);border-inline-start-color:#F4B925}
#sidebar .nav:focus-visible{outline-offset:-3px}
#sidebar .nav.active{background:#e3ebfc;color:#1A3B9C;border-inline-start-color:#F4B925;font-weight:700}
.dark #sidebar .nav.active{background:var(--night-raised);color:var(--night-text)}
#sidebar .nav:active{transform:none}

/* THIS IS THE CRITICAL FIX FOR THE INVISIBLE POPUP */
#modal { display: none; }
#modal.open { display: flex; }

.toast{pointer-events:none;transform:translateY(8px);opacity:0;transition:transform .2s,opacity .2s}.toast.show{transform:translateY(0);opacity:1}

:root{--brand:#F4B925;--brand2:#E0A619;--ink:#050B1E;--muted:#64748b;--line:#e2e8f0;--surface:#fff;--soft:#f8fafc}
*{box-sizing:border-box} body{letter-spacing:-.01em;background:#f6f7fb!important}.view{animation:fadeIn .22s ease}
.dark {
    color-scheme: dark;
    --night-bg: #0d0d0f;
    --night-surface: #18181b;
    --night-raised: #222225;
    --night-field: #141416;
    --night-hover: #2a2a2e;
    --night-border: #39393f;
    --night-text: #f5f5f6;
    --night-muted: #b9b9c0;
    --night-link: #fff;
}
.dark .scholar-workspace { background:var(--night-bg)!important; color:var(--night-text); }
.dark .scholar-workspace :is(.dark\:bg-slate-900, #sidebar) { background-color:var(--night-surface); }
.dark .scholar-workspace :is(.dark\:bg-slate-800, .dark\:bg-slate-800\/40) { background-color:var(--night-raised); }
.dark .scholar-workspace :is(.dark\:border-slate-800, .dark\:border-slate-700, #sidebarToggle) { border-color:var(--night-border); }
.dark .scholar-workspace :is(.text-slate-400, .text-slate-500, .text-slate-600):not([class*="dark:text-"]) { color:var(--night-muted); }
.dark .scholar-workspace :is(.text-dyci-blue, [class*="dark:text-blue-"], #themeToggle) { color:var(--night-link); }
.dark .scholar-workspace [class*="dark:bg-dyci-blue/"] { background-color:var(--night-raised); }
.dark .scholar-workspace .bg-dyci-blue { background-color:#fff; color:#141416; }
.dark .scholar-workspace .bg-dyci-blue:hover { background-color:#e7e7ea; }
.dark #dashboard .rounded-2xl > .dark\:text-slate-300 { color:#fff; }
.dark .scholar-workspace > main > header {
    background-color:var(--night-surface);
    border-color:var(--night-border);
}
.dark .scholar-workspace :is(input:not([type=checkbox]):not([type=radio]):not([type=hidden]), select) {
    background-color:var(--night-field); border-color:var(--night-border); color:var(--night-text);
}
.dark .scholar-workspace input::placeholder { color:var(--night-muted); }
.dark .scholar-workspace :is(a, button):focus-visible { outline-color:#f4b925; }
.dark .scholar-workspace :is(tbody tr:hover, .scholarship-option:hover, #themeToggle:hover) { background-color:var(--night-hover); }
.card,.rounded-2xl.border,.rounded-3xl{box-shadow:0 1px 2px rgba(15,23,42,.06)}
header{box-shadow:0 1px 0 rgba(15,23,42,.04)}
input,select{transition:border-color .15s,box-shadow .15s,background .15s}input:focus,select:focus{outline:none!important;border-color:#F4B925!important;box-shadow:0 0 0 4px rgba(244,185,37,.15)}
button{transition:transform .15s,box-shadow .15s,background .15s}button:active{transform:scale(.98)}
#modal{backdrop-filter:blur(7px)}#modal>div{animation:modalIn .22s ease}
.scholar-form-panel { max-height:calc(100vh - 2rem); max-height:calc(100dvh - 2rem); }
.scholar-form-scroll { margin-inline:4px; scrollbar-width:thin; scrollbar-color:#8b9bb2 transparent; overscroll-behavior-y:contain; }
.dark .scholar-form-scroll { scrollbar-color:#68686f transparent; }
#scholarshipCards>div{min-height:235px}.scholarship-card:hover{transform:translateY(-3px)}
@keyframes fadeIn{from{opacity:.35;transform:translateY(4px)}to{opacity:1;transform:none}}@keyframes modalIn{from{opacity:0;transform:translateY(12px) scale(.985)}to{opacity:1;transform:none}}
@media(max-width:1023px){main{margin-left:0!important}}
.scholarship-picker{max-height:330px;overflow:auto}
.scholarship-option{transition:.15s ease}
.scholarship-option:hover{background:#f8fafc}
.dark .scholarship-option:hover{background:var(--night-hover)}
.scholarship-option input{accent-color:#F4B925}
.chart-wrap{position:relative;height:330px}
@media(max-width:640px){.chart-wrap{height:280px}}
.tabular-nums, .chart-wrap { font-variant-numeric: tabular-nums; }
.table-pagination:not([hidden]) { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; padding:.75rem 1.25rem; border-top:1px solid #e2e8f0; font-size:.875rem; }
.dark .table-pagination { border-color:var(--night-border); }
.recent-scholar-row:enabled { cursor:pointer; }
.recent-scholar-row:enabled:is(:hover,:focus-visible) { background:#eef4ff; box-shadow:inset 3px 0 #1a3b9c; }
.dark .recent-scholar-row:enabled:is(:hover,:focus-visible) { background:var(--night-hover); box-shadow:inset 3px 0 var(--night-link); }
.recent-scholar-row:focus-visible { outline-offset:-3px; }
.recent-scholar-row:enabled:active { transform:none; box-shadow:inset 4px 0 #f4b925; }
.recent-scholar-arrow { color:#1a3b9c; font-size:1.4rem; line-height:1; transition:transform .18s ease; }
.dark .recent-scholar-arrow { color:var(--night-link); }
.recent-scholar-row:enabled:is(:hover,:focus-visible) .recent-scholar-arrow { transform:translateX(4px); }
.recent-scholar-row:enabled:active .recent-scholar-arrow { transform:translateX(1px); }
.skeleton { display:block; width:100%; max-width:24rem; height:.85rem; margin:.75rem 0; border-radius:.4rem; background:#e2e8f0; animation:pulse 1.4s ease-in-out infinite; }
.dark .skeleton { background:var(--night-border); }
@keyframes pulse { 50% { opacity:.48; } }
button:focus-visible, a:focus-visible { outline:2px solid #b07800; outline-offset:3px; }

.scholar-workspace {
    --workspace-line:rgba(25,47,79,.16);
    --workspace-tint:rgba(26,59,156,.08);
    --workspace-toolbar:linear-gradient(110deg,rgba(255,255,255,.97),rgba(240,245,255,.94));
    --workspace-depth:0 2px 4px rgba(15,23,42,.05);
    min-height:100vh;
    min-height:100dvh;
}
.dark .scholar-workspace {
    --workspace-line:rgba(228,228,238,.18);
    --workspace-tint:rgba(228,228,238,.09);
    --workspace-toolbar:linear-gradient(110deg,#1b1b1e,#151517);
    --workspace-depth:0 4px 16px rgba(0,0,0,.23);
}
@supports (color:oklch(50% .1 250)) {
    .scholar-workspace {
        --workspace-line:oklch(35% .07 255 / .19);
        --workspace-tint:oklch(48% .14 257 / .09);
    }
    .dark .scholar-workspace {
        --night-bg:oklch(16% .004 270);
        --night-surface:oklch(21% .006 270);
        --night-raised:oklch(25% .006 270);
        --night-field:oklch(19% .005 270);
        --night-hover:oklch(29% .007 270);
        --night-border:oklch(35% .009 270);
        --night-text:oklch(97% .002 270);
        --night-muted:oklch(80% .01 270);
        --workspace-line:oklch(90% .008 270 / .18);
        --workspace-tint:oklch(90% .008 270 / .09);
    }
}
@supports (background:linear-gradient(in oklch,white,black)) {
    .scholar-workspace { --workspace-toolbar:linear-gradient(110deg in oklch,oklch(100% 0 0 / .97),oklch(97% .018 258 / .94)); }
    .dark .scholar-workspace { --workspace-toolbar:linear-gradient(110deg in oklch,oklch(22% .005 270),oklch(19.5% .004 270)); }
}
.scholar-workspace > main > header { background-image:var(--workspace-toolbar); border-color:var(--workspace-line); }
.scholar-workspace :is(.rounded-2xl.border, .scholar-form-panel) { border-color:var(--workspace-line); }
.scholar-workspace .rounded-2xl.border:not(dialog) { box-shadow:var(--workspace-depth); }
.workspace-sidebar-brand { position:relative; isolation:isolate; }
.workspace-sidebar-brand::before {
    content:""; position:absolute; inset:0; z-index:-1; pointer-events:none;
    background:var(--workspace-tint);
    mask-image:linear-gradient(90deg,#000 15%,transparent 85%);
    mix-blend-mode:multiply;
}
.dark .workspace-sidebar-brand::before { mix-blend-mode:normal; }
.workspace-icon-button {
    --icon-radius:12px;
    display:grid; place-items:center; width:44px; height:44px; position:relative;
    border-radius:var(--icon-radius); border-color:var(--workspace-line);
}
.workspace-icon-button::before {
    content:""; position:absolute; inset:4px; pointer-events:none; opacity:0;
    border-radius:calc(var(--icon-radius) - 4px); background:var(--workspace-tint);
    transition:opacity .18s ease;
}
.workspace-icon-button:is(:hover,:focus-visible)::before { opacity:1; }
.workspace-icon-button svg {
    grid-area:1 / 1; position:relative; width:20px; height:20px; fill:none;
    stroke:currentColor; stroke-width:1.75; stroke-linecap:round; stroke-linejoin:round;
}
#themeToggle svg { transition:opacity .18s ease, filter .18s ease, transform .18s ease; }
.theme-icon-sun, .dark .theme-icon-moon { opacity:0; filter:blur(3px); transform:scale(.8); }
.dark .theme-icon-sun { opacity:1; filter:none; transform:none; }
.scholar-workspace .scholar-form-panel { --panel-radius:1.75rem; border-radius:var(--panel-radius); }
.scholar-form-panel .scholar-form-scroll { --panel-inset:1rem; margin-inline:0; padding:var(--panel-inset); }
.scholar-form-panel .scholar-form-scroll > .rounded-2xl { border-radius:calc(var(--panel-radius) - var(--panel-inset)); }
@media (min-width:640px) {
    .scholar-form-panel .scholar-form-scroll { --panel-inset:1.125rem; }
}
.scholar-workspace dialog { max-height:calc(100vh - 2rem); max-height:calc(100dvh - 2rem); }
.scholar-workspace dialog:not([open]) { display:none; }
.scholar-workspace dialog[open] { z-index:50; }
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
