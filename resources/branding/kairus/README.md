# Kairus — sorgenti del marchio

Sorgenti vettoriali originali del nuovo simbolo Kairus, conservate qui come
riferimento per rigenerazioni future. **Non sono servite dal sito**: gli
asset effettivamente pubblicati vivono in `public/assets/icons/` e
`public/favicon.ico`/`public/apple-touch-icon.png`, generati da questi file.

## File

- `kairus-symbol.svg` — simbolo trasparente, versione principale. Usato
  direttamente (nessuna conversione) nel markup pubblico/admin come
  `public/assets/icons/symbol.svg`, e come sorgente per tutte le
  rasterizzazioni (favicon, icone PWA, `Organization.logo`).
- `kairus-symbol-light.svg` / `kairus-symbol-dark.svg` — varianti quadrate
  con sfondo (chiaro/scuro), pensate per usi tipo avatar social. Non
  attualmente consumate dal sito: conservate per usi futuri.
- `kairus-favicon.svg` — variante semplificata con sfondo, sorgente di
  `public/assets/icons/favicon.svg`, `favicon-16.png`, `favicon-32.png` e
  dei frame di `public/favicon.ico`.
- `kairus-social-1024.png` / `kairus-social-dark-1024.png` — quadrati pronti
  per profili social (LinkedIn/Facebook), non consumati dal sito oggi.
- `kairus-symbol-master-1024.png` — master raster trasparente ad alta
  risoluzione, rasterizzato da `kairus-symbol.svg` a 1024×1024. Fonte di
  verità per qualunque variante raster futura, senza dover ripartire dal
  vettore ogni volta.

## Colori

- Ciano Kairus: `#20D6DF`
- Ciano profondo: `#00AEBC`
- Blu Kairus: `#0A4771`
- Blu notte: `#062A4B`
- Arancione nucleo: `#FF7024`
- Sfondo scuro: `#071B2F`
- Sfondo chiaro: `#F7FBFC`

## Regole

Non deformare, ruotare, ritagliare il simbolo o cambiare i rapporti fra gli
elementi. Non aggiungere ombre o bagliori. Il nome "Kairus" resta testo
HTML ovunque compaia accanto al simbolo (mai bruciato dentro un'immagine),
per restare nitido, accessibile e in tema con qualunque sfondo.
