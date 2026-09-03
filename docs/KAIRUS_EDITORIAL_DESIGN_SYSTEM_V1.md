# Kairus Editorial Design System V1

Catalogo delle fondamenta visive condivise introdotte dal cantiere
`feat/kairus-editorial-foundations`: un contratto CSS di token
(`public/css/editorial-system.css`) e dieci componenti Blade riutilizzabili
(`resources/views/components/kairus/`). **Nessuna pagina pubblica esistente
li usa ancora** — questa è deliberatamente un'infrastruttura in attesa di
adozione, non una migrazione.

Kairus è una rivista italiana di divulgazione scientifica: ogni testo,
etichetta ed esempio in questo documento e nei componenti stessi riflette
quell'identità. Nessun componente descrive Kairus come un prodotto
editoriale diverso (una newsletter B2B, un magazine lifestyle, ecc.).

## Principi visivi

- **Un solo accento alla volta.** Teal e ambra sono discreti: accompagnano il
  contenuto, non competono con l'arancio delle call-to-action già in uso sul
  sito (`--accent:#f97316` in `style.css`). Nessun componente introduce un
  terzo accento.
- **Tipografia coerente col brand esistente.** Fraunces per i titoli
  editoriali, Plus Jakarta Sans per corpo e interfaccia — le stesse due
  famiglie già caricate globalmente da `head.blade.php`. Il sistema non
  carica alcun font proprio.
- **Niente inventato.** Ogni componente mostra solo ciò che il chiamante
  passa esplicitamente: nessun dato fittizio, nessuna percentuale calcolata,
  nessuna certificazione o fonte presunta.
- **Un solo link per card.** Le superfici cliccabili (`article-card`,
  `path-card`, `path-step`) sono un unico `<a>`: mai un link annidato dentro
  un altro.
- **Leggibilità prima di tutto.** Titoli e testo non vengono mai troncati
  con `line-clamp`/`text-overflow`; le larghezze di lettura (`--kairus-
  measure-*`) tengono le righe a una lunghezza confortevole senza tagliare
  nulla.

## Token

Definiti in `:root` di `public/css/editorial-system.css`, tutti prefissati
`--kairus-`.

| Categoria | Token | Uso |
|---|---|---|
| Superfici | `--kairus-surface-warm` `--kairus-surface-sage` `--kairus-surface-navy` `--kairus-surface-raised` `--kairus-surface-sunk` | Sfondi di sezione, card, incavi |
| Inchiostro | `--kairus-ink` `--kairus-ink-soft` `--kairus-ink-faint` `--kairus-ink-on-navy` `--kairus-ink-on-navy-soft` | Testo primario/secondario/terziario, varianti per sfondo navy |
| Linee | `--kairus-line` `--kairus-line-strong` `--kairus-line-on-navy` | Separatori, bordi |
| Accenti | `--kairus-teal` `--kairus-teal-deep` `--kairus-teal-soft` `--kairus-amber` `--kairus-amber-soft` | Eyebrow, stati, evidenze discrete |
| Tipografia | `--kairus-font-display` `--kairus-font-body` `--kairus-font-interface` + scala `--kairus-text-xs`…`--kairus-text-3xl` | Ruoli e dimensioni |
| Spaziature | `--kairus-space-3xs`…`--kairus-space-2xl` | Gap e padding, responsive (si riducono sotto 1024/768/480px) |
| Raggi | `--kairus-radius-sm` `--kairus-radius-md` `--kairus-radius-lg` `--kairus-radius-pill` | Angoli di card, badge, pillole |
| Ombre | `--kairus-shadow-sm` `--kairus-shadow-md` `--kairus-shadow-lg` | Elevazione discreta, mai drammatica |
| Larghezze di lettura | `--kairus-measure-lead` `--kairus-measure-body` `--kairus-measure-wide` | Misure massime di paragrafi/lead |
| Focus e stati | `--kairus-focus-ring` `--kairus-focus-ring-on-navy` `--kairus-disabled-opacity` `--kairus-transition-fast` `--kairus-transition-base` | Accessibilità e micro-interazioni |

## Componenti

Ogni componente vive in `resources/views/components/kairus/` ed è
richiamabile con la sintassi `<x-kairus.nome-componente>`.

### `<x-kairus.page-header>`

Intestazione di pagina, un solo `<h1>`.

| Prop | Tipo | Obbligatorio |
|---|---|---|
| `eyebrow` | string | no |
| `title` | string | **sì** |
| `lead` | string | no |
| `tone` | `light` \| `sage` \| `navy` | no (default `light`) |
| `compact` | bool | no (default `false`) |

Slot: `meta` (facoltativo).

```blade
<x-kairus.page-header
    eyebrow="Fisica"
    title="Onde gravitazionali osservate di nuovo"
    lead="Un segnale durato 0,2 secondi, catturato da due rilevatori indipendenti."
    tone="sage"
>
    <x-slot:meta>
        <x-kairus.article-meta author="Redazione" :published-at="$article->published_at" :read-minutes="6" />
    </x-slot:meta>
</x-kairus.page-header>
```

### `<x-kairus.section-heading>`

Titolo di sezione, `h2` di default.

| Prop | Tipo | Obbligatorio |
|---|---|---|
| `eyebrow` | string | no |
| `title` | string | **sì** |
| `description` | string | no |
| `align` | `start` \| `between` | no (default `start`) |
| `headingLevel` | int 1–6 | no (default `2`) |

Slot: `action` (facoltativo — nessuna CTA automatica).

```blade
<x-kairus.section-heading
    eyebrow="Percorsi"
    title="Continua da qui"
    align="between"
>
    <x-slot:action>
        <a href="{{ route('percorsi.index') }}">Tutti i percorsi</a>
    </x-slot:action>
</x-kairus.section-heading>
```

### `<x-kairus.article-meta>`

Metadati articolo, presentation-only.

| Prop | Tipo | Note |
|---|---|---|
| `author` | string | omesso se assente |
| `publishedAt` | `\DateTimeInterface` | atteso già come Carbon (es. `$article->published_at`), non una stringa da riparsare |
| `updatedAt` | `\DateTimeInterface` | idem |
| `readMinutes` | int | omesso se assente |
| `categoryLabel` | string | omesso se assente |
| `density` | `standard` \| `compact` | default `standard` |

Nessuna prop è obbligatoria: senza alcun dato il componente non renderizza
nulla (nessun contenitore vuoto).

```blade
<x-kairus.article-meta
    author="Maria Conti"
    :published-at="$article->published_at"
    :updated-at="$article->updated_at"
    :read-minutes="$article->read_minutes"
    :category-label="$categoryLabel"
/>
```

### `<x-kairus.image-frame>`

Cornice per un'immagine reale fornita dal chiamante (tipicamente
`<x-responsive-image>`), mai duplicata.

| Prop | Tipo | Note |
|---|---|---|
| `ratio` | `hero` \| `landscape` \| `card` \| `square` | default `card` |
| `caption` | string | facoltativo |
| `credit` | string | facoltativo |

Slot di default: l'immagine stessa.

```blade
<x-kairus.image-frame ratio="hero" caption="Il rilevatore LIGO a Hanford." credit="NASA/JPL">
    <x-responsive-image :disk-name="$article->cover_image" :alt="$article->cover_alt" sizes="100vw" fetchpriority="high" loading="eager" />
</x-kairus.image-frame>
```

### `<x-kairus.article-card>`

Card articolo, quattro varianti: `featured`, `standard` (default),
`compact`, `list`.

| Prop | Tipo | Obbligatorio |
|---|---|---|
| `href` | string | **sì** |
| `title` | string | **sì** |
| `excerpt` | string | no (omesso nelle varianti `compact`/`list`) |
| `categoryLabel` | string | no |
| `variant` | vedi sopra | no |

Slot: `image`, `meta` (entrambi facoltativi). Lo slot `meta` non deve
contenere un altro elemento interattivo — l'intera card è già un `<a>`.

```blade
<x-kairus.article-card
    :href="route('articolo', $article->slug)"
    :title="$article->title"
    :excerpt="$article->excerpt"
    :category-label="$categoryLabel"
    variant="featured"
>
    <x-slot:image>
        <x-responsive-image :disk-name="$article->cover_image" :alt="$article->cover_alt" sizes="(min-width: 769px) 55vw, 100vw" />
    </x-slot:image>
    <x-slot:meta>
        <x-kairus.article-meta :published-at="$article->published_at" :read-minutes="$article->read_minutes" density="compact" />
    </x-slot:meta>
</x-kairus.article-card>
```

### `<x-kairus.path-card>`

Card Percorso.

| Prop | Tipo | Note |
|---|---|---|
| `href` | string | **sì** |
| `title` | string | **sì** |
| `description` | string | no (omessa in `compact`) |
| `articleCount` | int | no — mai una stima, il conteggio reale |
| `variant` | `featured` \| `standard` \| `compact` | default `standard` |
| `progress` | string | **puramente descrittivo** (es. "Aggiornato di recente") — mai una percentuale calcolata da questo componente |
| `cta` | string | testo esplicito passato dal chiamante, nessuna CTA generata automaticamente |

Slot: `image` (facoltativo), `fallback` (usato solo se `image` è assente).

```blade
<x-kairus.path-card
    :href="route('percorsi.show', $path->slug)"
    :title="$path->name"
    :description="$path->short_description"
    :article-count="$path->published_articles_count"
    cta="Inizia il percorso"
/>
```

### `<x-kairus.path-step>`

Passo di un Percorso in sequenza.

| Prop | Tipo | Note |
|---|---|---|
| `number` | string\|int | **sì** — puramente leggibile, decorativo per lo screen reader |
| `label` | string | **sì** |
| `categoryLabel` | string | no |
| `title` | string | **sì** |
| `description` | string | no |
| `href` | string | **sì** |
| `state` | `available` \| `current` \| `upcoming` | default `available` — sempre accompagnato da un'etichetta testuale, mai comunicato dal solo colore |

Slot: `image` (facoltativo).

```blade
<x-kairus.path-step
    number="2"
    label="Tappa 2 di 5"
    :category-label="$categoryLabel"
    title="Come si formano i buchi neri"
    description="Dal collasso stellare all'orizzonte degli eventi."
    :href="route('articolo', $step->slug)"
    state="current"
/>
```

### `<x-kairus.trust-panel>`

Pannello di trasparenza editoriale, a slot puri.

Slot: `sources`, `updated`, `corrections`, `author` — tutti facoltativi.
Renderizza solo gli slot passati; senza alcuno slot non produce output.

```blade
<x-kairus.trust-panel>
    <x-slot:sources>{{ $article->primary_sources }}</x-slot:sources>
    <x-slot:updated>Aggiornato il {{ $lastEditorialUpdate?->translatedFormat('d M Y') }}</x-slot:updated>
    <x-slot:author>{{ $article->user->name }}</x-slot:author>
</x-kairus.trust-panel>
```

### `<x-kairus.empty-state>`

Stato vuoto per ricerche senza risultati, conferme, pagine di errore.

| Prop | Tipo | Note |
|---|---|---|
| `title` | string | **sì** |
| `message` | string | no |
| `icon` | `search` \| `path` \| `notice` \| `error` | default `notice` — sempre decorativo, mai l'unico veicolo di significato |

Slot: `action` (facoltativo — nessun bottone senza di esso).

```blade
<x-kairus.empty-state
    title="Nessun articolo trovato"
    message="Prova a modificare i filtri di ricerca."
    icon="search"
>
    <x-slot:action>
        <a href="{{ route('notizie') }}">Torna a tutte le notizie</a>
    </x-slot:action>
</x-kairus.empty-state>
```

### `<x-kairus.form-shell>`

Guscio visivo per un form reale: non renderizza mai un proprio `<form>`.

| Prop | Tipo | Note |
|---|---|---|
| `title` | string | **sì** |
| `lead` | string | no |
| `status` | `default` \| `success` \| `error` | default `default` |

Slot: `form` (il `<form>` reale del chiamante, con la sua action/method/CSRF/
validazione), `aside` (facoltativo).

```blade
<x-kairus.form-shell title="Iscriviti alla newsletter" status="{{ $status }}">
    <x-slot:form>
        <form method="POST" action="{{ route('newsletter.subscribe') }}">
            @csrf
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
            <button type="submit">Iscrivimi</button>
        </form>
    </x-slot:form>
    <x-slot:aside>
        Un'email a settimana, disiscrizione in un clic.
    </x-slot:aside>
</x-kairus.form-shell>
```

## Volutamente fuori scope

- **Nessuna pagina pubblica migrata.** Home, articolo, notizie, categoria,
  ricerca, Percorsi, autore, Turing, header e footer restano invariati byte
  per byte in questo cantiere.
- **Nessun meccanismo di progresso utente.** `path-card`/`path-step`
  accettano solo etichette testuali fornite dal chiamante — costruire un
  vero tracciamento del progresso di lettura è una decisione a parte, non
  presa qui.
- **Nessuna icona reale.** Le icone di `empty-state` sono forme geometriche
  CSS minime, non un set di icone SVG/font — una libreria di icone è una
  scelta successiva, deliberatamente non presa in questo cantiere.
- **Nessun dark mode.** I token sono un solo tema (chiaro, con variante
  `tone="navy"` per singole sezioni) — non un sistema di temi commutabili.
- **Nessuna astrazione lato JavaScript.** Zero JS in questo cantiere, per
  vincolo esplicito: le micro-interazioni (hover, focus) sono CSS puro.

## Piano di adozione raccomandato

In quest'ordine, ciascuno come cantiere separato e reversibile:

1. **`feat/kairus-home-refresh`** — sostituire i blocchi equivalenti in
   `resources/views/home/partials/` con `page-header`/`section-heading`/
   `article-card`, a parità di contenuto e URL.
2. **`feat/kairus-article-refresh`** — `article-meta`, `image-frame` e
   `trust-panel` sulla pagina articolo, senza toccare structured data,
   canonical o il flusso "Continua da qui".
3. **`feat/kairus-paths-refresh`** — `path-card`/`path-step` su
   `percorsi.index`/`percorsi.show`, verificando che l'ordine dei Percorsi
   resti quello editoriale esistente.
4. **Discovery** (ricerca, categoria) — `empty-state` per zero risultati,
   `article-card` variant `list`.
5. **Trust Layer** — `trust-panel` sulle pagine di cui alle PR #516–#519
   (fonti, trasparenza revisione, metodologia), una volta che quelle PR
   sono in `main`.
6. **Pagine di servizio** (form di contatto, iscrizione) — `form-shell`
   attorno ai form reali esistenti, senza toccarne mai action/CSRF/
   validazione.

Ogni adozione è una PR separata con il proprio giro di test end-to-end
sulla pagina toccata — questo cantiere non pre-approva alcuna di esse.

## Invarianti

**SEO.** Zero: nessun canonical, structured data, meta tag o markup
esistente è toccato da questo cantiere — nulla è montato. In adozione,
ogni componente preserva il markup semantico che già esiste (un solo
`<h1>` per pagina, heading level configurabile su `section-heading`).

**Privacy.** Zero raccolta dati: nessun componente contiene un form
proprio, un tracker o un campo nascosto diverso da quelli di stato visivo
(`--kairus-disabled-opacity`, ecc.). `form-shell` non tocca mai consenso o
CSRF del form reale che avvolge.

**Continuità.** I cinque CSS pubblici esistenti (`style.css`,
`frontend-hardening.css`, `public-premium.css`, `public-unified.css`,
`premium-fixes.css`) restano invariati e nello stesso ordine;
`editorial-system.css` è aggiunto per ultimo. Nessuna classe/variabile
`--kairus-`/`.kairus-` esiste già altrove nel sito (verificato
nell'audit, Missione 01) — zero collisioni possibili.

**Performance (Missione 17 — esito).** Verificato per lettura diretta del
markup e del CSS, non con uno strumento automatico:

- Nessuno dei 10 componenti contiene `<script>`: zero JavaScript aggiunto.
- `image-frame` non renderizza un proprio `<img>` (solo lo slot ricevuto):
  nessuna duplicazione di immagine, nessun attributo `loading`/
  `fetchpriority` proprio — quelli restano decisi dal chiamante
  sull'immagine reale (verificato anche da un test dedicato in
  `KairusEditorialFoundationsTest`).
- `editorial-system.css` non contiene `@import`, alcun riferimento a
  `fonts.googleapis.com`/`fonts.gstatic.com` o altro host esterno, alcuna
  `url(http...)`, né `backdrop-filter`. Riusa solo le famiglie già caricate
  globalmente (Fraunces, Plus Jakarta Sans).
- Nessuna `@keyframes` definita: le uniche `transition` sono su
  `box-shadow`/`transform`/`border-color` a 200ms, disattivate del tutto
  sotto `prefers-reduced-motion: reduce`.
- Nessuna dipendenza aggiunta: `composer.json`, `composer.lock`,
  `package.json`, `package-lock.json` invariati.
