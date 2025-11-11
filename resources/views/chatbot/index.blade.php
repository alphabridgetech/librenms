@extends('layouts.librenmsv1')

@section('title', __('TeleQuill Server Chat'))

@section('content')
<div class="container-fluid">
    <x-panel>
        <x-slot name="title">
            <i class="fa fa-server fa-fw fa-lg"></i> {{ __('TeleQuill Server Chat Interface') }}
        </x-slot>

        {{-- Header Controls --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <span class="badge bg-primary" id="chat-mode-label">Chat Bubble Mode</span>
            </h5>
            <div class="d-flex align-items-center gap-2">
                <select id="voice-lang" class="form-select form-select-sm me-2" style="width:auto;">
                    <option value="en-IN">English (India)</option>
                    <option value="en-US">English (US)</option>
                    <option value="en-GB">English (UK)</option>
                    <option value="hi-IN">Hindi (India)</option>
                </select>
                <button id="toggle-mode" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-exchange"></i> Switch Mode
                </button>
                <button id="clear-chat" class="btn btn-outline-danger btn-sm">
                    <i class="fa fa-trash"></i> Clear
                </button>
            </div>
        </div>

        {{-- Chat Bubble Box --}}
        <div id="chat-bubble-box" class="p-3 border rounded bg-light" style="height:400px; overflow-y:auto;">
            <div class="text-center text-muted small">Chat session started...</div>
        </div>

        {{-- Terminal Box --}}
        <div id="chat-terminal-box" class="border rounded bg-black text-success p-3" 
             style="height:400px; overflow-y:auto; font-family:monospace; display:none;">
            <div class="text-secondary small">[System] MCP Terminal session started...</div>
        </div>

        {{-- Input with Voice Controls --}}
        <div id="chat-input-section" class="mt-4 d-flex align-items-center">
            <input type="text" id="chat-input" class="form-control me-2" placeholder="Type message..." style="height: 70px;">
            <div class="btn-group me-2" role="group" aria-label="voice controls">
                <button id="mic-btn" class="btn btn-outline-secondary" title="Start/Stop microphone">
                    <i class="fa fa-microphone"></i>
                </button>
                <button id="voice-toggle" class="btn btn-outline-secondary" title="Enable/disable assistant voice">
                    <i class="fa fa-volume-up"></i>
                </button>
            </div>
            <button id="send-btn" class="btn btn-primary">
                <i class="fa fa-paper-plane"></i> Send
            </button>
        </div>

        {{-- Terminal Input --}}
        <div id="terminal-input-section" class="mt-3 d-flex" style="display:none;">
            <span class="input-group-text bg-dark text-success border-0"></span>
            <input type="text" id="terminal-input" class="form-control bg-dark text-light border-0"
                   placeholder="Type command (help, status, clear)">
        </div>
    </x-panel>
</div>
@endsection

@section('css')
<style>
#chat-bubble-box { background: #f8f9fa; }
#chat-terminal-box { background: #000; border: 1px solid #333; color: #00ff66; }
.chat-msg { padding: 10px 14px; border-radius: 12px; margin-bottom: 8px; max-width: 80%; word-wrap: break-word; }
.chat-user { background-color: #0d6efd; color: #fff; margin-left: auto; }
.chat-mcp { background-color: #6c757d; color: #fff; margin-right: auto; }
.chat-mcp-libre { background-color: #198754; color: #fff; margin-right: auto; }
#mic-btn.active { background-color: #dc3545; color: #fff; }
#voice-toggle.active { background-color: #0d6efd; color: #fff; }
#voice-toggle.btn-warning { background-color: #ffc107; color: #000; }
pre, code { background: #212529; color: #f8f9fa; padding: 4px 6px; border-radius: 6px; display: block; white-space: pre-wrap; }
</style>
@endsection

@section('javascript')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const chatBox = document.getElementById('chat-bubble-box');
    const terminalBox = document.getElementById('chat-terminal-box');
    const chatInput = document.getElementById('chat-input');
    const termInput = document.getElementById('terminal-input');
    const sendBtn = document.getElementById('send-btn');
    const micBtn = document.getElementById('mic-btn');
    const voiceToggle = document.getElementById('voice-toggle');
    const voiceLangSelect = document.getElementById('voice-lang');
    const toggleBtn = document.getElementById('toggle-mode');
    const clearBtn = document.getElementById('clear-chat');
    const modeLabel = document.getElementById('chat-mode-label');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    // State
    let mode = localStorage.getItem('mcp_mode') || 'bubble';
    let chatHistory = JSON.parse(localStorage.getItem('mcp_bubble') || '[]');
    let terminalHistory = JSON.parse(localStorage.getItem('mcp_terminal') || '[]');
    let voiceEnabled = (localStorage.getItem('mcp_voice_enabled') === 'true');
    let recognitionLang = localStorage.getItem('mcp_voice_lang') || 'en-IN';
    voiceLangSelect.value = recognitionLang;

    // Speech Recognition
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
    let recognition = SpeechRecognition ? new SpeechRecognition() : null;
    let isListening = false;

    if (recognition) {
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        recognition.lang = recognitionLang;

        recognition.onstart = () => { isListening = true; micBtn.classList.add('active'); micBtn.innerHTML='<i class="fa fa-microphone-slash"></i>'; };
        recognition.onend = () => { isListening = false; micBtn.classList.remove('active'); micBtn.innerHTML='<i class="fa fa-microphone"></i>'; };
        recognition.onerror = (e) => { console.error('Recognition error:', e); isListening = false; };
        recognition.onresult = (e) => { handleVoiceInput(e.results[0][0].transcript.trim()); };
    } else {
        micBtn.disabled = true; micBtn.title = "Speech recognition not supported.";
    }

    // Speech Synthesis
    const synth = window.speechSynthesis;
    let voicesLoaded = false;
    function speakReply(text) {
        if (!voiceEnabled || !synth) return;
        if (!voicesLoaded && synth.getVoices().length > 0) voicesLoaded = true;
        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = voiceLangSelect.value || 'en-IN';
        const voices = synth.getVoices();
        const match = voices.find(v => v.lang.startsWith(utter.lang.split('-')[0]));
        if (match) utter.voice = match;
        synth.cancel(); synth.speak(utter);
    }
    synth.onvoiceschanged = () => { voicesLoaded = true; };

    // Mode handling
    function updateMode() {
        if(mode==='bubble'){ chatBox.style.display='block'; terminalBox.style.display='none'; document.getElementById('chat-input-section').style.display='flex'; document.getElementById('terminal-input-section').style.display='none'; modeLabel.textContent='Chat Bubble Mode'; }
        else { chatBox.style.display='none'; terminalBox.style.display='block'; document.getElementById('chat-input-section').style.display='none'; document.getElementById('terminal-input-section').style.display='flex'; modeLabel.textContent='Terminal Mode'; }
    }
    toggleBtn.addEventListener('click', ()=>{ mode=(mode==='bubble')?'terminal':'bubble'; localStorage.setItem('mcp_mode',mode); updateMode(); });
    updateMode();

    // Restore history
    chatHistory.forEach(msg=>addChatMessage(msg.sender,msg.text,msg.isLibre));
    terminalHistory.forEach(line=>addTerminalLine(line.text,line.isResponse,line.isLibre));

    // Send Chat
    sendBtn.addEventListener('click', sendChat);
    chatInput.addEventListener('keypress', e=>{ if(e.key==='Enter') sendChat(); });
    async function sendChat(){
        const text=chatInput.value.trim(); if(!text) return;
        addChatMessage('You',text); chatInput.value='';
        const typingId=showTyping('TeleQuill Server');
        try{
            const res=await fetch('{{ route("chatbot.message") }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({message:text,mode:'bubble'})});
            const data=await res.json(); removeTyping(typingId);
            if(res.ok && data.reply){ addChatMessage('TeleQuill Server',data.reply,data.type==='llm'); speakReply(data.reply); } 
            else addChatMessage('TeleQuill Server','[Error] '+(data.error||'Unknown'));
        }catch{ removeTyping(typingId); addChatMessage('TeleQuill Server','[Error] Unable to reach server.'); }
    }

    // Terminal commands
    termInput.addEventListener('keypress', e=>{ if(e.key==='Enter'){ const cmd=termInput.value.trim(); if(!cmd) return; addTerminalLine('$ '+cmd); processCommand(cmd); termInput.value=''; } });
    async function processCommand(cmd){
        const typingId=showTyping('TeleQuill Server',true);
        try{
            const res=await fetch('{{ route("chatbot.message") }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({message:cmd,mode:'terminal'})});
            const data=await res.json(); removeTyping(typingId);
            if(res.ok && data.reply){ addTerminalLine(data.reply,true,data.type==='llm'); speakReply(data.reply); }
            else addTerminalLine('[Error] '+(data.error||'Unknown'),true);
        }catch{ removeTyping(typingId); addTerminalLine('[Error] Unable to reach server.',true); }
    }

    // Helpers
    function addChatMessage(sender,text,isLibre=false){ const div=document.createElement('div'); div.className='chat-msg '+(sender==='You'?'chat-user':isLibre?'chat-mcp-libre':'chat-mcp'); div.innerHTML=`<small><b>${sender}</b></small><br>${formatText(text)}`; chatBox.appendChild(div); chatBox.scrollTop=chatBox.scrollHeight; chatHistory.push({sender,text,isLibre}); localStorage.setItem('mcp_bubble',JSON.stringify(chatHistory)); }
    function addTerminalLine(text,isResponse=false,isLibre=false){ const div=document.createElement('div'); div.className=isResponse?(isLibre?'text-success':'text-warning') : ''; div.innerHTML=formatText(text); terminalBox.appendChild(div); terminalBox.scrollTop=terminalBox.scrollHeight; terminalHistory.push({text,isResponse,isLibre}); localStorage.setItem('mcp_terminal',JSON.stringify(terminalHistory)); }
    function formatText(text){ text=text.replace(/\n{2,}/g,'</p><p>').replace(/\n/g,'<br>'); text=text.replace(/```([\s\S]*?)```/g,'<pre><code>$1</code></pre>'); return `<p>${text}</p>`; }
    function showTyping(sender,term=false){ const id='typing-'+Date.now(); const el=document.createElement('div'); el.id=id; el.className='text-secondary small'; el.innerHTML=`<em>${sender} is typing...</em>`; (term?terminalBox:chatBox).appendChild(el); return id; }
    function removeTyping(id){ const el=document.getElementById(id); if(el) el.remove(); }

    // Voice input
    micBtn.addEventListener('click', ()=>{ if(!recognition) return; recognition.lang=voiceLangSelect.value; try{ isListening?recognition.stop():recognition.start(); }catch{} });
    voiceLangSelect.addEventListener('change',()=>{ recognitionLang=voiceLangSelect.value; localStorage.setItem('mcp_voice_lang',recognitionLang); if(recognition) recognition.lang=recognitionLang; });
    async function handleVoiceInput(transcript){ if(!transcript) return; if(mode==='bubble'){ chatInput.value=transcript; sendChat(); } else{ termInput.value=transcript; processCommand(transcript); } }

    // Voice output toggle
    voiceToggle.addEventListener('click',()=>{ voiceEnabled=!voiceEnabled; localStorage.setItem('mcp_voice_enabled',voiceEnabled); const icon=voiceToggle.querySelector('i'); if(voiceEnabled){ icon.className='fa fa-volume-up'; voiceToggle.classList.add('active'); voiceToggle.classList.remove('btn-warning'); } else { icon.className='fa fa-volume-mute'; voiceToggle.classList.remove('active'); voiceToggle.classList.add('btn-warning'); if(window.speechSynthesis) window.speechSynthesis.cancel(); } });

    // Clear
    clearBtn.addEventListener('click',()=>{ chatBox.innerHTML='<div class="text-center text-muted small">Chat session cleared...</div>'; terminalBox.innerHTML='<div class="text-secondary small">[System] Cleared session...</div>'; localStorage.removeItem('mcp_bubble'); localStorage.removeItem('mcp_terminal'); chatHistory=[]; terminalHistory=[]; });
});
</script>
@endsection
