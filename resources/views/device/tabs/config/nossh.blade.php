<div class="alert alert-danger" style="margin-top:10px;">
    <i class="fa fa-plug-circle-xmark"></i>
    <strong>Can't read or change this switch's configuration.</strong>
    {{ $data['ssh_message'] }}
    <div class="text-muted" style="margin-top:8px; font-size:12px;">
        On the switch, enable the SSH server and allow logins from the TeleQuill server, then click
        <a href="{{ url()->current() }}?recheck=1" class="btn btn-default btn-xs" style="margin-left:4px;">
            <i class="fa fa-rotate-right"></i> Check again
        </a>
    </div>
</div>
