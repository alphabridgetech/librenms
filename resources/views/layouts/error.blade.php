<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>            body { background-color: #F9F9F9; color: #222; font: 14px/1.4 Helvetica, Arial, sans-serif; margin: 0; padding-bottom: 45px; }

        a { cursor: pointer; text-decoration: none; }
        a:hover { text-decoration: underline; }
        abbr[title] { border-bottom: none; cursor: help; text-decoration: none; }

        code, pre { font: 13px/1.5 Consolas, Monaco, Menlo, "Ubuntu Mono", "Liberation Mono", monospace; }

        table, tr, th, td { background: #FFF; border-collapse: collapse; vertical-align: top; }
        table { background: #FFF; border: 1px solid #E0E0E0; box-shadow: 0px 0px 1px rgba(128, 128, 128, .2); margin: 1em 0; width: 100%; }
        table th, table td { border: solid #E0E0E0; border-width: 1px 0; padding: 8px 10px; }
        table th { background-color: #E0E0E0; font-weight: bold; text-align: left; }

        .hidden-xs-down { display: none; }
        .block { display: block; }
        .break-long-words { -ms-word-break: break-all; word-break: break-all; word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; }
        .text-muted { color: #999; }

        .container { max-width: 1024px; margin: 0 auto; padding: 0 15px; }
        .container::after { content: ""; display: table; clear: both; }

        .exception-summary { background: #863836; border-bottom: 2px solid rgba(0, 0, 0, 0.1); border-top: 1px solid rgba(0, 0, 0, .3); flex: 0 0 auto; margin-bottom: 30px; }

        .exception-message-wrapper { display: flex; align-items: center; min-height: 70px; }
        .exception-message { flex-grow: 1; padding: 30px 0; }
        .exception-message, .exception-message a { color: #FFF; font-size: 21px; font-weight: 400; margin: 0; }
        .exception-message.long { font-size: 18px; }
        .exception-message a { border-bottom: 1px solid rgba(255, 255, 255, 0.5); font-size: inherit; text-decoration: none; }
        .exception-message a:hover { border-bottom-color: #ffffff; }

        .exception-illustration { flex-basis: 64px; flex-shrink: 0; height: 66px; margin-left: 15px; opacity: .7; }

        .trace + .trace { margin-top: 30px; }
        .trace-head .trace-class { color: #222; font-size: 18px; font-weight: bold; line-height: 1.3; margin: 0; position: relative; }

        .trace-message { font-size: 14px; font-weight: normal; margin: .5em 0 0; }

        .trace-file-path, .trace-file-path a { color: #222; margin-top: 3px; font-size: 13px; }
        .trace-class { color: #B0413E; }
        .trace-type { padding: 0 2px; }
        .trace-method { color: #B0413E; font-weight: bold; }
        .trace-arguments { color: #777; font-weight: normal; padding-left: 2px; }

        .message-block { margin: 30px 0; }

        hr.separator { border: 0; margin: 1.8em 0; height: 1px; background: #333 linear-gradient(to right, #ccc, #333, #ccc); }

        @media (min-width: 575px) {
            .hidden-xs-down { display: initial; }
        }</style>
</head>
<body>
<div class="exception-summary">
    <div class="container">
        <div class="exception-message-wrapper">
            <h1 class="break-long-words exception-message">@yield('title')</h1>
            <div class="exception-illustration hidden-xs-down">
                <img src="{{ asset('images/telequill_loginpage.svg') }}" alt="" width="200" height="70" />
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="message-block">
        @yield('content')
    </div>

    <hr class="separator"/>
    <p>{{ __("Check your log for more details.") }} ({{ isset($log_file) ? $log_file : 'Telequill.log' }})</p>

    <p>{{ __("If you need additional help, you can find how to get help at") }} <a target="_blank" href="https://support.alphabridge.tech/">https://support.alphabridge.tech/</a>.</p>

    @if(! empty(Flare::sentReports()->all()))
        <p>{{ __("Please include this Error-ID when reporting problems:") }} <b>{{ Flare::sentReports()->latestUuid() }}</b></p>
        <h1>here show licence details </h1>
    @endif
</div>
</body>
</html>
