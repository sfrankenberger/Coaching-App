# 01 Entscheidung: Warum Laravel, warum mandantenfähig

Stand 24.09.2026, entschieden von Sebastian.

## Ausgangslage

Auf leawernli.ch ist in sechs Wochen ein vollständiger Mitgliederbereich entstanden: Kursraum mit Wochen, Workbook, Chat mit Sprachnachrichten und Gelesen-Haken, Termine mit Erinnerungen, Aufgaben, Reflexion, Notizen, Merkliste, Themenfinder, Impulse, Podcast, Werkstatt für Lea und Andrea, Push, Telegram, Abendmail, KI-Zusammenfassungen, Passkeys und Google-/Apple-Login.

Technisch ist das keine WordPress-Seite mehr, sondern eine eigene Anwendung, die in der Laufzeit von WordPress wohnt: 203 PHP-Dateien in `wp-content/novamira-sandbox/` (rund 2 MB Eigenbau), eigene Seitenhülle (`lea-seiten.php`), eigene Rewrite-Regeln, Daten in `postmeta` und JetEngine-Relationen.

## Was uns in WordPress Zeit gekostet hat

- Die Sandbox lädt jede PHP-Datei automatisch. Ein Fehler in einer Datei schaltet den gesamten Mitgliederbereich in den Notmodus.
- Vier Cache-Schichten (OPcache, LiteSpeed, QUIC.cloud, Elementor-Element-Cache) mussten für dynamische Seiten einzeln ausgehebelt werden.
- Rewrite-Regeln brauchen getrennte Aufrufe, `?s=` kollidiert mit der WordPress-Suche.
- App-Daten in `postmeta` statt in eigenen Tabellen, keine echten Abfragen, keine Fremdschlüssel.
- Erinnerungen und Mails hängen an WP-Cron, Chat ohne Echtzeit, kaum Tests, kein Git.

## Entscheidung

1. **Die App wird eine eigenständige Laravel-Anwendung** auf `app.leawernli.ch`.
2. **Sie wird von Anfang an mandantenfähig gebaut**, als Produkt für weitere Coaches (und denkbar für Guide-Training). Lea ist Mandantin 1.
3. **leawernli.ch bleibt WordPress** für öffentliche Seiten, Shop (WooCommerce, Rechnung, Steuern, Bexio), Newsletter (Mailster), Podcast-Feed und SEO. Elementor und JetEngine bleiben dort.
4. **Parallelbetrieb statt Big Bang.** Ein Testkurs läuft zuerst in der App, WordPress bleibt so lange führend. Umschalten erst, wenn die App trägt.

## Was wir bewusst nicht tun

- Keinen eigenen Shop in Laravel für Lea. Woo bleibt, die App bekommt Zugänge per Webhook. Für spätere Mandanten ohne WordPress kommt Stripe direkt (Laravel Cashier) als zweite Zugangsquelle.
- Keinen Nachbau der öffentlichen Website in Laravel.
- Keine Datenbank pro Mandant (vorerst). Eine Datenbank, `tenant_id` in jeder Tabelle. Begründung in `02-ARCHITEKTUR.md`.
