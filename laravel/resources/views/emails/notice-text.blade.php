{!! $heading !!}
{!! str_repeat('=', min(60, max(8, mb_strlen($heading)))) !!}

@foreach ($intro as $line)
{!! $line !!}

@endforeach
@foreach ($fields as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
@if ($bodyText !== '')

@if ($bodyLabel !== '')
{!! $bodyLabel !!}:
@endif
{!! $bodyText !!}
@endif
@if (is_array($action) && ! empty($action['url']) && ! empty($action['label']))

{!! $action['label'] !!}: {!! $action['url'] !!}
@endif
@foreach ($outro as $line)

{!! $line !!}
@endforeach

--
{!! $siteName !!} · {!! $siteUrl !!}
