<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $campaign->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">

@if($campaign->preheader)
  {{-- Testo di anteprima invisibile nel client di posta, letto nella lista messaggi. --}}
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $campaign->preheader }}</div>
@endif

<div style="max-width:600px;margin:0 auto;padding:24px 16px;">
  <div style="background:#ffffff;border-radius:12px;padding:32px;">
    <h1 style="margin:0 0 8px;font-size:1.5rem;">Kairus</h1>

    <h2 style="margin:0 0 16px;font-size:1.15rem;">{{ $campaign->subject }}</h2>

    <div style="white-space:pre-line;line-height:1.6;font-size:.95rem;">{{ $campaign->content['body'] ?? '' }}</div>
  </div>

  <div style="border-top:1px solid #e5e7eb;margin-top:24px;padding-top:16px;text-align:center;">
    <p style="color:#9ca3af;font-size:.72rem;margin:0 0 8px;line-height:1.6;">
      @if($isPlaceholderRecipient)
        Nessun iscritto confermato disponibile — questa è una anteprima strutturale, non un invio a un destinatario reale.
      @else
        Hai ricevuto questa email perché sei iscritto a Kairus ({{ $subscriber->email }}).
      @endif
    </p>
    <p style="color:#9ca3af;font-size:.72rem;margin:0;line-height:1.6;">
      @if($isPlaceholderRecipient)
        Link di disiscrizione: non disponibile per un destinatario segnaposto.
      @else
        Link di disiscrizione: non ancora disponibile in questo blocco (token iscritto: {{ \Illuminate\Support\Str::limit($subscriber->unsubscribe_token, 12) }}…).
      @endif
    </p>
  </div>
</div>

</body>
</html>
