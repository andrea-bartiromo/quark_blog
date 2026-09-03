# Home + Percorsi Visual Adoption — Matrice viewport

Cantiere D, Prompt 05. Viewport obbligatori: **320, 375, 768, 1024, 1440px**.
Per ciascuno, controlli applicati a ogni superficie toccata (home, indice
Percorsi, dettaglio Percorso):

| Controllo | Cosa verifica | Come |
|---|---|---|
| Overflow orizzontale | `document.documentElement.scrollWidth <= clientWidth` | Lettura del markup/CSS (nessun `width` fisso più largo del viewport nelle nuove regole `.kairus-*`); dove disponibile, verifica browser |
| Focus | Ogni elemento interattivo (link, bottone, campo) resta raggiungibile da tastiera e visibilmente evidenziato (`:focus-visible`, token `--kairus-focus-ring`) | Lettura del markup + test PHP sul rendering, ispezione manuale dove serve interazione reale |
| Leggibilità | Nessun testo troncato con `line-clamp`/`text-overflow`; larghezza di riga entro `--kairus-measure-*` | Lettura del CSS applicato (mai introdotto line-clamp) |
| Card | Nessuna card con altezza fissata che tagli il contenuto; griglia che si adatta (3/2/1 colonne secondo il breakpoint) | Lettura delle regole `grid-template-columns` per viewport |
| Immagini | Nessuna deformazione (`object-fit: cover` sempre dentro un `aspect-ratio` fisso), nessun layout shift | Lettura CSS (`image-frame`/`aspect-ratio`) |
| CTA | Il target touch resta ragionevole (~44px), nessuna CTA che scompare o si sovrappone | Lettura CSS a ciascun breakpoint |
| Ordine DOM | L'ordine dei blocchi nel markup resta quello narrativo approvato — il CSS può cambiare la resa visiva (es. colonna singola) ma non deve riordinare il DOM per ottenerla | Lettura del markup Blade (mai `flex-direction: row-reverse` o `order` usato per invertire testo/immagine in modi che rompano l'ordine di lettura lineare) |

## Breakpoint del sistema

I token di spaziatura (`--kairus-space-*`) già si riducono sotto 1024/768/480px
(Kairus Editorial Foundations V1, Missione 14). Questo cantiere aggiunge, dove
necessario, regole `.kairus-*` proprie ai tre blocchi che richiedono un
comportamento responsive dedicato (hero a due colonne, griglie di card),
usando gli stessi punti di rottura già in uso nel sistema (1024px, 768px,
480px) più le verifiche puntuali a 320/375/1440px richieste da questo
cantiere (nessuna nuova variabile di breakpoint introdotta).

## Copertura per superficie

- **Home**: hero (Prompt 18, 22), blocco evidenza (Prompt 25), griglia
  ultimi articoli (Prompt 30), card Percorsi (Prompt 42), Newsletter
  (Prompt 49), Speciale (Prompt 55), categorie (già gestito dal carosello
  esistente, invariato).
- **Indice Percorsi**: griglia card (Prompt 75).
- **Dettaglio Percorso**: tappe e meta (Prompt 89).

Ogni riga della tabella sopra è il controllo minimo eseguito ad ogni
viewport per ciascuna di queste superfici nei rispettivi prompt di
implementazione.
