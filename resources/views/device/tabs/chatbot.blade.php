@extends('layouts.librenmsv1')

@section('content')
<x-device.page :device="$device">

    @if($data['smokeping']->hasGraphs())
        <x-panel class="with-nav-tabs">
            <x-slot name="heading">
                @if(\App\Facades\LibrenmsConfig::get('smokeping.url'))
                    <a href="{{ \App\Facades\LibrenmsConfig::get('smokeping.url') }}?target={{ $device->type }}.{{ str_replace('.', '_', $device->hostname) }}" target="_blank">
                        <span class="panel-title">{{ __('Smokeping') }} <i class="glyphicon glyphicon-share-alt"></i></span>
                    </a>
                @else
                    <span class="panel-title">{{ __('Smokeping') }}</span>
                @endif

                <ul class="nav nav-tabs" style="display: inline-block">
                    @foreach($data['smokeping_tabs'] as $tab)
                        <li @if($loop->first) class="active" @endif>
                            <a href="#{{ $tab }}" data-toggle="tab">{{ __('smokeping.' . $tab) }}</a>
                        </li>
                    @endforeach
                </ul>
            </x-slot>
        </x-panel>
    @endif

    <x-panel title="{{ __('Chatbot') }}">
        {{-- Modern Chatbot Section --}}
        <div class="chatbot-panel">
            <h4>Device Chatbot</h4>
            <div id="chatBox" class="chat-box">
                {{-- Messages will appear here --}}
            </div>
            <div class="chat-input-area">
                <textarea id="chatQuestion" placeholder="Ask about this device..." rows="2"></textarea>
                <button id="chatAskBtn" class="btn btn-primary">Send</button>
            </div>
        </div>
    </x-panel>

</x-device.page>
@endsection

@push('scripts')
<script type="text/javascript">
    $(function () {
        // Chatbot send button
        $("#chatAskBtn").click(function() {
            let question = $("#chatQuestion").val().trim();
            if (!question) return;

            // Append user message
            $("#chatBox").append(`<div class="message user-message">${question}</div>`);
            $("#chatQuestion").val('');

            // Append placeholder for bot
            let botMessage = $('<div class="message bot-message">Thinking...</div>');
            $("#chatBox").append(botMessage);
            $("#chatBox").scrollTop($("#chatBox")[0].scrollHeight);

            $.ajax({
                url: 'http://172.18.0.5:5000/ask',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ question: question }),
                success: function(res) {
                    let answerText = "";
                    if(res.answer && res.answer.parts && res.answer.parts.length > 0) {
                        answerText = res.answer.parts.map(p => p.text).join("\n");
                    } else {
                        answerText = "No response from chatbot.";
                    }
                    botMessage.text(answerText);
                    $("#chatBox").scrollTop($("#chatBox")[0].scrollHeight);
                },
                error: function(err) {
                    botMessage.text("Error contacting chatbot. Make sure the Flask server is running.");
                    console.error(err);
                }
            });
        });
    });
</script>
@endpush

@push('styles')
<style>
    /* Chatbot Panel */
    .chatbot-panel {
        max-width: 600px;
        margin-top: 20px;
        border: 1px solid #ccc;
        border-radius: 8px;
        padding: 15px;
        background: #f7f7f7;
    }

    .chat-box {
        height: 250px;
        overflow-y: auto;
        padding: 10px;
        border: 1px solid #ddd;
        background: #fff;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    .chat-input-area {
        display: flex;
        gap: 10px;
    }

    .chat-input-area textarea {
        flex: 1;
        resize: none;
        padding: 8px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }

    .chat-input-area button {
        min-width: 80px;
    }

    /* Chat messages */
    .message {
        padding: 8px 12px;
        margin-bottom: 8px;
        border-radius: 12px;
        max-width: 80%;
        line-height: 1.4;
        word-wrap: break-word;
    }

    .user-message {
        background-color: #007bff;
        color: #fff;
        align-self: flex-end;
        margin-left: auto;
        text-align: right;
    }

    .bot-message {
        background-color: #e4e6eb;
        color: #000;
        align-self: flex-start;
        margin-right: auto;
    }

    /* Make chat messages stack nicely */
    .chat-box {
        display: flex;
        flex-direction: column;
    }

    /* Scrollbar styling (optional) */
    .chat-box::-webkit-scrollbar {
        width: 6px;
    }

    .chat-box::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }
</style>
@endpush
