<?php
use App\Models\ApiToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;

session_start();

try {
    $apiToken = ApiToken::select('token_hash')
        ->where('user_id', Auth::user()->user_id)
        ->firstOrFail();
    $tokenHash = $apiToken->token_hash;
} catch (ModelNotFoundException $e) {
    $tokenHash = null;
}
?>

<div class="chatbot-wrapper">
    <div class="chatbot-panel">
        <h4 class="text-center">Telequill Chatbot v1</h4>

        <?php if (!$tokenHash): ?>
            <div class="alert alert-warning text-center">
                You don’t have an API token yet.<br>
                <a href="/api-access" class="btn btn-sm btn-primary mt-2">Generate API Token</a>
            </div>
        <?php else: ?>
            <div class="chat-container">
                <div class="prompt-panel">
                    <h5>Quick Prompts</h5>
                    <ul>
                        <li class="prompt-item">Show all devices</li>
                        <li class="prompt-item">Show all ports</li>
                        <li class="prompt-item">Add device 192.168.1.10</li>
                        <li class="prompt-item">Delete device 192.168.1.10</li>
                        <li class="prompt-item">Show alerts</li>
                    </ul>
                </div>

                <div class="chat-main">
                    <div id="chatBox" class="chat-box"></div>
                    <div class="chat-input-area">
                        <textarea id="chatQuestion" placeholder="Ask a question or type 'Add device ...' / 'Delete device ...'" rows="2"></textarea>
                        <button id="chatAskBtn" class="btn btn-primary">Send</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($tokenHash): ?>
<script type="text/javascript">
$(function () {
    const TELEQUILL_TOKEN = <?= json_encode($tokenHash) ?>;
    const API_BASE = "http://127.0.0.1:5000";

    function createChatBubble(text, sender="bot") {
        const bubbleClass = sender === "user" ? "user-bubble" : "bot-bubble";
        return `
            <div class="chat-bubble-wrapper ${sender}">
                <div class="${bubbleClass}">${text}</div>
            </div>
        `;
    }

    function markdownTableToHTML(md) {
        const lines = md.trim().split("\n");
        if(lines.length < 2) return md;
        const headers = lines[0].split("|").map(h=>h.trim()).filter(Boolean);
        const rows = lines.slice(2).map(l=>l.split("|").map(c=>c.trim()).filter(Boolean));
        let html = '<table class="bot-html-table"><thead><tr>';
        headers.forEach(h=>html+=`<th>${h}</th>`);
        html += '</tr></thead><tbody>';
        rows.forEach(r=>{ html+='<tr>'; r.forEach(c=>html+=`<td>${c}</td>`); html+='</tr>'; });
        html += '</tbody></table>';
        return html;
    }

    function sendMessage(questionText = null) {
        const question = questionText || $("#chatQuestion").val().trim();
        if(!question) return;
        $("#chatBox").append(createChatBubble(question, "user"));
        $("#chatQuestion").val('');
        scrollToBottom();

        const thinkingBubble = $('<div class="chat-bubble-wrapper bot"><div class="bot-bubble bubble-text">Thinking...</div></div>');
        $("#chatBox").append(thinkingBubble);
        scrollToBottom();

        $.ajax({
            url: `${API_BASE}/ask`,
            method: 'POST',
            contentType: 'application/json',
            headers: { "Authorization": "Bearer " + TELEQUILL_TOKEN },
            data: JSON.stringify({ question }),
            success: function(res) {
                let answerText = res.answer || "No response from chatbot.";
                if(answerText.includes("|")) answerText = markdownTableToHTML(answerText);
                else answerText = `<pre>${answerText}</pre>`;
                thinkingBubble.replaceWith(createChatBubble(answerText, "bot"));
                scrollToBottom();
            },
            error: function(err) {
                thinkingBubble.replaceWith(createChatBubble("Error contacting chatbot.", "bot"));
                scrollToBottom();
                console.error(err);
            }
        });
    }

    function scrollToBottom() {
        const chatBox = $("#chatBox")[0];
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    $("#chatAskBtn").click(()=>sendMessage());
    $("#chatQuestion").keydown(function(e){
        if(e.key==="Enter" && !e.shiftKey){ e.preventDefault(); sendMessage(); }
    });

    $(".prompt-item").click(function() {
        const text = $(this).text();
        sendMessage(text);
    });
});
</script>
<?php endif; ?>

<style>
.chat-container { display:flex; gap:15px; flex-wrap:wrap; }
.prompt-panel { width:200px; min-width:150px; border:1px solid #ccc; border-radius:8px; padding:10px; background:#f7f7f7; flex-shrink:0; }
.prompt-panel h5{ margin-top:0; }
.prompt-item{ cursor:pointer; padding:6px 8px; border-radius:5px; margin-bottom:6px; background:#e2e6ea; }
.prompt-item:hover{ background:#d0d4d9; }

.chat-main{ flex:1; display:flex; flex-direction:column; min-width:250px; }
.chatbot-panel{ width:100%; max-width:1000px; border:1px solid #ccc; border-radius:8px; padding:15px; background:#f7f7f7; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
.chat-box{ flex:1; min-height:400px; max-height:600px; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:10px; }

.chat-bubble-wrapper{ display:flex; flex-direction:column; }
.chat-bubble-wrapper.user{ align-items:flex-end; }
.chat-bubble-wrapper.bot{ align-items:flex-start; }

.user-bubble, .bot-bubble{ max-width:70%; padding:10px 15px; border-radius:15px; position:relative; word-break:break-word; white-space:pre-wrap; }
.user-bubble{ background:#007bff; color:#fff; border-bottom-right-radius:3px; }
.bot-bubble{ background:#f1f3f6; color:#000; border-bottom-left-radius:3px; }

.bot-html-table{ width:100%; border-collapse:collapse; margin-top:5px; }
.bot-html-table th, .bot-html-table td{ border:1px solid #ccc; padding:6px 10px; text-align:left; }
.bot-html-table th{ background:#e2e6ea; }

.chat-input-area{ display:flex; gap:10px; margin-top:5px; }
.chat-input-area textarea{ flex:1; resize:vertical; padding:8px; border-radius:5px; border:1px solid #ccc; }

@media(max-width:768px){
    .chat-container{ flex-direction:column; }
    .prompt-panel{ width:100%; margin-bottom:10px; }
}
</style>
