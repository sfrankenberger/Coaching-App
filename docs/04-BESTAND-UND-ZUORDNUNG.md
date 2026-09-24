# 04 Bestand in WordPress und wohin es geht

Stand 24.09.2026: 203 Dateien in `wp-content/novamira-sandbox/`, Personen: 320 Konten (281 subscriber, 36 customer, 2 lea_redaktion, 1 admin). Aktiv im Hybrid-Coaching: 6, im 1:1: 1.

Die Dateien lesen sich über `novamira/read-file` bzw. auf dem Server unter `/var/www/vhosts/leawernli.ch/httpdocs/wp-content/novamira-sandbox/`. **Beim Portieren die Logik lesen, nicht den Code kopieren.** Vieles ist Workaround für WordPress und fällt weg.

| Bereich in der App | WordPress-Dateien (Auswahl) | Hinweis |
|---|---|---|
| Seitenhülle, Navigation, Mobilmenü, Design | lea-seiten, lea-app, lea-app-modus, lea-ui, lea-karten, lea-elemente, lea-mobilmenue, lea-menue-ordnung, lea-design, lea-brand-override, lea-css-buendel, lea-eingabe-zweizeilig | Kartenmass: #FFFDF8, Rahmen #E4DFD2, Radius 16, Polster 14/16, Abstand 8, Seitenbreite 720, Schriftleiter 11/12.5/13.5/15.5/17/20/26/32. Eingaben zweizeilig (Feld oben, Knöpfe unten) |
| Start / Dashboard | lea-start2, lea-start-lea, lea-meine-woche, lea-woche, lea-neu, lea-neuigkeiten, lea-stand, lea-timeline, lea-dabei | getrennte Starts für Teilnehmerin und Lea |
| Anmeldung | lea-login-optionen, lea-anmeldedienste, lea-apple-login, lea-login-name, lea-zugang-direkt, lea-zugang-sichern, lea-doppelschutz, lea-demo-login | Mailadresse als Anmeldename. Secure-Passkeys-Tabellen für Übernahme prüfen |
| Einführung / Willkommen | lea-willkommen, lea-infofenster, lea-hilfe | 8 Schritte, unterscheidet Kurs und 1:1, fragt Telefonnummer ab |
| Gratis-Einstieg | lea-anfang-anmeldung, lea-anfang-strecke, lea-anfang-abschluss, lea-freebie-redirects, lea-gast | ohne Passwort, Rolle guest |
| Programme / Kursraum | lea-kursraum, lea-kurs-zeitstrahl, lea-kurs-kopf, lea-kurs-module, lea-kurs-infos, lea-kurs-edit, lea-kurs-automatik, lea-coach-kurs, lea-meine-kurse, lea-lektion-*, lea-club-lektion, lea-module, lea-wochenaufgaben, lea-wochencheck, lea-prozessschritte | Ziel: ein Kursmodell mit Taktung (siehe 03) |
| Workbook | lea-workbook, lea-workbook-bausteine, -aufgaben, -erledigt, -freigabe, -notizen, -prompts, lea-arbeitsbuecher | Vorbild für Übungen, Freigabe einmal am Anfang, Übung 21 schlägt frühere Antworten vor |
| Club | lea-club, lea-club-dashboard, lea-club-callbacks, lea-club-termin, lea-club-ressourcen*, lea-club-macros, lea-club-mailster, lea-club-visibility, lea-mitgliedschaft-neu, lea-mitgliedschaft-kuendigung | Club = Ort: alle Selbstlernkurse + Community + Aufzeichnungen + monatliches Q&A |
| Termine / Kalender | lea-termine, lea-termin-edit, lea-termin-anhaenge, lea-terminvorschlag, lea-kalender, lea-zoom, lea-aufzeichnungen, lea-player | Vimeo |
| Ressourcen | lea-ressourcen-v2, lea-ressourcen-v3, lea-ressourcen-teilen | v3 ist aktuell |
| Aufgaben | lea-aufgaben, lea-aufgaben-plus, lea-aufgaben-aus-zusammenfassung | inkl. Erinnerungen |
| Notizen, Reflexion, Journal | lea-notizen, lea-reflexion, lea-reflexion-rueckblick, lea-journal-start, lea-brief-mitnehmen, lea-liebesbrief-praxis, lea-selbsttest | |
| Chat | lea-chat, lea-chatknopf, lea-nachrichten, lea-diktieren, lea-kursraum-reaktionen | Gelesen-Haken, Reaktionen nur an fremden Nachrichten, Sprachnachrichten, schwebender Knopf |
| Coachees / Begleitung (Sicht Lea) | lea-coachees, lea-coachee-suche, lea-begleitung, lea-teilnehmerprofil, lea-kunden-liste, lea-coaching, lea-projekte | Dossier mit Kontaktknöpfen (Mail, WhatsApp, Anruf) |
| Werkstatt (Pflege für Lea/Andrea) | lea-werkstatt, lea-werkstatt-aufgaben, lea-steuerpult, lea-assistent | wird durch Filament-Panel `coach` ersetzt |
| Impulse / Blog / Podcast | lea-impulse, lea-impulsbild, lea-impuls-instagram-ki, lea-blog-*, lea-podcast-*, lea-coach-podcast | Podcast-Feed bleibt in WordPress, App liest Episoden per RSS |
| Themenfinder / Merkliste | lea-themen, lea-fundus, lea-gemerkt, lea-community-geteilt | Teilen an Coachees |
| Benachrichtigungen | lea-push, lea-pull, lea-termin-erinnerung, lea-abendmail, lea-was-kommt-an, lea-telegram-anschluss, lea-mails, lea-mail-template | siehe Architektur, Abschnitt Benachrichtigungen |
| Auswertung | lea-auswertung, lea-zahlen, lea-messung | später |
| Bleibt in WordPress | lea-bexio*, lea-kasse, lea-woo-*, lea-rechnung, lea-preis*, lea-waehrung, lea-verkauf, lea-shop-zugang, lea-kurs-shop, lea-produkt-verkauf, lea-mailster-*, lea-newsletter-doi, newsletter-testversand, lea-webinar-danke, lea-seite-angebot, lea-adhs-muetter | Shop, Rechnungen, Newsletter, öffentliche Seiten |
| Entfällt | lea-altlinks-301, lea-adminleiste-aufraeumen, lea-admin-farbschema, lea-elementor-icons, lea-fontawesome, lea-svg-uploads, lea-externe-bilder, lea-ballast, lea-umzug, lea-mail-debug-log, lea-testversand-bruecke, lea-vorschau, lea-app-vorschau | WordPress-spezifisch |

## Daten, die importiert werden

| Quelle (WordPress) | Menge | Ziel |
|---|---|---|
| wp_users + usermeta (Telefon, Schalter, Einführung) | 320 | users + memberships (nur relevante Rollen, Gäste optional) |
| CPT kurs / modul / lektion + Relationen 9, 10 | 13 / 63 / 136 | programs / program_steps / units |
| Workbook (lea-workbook, Optionen) | 1 | program type workbook + exercises |
| CPT termin + Relationen 19 bis 22 | 69 | events + event_attendees |
| CPT ressource + Relationen 12, 16 bis 18 | 23 | resources + resourceables |
| CPT aufgabe + Relation 38 | 18 | tasks |
| CPT notiz, reflexion, journal | 15 / 9 / 6 | notes / reflections / journal_entries |
| CPT chat + jet_messenger_* | 17 | conversations / messages |
| WP-Posts in Impuls-Kategorien | Teil von 143 | posts |
| CPT podcast | 100 | podcast_episodes (oder per RSS) |
| Relation 13 (Teilnehmer zu Kurse), wc_user_membership | | entitlements |
| Merkliste, Workbook-Antworten, Fortschritt (usermeta) | | bookmarks / answers / progress |
