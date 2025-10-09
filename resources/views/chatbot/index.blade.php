@extends('layouts.librenmsv1')

@section('title', __('MCP Server Chat'))

@section('content')
<div class="container-fluid">
    <x-panel>
        <x-slot name="title">
            <i class="fa fa-server fa-fw fa-lg"></i> {{ __('MCP Server Chat Interface') }}
        </x-slot>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <span class="badge bg-primary" id="chat-mode-label">Chat Bubble Mode</span>
            </h5>
            <div>
                <button id="toggle-mode" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-exchange"></i> Switch Mode
                </button>
                <button id="clear-chat" class="btn btn-outline-danger btn-sm">
                    <i class="fa fa-trash"></i> Clear
                </button>
            </div>
        </div>

        {{-- Chat Bubble Area --}}
        <div id="chat-bubble-box" class="p-3 border rounded bg-light" style="height:400px; overflow-y:auto;">
            <div class="text-center text-muted small">Chat session started...</div>
        </div>

        {{-- Terminal Area --}}
        <div id="chat-terminal-box" class="border rounded bg-black text-success p-3" 
             style="height:400px; overflow-y:auto; font-family:monospace; display:none;">
            <div class="text-secondary small">[System] MCP Terminal session started...</div>
        </div>

        {{-- Input Section --}}
        <div id="chat-input-section" class="mt-3 d-flex">
            <input type="text" id="chat-input" class="form-control me-2" placeholder="Type message...">
            <button id="send-btn" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Send</button>
        </div>

        <div id="terminal-input-section" class="mt-3 d-flex" style="display:none;margin-top:5px;">
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
#terminal-input::placeholder { color: #777; }
.chat-msg { padding: 8px 12px; border-radius: 12px; margin-bottom: 6px; max-width: 75%; word-wrap: break-word; }
.chat-user { background-color: #0d6efd; color: #fff; margin-left: auto; }
.chat-mcp { background-color: #6c757d; color: #fff; margin-right: auto; }
.chat-mcp-libre { background-color: #198754; color: #fff; margin-right: auto; }
</style>
@endsection

@section('javascript')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let mode = localStorage.getItem('mcp_mode') || 'bubble';
    const toggleBtn = document.getElementById('toggle-mode');
    const clearBtn = document.getElementById('clear-chat');
    const modeLabel = document.getElementById('chat-mode-label');
    const chatBox = document.getElementById('chat-bubble-box');
    const terminalBox = document.getElementById('chat-terminal-box');
    const chatInput = document.getElementById('chat-input');
    const termInput = document.getElementById('terminal-input');
    const sendBtn = document.getElementById('send-btn');
    const chatInputSection = document.getElementById('chat-input-section');
    const terminalInputSection = document.getElementById('terminal-input-section');

    let chatHistory = JSON.parse(localStorage.getItem('mcp_bubble') || '[]');
    let terminalHistory = JSON.parse(localStorage.getItem('mcp_terminal') || '[]');

    // Restore previous messages
    chatHistory.forEach(msg => addChatMessage(msg.sender, msg.text, msg.isLibre));
    terminalHistory.forEach(line => addTerminalLine(line.text, line.isResponse, line.isLibre));

    // Mode Switch
    updateMode();
    toggleBtn.addEventListener('click', () => {
        mode = (mode === 'bubble') ? 'terminal' : 'bubble';
        localStorage.setItem('mcp_mode', mode);
        updateMode();
    });

    function updateMode() {
        if (mode === 'bubble') {
            chatBox.style.display = 'block';
            terminalBox.style.display = 'none';
            chatInputSection.style.display = 'flex';
            terminalInputSection.style.display = 'none';
            toggleBtn.innerHTML = '<i class="fa fa-terminal"></i> Switch to Terminal Mode';
            modeLabel.textContent = 'Chat Bubble Mode';
            chatInput.focus();
        } else {
            chatBox.style.display = 'none';
            terminalBox.style.display = 'block';
            chatInputSection.style.display = 'none';
            terminalInputSection.style.display = 'flex';
            toggleBtn.innerHTML = '<i class="fa fa-comments"></i> Switch to Chat Bubble Mode';
            modeLabel.textContent = 'Terminal Mode';
            termInput.focus();
        }
    }

    // CSRF token for AJAX
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    // Clear chat
    clearBtn.addEventListener('click', () => {
        chatBox.innerHTML = '';
        terminalBox.innerHTML = '';
        chatHistory = [];
        terminalHistory = [];
        localStorage.removeItem('mcp_bubble');
        localStorage.removeItem('mcp_terminal');
    });

    // Chat bubble send
    sendBtn.addEventListener('click', sendChatMessage);
    chatInput.addEventListener('keypress', e => { if (e.key === 'Enter') sendChatMessage(); });

    async function sendChatMessage() {
        const text = chatInput.value.trim();
        if (!text) return;
        addChatMessage('You', text);
        saveChat('You', text, false);
        chatInput.value = '';

        const typingId = showTypingIndicator('MCP Server');

        try {
            const resp = await fetch('{{ route("chatbot.message") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: text, mode: 'bubble' })
            });
            const data = await resp.json();
            removeTypingIndicator(typingId);

            if (resp.ok && data.reply) {
                const isLibre = data.type === 'llm';
                addChatMessage('MCP Server', data.reply, isLibre);
                saveChat('MCP Server', data.reply, isLibre);
            } else {
                addChatMessage('MCP Server', '[Error] ' + (data.error || 'Unknown'));
            }
        } catch (err) {
            removeTypingIndicator(typingId);
            addChatMessage('MCP Server', '[Error] Unable to reach server.');
        }
    }

    function addChatMessage(sender, text, isLibre=false) {
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (sender === 'You' ? 'chat-user' : isLibre ? 'chat-mcp-libre' : 'chat-mcp');
        div.innerHTML = `<small><b>${sender}</b></small><br>${text}`;
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function saveChat(sender, text, isLibre=false) {
        chatHistory.push({ sender, text, isLibre });
        localStorage.setItem('mcp_bubble', JSON.stringify(chatHistory));
    }

    // Terminal send
    termInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
            const cmd = termInput.value.trim();
            if (!cmd) return;
            addTerminalLine(`$ ${cmd}`, false, false);
            saveTerminal(`$ ${cmd}`, false, false);
            processCommand(cmd);
            termInput.value = '';
        }
    });

    async function processCommand(cmd) {
        if (cmd === 'clear') {
            chatBox.innerHTML = '';
            terminalBox.innerHTML = '[System] Cleared session...';
            localStorage.removeItem('mcp_terminal');
            localStorage.removeItem('mcp_bubble');
            chatHistory = [];
            terminalHistory = [];
            return;
        }

        const typingId = showTypingIndicator('MCP Server', true);

        try {
            const resp = await fetch('{{ route("chatbot.message") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: cmd, mode: 'terminal' })
            });
            const data = await resp.json();
            removeTypingIndicator(typingId);

            if (resp.ok && data.reply) {
                const isLibre = data.type === 'llm';
                addTerminalLine(data.reply, true, isLibre);
                saveTerminal(data.reply, true, isLibre);
            } else {
                addTerminalLine('[Error] ' + (data.error || 'Unknown'), true, false);
            }
        } catch (err) {
            removeTypingIndicator(typingId);
            addTerminalLine('[Error] Unable to reach server.', true, false);
        }
    }

    function addTerminalLine(text, isResponse=false, isLibre=false) {
        const div = document.createElement('div');
        div.className = isResponse ? (isLibre ? 'text-success' : 'text-warning') : '';
        div.textContent = text;
        terminalBox.appendChild(div);
        terminalBox.scrollTop = terminalBox.scrollHeight;
    }

    function saveTerminal(text, isResponse=false, isLibre=false) {
        terminalHistory.push({ text, isResponse, isLibre });
        localStorage.setItem('mcp_terminal', JSON.stringify(terminalHistory));
    }

    function showTypingIndicator(sender, isTerminal=false) {
        const id = 'typing-' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.className = isTerminal ? 'text-secondary small' : 'text-start mb-2 text-muted small';
        div.innerHTML = isTerminal ? `${sender} is thinking...` : `<em>${sender} is typing...</em>`;
        if (isTerminal) terminalBox.appendChild(div);
        else chatBox.appendChild(div);
        if (isTerminal) terminalBox.scrollTop = terminalBox.scrollHeight;
        else chatBox.scrollTop = chatBox.scrollHeight;
        return id;
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }
});
</script>
@endsection
