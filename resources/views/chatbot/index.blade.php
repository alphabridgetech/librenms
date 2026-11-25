@extends('layouts.librenmsv1')

@section('title', __('TeleQuill AI'))

@section('content')
@php($tqUserName = auth()->user()->realname ?: auth()->user()->username)
<div class="container-fluid">
<div class="tq-ai" id="tq-ai">

    {{-- Saved chats --}}
    <aside class="tq-side" id="tq-side">
        <button type="button" id="new-chat" class="tq-new"><i class="fa fa-plus"></i> New chat</button>
        <div class="tq-side-label">Your chats</div>
        <div id="chat-list" class="tq-list" aria-label="Saved chats">
            <div class="tq-list-empty">Loading…</div>
        </div>
    </aside>

    <div class="tq-main">
    {{-- Header --}}
    <div class="tq-header">
        <div class="tq-brand">
            <button type="button" id="side-toggle" class="tq-icon-btn tq-side-toggle" title="Show or hide your chats"><i class="fa fa-bars"></i></button>
            <div class="tq-logo"><i class="fa fa-wand-magic-sparkles"></i></div>
            <div>
                <div class="tq-title" id="tq-chat-title">TeleQuill AI</div>
                <div class="tq-sub"><span class="tq-dot" id="tq-status-dot"></span> <span id="tq-status-text">Network assistant</span></div>
            </div>
        </div>
        <div class="tq-tools">
            <div class="tq-seg" role="tablist" aria-label="Mode">
                <button type="button" class="tq-seg-btn" data-mode="bubble"><i class="fa fa-comments"></i> Chat</button>
                <button type="button" class="tq-seg-btn" data-mode="terminal"><i class="fa fa-terminal"></i> Terminal</button>
            </div>
            <select id="voice-lang" class="tq-select" title="Voice language">
                <option value="en-IN">English (India)</option>
                <option value="en-US">English (US)</option>
                <option value="en-GB">English (UK)</option>
                <option value="hi-IN">Hindi (India)</option>
            </select>
            <button type="button" id="voice-toggle" class="tq-icon-btn" title="Read replies aloud">
                <i class="fa fa-volume-xmark"></i>
            </button>
            <button type="button" id="clear-chat" class="tq-btn tq-btn-danger" title="Delete all messages in this chat">
                <i class="fa fa-trash-can"></i> <span>Clear chat</span>
            </button>
        </div>
    </div>

    {{-- Chat mode --}}
    <div id="chat-view" class="tq-view">
        <div id="chat-scroll" class="tq-scroll">
            <div class="tq-column">
                <div id="tq-welcome" class="tq-welcome">
                    <div class="tq-welcome-logo"><i class="fa fa-wand-magic-sparkles"></i></div>
                    <h2>Hi {{ $tqUserName }}, how can I help with your network?</h2>
                    <p>Ask about devices, ports, alerts and sensors, or ask me to add or remove a device.</p>
                    <div class="tq-suggest">
                        <button type="button" class="tq-card" data-prompt="Which devices are down right now?">
                            <i class="fa fa-server"></i>
                            <span class="tq-card-title">Device health</span>
                            <span class="tq-card-text">Which devices are down right now?</span>
                        </button>
                        <button type="button" class="tq-card" data-prompt="Summarize the active alerts by severity.">
                            <i class="fa fa-bell"></i>
                            <span class="tq-card-title">Alerts</span>
                            <span class="tq-card-text">Summarize the active alerts by severity</span>
                        </button>
                        <button type="button" class="tq-card" data-prompt="Which ports have errors or are operationally down?">
                            <i class="fa fa-ethernet"></i>
                            <span class="tq-card-title">Ports</span>
                            <span class="tq-card-text">Which ports have errors or are down?</span>
                        </button>
                        <button type="button" class="tq-card" data-prompt="Show sensors with high temperature.">
                            <i class="fa fa-temperature-half"></i>
                            <span class="tq-card-title">Sensors</span>
                            <span class="tq-card-text">Show sensors with high temperature</span>
                        </button>
                    </div>
                </div>
                <div id="chat-messages" class="tq-messages" aria-live="polite"></div>
            </div>
        </div>

        <div class="tq-composer-wrap">
            <div class="tq-column">
                <div class="tq-composer">
                    <textarea id="chat-input" rows="1" placeholder="Message TeleQuill AI…" aria-label="Message"></textarea>
                    <div class="tq-composer-actions">
                        <button type="button" id="mic-btn" class="tq-icon-btn" title="Speak your message">
                            <i class="fa fa-microphone"></i>
                        </button>
                        <button type="button" id="send-btn" class="tq-send" title="Send (Enter)" disabled>
                            <i class="fa fa-arrow-up"></i>
                        </button>
                    </div>
                </div>
                <div class="tq-foot">Enter to send · Shift+Enter for a new line · ↑ for earlier messages · Chats are saved to your account · TeleQuill AI can make mistakes, so check changes before applying them to devices.</div>
            </div>
        </div>
    </div>

    {{-- Terminal mode (same chat, shown as a console) --}}
    <div id="terminal-view" class="tq-view tq-terminal" style="display:none;">
        <div id="chat-terminal-box" class="tq-term-out"></div>
        <div class="tq-term-in">
            <span class="tq-term-prompt">telequill@ai:~$</span>
            <input type="text" id="terminal-input" autocomplete="off" spellcheck="false" aria-label="Terminal command">
        </div>
    </div>
    </div>

</div>
</div>
@endsection

@section('css')
<style>
.tq-ai {
    --tq-bg: #ffffff;
    --tq-surface: #f7f7f9;
    --tq-side: #f9f9fb;
    --tq-border: #e4e4ea;
    --tq-text: #1f2328;
    --tq-muted: #6b7280;
    --tq-accent: #6d5dfc;
    --tq-accent-2: #2f80ed;
    --tq-user: #eef0ff;
    --tq-user-text: #1f2328;
    --tq-code: #0f172a;
    --tq-error: #fdecec;
    --tq-error-text: #b42318;
    position: relative;
    display: flex;
    height: calc(100vh - 140px); min-height: 520px;
    background: var(--tq-bg); color: var(--tq-text);
    border: 1px solid var(--tq-border); border-radius: 16px;
    overflow: hidden; margin-bottom: 15px;
    font-size: 14px;
}
html.dark .tq-ai {
    --tq-bg: #16171d;
    --tq-surface: #1f2028;
    --tq-side: #121318;
    --tq-border: #2e303a;
    --tq-text: #e6e7eb;
    --tq-muted: #9ca3af;
    --tq-user: #2c2a4a;
    --tq-user-text: #ecebff;
    --tq-code: #0b0d12;
    --tq-error: #3a1d1d;
    --tq-error-text: #fca5a5;
}
.tq-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

/* Saved chats sidebar */
.tq-side { width: 260px; flex: 0 0 260px; display: flex; flex-direction: column; gap: 8px;
    background: var(--tq-side); border-right: 1px solid var(--tq-border); padding: 12px 10px; }
.tq-ai.side-hidden .tq-side { display: none; }
.tq-new { display: flex; align-items: center; justify-content: center; gap: 8px; height: 38px; border-radius: 10px; border: 0;
    color: #fff; font-weight: 600; background: linear-gradient(135deg, var(--tq-accent), var(--tq-accent-2)); }
.tq-new:hover { filter: brightness(1.06); }
.tq-side-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--tq-muted); padding: 8px 6px 2px; }
.tq-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 2px; }
.tq-list-empty { color: var(--tq-muted); font-size: 12.5px; padding: 6px; }
.tq-item { display: flex; align-items: center; gap: 6px; padding: 8px 8px 8px 10px; border-radius: 9px; cursor: pointer; color: var(--tq-text); }
.tq-item:hover { background: var(--tq-surface); }
.tq-item.active { background: var(--tq-user); }
.tq-item-text { flex: 1; min-width: 0; }
.tq-item-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px; }
.tq-item-time { font-size: 11px; color: var(--tq-muted); }
.tq-item-act { display: none; border: 0; background: transparent; color: var(--tq-muted); width: 24px; height: 24px; border-radius: 6px; font-size: 12px; }
.tq-item:hover .tq-item-act, .tq-item.active .tq-item-act { display: inline-block; }
.tq-item-act:hover { background: var(--tq-border); color: var(--tq-text); }
.tq-item-act.del:hover { color: #dc2626; }
.tq-item.renaming .tq-item-act { display: none; }
.tq-rename { width: 100%; height: 26px; border: 1px solid var(--tq-accent); border-radius: 6px; padding: 0 6px;
    background: var(--tq-bg); color: var(--tq-text); font-size: 13px; outline: 0; box-shadow: 0 0 0 3px rgba(109,93,252,.15); }
.tq-modal-back { position: absolute; inset: 0; z-index: 20; display: flex; align-items: center; justify-content: center;
    background: rgba(15,17,25,.35); backdrop-filter: blur(2px); animation: tq-in .12s ease-out; }
.tq-modal { width: min(400px, calc(100% - 32px)); background: var(--tq-bg); color: var(--tq-text); border: 1px solid var(--tq-border);
    border-radius: 14px; padding: 20px; box-shadow: 0 20px 50px rgba(0,0,0,.25); }
.tq-modal-title { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
.tq-modal-text { color: var(--tq-muted); font-size: 13.5px; line-height: 1.5; }
.tq-modal-btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
.tq-modal-btns button { height: 34px; padding: 0 16px; border-radius: 9px; font-size: 13px; font-weight: 600; }
.tq-modal-btns .no { border: 1px solid var(--tq-border); background: var(--tq-bg); color: var(--tq-text); }
.tq-modal-btns .no:hover { background: var(--tq-surface); }
.tq-modal-btns .yes { border: 0; color: #fff; background: linear-gradient(135deg, var(--tq-accent), var(--tq-accent-2)); }
.tq-modal-btns .yes.danger { background: #dc2626; }
.tq-modal-btns .yes.danger:hover { background: #b91c1c; }
.tq-toast { position: absolute; z-index: 21; left: 50%; bottom: 90px; transform: translateX(-50%); max-width: calc(100% - 32px);
    background: #1f2328; color: #fff; padding: 9px 14px; border-radius: 10px; font-size: 13px; box-shadow: 0 8px 24px rgba(0,0,0,.25); }

/* Header */
.tq-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    padding: 12px 18px; border-bottom: 1px solid var(--tq-border); background: var(--tq-bg); }
.tq-brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
.tq-logo, .tq-avatar, .tq-welcome-logo {
    display: flex; align-items: center; justify-content: center; color: #fff;
    background: linear-gradient(135deg, var(--tq-accent), var(--tq-accent-2));
}
.tq-logo { width: 38px; height: 38px; flex: 0 0 38px; border-radius: 12px; font-size: 17px; }
.tq-title { font-weight: 700; font-size: 16px; line-height: 1.2; max-width: 360px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tq-sub { font-size: 12px; color: var(--tq-muted); }
.tq-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #22c55e; margin-right: 3px; }
.tq-tools { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tq-seg { display: inline-flex; background: var(--tq-surface); border: 1px solid var(--tq-border); border-radius: 10px; padding: 3px; }
.tq-seg-btn { border: 0; background: transparent; color: var(--tq-muted); padding: 5px 12px; border-radius: 7px; font-size: 13px; }
.tq-seg-btn.active { background: var(--tq-bg); color: var(--tq-text); box-shadow: 0 1px 3px rgba(0,0,0,.12); font-weight: 600; }
.tq-dot.offline { background: #f59e0b; }
.tq-btn-danger:hover { color: #dc2626; border-color: #dc2626; background: rgba(220,38,38,.06); }
.tq-select { height: 32px; border: 1px solid var(--tq-border); border-radius: 8px; background: var(--tq-bg); color: var(--tq-text); padding: 0 8px; font-size: 13px; }
.tq-btn { height: 32px; border: 1px solid var(--tq-border); border-radius: 8px; background: var(--tq-bg); color: var(--tq-text); padding: 0 12px; font-size: 13px; }
.tq-btn:hover, .tq-icon-btn:hover { background: var(--tq-surface); }
.tq-icon-btn { width: 32px; height: 32px; flex: 0 0 32px; border: 1px solid var(--tq-border); border-radius: 8px; background: var(--tq-bg); color: var(--tq-muted); }
.tq-icon-btn.on { color: var(--tq-accent); border-color: var(--tq-accent); }
.tq-icon-btn.listening { color: #fff; background: #ef4444; border-color: #ef4444; animation: tq-pulse 1.2s infinite; }
.tq-icon-btn:disabled { opacity: .4; cursor: not-allowed; }

/* Layout */
.tq-view { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.tq-scroll { flex: 1; overflow-y: auto; padding: 24px 16px 8px; }
.tq-column { max-width: 820px; margin: 0 auto; width: 100%; }

/* Welcome */
.tq-welcome { text-align: center; padding: 6vh 0 12px; }
.tq-welcome-logo { width: 60px; height: 60px; border-radius: 18px; font-size: 26px; margin: 0 auto 16px;
    box-shadow: 0 10px 30px rgba(109,93,252,.35); }
.tq-welcome h2 { font-size: 24px; font-weight: 700; margin: 0 0 6px; color: var(--tq-text); }
.tq-welcome p { color: var(--tq-muted); margin: 0 0 24px; }
.tq-suggest { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; text-align: left; }
.tq-card { display: flex; flex-direction: column; gap: 3px; padding: 14px; border: 1px solid var(--tq-border);
    border-radius: 12px; background: var(--tq-bg); color: var(--tq-text); text-align: left; transition: all .15s; }
.tq-card:hover { border-color: var(--tq-accent); background: var(--tq-surface); transform: translateY(-1px); }
.tq-card i { color: var(--tq-accent); font-size: 15px; margin-bottom: 4px; }
.tq-card-title { font-weight: 600; font-size: 13px; }
.tq-card-text { color: var(--tq-muted); font-size: 12.5px; }

/* Messages */
.tq-msg { display: flex; gap: 12px; margin: 0 0 22px; animation: tq-in .2s ease-out; }
.tq-msg.user { justify-content: flex-end; }
.tq-avatar { flex: 0 0 30px; width: 30px; height: 30px; border-radius: 9px; font-size: 13px; margin-top: 2px; }
.tq-body { min-width: 0; max-width: 100%; }
.tq-user-wrap { max-width: 78%; display: flex; flex-direction: column; align-items: flex-end; }
.tq-msg.user .tq-body { max-width: 100%; background: var(--tq-user); color: var(--tq-user-text);
    padding: 10px 14px; border-radius: 18px 18px 4px 18px; }
.tq-msg.assistant [data-act="regen"] { display: none; }
.tq-msg.assistant.tq-last [data-act="regen"] { display: inline-block; }
.tq-msg.assistant.tq-last .tq-actions { opacity: 1; }
.tq-edit { width: min(78%, 640px); background: var(--tq-surface); border: 1px solid var(--tq-accent); border-radius: 14px; padding: 10px; }
.tq-edit textarea { width: 100%; border: 0; outline: 0; resize: none; background: transparent; color: var(--tq-text); font-size: 14px; line-height: 1.5; }
.tq-edit-btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 6px; }
.tq-edit-btns button { height: 30px; padding: 0 14px; border-radius: 8px; font-size: 13px; font-weight: 600; }
.tq-edit-btns .yes { border: 0; color: #fff; background: linear-gradient(135deg, var(--tq-accent), var(--tq-accent-2)); }
.tq-edit-btns .no { border: 1px solid var(--tq-border); background: var(--tq-bg); color: var(--tq-text); }
.tq-msg.assistant .tq-body { flex: 1; padding-top: 4px; line-height: 1.6; }
.tq-body p { margin: 0 0 8px; } .tq-body p:last-child { margin-bottom: 0; }
.tq-md h1, .tq-md h2, .tq-md h3, .tq-md h4, .tq-md h5, .tq-md h6 { font-weight: 700; line-height: 1.35; margin: 14px 0 6px; color: var(--tq-text); }
.tq-md h1 { font-size: 18px; } .tq-md h2 { font-size: 16.5px; } .tq-md h3 { font-size: 15px; } .tq-md h4, .tq-md h5, .tq-md h6 { font-size: 14px; }
.tq-md > :first-child { margin-top: 0; }
.tq-md ul, .tq-md ol { margin: 0 0 10px; padding-left: 22px; }
.tq-md li { margin: 2px 0; } .tq-md li > ul, .tq-md li > ol { margin: 2px 0 0; }
.tq-md code { background: var(--tq-surface); border: 1px solid var(--tq-border); border-radius: 5px; padding: 1px 5px; font-size: 12.5px; color: inherit; }
.tq-md pre code { background: none; border: 0; padding: 0; color: inherit; }
.tq-md table { border-collapse: collapse; margin: 6px 0 12px; font-size: 13px; display: block; overflow-x: auto; max-width: 100%; }
.tq-md th, .tq-md td { border: 1px solid var(--tq-border); padding: 6px 10px; text-align: left; vertical-align: top; }
.tq-md th { background: var(--tq-surface); font-weight: 600; }
.tq-md hr { border: 0; border-top: 1px solid var(--tq-border); margin: 12px 0; }
.tq-md blockquote { margin: 0 0 10px; padding: 4px 12px; border-left: 3px solid var(--tq-accent); color: var(--tq-muted); }
.tq-md a { color: var(--tq-accent-2); text-decoration: underline; }
.tq-body ul { margin: 0 0 8px; padding-left: 20px; }
.tq-body code.tq-inline { background: var(--tq-surface); border: 1px solid var(--tq-border); border-radius: 5px; padding: 1px 5px; font-size: 12.5px; color: inherit; }
.tq-msg.error .tq-body { background: var(--tq-error); color: var(--tq-error-text); padding: 10px 14px; border-radius: 12px; }
.tq-badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; margin-bottom: 6px;
    background: rgba(34,197,94,.15); color: #16a34a; }
.tq-badge-builtin { background: rgba(47,128,237,.13); color: var(--tq-accent-2); }
.tq-badge-ai { background: rgba(109,93,252,.13); color: var(--tq-accent); }
.tq-badge-warn { background: rgba(245,158,11,.15); color: #b45309; }
.tq-badge-muted { background: var(--tq-surface); color: var(--tq-muted); }
.tq-confirm { display: flex; gap: 8px; margin-top: 10px; }
.tq-confirm button { height: 32px; padding: 0 14px; border-radius: 8px; font-size: 13px; font-weight: 600; }
.tq-confirm .yes { border: 0; color: #fff; background: #16a34a; }
.tq-confirm .yes:hover { background: #15803d; }
.tq-confirm .no { border: 1px solid var(--tq-border); background: var(--tq-bg); color: var(--tq-text); }
.tq-confirm .no:hover { background: var(--tq-surface); }
html.dark .tq-badge-warn { color: #fbbf24; }
.tq-actions { display: flex; gap: 4px; margin-top: 6px; opacity: 0; transition: opacity .15s; }
.tq-msg:hover .tq-actions { opacity: 1; }
.tq-actions button { border: 0; background: transparent; color: var(--tq-muted); padding: 3px 6px; border-radius: 6px; font-size: 12px; }
.tq-actions button:hover { background: var(--tq-surface); color: var(--tq-text); }
.tq-pre { position: relative; margin: 8px 0; }
.tq-pre pre { background: var(--tq-code); color: #e5e7eb; border: 0; border-radius: 10px; padding: 14px; margin: 0;
    white-space: pre-wrap; word-break: break-word; font-size: 12.5px; }
.tq-pre .tq-copy { position: absolute; top: 6px; right: 6px; border: 0; border-radius: 6px; font-size: 11px;
    padding: 3px 8px; background: rgba(255,255,255,.1); color: #cbd5e1; }
.tq-typing { display: inline-flex; align-items: center; gap: 4px; color: var(--tq-muted); font-size: 13px; }
.tq-typing span { width: 6px; height: 6px; border-radius: 50%; background: var(--tq-accent); animation: tq-bounce 1.2s infinite; }
.tq-typing span:nth-child(2) { animation-delay: .15s; } .tq-typing span:nth-child(3) { animation-delay: .3s; }
.tq-typing em { margin-left: 6px; font-style: normal; }

/* Composer */
.tq-composer-wrap { padding: 8px 16px 14px; background: var(--tq-bg); }
.tq-composer { display: flex; align-items: flex-end; gap: 8px; border: 1px solid var(--tq-border); background: var(--tq-surface);
    border-radius: 18px; padding: 8px 8px 8px 16px; transition: border-color .15s, box-shadow .15s; }
.tq-composer:focus-within { border-color: var(--tq-accent); box-shadow: 0 0 0 3px rgba(109,93,252,.15); }
.tq-composer textarea { flex: 1; border: 0; outline: 0; resize: none; background: transparent; color: var(--tq-text);
    max-height: 180px; padding: 6px 0; line-height: 1.5; font-size: 14px; }
.tq-composer-actions { display: flex; gap: 6px; }
.tq-send { width: 34px; height: 34px; border: 0; border-radius: 50%; color: #fff;
    background: linear-gradient(135deg, var(--tq-accent), var(--tq-accent-2)); }
.tq-send:disabled { background: var(--tq-border); color: var(--tq-muted); cursor: not-allowed; }
.tq-foot { text-align: center; color: var(--tq-muted); font-size: 11.5px; margin-top: 7px; }

/* Terminal */
.tq-terminal { background: #0b0d12; }
.tq-term-out { flex: 1; overflow-y: auto; padding: 16px 18px; font-family: SFMono-Regular, Consolas, monospace; font-size: 13px; color: #d1d5db; }
.tq-term-out .tq-line { white-space: pre-wrap; word-break: break-word; margin-bottom: 4px; }
.tq-term-out .tq-line.cmd { color: #a5b4fc; }
.tq-term-out .tq-line.reply { color: #86efac; }
.tq-term-out .tq-line.err { color: #fca5a5; }
.tq-term-sys { color: #6b7280; margin-bottom: 8px; }
.tq-term-in { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-top: 1px solid #1f2430; }
.tq-term-prompt { color: #a5b4fc; font-family: SFMono-Regular, Consolas, monospace; font-size: 13px; white-space: nowrap; }
.tq-term-in input { flex: 1; border: 0; outline: 0; background: transparent; color: #e5e7eb; font-family: SFMono-Regular, Consolas, monospace; font-size: 13px; }

@keyframes tq-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
@keyframes tq-bounce { 0%, 60%, 100% { transform: translateY(0); opacity: .5; } 30% { transform: translateY(-4px); opacity: 1; } }
@keyframes tq-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.5); } 50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); } }

/* Narrow screens: the chat list slides over the conversation */
@media (max-width: 900px) {
    .tq-side { position: absolute; z-index: 5; top: 0; bottom: 0; left: 0; box-shadow: 4px 0 18px rgba(0,0,0,.15); }
    .tq-ai:not(.side-open) .tq-side { display: none; }
    .tq-title { max-width: 180px; }
}
@media (max-width: 640px) {
    .tq-ai { height: calc(100vh - 110px); border-radius: 0; }
    .tq-suggest { grid-template-columns: 1fr; }
    .tq-btn span, .tq-select { display: none; }
    .tq-user-wrap, .tq-edit { max-width: 90%; width: auto; }
}
</style>
@endsection

@section('javascript')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);
    const root = $('tq-ai');
    const chatScroll = $('chat-scroll');
    const chatMessages = $('chat-messages');
    const welcome = $('tq-welcome');
    const chatInput = $('chat-input');
    const sendBtn = $('send-btn');
    const micBtn = $('mic-btn');
    const voiceToggle = $('voice-toggle');
    const voiceLangSelect = $('voice-lang');
    const clearBtn = $('clear-chat');
    const terminalBox = $('chat-terminal-box');
    const termInput = $('terminal-input');
    const chatView = $('chat-view');
    const terminalView = $('terminal-view');
    const chatList = $('chat-list');
    const chatTitle = $('tq-chat-title');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const endpoint = '{{ route("chatbot.message") }}';
    const convUrl = '{{ url("chatbot/conversations") }}';
    const TERM_HELLO = 'TeleQuill AI terminal · type a question or command and press Enter';

    // Browser storage only remembers UI preferences; chats live in the database.
    const store = {
        get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
        del(k) { try { localStorage.removeItem(k); } catch (e) {} },
    };

    let mode = store.get('mcp_mode', 'bubble');
    let voiceEnabled = store.get('mcp_voice_enabled', 'false') === 'true';
    voiceLangSelect.value = store.get('mcp_voice_lang', 'en-IN');
    let busy = false;
    let conversations = [];   // [{id, title, updated_at}]
    let currentId = null;     // null = new chat, not saved until the first message
    let messages = [];        // messages of the open chat: [{role, type, content}]
    let pending = false;      // the open chat has a change waiting for Confirm / Cancel

    // ---------- Server calls ----------
    async function api(method, url, body) {
        const res = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = {};
        try { data = await res.json(); } catch (e) {}
        if (!res.ok) throw Object.assign(new Error(data.message || ('HTTP ' + res.status)), { status: res.status, data });
        return data;
    }

    // ---------- Online / offline status (automatic) ----------
    // The server uses the AI whenever it is set up and reachable, and falls back
    // to built-in answers from TeleQuill data when there is no internet.
    function setStatus(isOnline) {
        $('tq-status-dot').classList.toggle('offline', !isOnline);
        $('tq-status-text').textContent = isOnline ? 'Online · AI answers' : 'Offline · answers from TeleQuill data';
        $('tq-status-text').title = isOnline ? 'Connected to the AI' : 'No AI connection: answering from TeleQuill data only';
    }
    setStatus(@json($aiAvailable));

    // ---------- Formatting (escape first, then light markdown) ----------
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function formatInline(s) {
        return s.replace(/`([^`\n]+)`/g, '<code class="tq-inline">$1</code>')
                .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    }
    function formatProse(s) {
        return s.split(/\n{2,}/).filter(p => p.trim()).map(para => {
            let out = '', lines = [], items = [];
            const flushLines = () => { if (lines.length) { out += '<p>' + lines.join('<br>') + '</p>'; lines = []; } };
            const flushList = () => { if (items.length) { out += '<ul>' + items.map(i => '<li>' + i + '</li>').join('') + '</ul>'; items = []; } };
            para.split('\n').forEach(line => {
                const m = line.match(/^\s*[-*•]\s+(.*)$/);
                if (m) { flushLines(); items.push(m[1]); } else { flushList(); lines.push(line); }
            });
            flushLines(); flushList();
            return out;
        }).join('');
    }
    function formatText(text) {
        return escapeHtml(text).split(/```[a-zA-Z0-9_-]*\n?([\s\S]*?)```/).map((part, i) => i % 2
            ? `<div class="tq-pre"><button type="button" class="tq-copy">Copy</button><pre>${part.replace(/\n$/, '')}</pre></div>`
            : formatProse(formatInline(part.replace(/^\n+|\n+$/g, '')))
        ).join('');
    }

    // ---------- Chat list (sidebar) ----------
    function timeAgo(iso) {
        const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + ' min ago';
        if (s < 86400) return Math.floor(s / 3600) + ' h ago';
        if (s < 7 * 86400) return Math.floor(s / 86400) + ' d ago';
        return new Date(iso).toLocaleDateString();
    }
    function renderList() {
        chatList.innerHTML = '';
        if (!conversations.length) {
            chatList.innerHTML = '<div class="tq-list-empty">No saved chats yet. Ask something to start one.</div>';
            return;
        }
        conversations.forEach(c => {
            const item = document.createElement('div');
            item.className = 'tq-item' + (c.id === currentId ? ' active' : '');
            item.innerHTML = `<div class="tq-item-text"><div class="tq-item-title"></div><div class="tq-item-time"></div></div>
                <button type="button" class="tq-item-act" data-act="rename" title="Rename"><i class="fa fa-pen"></i></button>
                <button type="button" class="tq-item-act del" data-act="delete" title="Delete chat"><i class="fa fa-trash-can"></i></button>`;
            item.querySelector('.tq-item-title').textContent = c.title;   // titles are user text: never innerHTML
            item.querySelector('.tq-item-time').textContent = timeAgo(c.updated_at);
            item.addEventListener('click', (e) => {
                const act = e.target.closest('[data-act]')?.dataset.act;
                if (act === 'rename') renameChat(c, item);
                else if (act === 'delete') deleteChat(c);
                else openChat(c.id);
            });
            chatList.appendChild(item);
        });
    }
    async function loadList() {
        try { conversations = await api('GET', convUrl); } catch (e) { conversations = []; }
        renderList();
    }
    function bumpToTop(id, title) {
        const existing = conversations.find(c => c.id === id);
        conversations = conversations.filter(c => c.id !== id);
        conversations.unshift({ id, title: title ?? existing?.title ?? 'New chat', updated_at: new Date().toISOString() });
        renderList();
    }

    // ---------- Open / new / rename / delete ----------
    function setCurrent(id, title) {
        if (id !== currentId) resetRecall();
        currentId = id;
        chatTitle.textContent = title || 'TeleQuill AI';
        if (id) store.set('tq_chat_id', String(id)); else store.del('tq_chat_id');
        renderList();
    }
    function newChat() {
        if (busy) return;
        messages = [];
        pending = false;
        setCurrent(null, null);
        renderAll();
        closeSideOnMobile();
        (mode === 'bubble' ? chatInput : termInput).focus();
    }
    async function openChat(id) {
        if (busy) return;
        busy = true;
        try {
            const c = await api('GET', convUrl + '/' + id);
            messages = c.messages;
            pending = !!c.pending;
            setCurrent(c.id, c.title);
            renderAll();
        } catch (e) {
            if (e.status === 404) { conversations = conversations.filter(c => c.id !== id); newChat(); }
        } finally { busy = false; updateSendState(); closeSideOnMobile(); }
    }
    /** Rename in place: the chat name becomes a text field (Enter saves, Esc cancels). */
    function renameChat(c, item) {
        const titleEl = item.querySelector('.tq-item-title');
        const input = document.createElement('input');
        input.type = 'text'; input.className = 'tq-rename'; input.maxLength = 120; input.value = c.title;
        input.setAttribute('aria-label', 'Chat name');
        item.classList.add('renaming');
        titleEl.replaceWith(input);
        input.focus(); input.select();
        let done = false;
        const finish = async (save) => {
            if (done) return; done = true;
            const title = input.value.trim();
            if (save && title && title !== c.title) {
                try {
                    const r = await api('PATCH', convUrl + '/' + c.id, { title });
                    c.title = r.title;
                    if (c.id === currentId) chatTitle.textContent = r.title;
                } catch (e) { notify('Could not rename the chat: ' + e.message); }
            }
            renderList();
        };
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); finish(true); }
            if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        });
        input.addEventListener('blur', () => finish(true));
        input.addEventListener('click', (e) => e.stopPropagation()); // don't open the chat while typing
    }
    async function deleteChat(c) {
        const ok = await ask({ title: 'Delete chat?', message: `"${c.title}" and all its messages will be deleted.`, ok: 'Delete', danger: true });
        if (!ok) return;
        try {
            await api('DELETE', convUrl + '/' + c.id);
            conversations = conversations.filter(x => x.id !== c.id);
            if (c.id === currentId) newChat(); else renderList();
        } catch (e) { notify('Could not delete the chat: ' + e.message); }
    }

    // ---------- In-page confirm box and notice (instead of browser pop-ups) ----------
    function ask({ title, message, ok = 'OK', cancel = 'Cancel', danger = false }) {
        return new Promise(resolve => {
            const back = document.createElement('div');
            back.className = 'tq-modal-back';
            back.innerHTML = `<div class="tq-modal" role="dialog" aria-modal="true">
                <div class="tq-modal-title"></div><div class="tq-modal-text"></div>
                <div class="tq-modal-btns"><button type="button" class="no"></button><button type="button" class="yes"></button></div></div>`;
            back.querySelector('.tq-modal-title').textContent = title;
            back.querySelector('.tq-modal-text').textContent = message;
            const yes = back.querySelector('.yes'), no = back.querySelector('.no');
            yes.textContent = ok; no.textContent = cancel;
            if (danger) yes.classList.add('danger');
            const close = (v) => { document.removeEventListener('keydown', onKey, true); back.remove(); resolve(v); };
            const onKey = (e) => {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(false); }
                if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); close(true); }
            };
            yes.addEventListener('click', () => close(true));
            no.addEventListener('click', () => close(false));
            back.addEventListener('click', (e) => { if (e.target === back) close(false); });
            document.addEventListener('keydown', onKey, true);
            root.appendChild(back);
            yes.focus();
        });
    }
    function notify(text) {
        const n = document.createElement('div');
        n.className = 'tq-toast'; n.setAttribute('role', 'status'); n.textContent = text;
        root.appendChild(n);
        setTimeout(() => n.remove(), 4000);
    }
    $('new-chat').addEventListener('click', newChat);

    // Sidebar show/hide (slides over the chat on narrow screens)
    $('side-toggle').addEventListener('click', () => {
        if (window.matchMedia('(max-width: 900px)').matches) root.classList.toggle('side-open');
        else { root.classList.toggle('side-hidden'); store.set('tq_side_hidden', root.classList.contains('side-hidden') ? '1' : '0'); }
    });
    function closeSideOnMobile() { root.classList.remove('side-open'); }
    if (store.get('tq_side_hidden', '0') === '1') root.classList.add('side-hidden');

    // ---------- Rendering the open chat ----------
    const BADGES = {
        action: ['', 'fa-circle-check', 'Done', 'The change was made'],
        confirm: ['tq-badge-warn', 'fa-circle-question', 'Needs your confirmation', 'Nothing has changed yet. Confirm to run it, or Cancel.'],
        cancelled: ['tq-badge-muted', 'fa-ban', 'Cancelled', 'Nothing was changed'],
        'notice-expired': ['tq-badge-muted', 'fa-clock', 'Expired', 'The confirmation expired; nothing was changed'],
        builtin: ['tq-badge-builtin', 'fa-database', 'Offline · TeleQuill data', "No AI connection, so this was answered from TeleQuill's own data"],
        llm: ['tq-badge-ai', 'fa-wand-magic-sparkles', 'AI', 'Answered by the AI'],
        'notice-offline': ['tq-badge-builtin', 'fa-database', 'Offline', 'This question was not sent to the AI'],
        'notice-failed': ['tq-badge-warn', 'fa-wifi', 'Offline', 'No connection to the AI, so only built-in questions can be answered'],
        'notice-disabled': ['tq-badge-warn', 'fa-triangle-exclamation', 'AI not set up', 'No AI key is set on this server'],
    };
    // AI replies arrive as safe HTML rendered by the server (headings, lists,
    // tables...). Plain-text fallback for anything without it.
    function chatRow(m) {
        const { role, content: text, type, html } = m;
        const row = document.createElement('div');
        row.className = 'tq-msg ' + role + (type === 'error' ? ' error' : '');
        row.tqMessage = m;
        if (role === 'user') {
            row.innerHTML = `<div class="tq-user-wrap"><div class="tq-body">${formatText(text)}</div>
                <div class="tq-actions">
                    <button type="button" data-act="copy" title="Copy"><i class="fa fa-copy"></i> Copy</button>
                    <button type="button" data-act="edit" title="Edit and send again"><i class="fa fa-pen"></i> Edit</button>
                </div></div>`;
            row.querySelector('[data-act="copy"]').addEventListener('click', (e) => copyText(text, e.currentTarget));
            row.querySelector('[data-act="edit"]').addEventListener('click', () => startEdit(m, row));
        } else {
            // The badge says where the answer came from, not which mode is selected.
            const b = BADGES[type];
            const badge = b ? `<span class="tq-badge ${b[0]}" title="${b[3]}"><i class="fa ${b[1]}"></i> ${b[2]}</span>` : '';
            row.innerHTML = `<div class="tq-avatar"><i class="fa fa-wand-magic-sparkles"></i></div>
                <div class="tq-body">${badge}<div class="tq-md">${html || formatText(text)}</div>
                    <div class="tq-actions">
                        <button type="button" data-act="copy" title="Copy"><i class="fa fa-copy"></i> Copy</button>
                        <button type="button" data-act="speak" title="Read aloud"><i class="fa fa-volume-high"></i></button>
                        <button type="button" data-act="regen" title="Ask again for a new answer"><i class="fa fa-rotate-right"></i> Regenerate</button>
                    </div>
                </div>`;
            row.querySelector('[data-act="regen"]').addEventListener('click', regenerate);
            row.querySelector('[data-act="copy"]').addEventListener('click', (e) => copyText(plain(text), e.currentTarget));
            row.querySelector('[data-act="speak"]').addEventListener('click', () => speak(plain(text), true));
            // Code blocks from the server: add the same Copy button as before.
            row.querySelectorAll('.tq-md pre').forEach(pre => {
                if (pre.parentElement.classList.contains('tq-pre')) return;
                const wrap = document.createElement('div'); wrap.className = 'tq-pre';
                pre.replaceWith(wrap);
                wrap.innerHTML = '<button type="button" class="tq-copy">Copy</button>';
                wrap.appendChild(pre);
            });
        }
        row.querySelectorAll('.tq-copy').forEach(btn =>
            btn.addEventListener('click', () => copyText(btn.nextElementSibling.textContent, btn)));
        return row;
    }
    function termLine(role, text, type) {
        const div = document.createElement('div');
        div.className = 'tq-line ' + (role === 'user' ? 'cmd' : (type === 'error' ? 'err' : 'reply'));
        div.textContent = role === 'user' ? '$ ' + text : plain(text);
        return div;
    }
    /** Markdown symbols removed, for the terminal, copying and reading aloud. */
    function plain(text) {
        return String(text)
            .replace(/^```[\w-]*\s*$/gm, '')
            .replace(/^\s{0,3}#{1,6}\s+/gm, '')
            .replace(/\*\*([^*\n]+)\*\*|__([^_\n]+)__/g, '$1$2')
            .replace(/(^|[\s(])[*_]([^*_\n]+)[*_](?=[\s).,:;!?]|$)/gm, '$1$2')
            .replace(/`([^`\n]+)`/g, '$1')
            .replace(/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/gm, '')
            .replace(/^\s*[-*_]{3,}\s*$/gm, '')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }
    function updateWelcome() { welcome.style.display = chatMessages.children.length ? 'none' : ''; }
    function scrollDown() { chatScroll.scrollTop = chatScroll.scrollHeight; terminalBox.scrollTop = terminalBox.scrollHeight; }

    /** Draw the whole open chat in both views at once (one reflow). */
    function renderAll() {
        const chatFrag = document.createDocumentFragment();
        const termFrag = document.createDocumentFragment();
        const hello = document.createElement('div'); hello.className = 'tq-term-sys'; hello.textContent = TERM_HELLO;
        termFrag.appendChild(hello);
        messages.forEach(m => { chatFrag.appendChild(chatRow(m)); termFrag.appendChild(termLine(m.role, m.content, m.type)); });
        chatMessages.replaceChildren(chatFrag);
        terminalBox.replaceChildren(termFrag);
        drawConfirm();
        updateWelcome();
        scrollDown();
    }

    /** Confirm / Cancel under the last message while a change is waiting. */
    function drawConfirm() {
        document.querySelectorAll('.tq-confirm, .tq-term-confirm').forEach(el => el.remove());
        // Regenerate only on the last answer, when it follows a saved question.
        const rows = chatMessages.querySelectorAll('.tq-msg.assistant');
        rows.forEach(r => r.classList.remove('tq-last'));
        const lastRow = rows[rows.length - 1];
        if (lastRow && lastRow === chatMessages.lastElementChild && lastQuestion()) lastRow.classList.add('tq-last');
        const last = messages[messages.length - 1];
        if (!pending || !last || last.type !== 'confirm') return;
        const bar = document.createElement('div');
        bar.className = 'tq-confirm';
        bar.innerHTML = '<button type="button" class="yes"><i class="fa fa-check"></i> Confirm</button><button type="button" class="no">Cancel</button>';
        chatMessages.lastElementChild?.classList.remove('tq-last'); // no Regenerate while a change waits
        bar.querySelector('.yes').addEventListener('click', () => decide('confirm'));
        bar.querySelector('.no').addEventListener('click', () => decide('cancel'));
        chatMessages.lastElementChild?.querySelector('.tq-body')?.appendChild(bar);
        const hint = document.createElement('div');
        hint.className = 'tq-term-sys tq-term-confirm';
        hint.textContent = 'Type yes to confirm or no to cancel.';
        terminalBox.appendChild(hint);
    }

    /** Confirm or cancel the waiting change. */
    async function decide(what) {
        if (busy || !currentId) return;
        busy = true; updateSendState();
        document.querySelectorAll('.tq-confirm button').forEach(b => b.disabled = true);
        const stopTyping = what === 'confirm' ? showTyping('Running… this can take a minute on a switch') : () => {};
        try {
            const data = await api('POST', convUrl + '/' + currentId + '/' + what);
            stopTyping();
            pending = !!data.pending;
            appendMessage('assistant', data.reply || '', data.type || 'action', data.html, data.message_id);
            bumpToTop(currentId, data.title);
        } catch (e) {
            stopTyping();
            appendMessage('assistant', 'Could not ' + what + ': ' + (e.data?.message || e.message), 'error');
        } finally { busy = false; updateSendState(); }
    }
    /** Add one message to the open chat (both views). */
    function appendMessage(role, content, type, html, id) {
        const m = { id, role, content, type, html };
        messages.push(m);
        chatMessages.appendChild(chatRow(m));
        terminalBox.appendChild(termLine(role, content, type));
        drawConfirm();
        updateWelcome();
        scrollDown();
        return m;
    }

    // ---------- Edit and Regenerate (like ChatGPT) ----------
    /** The question the last answer replied to, if it is saved. */
    function lastQuestion() {
        const last = messages[messages.length - 1], prev = messages[messages.length - 2];
        return last?.role === 'assistant' && prev?.role === 'user' && prev.id ? prev : null;
    }
    function regenerate() {
        const q = lastQuestion();
        if (q && !busy) send(q.content, 'bubble', q);
    }
    function startEdit(m, row) {
        if (busy || !m.id) return;
        const wrap = row.querySelector('.tq-user-wrap');
        const editor = document.createElement('div');
        editor.className = 'tq-edit';
        editor.innerHTML = `<textarea rows="1" aria-label="Edit message"></textarea>
            <div class="tq-edit-btns"><button type="button" class="no">Cancel</button><button type="button" class="yes">Send</button></div>`;
        const ta = editor.querySelector('textarea');
        ta.value = m.content;
        const grow = () => { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 240) + 'px'; };
        const cancel = () => { editor.replaceWith(wrap); };
        const submit = async () => {
            const text = ta.value.trim();
            if (!text) return;
            const later = messages.slice(messages.indexOf(m) + 1);
            if (later.some(x => x.type === 'action') && !(await ask({
                title: 'Replace the later messages?',
                message: 'Sending this edit removes the later messages from this chat. Changes already made on switches are NOT undone.',
                ok: 'Send edit',
            }))) return;
            send(text, 'bubble', m);
        };
        ta.addEventListener('input', grow);
        ta.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); submit(); }
            if (e.key === 'Escape') { e.preventDefault(); cancel(); }
        });
        editor.querySelector('.no').addEventListener('click', cancel);
        editor.querySelector('.yes').addEventListener('click', submit);
        wrap.replaceWith(editor);
        grow(); ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
    }

    function showTyping(label) {
        const row = document.createElement('div');
        row.className = 'tq-msg assistant';
        row.innerHTML = `<div class="tq-avatar"><i class="fa fa-wand-magic-sparkles"></i></div>
            <div class="tq-body"><div class="tq-typing"><span></span><span></span><span></span><em>${escapeHtml(label || 'Thinking…')}</em></div></div>`;
        const wait = document.createElement('div'); wait.className = 'tq-term-sys'; wait.textContent = (label || 'thinking…').toLowerCase();
        chatMessages.appendChild(row); terminalBox.appendChild(wait);
        updateWelcome(); scrollDown();
        return () => { row.remove(); wait.remove(); };
    }

    function copyText(text, btn) {
        const done = () => { const old = btn.innerHTML; btn.innerHTML = '<i class="fa fa-check"></i> Copied'; setTimeout(() => btn.innerHTML = old, 1200); };
        if (navigator.clipboard) navigator.clipboard.writeText(text).then(done).catch(() => {});
    }

    // ---------- Sending (chat and terminal share one path) ----------
    /** Send a message; with `from` (an earlier question) it replaces that question and everything after it. */
    async function send(text, viewMode, from) {
        text = (text || '').trim();
        if (!text || busy) return;
        busy = true; updateSendState();
        pending = false; // a new message answers or replaces any waiting change
        if (from) { messages = messages.slice(0, messages.indexOf(from)); renderAll(); }
        const question = appendMessage('user', text);
        const stopTyping = showTyping(/^(yes|y|ok|okay|confirm|haan|ha|han|ji)\b/i.test(text) ? 'Running… this can take a minute on a switch' : null);
        try {
            const data = from
                ? await api('POST', convUrl + '/' + currentId + '/resend', { message_id: from.id, message: text, mode: viewMode })
                : await api('POST', endpoint, { message: text, mode: viewMode, conversation_id: currentId });
            stopTyping();
            question.id = data.user_message_id;
            // A 'notice' carries why the AI did not answer: offline | failed | disabled.
            const type = data.type === 'notice' ? 'notice-' + (data.reason || 'disabled') : (data.type || 'llm');
            pending = !!data.pending;
            appendMessage('assistant', data.reply || '', type, data.html, data.message_id);
            setStatus(!['builtin', 'notice-failed', 'notice-disabled'].includes(type));
            if (data.conversation_id && data.conversation_id !== currentId) setCurrent(data.conversation_id, data.title);
            bumpToTop(data.conversation_id, data.title);
            speak(plain(data.reply || ''));
        } catch (e) {
            stopTyping();
            const msg = e.status === 404 ? 'This chat no longer exists. Start a new chat.'
                : (e.data?.reply || e.data?.message || 'Unable to reach the TeleQuill server. Check your connection and try again.');
            appendMessage('assistant', msg, 'error');
        } finally {
            busy = false; updateSendState();
            (viewMode === 'bubble' ? chatInput : termInput).focus();
        }
    }

    // ---------- ↑ / ↓ : bring back earlier messages ----------
    // Like a terminal: ↑ shows the messages you sent in this chat (newest first),
    // ↓ goes forward again and finally restores what you were typing.
    const recall = { index: -1, draft: '' };
    function resetRecall() { recall.index = -1; recall.draft = ''; }
    function stepHistory(input, dir) {   // dir -1 = older (↑), +1 = newer (↓)
        const sent = messages.filter(m => m.role === 'user').map(m => m.content);
        if (!sent.length) return false;
        if (recall.index === -1) {
            if (dir > 0) return false;          // not browsing: ↓ does nothing
            recall.draft = input.value;
            recall.index = sent.length;
        }
        const next = recall.index + dir;
        if (next < 0) return true;              // already at the oldest message
        if (next >= sent.length) { input.value = recall.draft; resetRecall(); }
        else { recall.index = next; input.value = sent[next]; }
        input.setSelectionRange(input.value.length, input.value.length);
        if (input === chatInput) { autoGrow(); updateSendState(); }
        return true;
    }
    function historyKeys(e, input, multiline) {
        if (e.isComposing || e.shiftKey || e.altKey || e.ctrlKey || e.metaKey) return;
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
        // In a multi-line message the arrows still move between lines; history only from the first / last line.
        const pos = input.selectionStart, val = input.value;
        if (multiline && e.key === 'ArrowUp' && val.slice(0, pos).includes('\n')) return;
        if (multiline && e.key === 'ArrowDown' && val.slice(input.selectionEnd).includes('\n')) return;
        if (stepHistory(input, e.key === 'ArrowUp' ? -1 : 1)) e.preventDefault();
    }

    // ---------- Composer ----------
    function autoGrow() { chatInput.style.height = 'auto'; chatInput.style.height = Math.min(chatInput.scrollHeight, 180) + 'px'; }
    function updateSendState() { sendBtn.disabled = busy || !chatInput.value.trim(); }
    function sendFromInput() { const t = chatInput.value; chatInput.value = ''; autoGrow(); send(t, 'bubble'); }
    chatInput.addEventListener('input', () => { resetRecall(); autoGrow(); updateSendState(); });
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); if (!busy) { resetRecall(); sendFromInput(); } return; }
        historyKeys(e, chatInput, true);
    });
    sendBtn.addEventListener('click', sendFromInput);
    document.querySelectorAll('.tq-card').forEach(card =>
        card.addEventListener('click', () => send(card.dataset.prompt, 'bubble')));

    // ---------- Terminal ----------
    termInput.addEventListener('input', resetRecall);
    termInput.addEventListener('keydown', (e) => {
        historyKeys(e, termInput, false);
        if (e.key !== 'Enter' || busy) return;
        resetRecall();
        const cmd = termInput.value.trim();
        termInput.value = '';
        if (cmd === 'clear') {   // like a real terminal: clears the screen, not the saved chat
            const hello = document.createElement('div'); hello.className = 'tq-term-sys'; hello.textContent = TERM_HELLO;
            terminalBox.replaceChildren(hello);
            return;
        }
        send(cmd, 'terminal');
    });

    // ---------- Mode ----------
    function applyMode() {
        chatView.style.display = mode === 'bubble' ? '' : 'none';
        terminalView.style.display = mode === 'terminal' ? '' : 'none';
        document.querySelectorAll('.tq-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
        (mode === 'bubble' ? chatInput : termInput).focus();
        scrollDown();
    }
    document.querySelectorAll('.tq-seg-btn').forEach(b => b.addEventListener('click', () => {
        mode = b.dataset.mode; store.set('mcp_mode', mode); applyMode();
    }));

    // ---------- Voice output ----------
    const synth = window.speechSynthesis || null;
    function speak(text, force) {
        if (!text || !synth || (!voiceEnabled && !force)) return;
        const utter = new SpeechSynthesisUtterance(String(text).replace(/```[\s\S]*?```/g, ''));
        utter.lang = voiceLangSelect.value || 'en-IN';
        const match = synth.getVoices().find(v => v.lang.startsWith(utter.lang.split('-')[0]));
        if (match) utter.voice = match;
        synth.cancel(); synth.speak(utter);
    }
    function applyVoiceToggle() {
        voiceToggle.classList.toggle('on', voiceEnabled);
        voiceToggle.querySelector('i').className = voiceEnabled ? 'fa fa-volume-high' : 'fa fa-volume-xmark';
        voiceToggle.title = voiceEnabled ? 'Reading replies aloud (click to turn off)' : 'Read replies aloud';
    }
    voiceToggle.addEventListener('click', () => {
        voiceEnabled = !voiceEnabled; store.set('mcp_voice_enabled', voiceEnabled);
        if (!voiceEnabled && synth) synth.cancel();
        applyVoiceToggle();
    });
    if (!synth) voiceToggle.disabled = true;

    // ---------- Voice input ----------
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
    const recognition = SpeechRecognition ? new SpeechRecognition() : null;
    let listening = false;
    if (recognition) {
        recognition.continuous = false; recognition.interimResults = false; recognition.maxAlternatives = 1;
        recognition.lang = voiceLangSelect.value;
        recognition.onstart = () => { listening = true; micBtn.classList.add('listening'); };
        recognition.onend = () => { listening = false; micBtn.classList.remove('listening'); };
        recognition.onerror = () => { listening = false; micBtn.classList.remove('listening'); };
        recognition.onresult = (e) => {
            const said = e.results[0][0].transcript.trim();
            if (said) send(said, mode);
        };
        micBtn.addEventListener('click', () => {
            recognition.lang = voiceLangSelect.value;
            try { listening ? recognition.stop() : recognition.start(); } catch (e) {}
        });
    } else {
        micBtn.disabled = true; micBtn.title = 'Voice input is not supported in this browser';
    }
    voiceLangSelect.addEventListener('change', () => {
        store.set('mcp_voice_lang', voiceLangSelect.value);
        if (recognition) recognition.lang = voiceLangSelect.value;
    });

    // ---------- Clear chat: delete the messages, keep the chat ----------
    clearBtn.addEventListener('click', async () => {
        if (busy || !messages.length) return;
        if (!(await ask({ title: 'Clear this chat?', message: 'All messages in this chat will be deleted. The chat stays in your list.', ok: 'Clear chat', danger: true }))) return;
        try {
            if (currentId) await api('DELETE', convUrl + '/' + currentId + '/messages');
            messages = [];
            if (synth) synth.cancel();
            renderAll();
        } catch (e) { notify('Could not clear the chat: ' + e.message); }
    });

    // ---------- Start: list chats, reopen the last one ----------
    // Chats now live in the database; any old browser-only history is left untouched and unused.
    applyVoiceToggle();
    applyMode();
    renderAll();
    autoGrow(); updateSendState();
    loadList().then(() => {
        const last = parseInt(store.get('tq_chat_id', ''), 10);
        if (last && conversations.some(c => c.id === last)) openChat(last);
    });
});
</script>
@endsection
