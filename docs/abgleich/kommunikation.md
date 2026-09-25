# Abgleich Kommunikation und Integrationen (alt WordPress, neu Laravel)

Stand: 25.09.2026. Gelesen: die vier Plugins `novamira-coaching`, `novamira-aufzeichnungen`, `novamira-zoom-anwesenheit`, `novamira-telegram` sowie die Sandbox-Dateien lea-chat, lea-chatknopf, lea-nachrichten, lea-push, lea-abendmail, lea-telegram-anschluss, lea-sprachnachricht-format, lea-diktieren, lea-doppelschutz, lea-eingabe-zweizeilig, lea-neuigkeiten, lea-neu, lea-mails, lea-club-mailster, lea-assistent, lea-aufgaben-aus-zusammenfassung, lea-aufzeichnungen, lea-zoom, lea-was-kommt-an. Zum Verständnis kurz dazu: `lea_kr_benachrichtigen` (lea-kursraum), `lea_dabei_*` (lea-dabei).

Geheimnisse: In keiner der gelesenen Dateien steht ein Schlüssel, Token oder Passwort hart im Code (**hart im Code: nein**). Alles liegt in WordPress-Optionen oder als Konstante in der `wp-config.php`. Hart im Code stehen dagegen Mandantendaten wie Leas Name in Texten und Terminen, die Farbe #B4795F, die Seiten-ID 765, die Logo-ID 1756, `mail@leawernli.ch` und Leas Benutzer-ID (`LEA_KR_LEA`, sonst 2).

Hinweis zu `lea-nachrichten.php`: Die Datei enthält nicht den Chat, sondern den Strom **"Was ist neu"** (`lea_ns_strom`), also die Sammlung auf der Startseite.

---

## 1. Funktionen im Vergleich

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| **Chat 1:1** zwischen Person und Coachin, als Verlauf mit Tagestrennern | lea-chat | gleich | `app/Chat/Chat.php` (`directFor`), `GespraechController`, `resources/views/gespraech/show.blade.php` |
| Gruppengespräch je Programm | (alt nur Community) | besser | `Chat::groupFor`, Route `kurse.austausch` |
| Nachrichten ohne Neuladen, Nachladen alle 5 s | lea-chat (JS in Option `lea_ch_js`) | gleich | Polling `gespraech.neu` in `public/js/app.js` |
| Nur die letzten 20 Nachrichten, "ältere zeigen" | lea-chat | gleich | 30 Nachrichten, `?alle=1` |
| Gelesen-Haken (1 Haken gesendet, 2 gelesen) | lea-chat | gleich | `Chat::readUntilByOthers`, `_nachricht.blade.php` |
| Reaktionen (Emoji) nur an fremden Nachrichten | lea-chat | gleich | `GespraechController::reaktion`, `Reaction::EMOJIS` |
| Trenner "Neu" vor der ersten ungelesenen Nachricht | lea-chat | fehlt | Klein, aber hilft beim Wiedereinstieg |
| Hinweis "schreibt für Lea" (Team-Name an Nachrichten von Andrea) | lea-chat (`lea_von`, `lea_team_name_text`) | fehlt | Im 1:1 steht bei fremden Nachrichten kein Name, die Person sieht nicht, dass Andrea schreibt |
| Datei oder Foto anhängen | lea-chat | gleich | `Chat::send` (Speicher `tenants/{id}/chat/{conv}`), Auslieferung nur für Beteiligte (`nachricht.datei`) |
| **Element anhängen** (Aufgabe, Notiz, Reflexion, Termin, Aufzeichnung, Material, mit Suche) | lea-chat (`lea_ch_anhaenge`, `lea_ch_elementkarte`) | vereinfacht | Datenmodell und Anzeige da (`ref_type`, `ref_id`, Karte in `_nachricht`), aber **keine Auswahl im Eingabefeld**. Aufzeichnung in der Karte direkt abspielen fehlt ebenfalls |
| **Sprachnachricht** aufnehmen, mit Dauer | lea-chat | besser | `app.js` (MediaRecorder), `Chat::send`. Neu für alle, alt nur für Lea und Team. Probehören vor dem Senden fehlt |
| Transkript der Sprachnachricht (Browser, sonst Whisper), Knopf "Transkript erstellen" | lea-chat (`lea_ch_transkribieren`, OpenAI `whisper-1`) | fehlt | Feld `messages.transcript` und Anzeige da, aber `app.js` schickt kein Transkript mit, es gibt keine Nachverarbeitung. Nur importierte Transkripte erscheinen |
| **Sprachnachrichten für Safari/iPhone umwandeln** (webm nach m4a mit ffmpeg), Bestand nachziehen | lea-sprachnachricht-format | fehlt | Chrome nimmt webm/Opus auf, Safari spielt das nicht ab. In der App wird webm unverändert gespeichert. **Auf iPhones bleiben Sprachnachrichten aus Chrome stumm** |
| Nachfassen: 1:1-Nachricht nach 20 Minuten ungelesen, dann Mail | lea-chat (`lea_ch_nachfassen`) | gleich | `Runden::nachfassen`, alle 10 Minuten. Unterschied: geht an alle Beteiligten im 1:1, also an Coachin **und** Team, nicht nur an Lea |
| Benachrichtigung bei neuer Nachricht (Push, sonst Mail) | lea-chat, lea-kursraum (`lea_kr_benachrichtigen`) | besser | `Listeners/BenachrichtigeBeiNachricht` über `Notifier` (Push, Telegram, Mail je nach Person) |
| **Schwebender Chatknopf** mit Zahl für Ungelesenes | lea-chatknopf | vereinfacht | `components/layouts/app.blade.php` (`.chat-knopf`). Öffnet eine Seite statt einer Schublade. Einblenden beim Scrollen fehlt, spielt kaum eine Rolle |
| Liste aller Gespräche für Coachin und Team, "wartet" zuerst | lea-chatknopf (`lea_cb_liste_html`) | vereinfacht | `gespraech/index.blade.php`. Sortiert nach letzter Nachricht, mit Zahl ungelesen. Die Markierung "wartet auf Antwort" (letzte Nachricht von der Person) fehlt. Testkonten werden nicht ausgeblendet |
| Gesehen erst beim Öffnen der Schublade | lea-chatknopf | bewusst weg | Keine Schublade mehr, gesehen heisst: Gespräch geöffnet |
| **"Was ist neu"**-Strom auf der Startseite (Nachrichten, Beiträge, Antworten auf eigene Einträge, Community, Aufzeichnungen, Instagram, Podcast), "Alles gesehen" | lea-nachrichten | vereinfacht | `HomeController` mit `Runden::neuesFuer`: nur Nachrichten (als Zahl), Aufzeichnungen, neue Termine, Material, Aufgaben. Es fehlen Beiträge und Impulse, Podcast, Antworten auf eigene Einträge, Absender und Textanriss, Zusammenfassung mehrerer Antworten, "Alles gesehen" |
| **Web Push**: Abo je Gerät, abgelaufene Abos entfernen | lea-push | gleich | `PushController`, `WebPushChannel`, Tabelle `push_subscriptions`, VAPID je Mandant (`settings.push.vapid`, `push:keys`) |
| Push für jeden neuen Beitrag nach Sichtbarkeit (Free an alle mit Push, Club-intern an alle mit Kurs, Kurs an Kursteilnehmerinnen) | lea-push | vereinfacht | `PostObserver`: nur Beiträge, die in der App angelegt sind (`source = app`) und Kanäle gewählt haben. Beiträge aus WordPress oder Feed lösen nichts aus. Sichtbarkeit "alle mit Push" (auch Gäste) gibt es nicht |
| Test-Push an sich selbst, Spalte "Push" in der Benutzerliste | lea-push | vereinfacht | Kein Test-Push. Anzahl Geräte und Telegram stehen im Dossier (`Memberships/Pages/Dossier.php`) |
| **Abendmail** 19:30, nur ohne Push, nur bei Neuem, höchstens einmal am Tag | lea-abendmail | vereinfacht | `Runden::abendmail`, Scheduler 19:30. Inhalt aus `neuesFuer` (siehe oben, ohne Beiträge, Podcast, Antworten), als Textliste mit einem Knopf statt Karten mit je einem Link |
| Hinweis "Willst du es merken, wenn Lea schreibt?" mit "Zeig mir wie" (App installieren, Push) | lea-abendmail (`lea_am_hinweis`) | vereinfacht | Nur Text im Profil und in der Einführung (`/willkommen`). Kein Hinweis im Alltag für Personen ohne Push |
| **Drei Schalter "Was dich erreicht"** (Termin-Erinnerungen, Abendmail, Aufgaben-Erinnerungen) | lea-was-kommt-an | gleich | `profil.blade.php`, `ProfilController::notifications`, `Notifier::wants`, Import der alten Schalter in `UsersImport` |
| **Telegram verbinden** über Startlink, trennen | novamira-telegram, lea-telegram-anschluss | gleich | `TelegramController::verbinden/trennen`, Karte im Profil |
| Alles, was als Push geht, geht auch nach Telegram | lea-telegram-anschluss | besser | `Notifier` wählt Telegram als eigenen Kanal. Telegram mit Knopf "Öffnen" statt Link im Text |
| Termin-Erinnerungen auch für Personen nur mit Telegram | lea-telegram-anschluss | gleich | `Notifier::channelsFor` |
| Antworten aus Telegram landen im Gespräch; Coachin antwortet per "Antworten" auf die gemeldete Nachricht, sonst 30 Minuten im letzten Gespräch | lea-telegram-anschluss | gleich | `TelegramController::webhook`, Zuordnung über `telegram_links.settings.map` und `settings.letzte`. Neu auch für Andrea und für Gruppen |
| Telegram pausieren mit `/stop` bzw. "stopp", weiter mit "start" | novamira-telegram | fehlt | Nur ganz trennen im Profil |
| Webhook bei Telegram anmelden (setWebhook), Stand anzeigen (Bot, Webhook, letzter Fehler, Anzahl verbunden), Fehler je Person merken, Versandprotokoll | novamira-telegram, lea-telegram-anschluss (`lea_tg_log`) | vereinfacht | Webhook von Hand setzen (docs/05). Kein Stand, kein Protokoll, Fehler nur im Log |
| Termin-Erinnerungen am Morgen und eine Stunde vorher | (lea-termin-erinnerung, via was-kommt-an) | gleich | `Runden::terminErinnerungen` (9 Uhr und 60 Minuten vorher) |
| Aufgaben-Erinnerungen morgens und abends | (lea-aufgaben, via was-kommt-an) | gleich | `Runden::aufgabenHinweis`, nur Push/Telegram |
| **Diktieren** in jedem Antwort- und Kommentarfeld (Browser-Spracherkennung de-CH) | lea-diktieren (Code in Optionen `lea_mic_js`, `lea_mic_css`), lea-chat (Knopf `ch-mic`) | fehlt | Nirgends `SpeechRecognition` in `public/js` oder `resources/views`. Auch bei den Vorbereitungsfragen der Buchung und im Assistenten war das Mikrofon da |
| **Doppelschutz**: gleicher Text derselben Person am selben Ort innert 2 Minuten nur einmal; Knopf sperren; gleiche Anfrage nicht zweimal | lea-doppelschutz | fehlt | Chat-Formular sperrt beim Senden nicht, der Server prüft nicht auf Doppel. Gleiches für Kommentare, Reflexion, Antworten |
| **Eingabe zweizeilig** (Textfeld oben über die ganze Breite, Knöpfe darunter) | lea-eingabe-zweizeilig | fehlt | Chat-Eingabe ist einzeilig: Anhang, Textfeld, Mikrofon, Senden in einer Reihe. docs/04 nennt "Eingaben zweizeilig" als Regel |
| **Neuigkeiten**: Kursbeiträge nach Sichtbarkeit, Filter je Kurs, nach Monaten, Ungelesen-Punkt, Popup mit Vor/Zurück, Wischen zum Schliessen, `{firstname}` ersetzen | lea-neuigkeiten | vereinfacht | `ImpulseController`, `impulse/index` mit Filter Impuls/Neuigkeit, Sichtbarkeit über `Inhalte::postsQuery`. Es fehlen: gelesen je Beitrag und Punkt, Filter je Kurs, Popup mit Blättern |
| Blog-Beiträge aus der Website nur 120 Tage und nur Impuls-Kategorien | lea-neuigkeiten (`lea_nw_kats`) | vereinfacht | Kommt per Feed und Import (`inhalte:feeds`, `--only=inhalte`). Kategoriefilter über die Feed-Adresse |
| **Neu-Punkte im Menü** (Neuigkeiten, Aufzeichnungen, Ressourcen) | lea-neu | vereinfacht | Nur die Zahl beim Gespräch (Leiste und Chatknopf). Für Aufzeichnungen und Material gibt es "Neu seit dem letzten Besuch" auf der Startseite |
| **Einheitliche Mailgestalt** mit Logo, Titel, Fusszeile (Website, Adresse) | lea-mails | vereinfacht | `resources/views/mail/nachricht.blade.php` mit Branding (Farben, Schrift, Radius). App-Name statt Logo, keine Fusszeile mit Website und Kontakt |
| Passwort-Mail im selben Gewand, keine zweite WordPress-Mail beim Anlegen | lea-mails | bewusst weg | Magic Link statt Passwort (`MagicLinkMail`). Passwort ist optional |
| Club-Mailster: Kurszugang, dann Mailster-Liste und Kurs-Tag, Willkommenskampagne je Kurs einmal | lea-club-mailster | vereinfacht | `Shop/Zugang.php` schickt `WillkommenMail` direkt beim Woo-Webhook. **Keine Pflege der Mailster-Liste und der Kurs-Tags**, siehe Liste unten |
| Club-News, dann pausierte Mailster-Kampagne an Liste oder Kurs-Tags | lea-club-mailster | besser | `Filament/Coach/Pages/Rundnachricht.php`, `Notifications/Rundsendung.php` (Push, Mail, Gruppengespräch). Bei Beiträgen `PostObserver`. Mail geht dort aber nur an Personen **ohne** Push/Telegram |
| Neuer Termin, dann Mail "Neuer Termin für dich" an die Kursteilnehmerinnen | lea-club-mailster | fehlt | Neue Termine erscheinen nur auf der Startseite und in der Abendmail. Keine Meldung beim Anlegen |
| **KI-Assistent für die Coachin** ("Wo finde ich was?", mit Vorschlägen, Wissen aus Option `lea_as_wissen`) | lea-assistent | fehlt | Anthropic-Zugang ist da (`app/Ai/Anthropic.php`), die Seite fehlt |
| Themen prüfen ("Passt" oder "Ändern", Stand x von y) | lea-assistent | fehlt | Daten da (`finder_profiles.is_checked`, `ThemenProfil`), keine Prüfseite in Filament |
| Werkzeuge für die Ausbildung, Geteiltes (Sammlungen) | lea-assistent | fehlt | Gehört in den Bereich Inhalte/Fundus, hier nur vermerkt |
| **Aufgaben aus der KI-Zusammenfassung** mit einem Klick (nur 1:1, nur Lea) | lea-aufgaben-aus-zusammenfassung | besser | `Summarizer::event` liefert Aufgaben als JSON, `EditEvent` Aktion "Aufgaben aus Zusammenfassung" (Auswahl, auch Gruppencalls, je Person), die Person selbst kann in ihrer 1:1-Sitzung übernehmen (`TermineController::aufgabe`). Doppel werden erkannt |
| **Aufzeichnungen-Übersicht** mit Bild, Kurs-Abzeichen, Filter (Kurs, angeschaut, Sortierung), Popup | lea-aufzeichnungen | vereinfacht | `termine/index` (Zeit, Kurs, Hinweis "Aufzeichnung da"), `termine/show` mit Einbettung (`Support/Video`). Filter "noch nicht angeschaut" und Vorschaubild fehlen |
| Zeitmarken "(ab 12:00)" in der Zusammenfassung als Sprung ins Video | lea-aufzeichnungen, novamira-aufzeichnungen | fehlt | Zusammenfassung wird als reiner Text gezeigt |
| Importierte Zusammenfassungen (HTML mit `<p>`, `<h3>`) | (Bestand) | fehlt | **Fehler**: `BegleitungImport` übernimmt `ki_zusammenfassung` als HTML, `termine/show.blade.php` gibt `{{ $event->summary }}` escaped aus. Die Tags erscheinen als Text |
| "Als angeschaut markieren", "Live dabei" | lea-aufzeichnungen, lea-dabei | gleich | `TermineController::gesehen` (Status `watched`/`attended`) |
| Kasten "Aufzeichnung pflegen" mit Stufen (keine, Video ohne Zusammenfassung, bereit, benachrichtigt) | novamira-aufzeichnungen | vereinfacht | `EventResource`, Abschnitt Aufzeichnung (Link, Dauer, Zusammenfassung, Abschrift). Kein Status in der Liste, nur ein Haken |
| **Bewusste Freigabe**: Versand erst auf Knopfdruck, mit Wahl Mail und Push | novamira-aufzeichnungen | vereinfacht | `EventObserver` meldet **sofort beim Eintragen des Links**, nur Push/Telegram (`mailWennKeinPush: false`). Keine Prüfung vorher, keine Wahl. Wer weder Push noch Telegram hat, erfährt es erst in der Abendmail |
| Mail zur Aufzeichnung mit der Zusammenfassung im Text, 1:1 als "Eure Sitzung" | novamira-aufzeichnungen (`nva_e_versenden`) | fehlt | Siehe oben |
| **Vimeo-Abgleich**: neue Videos holen, Termin über Datum im Titel bzw. Upload-Zeit zuordnen | novamira-aufzeichnungen (vimeo.php) | fehlt | Keine Vimeo-API in der App |
| **Wache nach jedem Termin**: 20 Minuten nach Ende suchen, alle 15 Minuten bis 24 Mal, Abschrift nachholen (12 Mal), dann Zusammenfassung, dann an Lea melden oder automatisch verschicken | novamira-aufzeichnungen (wache.php) | fehlt | Die Coachin muss Link und Abschrift selbst eintragen und die Zusammenfassung von Hand starten (`EditEvent` Aktion "Zusammenfassen (KI)", Job `SummarizeEvent`) |
| Abschrift aus der Vimeo-Textspur (VTT, Zeitmarke alle 2 Minuten) | novamira-aufzeichnungen | fehlt | Siehe oben |
| KI-Zusammenfassung mit Kapiteln und "Deine Aufgaben für die Woche" | novamira-aufzeichnungen | besser | `Ai/Summarizer::event` (JSON: Zusammenfassung, Kernsätze, Aufgaben), Modell je Mandant (`settings.ai.model`), Opus. Kapitel mit Zeitmarken fehlen dafür |
| Anweisung an die KI im Adminbereich änderbar | novamira-aufzeichnungen | fehlt | Prompt steht im Code. Stil-Regeln in `Anthropic::STIL` |
| Mail an Lea: Aufzeichnung bereit, ohne Abschrift, verschickt, nicht gefunden | novamira-aufzeichnungen | vereinfacht | Nur "Zusammenfassung fertig/nicht möglich" an die Person, die sie angestossen hat (`SummarizeEvent`) |
| **Material mit Vimeo-Video**: Dauer, Vorschaubild, Abschrift, Zusammenfassung von selbst (5 Minuten nach Speichern, 6 Versuche) | novamira-aufzeichnungen (ressourcen.php) | fehlt | `MaterialResource` kennt keine Abschrift und keine Zusammenfassung |
| **Coaching buchen**: Art wählen (Klarheitsgespräch, Erstgespräch Ausbildung, 1:1), Tag, Zeit, Vorbereitungsfragen | novamira-coaching | fehlt | Keine Buchung in der App. Termine legt die Coachin in Filament an |
| Erstgespräch ohne Konto: Konto wird angelegt, Zugangsmail, Newsletter-Einwilligung (Mailster, doppelte Bestätigung), Herkunft (UTM, Verweis) | novamira-coaching | fehlt | |
| Nur buchbar mit offenem Kontingent (Sitzungen aus Einzelbegleitung und Kursen), Erstgespräch nur einmal offen | novamira-coaching | fehlt | `sitzungen_gesamt` wird importiert (`ProgramsImport`, `programs.settings`), aber nirgends gelesen |
| Lea bucht für eine Coachee | novamira-coaching | fehlt | |
| Verschieben und Absagen mit Frist (2 h), Absage gibt die Sitzung zurück | novamira-coaching | fehlt | Die App kennt nur "nicht dabei" für Gruppentermine |
| Termin in Google-Kalender eintragen, ändern, löschen; freie Zeiten aus Blöcken mit Stichwort minus belegte Einträge | novamira-coaching (google.php, verfuegbarkeit.php) | fehlt | |
| Bestätigungsmail mit Kalenderdatei, Mail an Lea, Absagemail | novamira-coaching (mail.php) | fehlt | Kalenderdatei je Termin und **Kalender-Abo je Person** gibt es (`KalenderController`, `Support/Ics`), das ist besser als ein einmaliger Anhang |
| Knöpfe am gebuchten Termin: Verschieben, In meinen Kalender, Absagen | novamira-coaching (termin-aktionen.php) | vereinfacht | Nur "In meinen Kalender" (`termine.ics`) |
| Stündlicher Cron `nvc_erinnerung` | novamira-coaching | bewusst weg | Wird angelegt, hat aber keinen Handler (toter Eintrag). Erinnerungen laufen in der App über `Runden::terminErinnerungen` |
| **Zoom-Anwesenheit**: nach dem Call Teilnehmerliste holen, Personen über Mail, Name, Vor- oder Nachname zuordnen, Mindestdauer, Gastgeberin ausnehmen, Bericht | novamira-zoom-anwesenheit | fehlt | Roadmap: "später". Von Hand: Coachin setzt "Live dabei" im Relation Manager `AttendeesRelationManager`, die Person selbst auf der Terminseite |
| Anschluss: nur Gruppencalls, Kandidaten = Kursteilnehmerinnen plus gebuchte Person, nicht überschreiben, was die Person selbst gesagt hat, dann "live dabei" (zählt als erledigt) | lea-zoom | fehlt | Ziel wäre `event_attendees.status = attended` |
| Zoom-Name je Person merken, damit es nächstes Mal ohne Raten passt | novamira-zoom-anwesenheit | fehlt | |

---

## 2. Die vier Plugins im Einzelnen

### 2.1 Coaching-Buchung (`novamira-coaching`, Version 0.1.0)

**Was es tut**

Buchbare Einzelsitzungen. Andrea trägt im Google-Kalender Blöcke mit einem Stichwort ein (Standard "Coaching"; Zusatz "Erst", "Ausbildung" oder "1:1" grenzt auf eine Art ein). Die Person wählt eine Art, einen Tag, eine Startzeit im Raster und beantwortet Vorbereitungsfragen (mit Mikrofon). Ablauf:

1. **Freie Zeiten** (`nvc_freie_zeiten`): alle Einträge im Zeitraum von Google holen; Einträge mit Stichwort sind Blöcke, andere sind belegt (ausser "frei" markiert), ganztägige sperren nur, wenn belegt; schon gebuchte Sitzungen aus dem eigenen Bestand sperren zusätzlich. Startzeiten im Raster, die Sitzung inklusive Puffer ("block", z. B. 75 statt 60 Minuten) muss ganz in den Block passen. Vorlauf 24 h, Horizont 42 Tage.
2. **Buchen** (`nvc_buchen`): Berechtigung prüfen (offene Arten für alle, einmal gleichzeitig; 1:1 nur mit Kontingent aus Einzelbegleitung oder Kurs). Bei Gästen: Konto anlegen (Rolle subscriber) und Zugangsmail mit Passwortlink, optional Mailster-Eintrag mit doppelter Bestätigung. Termin als CPT `termin` mit Wanduhrzeit, Zoom-Link aus den Einstellungen, Antworten, Herkunft (UTM, Verweis, Seite), Relation 19 zum Kurs.
3. **Google-Kalender**: Eintrag mit Titel "Art · Name", Beschreibung mit Antworten und Kontakt-Mail (ein Dienstkonto darf keine Gäste einladen), ID am Termin merken.
4. Hook `nvc_gebucht` (die Aufzeichnungs-Wache meldet den Termin an).
5. **Mails**: Bestätigung mit `.ics`-Anhang (METHOD:REQUEST) an die Person, Kurzmail an Lea.
6. **Verschieben** (`nvc_verschieben`): Zeit neu prüfen, Google-Eintrag per PATCH ändern, Mail "Neuer Termin". **Absagen** (`nvc_absagen`): nur bis 2 h vorher selbst, Google-Eintrag löschen, Termin auf Entwurf, Absagemail mit Kontingentstand an Person und Lea.
7. Knöpfe am Termin: Verschieben, In meinen Kalender (`.ics` per `admin-post`), Absagen.

**Einstellungen und Schlüssel (nur Namen)**

- Option `nvc_einstellungen`: `termin_cpt`, `feld_start`, `feld_ende`, `feld_zoom`, `zoom_link`, `vorlauf_stunden`, `absage_stunden`, `horizont_tage`, `raster_minuten`, `kalender_id`, `dienstkonto` (ungenutzt), `block_stichwort`, `sitzung_verbrauchen`, `arten` je Art (`erst`, `ausbildung`, `coaching`) mit `titel`, `termin_titel`, `offen`, `dauer`, `block`, `preis`, `text`, `fragen`
- Option `nvc_dienstkonto_json` (Google-Dienstkonto, JSON mit `client_email` und `private_key`): Geheimnis, liegt in der Datenbank
- Transient `nvc_g_token` (Zugriffstoken, 50 Minuten)
- Option `nvc_newsletter_listen` (Mailster-Listen)
- Optionen `nvc_css`, `nvc_js` (Oberfläche, Code nicht in der Datei)
- Konstante `LEA_KR_LEA` (Leas Benutzer-ID)
- Postmeta am Termin: `nvc_gebucht`, `nvc_art`, `nvc_person`, `nvc_gast_name`, `nvc_gast_mail`, `nvc_block_ende`, `nvc_antworten`, `nvc_herkunft`, `nvc_google_id`, `nvc_abgesagt`, `nvc_verbraucht`, `nvc_wanduhr`; Usermeta `nvc_ueber_erstgespraech`
- Hart im Code: keine Geheimnisse. Aber Standardtexte mit "Lea", Titel "Klarheitsgespräch mit Lea", Farbe #B4795F.

**Externe APIs**

- `POST https://oauth2.googleapis.com/token` (JWT-Bearer, RS256, Scope `https://www.googleapis.com/auth/calendar`)
- `GET https://www.googleapis.com/calendar/v3/calendars/{kalender_id}/events` (`timeMin`, `timeMax`, `singleEvents=true`, `orderBy=startTime`, `maxResults=250`, `pageToken`, höchstens 10 Seiten)
- `POST .../calendars/{kalender_id}/events`
- `PATCH .../calendars/{kalender_id}/events/{eventId}`
- `DELETE .../calendars/{kalender_id}/events/{eventId}`
- `GET .../users/me/calendarList` (`minAccessRole=reader`)
- Mailster lokal (`mailster('subscribers')`), kein Netzaufruf

**Webhooks und Endpunkte**

Keine eingehenden Webhooks. AJAX: `nvc_zeiten` und `nvc_buchen` (auch ohne Anmeldung), `nvc_nonce` (frischer Nonce für Seiten aus dem Cache), `nvc_absagen`. `admin-post`: `nvc_ics` (auch ohne Anmeldung, mit Nonce). Shortcodes `[nvc_buchung art=...]`, `[nvc_termin_aktionen]`. Aktionen `nvc_gebucht`, `nvc_verschoben`, `nvc_abgesagt`. Für das Verschieben gibt es im Plugin keinen eigenen AJAX-Endpunkt, das läuft über das JavaScript in `nvc_js`.

**Übertragung in die App (mandantenfähig)**

- `tenants.settings.booking`: `enabled`, `calendar_id`, `block_keyword`, `lead_hours`, `cancel_hours`, `horizon_days`, `grid_minutes`, `consume_on_cancel`, `zoom_url`, `notify_emails`. Das Dienstkonto als Geheimnis in `tenants.settings.google.service_account` (verschlüsselt, nur Plattform pflegt), analog zu `telegram.bot_token`. Alternativ OAuth je Mandant, falls Coachinnen ihren Kalender selbst verbinden sollen.
- Neue Tabelle `booking_types` (`tenant_id`, `key`, `title`, `event_title`, `is_open`, `duration`, `block_minutes`, `price`, `text`, `questions` json, `position`, `unique(['tenant_id','key'])`), in Filament pflegbar. Kein "Lea" in Texten, Titel kommen aus dieser Tabelle.
- Neue Tabelle `bookings` (`tenant_id`, `event_id`, `user_id`, `booking_type_id`, `answers` json, `origin` json, `google_event_id`, `block_ends_at`, `status` gebucht/verschoben/abgesagt, `cancelled_at`, `consumed` bool, `booked_by`), Index `tenant_id`. Der Termin selbst bleibt ein `events`-Eintrag (`type = one_on_one`, `user_id`), damit Erinnerungen, Aufzeichnung und Zusammenfassung wie bisher greifen.
- Kontingent: `programs.settings.sitzungen_gesamt` (schon importiert) minus nicht abgesagte Buchungen der Person im Programm; an einer Stelle berechnen (Dienst `App\Booking\Kontingent`).
- Dienste: `App\Booking\Verfuegbarkeit` (Google-Einträge je Mandant cachen, Schlüssel mit `tenant_id`, Redis-Prefix beachten), `App\Booking\GoogleCalendar` (Token-Cache je Mandant).
- Jobs mit `tenantId`: `SyncBookingToGoogle(tenantId, bookingId, action)`, Bestätigungs- und Absage-Mails als Mailable mit Anhang aus `App\Support\Ics` (Absender aus `settings.mail`). Die Doppelprüfung "Zeit noch frei" direkt vor dem Speichern, in einer Transaktion mit Sperre.
- Gäste: Konto plus Mitgliedschaft `guest` und **Magic Link** statt Passwortlink. Newsletter-Einwilligung bleibt in WordPress (Mailster); entweder weglassen oder per signiertem Aufruf an WordPress weiterreichen, klären.
- Routen: `GET /buchen`, `GET /buchen/{art}/zeiten`, `POST /buchen`, `POST /termine/{termin}/verschieben`, `POST /termine/{termin}/absagen`; öffentliche Buchung nur für offene Arten, mit Drosselung.
- Test: Mandant B sieht weder `booking_types` noch `bookings` von A; Verfügbarkeit von A nutzt nie den Kalender von B.

### 2.2 Aufzeichnungen und Vimeo (`novamira-aufzeichnungen`, Version 0.9.0)

**Was es tut**

Wichtig vorweg: **Zoom ist im Code nicht beteiligt.** Die Zoom-Cloud-Aufzeichnung landet über die Vimeo-Integration im Zoom- bzw. Vimeo-Konto bei Vimeo. Das Plugin fragt Vimeo ab. Ablauf:

1. **Anmelden**: Beim Speichern eines Termins (`save_post_termin`), nach einer Buchung (`nvc_gebucht`) und zweimal täglich (`nva_w_planen`, Termine von gestern bis in 3 Tagen) wird der Termin zur Beobachtung angemeldet. Reflexions- und Fragentage nicht.
2. **Wache** (`nva_w_blick`): 20 Minuten nach Terminende die letzten 20 Videos holen und den Termin suchen (Datum/Uhrzeit im Videotitel, sonst Upload-Zeit; Fenster 4 bzw. 18 Stunden; Video nicht schon an anderem Termin). Nicht gefunden: alle 15 Minuten wieder, höchstens 24 Mal, dann Mail an Lea "Keine Aufzeichnung gefunden".
3. **Übernehmen**: Link, Vimeo-ID, Dauer, Vorschaubild (ab 640 px) am Termin.
4. **Abschrift**: Textspur (bevorzugt Deutsch) als VTT holen und in Text mit Zeitmarke alle 2 Minuten wandeln. Noch keine Spur: alle 15 Minuten, höchstens 12 Mal, dann Mail an Lea "ohne Abschrift".
5. **Zusammenfassung** mit Anthropic (Anweisung aus Option, sonst Standard: "Worum es ging", "Deine Aufgaben für die Woche", 6 bis 10 Kapitel mit "(ab MM:SS)").
6. **Freigabe**: je nach Einstellung automatisch (Kurs, 1:1 getrennt) oder Mail an Lea "Aufzeichnung bereit". Im Kasten am Termin: Link, Dauer, Zusammenfassung bearbeiten, Mail und Push wählen, Knopf "Teilnehmerinnen benachrichtigen".
7. **Versand** (`nva_e_versenden`): Empfänger bei 1:1 die gebuchte Person, sonst die Kursteilnehmerinnen (Relation 13 über Relation 19) plus direkt zugeordnete Personen. Mail mit Zusammenfassung und Knopf "Jetzt ansehen", Push über `lea_kr_benachrichtigen`. Merker `nva_versandt`.
8. **Material mit Vimeo-Video**: Sobald ein Vimeo-Link an einer Ressource steht, 5 Minuten später Dauer, Bild, Abschrift und Zusammenfassung (eigene Anweisung) holen, bei fehlender Spur 6 Mal alle 10 Minuten. Zusätzlich stündlich höchstens 10 Ressourcen.

Auffällig im alten Code: Zwei Einstellungsseiten mit zwei Speicherorten für denselben Token (`nva_einstellungen.vimeo_token` wird nicht gelesen, `vimeo.php` nutzt `nva_vimeo_token`). Der Knopf "Teilnehmerinnen benachrichtigen" (`nva_freigeben`) setzt nur den Merker und löst die Aktion `nva_aufzeichnung_freigegeben` aus, verschickt selbst nichts; `nva_e_versenden` löst dieselbe Aktion noch einmal aus (Gefahr doppelter Meldung, falls jemand daran hängt).

**Einstellungen und Schlüssel (nur Namen)**

- Option `nva_einstellungen`: `posttyp`, `feld_link`, `feld_dauer`, `feld_text`, `feld_start`, `vimeo_token`, `vimeo_ordner`, `ki_schluessel`, `ki_anweisung`, `mail_standard`, `push_standard`
- Option `nva_vimeo_token` (Personal Access Token, Rechte Private, Video Files, Interact): Geheimnis
- Option `nva_ki_schluessel` (Anthropic): Geheimnis
- Optionen `nva_ki_auftrag`, `nva_ki_auftrag_ressource` (Anweisungen)
- Option `nva_wache`: `wartezeit`, `abstand`, `versuche`, `auto_freigabe`, `auto_freigabe_einzel`, `meldung_an_lea`
- Konstante `NVA_FREI_META`, `LEA_KR_LEA`
- Postmeta Termin: `recording_url`, `recording_dauer`, `recording_vimeo_id`, `recording_bild`, `recording_abschrift`, `ki_zusammenfassung`, `recording_stand`, `nva_wache`, `nva_abschrift_versuche`, `aufzeichnung_freigegeben`, `aufzeichnung_wege`, `nva_versandt`, `nva_versandt_an`. Ressource: `ressource_vimeo_id`, `ressource_url`, `ressource_datei`, `ressource_text`, `ressource_typ`, `ressource_dauer`, `ressource_bild`, `ressource_stand`, `ressource_versuche`
- Hart im Code: keine Geheimnisse. Modell `claude-sonnet-4-6` fest, Texte mit "Lea" in den Anweisungen und Mails.

**Externe APIs**

- `GET https://api.vimeo.com/me/videos` (`per_page`, `sort=date`, `direction=desc`, `fields=uri,name,description,link,player_embed_url,duration,created_time,pictures.sizes,parent_folder.name`), Header `Accept: application/vnd.vimeo.*+json;version=3.4`
- `GET https://api.vimeo.com/videos/{id}` (`fields=name,duration,link,pictures.sizes`)
- `GET https://api.vimeo.com/videos/{id}/texttracks`, danach die VTT-Datei über den `link` der Spur
- `POST https://api.anthropic.com/v1/messages` (`anthropic-version: 2023-06-01`, `max_tokens` 6000)

**Webhooks und Endpunkte**

Keine eingehenden Webhooks (weder von Zoom noch von Vimeo). WP-Cron: `nva_vimeo_abgleich` stündlich, `nva_w_planen` zweimal täglich, Einzelereignisse `nva_w_blick`, `nva_w_abschrift`, `nva_r_nachschauen`. AJAX: `nva_speichern`, `nva_freigeben`, `nva_einstellungen`. Shortcode `[nva_aufzeichnung]`. Aktionen `nva_aufzeichnung_bereit`, `nva_aufzeichnung_freigegeben`, `nva_ressource_bereit`.

**Übertragung in die App (mandantenfähig)**

- `tenants.settings.vimeo.token` (Geheimnis, nur Plattform), `settings.vimeo.folder` (optional, nur Videos aus diesem Ordner), `settings.recordings`: `wait_minutes` (20), `retry_minutes` (15), `max_tries` (24), `transcript_tries` (12), `auto_release_group`, `auto_release_one_on_one`, `notify_coach`, `default_channels` (mail, push). KI-Schlüssel gibt es schon (`settings.ai.anthropic_key`); Anweisungen in `settings.ai.prompts.recording` und `settings.ai.prompts.resource`, in Filament pflegbar.
- `events` neue Spalten (neue Migration): `vimeo_id` (mit `unique(['tenant_id','vimeo_id'])`, verhindert Doppelzuordnung), `recording_thumb`, `recording_status` (wartet, gefunden, abschrift, zusammenfassung, bereit, freigegeben, nicht_gefunden), `recording_tries`, `recording_released_at`, `release_channels` json. `resources`: `vimeo_id`, `transcript`, `summary`, `duration`, `thumb`, `prepare_status`, `prepare_tries`.
- Jobs mit `tenantId`: `WatchRecording(tenantId, eventId, attempt)` wird beim Speichern eines Termins mit `delay(ends_at + wait)` eingeplant, erneut mit `release(retry)`; `FetchTranscript(tenantId, eventId, attempt)`; danach den vorhandenen `SummarizeEvent` automatisch; `ReleaseRecording(tenantId, eventId, channels)`. Für Material `PrepareResourceVideo(tenantId, resourceId, attempt)`. Ein Kommando `aufzeichnungen:planen` zweimal täglich als Netz (wie `nva_w_planen`), läuft über `Runden::jeMandant`.
- `EventObserver` umbauen: nicht mehr beim Eintragen des Links melden, sondern bei Freigabe. Filament-Aktion "Teilnehmerinnen benachrichtigen" mit Wahl Mail/Push, Status-Spalte in der Terminliste.
- Zusammenfassung als Markdown oder erlaubtes HTML speichern und sicher rendern, Zeitmarken "(ab MM:SS)" in Sprungmarken wandeln (Vimeo Player API). Dabei gleich den Import-Fehler beheben (HTML aus WordPress wird escaped angezeigt).
- Optional statt Abfragen: Zoom-Webhook `recording.completed` an `POST /hooks/zoom/{secret}` als Auslöser, der `WatchRecording` sofort startet. Das Holen bleibt bei Vimeo.
- Test: Mandant B sieht keine Vimeo-Zuordnung von A; `WatchRecording` mit fremder `tenantId` findet den Termin nicht.

### 2.3 Zoom-Anwesenheit (`novamira-zoom-anwesenheit`, Version 1.0.2, Anschluss `lea-zoom.php`)

**Was es tut**

Nach einem Zoom-Meeting die Teilnehmerliste holen und melden, wer dabei war. Ablauf (`nvz_abgleich`):

1. Meetingnummer aus dem Zoom-Link am Termin (`/j/(\d+)`), notfalls aus einem verknüpften Beitrag.
2. Startzeit (Wanduhr in echte Zeit umrechnen), passende Instanz über `past_meetings/{nummer}/instances` suchen (Toleranz 120 Minuten; gibt es nur eine, die nehmen).
3. Teilnehmende mit Seitenweise-Abruf holen, Minuten je Person zusammenzählen (Schlüssel Mail, sonst Name).
4. Kandidaten vom Termin (Filter `nvz_kandidaten`; in `lea-zoom`: Kursteilnehmerinnen des Termins plus gebuchte 1:1-Person). Zuordnung: Mail, sonst voller Name, Namensteile, Vor- oder Nachname, nur wenn eindeutig. Gastgeberin (Name oder Mail aus Einstellungen) getrennt.
5. Überspringen: kürzer als 10 Minuten; in `lea-zoom` zusätzlich, wenn die Person selbst "war ich nicht" gesagt hat oder schon erfasst ist.
6. Treffer: Aktion `nvz_anwesend`, in `lea-zoom` dann `lea_dabei_setzen` (live dabei, zählt auch als angeschaut). Erkannten Zoom-Namen am Profil merken (`nvz_zoomname`).
7. Bericht (dabei, über Namen erkannt, übersprungen, Gastgeberin, nicht zugeordnet), Liste am Termin speichern.
8. Stündlich über die Termine der letzten 14 Tage, wenn eingeschaltet. In `lea-zoom` nur Gruppencalls (ohne Reflexions- und Fragentage).

**Einstellungen und Schlüssel (nur Namen)**

- Option `nvz_optionen`: `konto` (Account ID), `id` (Client ID), `geheim` (Client Secret, Geheimnis), `cpt`, `feld_start`, `feld_zoom`, `feld_verweis`, `wanduhr`, `mindest_minuten`, `toleranz_minuten`, `gastgeber`, `namen`, `tage`, `an`
- Alternativ Konstanten `NVZ_KONTO`, `NVZ_ID`, `NVZ_GEHEIM` in der `wp-config.php`
- Transient `nvz_token`; Usermeta `nvz_zoomname`; Postmeta `nvz_teilnehmer`, `nvz_abgeglichen`
- Zoom: Server-to-Server-OAuth-App, Scope `meeting:read:list_past_participants:admin` (alt `report:read:admin`, `meeting:read:admin`), bezahlter Plan nötig
- Hart im Code: keine Geheimnisse

**Externe APIs**

- `POST https://zoom.us/oauth/token?grant_type=account_credentials&account_id={konto}` (Basic-Auth mit Client ID und Secret)
- `GET https://api.zoom.us/v2/past_meetings/{meetingId}/instances`
- `GET https://api.zoom.us/v2/past_meetings/{uuid}/participants` (`page_size=300`, `next_page_token`, höchstens 10 Seiten; UUID mit `/` doppelt kodiert). Bei 401 Token verwerfen.

**Webhooks und Endpunkte**

Keine eingehenden Webhooks. WP-Cron `nvz_cron` stündlich. Filter und Aktionen: `nvz_kandidaten`, `nvz_ueberspringen`, `nvz_anwesend`, `nvz_abgeglichen`, `nvz_termine`, `nvz_nummer`, `nvz_person`, `nvz_mindest_sekunden`, `nvz_optionen`, `nvz_felder_nummer`. Adminseite mit "Verbindung prüfen" und "Jetzt abgleichen" (nur zeigen).

**Übertragung in die App (mandantenfähig)**

- `tenants.settings.zoom`: `account_id`, `client_id`, `client_secret` (Geheimnis, nur Plattform), `enabled`, `host` (Namen/Mails), `min_minutes`, `tolerance_minutes`, `lookback_days`, `name_matching`.
- `events` neue Spalten: `zoom_meeting_id` (aus `zoom_url` abgeleitet, beim Speichern), `attendance_synced_at`, `attendance_report` json (für "nicht zugeordnet" usw.).
- `event_attendees` ergänzen: `minutes`, `source` (zoom, selbst, coach), `match` (mail, name, vorname). Die Regel "was die Person selbst gesagt hat, wird nicht überschrieben" über `source` abbilden. Dafür braucht die App ein "war nicht dabei" nach dem Call (heute gibt es nur "nicht dabei" als Absage vorher).
- Zoom-Name je Mandant, nicht am globalen `users`: `memberships.settings.zoom_name`.
- Job `SyncZoomAttendance(tenantId, eventId, dryRun)`, Kommando `zoom:anwesenheit {tenant} --dry-run`, stündlich über `Runden::jeMandant`. Token-Cache mit Schlüssel `zoom_token_{tenant_id}`.
- Optional sofort statt stündlich: Zoom-Webhook `meeting.ended` an `POST /hooks/zoom/{secret}`, mit Prüfung `x-zm-signature` und der Endpunkt-Bestätigung (`endpoint.url_validation`); `hooks/*` ist schon von CSRF ausgenommen.
- Filament: Bericht am Termin (im Relation Manager Teilnahme), Knopf "Jetzt abgleichen (nur zeigen)".
- Test: Abgleich in Mandant A ordnet nie Personen aus B zu (Kandidaten nur aus `program_members` bzw. `memberships` des Mandanten).

### 2.4 Telegram (`novamira-telegram`, Version 1.0.0, Anschluss `lea-telegram-anschluss.php`)

**Was es tut**

Jede Person verbindet sich selbst über einen Startlink (`t.me/{bot}?start={code}`). Danach kommt alles, was als Push geht, auch als Telegram-Nachricht (Titel fett, Text, Link "Öffnen"). Antworten in Telegram landen im 1:1-Gespräch mit Lea. Lea antwortet per "Antworten" auf die gemeldete Nachricht (Zuordnung über die Telegram-Nachrichten-ID, 300 gemerkt), sonst 30 Minuten lang im zuletzt benutzten Gespräch. `/stop` bzw. "stopp" pausiert, "start" nimmt wieder auf. Protokoll der letzten 100 Sendungen ohne Text. Bot und Webhook-Status auf der Einstellungsseite.

**Einstellungen und Schlüssel (nur Namen)**

- Option `nvt_optionen`: `token` (Bot-Token, Geheimnis), `botname`
- Option `nvt_geheimnis` (Webhook-Geheimnis, wird erzeugt)
- Usermeta `nvt_chat_id`, `nvt_code`, `nvt_aus`, `nvt_fehler`; an Lea `lea_tg_map`, `lea_tg_letzte`; Option `lea_tg_log`
- Hart im Code: keine Geheimnisse. Texte mit "Lea".

**Externe APIs**

- `POST https://api.telegram.org/bot{token}/sendMessage` (`parse_mode=HTML`)
- `POST .../setWebhook` (`url`, `allowed_updates=["message"]`)
- `POST .../getMe`, `POST .../getWebhookInfo`

**Webhooks und Endpunkte**

Eingehend: `https://{site}/?nvt_hook={geheimnis}` (im `init`-Hook), verarbeitet `message` und `edited_message`: `/start CODE`, `/stop`, `stopp`, `start`, sonst Filter `nvt_eingehend`. Shortcode `[telegram_verbinden]`, AJAX `nvt_trennen`, Aktion `nvt_nachricht`.

**Stand in der App und was noch fehlt**

Portiert: `App\Notifications\TelegramChannel` (Bot-Token je Mandant, MarkdownV2, Knopf "Öffnen", Zuordnung über `telegram_links.settings.map`), `TelegramController` (Verbinden mit Code, Trennen, Webhook `POST /hooks/telegram/{secret}`, Antworten der Coachin und des Teams ins richtige Gespräch, auch in Gruppen), Tabelle `telegram_links` mit `tenant_id` und `unique(['tenant_id','user_id'])`, Einstellungen `settings.telegram.bot_token`, `bot_username`, `webhook_secret`. Versand läuft in `AppNotification` mit `tenantId` in der Queue. Das ist mandantenfähig.

Noch übertragen:

- Pausieren: `/stop`, `stopp`, `start` im Webhook, Feld `telegram_links.settings.paused` oder `active = false` ohne Trennen; `Notifier::channelsFor` beachtet das.
- Webhook anmelden aus der Plattform (Aktion in `TenantResource`): `setWebhook` mit `url` und `secret_token`, dann zusätzlich den Header `X-Telegram-Bot-Api-Secret-Token` prüfen. Stand anzeigen (`getMe`, `getWebhookInfo`, letzter Fehler, Anzahl verbunden).
- Fehler je Verbindung merken (`telegram_links.settings.last_error`), bei `403 bot was blocked` automatisch deaktivieren.
- Übernahme der bestehenden Verbindungen: Wenn beim Umschalten **derselbe Bot** weiterläuft, bleiben die `chat_id` gültig; dann `nvt_chat_id` im Import nach `telegram_links` übernehmen, statt dass alle neu verbinden. Ein Bot hat nur einen Webhook, also erst beim Umschalten auf die App zeigen lassen (im Parallelbetrieb zweiter Bot oder WordPress behält ihn).

---

## 3. Fehlt und ist wichtig (nach Wichtigkeit)

1. **Sprachnachrichten auf iPhone und Mac nicht abspielbar.** Chrome nimmt webm/Opus auf, Safari kann das nicht. Die App speichert unverändert. Nötig: nach dem Hochladen in m4a (AAC) wandeln, als Job mit `tenantId` (ffmpeg auf dem Server prüfen), Bestand nachziehen. Dazu fehlt das Transkript: `app.js` schickt keins mit, Whisper-Ersatz oder "Transkript erstellen" gibt es nicht.
2. **Importierte Zusammenfassungen werden kaputt angezeigt.** Das HTML aus WordPress steht als Text mit Tags auf der Terminseite (`termine/show.blade.php` gibt `summary` escaped aus). Beim Beheben gleich die Sprungmarken "(ab 12:00)" ins Video mitbauen.
3. **Aufzeichnungen laufen nicht mehr von selbst.** Kein Vimeo-Abgleich, keine Wache nach dem Termin, keine Abschrift aus der Textspur, Zusammenfassung nur von Hand. Die Coachin (bzw. Andrea) muss Link und Abschrift selbst eintragen. Material mit Vimeo-Video wird gar nicht aufbereitet.
4. **Aufzeichnung wird ohne Prüfung und ohne Mail verschickt.** Sobald ein Link eingetragen ist, geht die Meldung sofort raus (`EventObserver`), nur per Push/Telegram. Alt: bewusste Freigabe mit Wahl Mail/Push, Mail mit Zusammenfassung an alle, 1:1 als "Eure Sitzung", Meldung an Lea (bereit, ohne Abschrift, nicht gefunden).
5. **Coaching-Buchung fehlt ganz** (1:1 im Kontingent, kostenloses Klarheitsgespräch und Erstgespräch Ausbildung auch ohne Konto, Google-Kalender, Verschieben, Absagen mit Frist, Bestätigung mit Kalenderdatei, Mail an die Coachin, Lea bucht für jemanden). Solange die Buchungsseite auf leawernli.ch bleibt, geht es; beim Umschalten muss klar sein, wo gebucht wird. Das importierte Kontingent (`sitzungen_gesamt`) wird nirgends genutzt.
6. **Neue Termine werden nicht angekündigt.** Alt ging eine Mail "Neuer Termin für dich" an die Kursteilnehmerinnen. Neu nur Startseite und Abendmail.
7. **Mailster-Pflege beim Kurszugang.** Alt: beim Zuordnen zu einem Kurs Mailster-Liste (Club), Kurs-Tag und einmalige Willkommenskampagne. Nach dem Umschalten pflegt WordPress die Relation 13 nicht mehr, dann veralten Listen und Tags für Newsletter an Kursgruppen. Klären: App meldet Zugänge an WordPress zurück, oder Kursmails laufen nur noch über die App.
8. **Zoom-Anwesenheit** (automatisch "live dabei"). Ohne sie bleibt der Status "erledigt" bei allen offen, die nicht selbst klicken. Laut Roadmap "später", aber in docs/04 und Leas Assistent ("Wo finde ich, wer beim Call dabei war?") schon als Alltag vorausgesetzt.
9. **Chat im Alltag:** Element anhängen (Aufgabe, Notiz, Termin, Aufzeichnung, Material) ohne Auswahl im Eingabefeld; Diktieren fehlt überall; kein Doppelschutz beim Senden (Knopf sperrt nicht, Server erkennt Doppel nicht); Eingabe nicht zweizeilig (Designregel aus docs/04); kein Hinweis, wenn Andrea für die Coachin schreibt; kein "Neu"-Trenner.
10. **Mail an Personen ohne Push bei Beiträgen und Aufzeichnungen.** `PostObserver` und `EventObserver` schicken Mail nur, wenn weder Push noch Telegram da ist, bzw. gar nicht. Beiträge aus WordPress oder Feed lösen keinen Push aus.
11. **"Was ist neu" und Abendmail unvollständig:** es fehlen Beiträge, Podcast, Antworten auf eigene Einträge, Absender und Anriss bei Nachrichten, je Eintrag ein Link. Neuigkeiten ohne Gelesen-Punkt, Menü ohne Neu-Zahlen ausser beim Gespräch.
12. **Telegram-Betrieb:** Pausieren mit `/stop`, Webhook aus der Plattform setzen, Stand und Fehler anzeigen, bestehende `nvt_chat_id` übernehmen.
13. **KI-Assistent und Themenprüfung für die Coachin:** Fragen "wo finde ich was" und "Passt" für KI-Themen (`finder_profiles.is_checked` ohne Oberfläche).
14. **Mailgestalt:** Logo und Fusszeile (Website, Kontakt) aus `tenants.branding` bzw. `settings` in `mail/nachricht.blade.php`.
