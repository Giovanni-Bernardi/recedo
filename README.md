# Recedo

> Pulsante di recesso 54-bis per WooCommerce — *recedo*, dal latino "io recedo".

**Versione:** 1.0.0  
**Autore:** [Giovanni Bernardi](https://github.com/Giovanni-Bernardi)  
**Licenza:** GPL-2.0-or-later  
**WooCommerce:** 7.0+  
**PHP:** 8.0+

---

## Cosa fa

Aggiunge il pulsante di recesso obbligatorio ex **art. 54-bis Codice del Consumo** (D.Lgs. 209/2025) agli ordini WooCommerce.

**Plug & play — zero configurazione richiesta.**

### Funzionalità incluse

- ✅ **Pulsante "Recedere"** nella tabella ordini del "Mio Account" e nella singola pagina ordine
- ✅ **Flusso a doppia conferma** (art. 54-bis c. 3): step 1 raccolta dati → step 2 conferma definitiva
- ✅ **Ricevuta legale automatica via email** al cliente con data/ora esatte (supporto durevole)
- ✅ **Notifica email al merchant**
- ✅ **Log probatorio** in tabella DB dedicata (`wp_wcr54_log`) con timestamp, IP, user agent
- ✅ **Stato ordine custom** `wc-recesso` ("Recesso richiesto"), compatibile HPOS
- ✅ **Finestra 14 giorni** calcolata sulla data completamento/creazione ordine
- ✅ **Guest checkout** supportato tramite order key
- ✅ **Accessibilità WCAG 2.1 AA**: contrasto, focus visibile, aria-label, navigazione tastiera
- ✅ Compatibile con qualsiasi tema WooCommerce

---

## Installazione

1. Carica la cartella `recedo/` in `/wp-content/plugins/`
2. Attiva il plugin da **Plugin → Plugin installati**
3. Fine. Il pulsante appare automaticamente.

---

## Struttura

```
recedo/
├── recedo.php          # Entry point, installa DB
├── includes/
│   ├── class-wcr54-order-status.php  # Stato custom "Recesso richiesto"
│   ├── class-wcr54-frontend.php      # Pulsante + modale doppia conferma
│   ├── class-wcr54-handler.php       # AJAX: validazione, log, cambio stato
│   └── class-wcr54-email.php         # Email ricevuta cliente + notifica merchant
└── assets/
    ├── recesso.css               # Stile pulsante e modale
    └── recesso.js                # Logica modale e invio AJAX
```

---

## Cosa NON fa (da completare per produzione)

- Non gestisce i **prodotti esclusi dal recesso** (art. 59 Cod. Consumo): beni personalizzati, contenuti digitali senza consenso esplicito, ecc. — aggiungere filtro per categoria/tag prodotto
- Non integra il **flusso di rimborso** (da gestire manualmente dall'admin o con WC Refunds)
- Non ha una **pagina di admin** per visualizzare il log (la tabella `wp_wcr54_log` è interrogabile da phpMyAdmin o con query custom)
- Non gestisce la **traduzione WPML/Polylang** delle email (il text domain è caricato, ma le email sono in italiano hardcoded)

## Note legali importanti

- **Decorrenza dei 14 giorni**: legalmente il termine parte dalla *ricezione della merce*, non dalla data dell'ordine. Il plugin usa la data di completamento/creazione come approssimazione. Per la data di consegna reale, aggancia il filtro `wcr54_reference_date`:
  ```php
  add_filter( 'wcr54_reference_date', function ( $date, $order ) {
      // restituisci un WC_DateTime con la data di consegna effettiva
      return $date;
  }, 10, 2 );
  ```

---

## Avvertenza legale

Questo plugin è uno strumento tecnico che implementa i requisiti procedurali dell'art. 54-bis.  
**Non sostituisce una consulenza legale.** Verifica con un avvocato esperto di diritto del consumo che l'implementazione sia conforme alla tua specifica situazione.
