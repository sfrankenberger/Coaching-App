# G3 Begleitung, Chat, Termine, Coach-Werkzeuge, Profil

Stand der neuen App: Commit f337175 (main). Alle Aussagen am Code geprueft (grep und Lesen). Die frueheren Abgleich-Dateien (docs/abgleich/kommunikation.md) sind an mehreren Stellen ueberholt: Buchung, Zoom-Abgleich, Vimeo-Wache, Freigabe und Umwandlung der Sprachnachrichten gibt es inzwischen im Code.

## Gelesene Dateien

Alt, PHP (alt/novamira-sandbox):
- lea-chat.php: 1:1-Verlauf, Senden, Reaktionen, Whisper
- lea-chatknopf.php: schwebender Knopf, Team-Gespraechsliste
- lea-nachrichten.php: Startseiten-Strom "Was ist neu"
- lea-sprachnachricht-format.php: webm nach m4a mit ffmpeg
- lea-terminvorschlag.php: Zeiten vorschlagen, annehmen, fix eintragen
- lea-begleitung.php: Einzelbegleitung, Journal, Projekte, Schritte
- lea-coaching.php: Seite "Coaching mit Lea", Buchen, Anfrage
- lea-coachees.php: Coachee-Liste, Dossier, Rundnachricht, KI-Vorbereitung
- lea-coachee-suche.php: Menschen suchen, Einordnung
- lea-termine.php: Terminliste, Filter, Nicht dabei
- lea-termin-anhaenge.php: Anhaenge beim Buchen mitgeben
- lea-termin-edit.php: Termin und Ressource im Frontend anlegen
- lea-termin-erinnerung.php: Erinnerung 9 Uhr und 1 Stunde vorher
- lea-kalender.php: ICS-Abo, Einzeltermin, Kalenderfenster
- lea-club-termin.php: Terminseite, Zugang, Kalender, Zeitmarken
- lea-dabei.php: live dabei, Zoom-Nachlauf alle 10 Minuten
- lea-zoom.php: Zoom-Anschluss an Kurse
- lea-aufzeichnungen.php: Aufzeichnungsuebersicht mit Filtern, Popup
- lea-stand.php: Wiedergabe-Position merken
- lea-auswertung.php: Zahlen, Ausgaben, Richtwerte, KI-Blick
- lea-steuerpult.php: Auskunft zu Betrieb und Menschen
- lea-assistent.php: Fragen, Themen pruefen, Werkzeuge, Geteiltes
- lea-rollen.php: Team-Rolle, antwortet als Lea
- lea-teilnehmerprofil.php: Profilbild, Bio, Teilnehmerliste
- lea-profil.php: Profilkacheln, Benachrichtigungen, Newsletter
- lea-konto.php: WooCommerce-Konto-Layout
- lea-was-kommt-an.php: drei Schalter im Profil
- lea-kunden-liste.php: Kaeufer in Mailster-Liste
- lea-messung.php: Zaehlung Gratiskurs "Der Anfang"
- .lea-coach.php.disabled: leer (0 Byte)
- .lea-crm-automation, -business, -cockpit, -engagement, -shared (.disabled): altes Leads-CRM, abgeschaltet

Alt, JS (wpjs): lea_ch_js.js (Chat, ganz gelesen), lea_co2_js.js (Dossier, Notizen, Vorschlag, Rundnachricht), lea_az_js.js (Aufzeichnungs-Popup), lea_tm_js.js (Nicht dabei), lea_c1_js.js (Anfrage-Vorlage), lea_jr_js.js (Journal). lea_ap_js.js und lea_aufbau_js.js gehoeren nicht zu meinen Dateien (kein get_option dort) und wurden nicht gelesen.

Alt, Plugins (zur Klaerung der Gegenstuecke in app/Booking, app/Recordings, app/Zoom): novamira-coaching (Buchung, Google, Mail, Termin-Aktionen), novamira-aufzeichnungen (Freigabe, Wache, Vimeo), novamira-zoom-anwesenheit (liesmich). novamira-telegram nur angeschaut, nicht geprueft (gehoert zu Benachrichtigungen).

Neu: app/Chat, GespraechController, resources/views/gespraech, public/js/app.js (Chat, Diktat, Anhang), Listeners/BenachrichtigeBeiNachricht, Notifications/Runden, Rundsendung, Notifier, TermineController, KalenderController, Support/Ics, BuchenController, GastBuchenController, app/Booking/*, CoacheesController, DossierController, views coachees/*, Coach/Lage, ProfilController, profil.blade.php, AvatarController, FragenController::leute, AssistentController, Ai/Assistent, Filament Coach (Assistent, Rundnachricht, Buchhaltung, EventResource, ProgramForm), app/Recordings/*, app/Zoom/*, MedienController, HomeController (Neu-Strom), routes/web.php, routes/console.php.

## Tabelle

Status: vorhanden, teilweise, fehlt, Website, Altlast.

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| **CHAT** | | | | |
| 1:1-Verlauf, links/rechts, Avatar, Uhrzeit | lea-chat | GespraechController::show, gespraech/show und _nachricht | vorhanden | |
| Tagestrenner im Verlauf | lea-chat | app.js tagTrenner | vorhanden | Format 25.09.2026 statt "25. September 2026" |
| Trenner "Neu" vor der ersten ungelesenen Nachricht, ungelesene hervorgehoben, Trenner verschwindet nach 4 Sekunden | lea-chat, lea-chatknopf | nichts (grep "trenner" findet nur Tagestrenner) | fehlt | Wiedereinstieg in lange Gespraeche ohne Marke |
| Nur die letzten Nachrichten, "N aeltere zeigen" | lea-chat | show (30, Link ?alle=1) | vorhanden | 30 statt 20, Seite laedt neu |
| Senden ohne Neuladen | lea-chat | app.js senden() | vorhanden | |
| Sofortige Vorschau "wird gesendet ...", Knopf waehrend Senden gesperrt | lea-chat, lea_ch_js | nichts | fehlt | Bei langsamem Netz kann doppelt gesendet werden, kein Doppelschutz auch serverseitig |
| Cmd/Strg+Enter sendet, Feld waechst mit | lea_ch_js | app.js | vorhanden | |
| Neue Nachrichten von selbst (Polling, Echtzeit) | lea_ch_js (nur Ziehen zum Aktualisieren) | Polling 5 s, Reverb-Kanal gespraech.{id} | vorhanden | besser als alt |
| Ziehen zum Aktualisieren | lea_ch_holen | app.js (.pull) | vorhanden | |
| Gelesen-Haken (ein Haken gesendet, zwei gelesen) | lea-chat | _nachricht, Chat::readUntilByOthers, app.js haken() | vorhanden | zaehlt jede andere Beteiligte (auch Kollegin im Team), alt nur Lea und Redaktion |
| Foto oder Datei anhaengen, Bild im Verlauf, Datei als Karte | lea-chat | Chat::send, Route nachricht.datei | vorhanden | |
| Etwas aus der App anhaengen (Aufgabe, Notiz, Reflexion, Termin, Aufzeichnung, Material) mit Suche | lea-chat (lea_ch_anhaenge) | x-anhang-wahl, Support/Anhaenge (dazu Lektion) | vorhanden | gestern neu gebaut |
| Aufzeichnung in der Chat-Karte direkt abspielen ("Ansehen") | lea-chat (lea_pl_knopf) | x-anhang-karte | teilweise | Karte fuehrt auf die Terminseite, kein Player im Chat |
| Diktat im Chat (de-CH) | lea-chat, lea_ch_js | app.js [data-diktat], lang aus html lang | vorhanden | gestern neu gebaut |
| Sprachnachricht aufnehmen (Timer, verwerfen) | lea_ch_js | app.js [data-sprache] | vorhanden | fuer alle, alt nur Team |
| Sprachnachricht vorhoeren vor dem Senden | lea_ch_js | app.js [data-probe] | vorhanden | gestern neu gebaut |
| Eigener Sprachnachricht-Player (Play/Pause, Balken, Klick springt, nur einer spielt, Hinweis "nicht abspielbar") | lea_ch_js | natives audio controls in _nachricht | teilweise | mehrere Nachrichten koennen gleichzeitig laufen, keine Fehlermeldung bei nicht abspielbar |
| Dauer der Sprachnachricht speichern und anzeigen, fehlende Dauer nachtragen | lea_ch_js (lea_ch_audio_dauer) | messages.audio_seconds wird beim Senden gespeichert | teilweise | angezeigt nur ueber den Browser-Player, kein Nachtragen fuer Bestand |
| Transkript auf- und zuklappen | lea_ch_js | details in _nachricht | vorhanden | |
| Transkript beim Aufnehmen mit Browser-Spracherkennung | lea_ch_js | Feld transkript wird angenommen, app.js schickt keines | fehlt | Anzeige gibt es nur fuer importierte Transkripte |
| Transkript per Whisper wenn Browser keines liefert, Knopf "Transkript erstellen" | lea-chat (lea_ch_transkribieren) | nichts (grep whisper, openai leer) | fehlt | Anthropic kann kein Audio, braucht anderen Dienst |
| Sprachnachrichten fuer Safari und iPhone umwandeln (ffmpeg), Bestand nachziehen | lea-sprachnachricht-format | Jobs/ConvertAudio, Befehl audio:umwandeln | vorhanden | braucht ffmpeg auf dem Server (nicht geprueft ob installiert) |
| Reaktionen (Emoji) auf fremde Nachrichten, Zaehler, eigene markiert | lea-chat | GespraechController::reaktion, _reaktionen | vorhanden | kein Auswahlfenster, alle Emojis gedimmt sichtbar, Seite laedt beim Tippen neu (kein JS) |
| Nachfassen: nach 20 Minuten ungelesen, Mail mit Zitat und Knopf "Antworten" | lea-chat (lea_ch_nachfassen) | Runden::nachfassen (alle 10 Minuten) | vorhanden | geht an alle Beteiligten im 1:1, schreibt Andrea, wird auch Lea genudgt |
| Benachrichtigung bei neuer Nachricht (Push/Telegram, sonst Mail) | lea-chat (lea_kr_benachrichtigen) | Listeners/BenachrichtigeBeiNachricht, Notifier | vorhanden | besser als alt |
| "Schreibt fuer Lea": Andrea antwortet, Nachricht steht als Lea mit Hinweis "Team Lea", Lea sieht "geschrieben von Andrea" | lea-chat, lea-rollen (lea_von, lea_team_name_text, lea_antwortet_als_lea) | nichts (Nachricht steht unter Name und Bild der Schreibenden, Push "Andrea hat dir geschrieben") | fehlt | Teilnehmerin sieht nicht, dass das Team schreibt; keine Team-Bezeichnung je Mandant |
| Rundnachricht an alle, an Kurs, an Einzelne, erscheint im 1:1 jeder Person, Antworten einzeln | lea-coachees (lea_co2_rund) | Filament Pages/Rundnachricht, Notifications/Rundsendung ("persoenlich"), Knopf in coachees/index und Heute | vorhanden | Auswahl nach Programm statt Kurs, Formular in /coach statt in der App-Huelle |
| Terminvorschlag: Coachin schickt Zeiten (freie Bloecke ankreuzen, eigene Zeit, Satz) | lea-coachees (vorschlagblatt, vorschlag2) | DossierController::vorschlag, dossier/_termine, Chat/Terminvorschlag::vorschlagen | teilweise | drei Handeingabefelder statt Liste der freien Zeiten aus dem Kalender; Knopf "drei freie Zeiten vorschlagen" fehlt (nur MCP-Werkzeug ZeitenVorschlagen) |
| Person tippt Zeit an: Termin gebucht, Bestaetigung im Chat, Info an Lea | lea-terminvorschlag (lea_tv_nimm) | Terminvorschlag::waehlen, Route nachricht.termin | teilweise | legt nur ein Event an: kein Booking (Verschieben und Absagen fehlen), kein Google-Kalendereintrag, keine Bestaetigungsmail mit Kalenderdatei, keine Pruefung gegen Doppelbelegung |
| Vorschlagskarte zeigt "Gebucht: ..." und "Zeiten sind vorbei" | lea-terminvorschlag | _nachricht (vorschlag an/vorbei) | vorhanden | |
| Lea traegt vorgeschlagene Zeit gleich fix ein (Knopf an der Karte) und "Termin fix eintragen" mit Uhrzeit, Notiz im Chat | lea-terminvorschlag (lea_tv_fix) | DossierController::termin ("Termin eintragen (1:1)") | teilweise | Knoepfe an der Vorschlagskarte fehlen, keine Chat-Notiz, kein Google-Eintrag, kein Booking |
| Schwebender Chatknopf mit Zahl, blendet beim Scrollen aus | lea-chatknopf | layouts/app (.chat-knopf), app.js | vorhanden | oeffnet eine Seite statt Schublade (bewusst) |
| Team-Liste aller Gespraeche: "wartet" zuerst, Textvorschau, "neu"-Marke, "gelesen", Testkonten ausgeblendet | lea-chatknopf (lea_cb_liste_html) | gespraech/index, Chat::conversationsFor | teilweise | sortiert nach letzter Nachricht mit Zahl ungelesen; ohne Textvorschau, ohne Marke "wartet", ohne "gelesen", Testkonten nicht ausgeblendet ("wartet" gibt es in der Coachees-Liste) |
| Zahl am Knopf: Person = ungelesene, Team = Personen die warten | lea-chatknopf (lea_cb_ungelesen) | Chat::unreadFor | teilweise | Team sieht Zahl ungelesener Nachrichten statt Zahl wartender Personen |
| 1:1-Seite: Sitzungen offen mit Balken, naechster Termin, Buchen/Anfragen | lea-coaching, lea-begleitung | gespraech/show (Karte oben), kurse/show ("Termin anfragen" mit Entwurf) | vorhanden | |
| Gruppengespraech je Kurs | (alt nur Community) | Chat::groupFor | vorhanden | neu |
| **START / NEU** | | | | |
| "Was ist neu": Nachrichten, Beitraege, Podcast, Aufzeichnungen, "Alles gesehen", "Weitere N anzeigen" | lea-nachrichten | HomeController, Runden::neuesFuer, home.blade | vorhanden | dazu neue Termine, Material, Aufgaben |
| Antworten auf eigene Eintraege und Community-Kommentare im Strom | lea-nachrichten | nichts in neuesFuer | fehlt | |
| Instagram-Posts im Strom | lea-nachrichten | nichts (kein Instagram im Code) | fehlt | |
| Karte je Eintrag ungelesen/gelesen, mehrere Antworten zu einer Zeile | lea-nachrichten | nur Zeitpunkt "seit", Zusammenfassung nur bei Nachrichten | teilweise | |
| {firstname}-Platzhalter im Vorschautext ersetzen | lea-nachrichten | Post.php Zeile 84 | vorhanden | |
| **TERMINE** | | | | |
| Terminliste als Karten, Monatsueberschriften, Zoom-Knopf ("Jetzt beitreten" ab 15 Minuten vorher) | lea-termine | TermineController::index, termine/index, Event::isLive | vorhanden | |
| Filter Kommende/Vergangene/Alle und Kurs | lea-termine | termine/index | vorhanden | |
| Filter "Was" (Alles, Nur Termine, Nur Aufgaben) und Textsuche | lea-termine | nichts | fehlt | |
| Aufgaben mit Faelligkeit im Terminkalender (eigene und Wochenaufgaben) | lea-termine (lea_tm_aufgaben) | nichts | fehlt | Aufgaben nur unter /aufgaben |
| Knopf "Termin mit Lea buchen" | lea-termine | termine/index (buchenUrl) | vorhanden | |
| "Nicht dabei" / "Doch dabei" direkt an der Karte | lea-termine, lea_tm_js | nur auf der Terminseite (termine.dabei) | teilweise | in der Liste nur der Hinweis "Du bist nicht dabei" |
| Wer fehlt: Kuerzel fuer alle, Namen fuer Lea | lea-termine (lea_tm_absagen_hinweis) | nur auf der Terminseite | teilweise | nicht an der Karte in der Liste |
| Aufzeichnung "Ansehen" an der Karte, Balken "weiter ab" | lea-termine, lea-stand | Link Ansehen; Balken nur auf der Terminseite | teilweise | Balken in Listen fehlt |
| Terminseite: Statuspille (laeuft, heute, morgen, in N Tagen, Aufzeichnung da), Fakten (wann, wo, Laenge, Zugang) | lea-club-termin | termine/show | teilweise | "Laeuft gerade" und Aufzeichnung ja; Pille "Heute/Morgen/In N Tagen" und Zeile "Zugang" (inklusive, kostenlos, kostenpflichtig) fehlen |
| Aufzeichnung einbetten (Vimeo), weiter wo aufgehoert, "Gesehen" | lea-club-termin, lea-stand | termine/show, Support/Video, MedienController (ab 80 Prozent gesehen) | vorhanden | |
| Zeitmarken "(ab 12:00)" springen im Video | lea-club-termin | x-kapitel, Support/Kapitel | vorhanden | |
| Zusammenfassung, Hinweis "Aufzeichnung kommt noch", Material zum Termin | lea-club-termin | termine/show | vorhanden | |
| Termine mit Zugangsart kostenlos/kostenpflichtig (Produkt), oeffentliche Seite ohne Login, Knopf "Platz sichern", Block "Du willst dranbleiben?" | lea-club-termin | nichts; Sicht nur ueber Programm-Mitgliedschaft (Begleitung::eventsQuery) | Website | oeffentliche Gratis-Abende gehoeren zur Website; Termin einzeln kaufen ist in der App nicht vorgesehen |
| Kalender-Abo je Person (webcal) | lea-kalender | KalenderController::abo, Support/Ics, Profil, Terminliste | vorhanden | |
| Kalender-Feed nur fuer einen Kurs (/kalender/token-kursid.ics) | lea-kalender | nichts | fehlt | |
| Erinnerung 15 Minuten vorher im Kalender (VALARM), Reflexionstage ohne Alarm und als "frei" | lea-kalender | Ics::vevent ohne VALARM/TRANSP | fehlt | |
| Abgesagte Termine im Feed als abgesagt markieren (STATUS, SEQUENCE) | lea-kalender | nichts; Absage setzt is_published = false, Termin verschwindet | fehlt | Kalender-Programme koennen den Eintrag stehen lassen |
| Kalenderfenster Apple, Google, Outlook, Link kopieren; Einzeltermin mit Google/Outlook-Link | lea-kalender, lea-club-termin | Profil und Terminliste: webcal-Knopf und "Link kopieren"; Einzeltermin nur .ics | teilweise | Google- und Outlook-Direktlinks und Auswahlfenster fehlen |
| Einzeltermin als .ics | lea-kalender | Route termine.ics | vorhanden | |
| Termin-Erinnerung 9 Uhr und 1 Stunde vorher (Push, sonst Mail), abschaltbar | lea-termin-erinnerung | Runden::terminErinnerungen (alle 10 Min), Schalter im Profil | vorhanden | |
| Erinnerung auch fuer Lea/Team | lea-termin-erinnerung (lea_te_leute) | EventObserver::recipients ohne Team | fehlt | Lea bekommt keine Erinnerung an ihre Calls und 1:1 |
| Anhaenge beim Buchen mitgeben (Foto, Datei, Element aus der App), "Mitgegeben" im Dossier | lea-termin-anhaenge | nichts (Buchungsseite ohne Anhang) | fehlt | |
| Termin im Frontend anlegen (Titel, Datum, Uhrzeit, ganztaegig, Dauer, Kurs, Woche, Zoom, Text) | lea-termin-edit | Filament EventResource (Coach-Bereich, Termine) | teilweise | vorhanden im Coach-Bereich, nicht in der App-Huelle; feste Zoom-Vorgabe fehlt |
| Ressource im Frontend anlegen (Datei/Link, Ordner, Woche) | lea-termin-edit | Filament MaterialResource | vorhanden | |
| Live dabei aus Zoom uebernehmen (Teilnehmerliste, Zuordnung ueber Mail/Name, Mindestdauer, Gastgeberin, Widerspruch) | lea-zoom, lea-dabei, novamira-zoom-anwesenheit | app/Zoom/Anwesenheit, Befehl zoom:anwesenheit, Aktion im EditEvent | vorhanden | |
| Zoom-Nachlauf alle 10 Minuten nach Ende | lea-dabei (lea_nvz_nachcall) | Scheduler stuendlich (hourlyAt 25) | teilweise | Ergebnis kommt bis zu einer Stunde spaeter |
| "Ich war live dabei" von Hand, "War ich doch nicht", Hinweistext "wird automatisch aus Zoom uebernommen" | lea-dabei | termine/show, TermineController::gesehen | teilweise | Zuruecknehmen und Hinweistexte fehlen |
| Aufzeichnungs-Uebersicht: Karten mit Vorschaubild und Kurs-Abzeichen, Filter Kurs/Status (nicht angeschaut)/Sortierung, Popup | lea-aufzeichnungen, lea_az_js | termine/index?zeit=vorbei (Badge "Aufzeichnung") | teilweise | kein Filter "noch nicht angeschaut", kein Vorschaubild, keine eigene Seite, Terminseite statt Popup |
| "Als angeschaut markieren" umschaltbar | lea_az_js | TermineController::gesehen | teilweise | nur setzen, nicht zuruecknehmen |
| Wo war ich stehengeblieben (Video/Audio), ab 80 Prozent erledigt | lea-stand | MedienController::position, MediaPosition | vorhanden | |
| **BUCHUNG (Plugin novamira-coaching)** | | | | |
| Art waehlen, Tag, Zeit, Vorbereitungsfragen, Diktat in Fragen | novamira-coaching | BuchenController, buchen/zeiten, globales Diktat auf textarea.feld | vorhanden | |
| Freie Zeiten aus Kalender-Bloecken (Stichwort, Zusatz je Art), Vorlauf, Horizont, Raster, Puffer | novamira-coaching (verfuegbarkeit) | Booking/Verfuegbarkeit, BookingType.block_tag | vorhanden | |
| Termin in Google-Kalender eintragen, aendern, loeschen | novamira-coaching (google) | Booking/GoogleCalendar, Buchung | vorhanden | |
| Bestaetigungsmail mit .ics und Kurzmail an Lea; Absagemail | novamira-coaching (mail) | Buchung: Notifier-Nachricht an Person, teamMelden | teilweise | Mail nur wenn kein Push, ohne angehaengte Kalenderdatei |
| Verschieben und Absagen mit Frist, Sitzung geht zurueck | novamira-coaching | Buchung::verschieben/absagen, x-termin-aktionen | vorhanden | |
| Option "Absage verbraucht Sitzung" | novamira-coaching (sitzung_verbrauchen) | nichts | fehlt | Standard war aus, nur Einstellung |
| Erstgespraech nur einmal offen, 1:1 nur mit Kontingent | novamira-coaching | Buchung::hindernis | vorhanden | |
| Kontingent aus Einzelbegleitung und aus Kursen mit Sitzungen im Paket | lea-begleitung, nvc_kontingent | Lage::kontingent (nur Programme vom Typ one_on_one), ProgramForm Feld nur bei 1:1 sichtbar | teilweise | Gruppenprogramme mit enthaltenen 1:1 (Hybrid) zaehlen nicht |
| Gast bucht Klarheitsgespraech ohne Konto, Konto und Zugangsmail | novamira-coaching | GastBuchenController, MagicLink | vorhanden | |
| Newsletter-Haken bei Gastbuchung (Mailster, doppelte Bestaetigung) | novamira-coaching | nichts | fehlt | Mailster bleibt in WordPress (Roadmap Etappe 11) |
| Herkunft der Buchung (UTM, Verweis) und "kam ueber ..." im Dossier | novamira-coaching, lea-coachees | nichts (grep origin leer) | fehlt | |
| Lea bucht fuer eine Coachee (freie Zeit, Kontingent, Google, Mail) | novamira-coaching (fuer), lea-coachees | DossierController::termin | teilweise | freie Uhrzeit, keine Blockpruefung, kein Google-Eintrag, kein Booking |
| Vorab-Antworten der Person fuer Lea sichtbar | lea-coachees (lea_co2_termin_details) | nur Filament-Dossier (dossier.blade Zeile 76) und Team-Meldung (300 Zeichen) | teilweise | im Dossier der App-Huelle fehlen sie |
| **COACH-WERKZEUGE** | | | | |
| Coachees-Liste: Karten mit naechstem Termin, Kontingent, Aufgaben, zuletzt da, "wartet", Test, Kontakt, weitere Kontakte eingeklappt | lea-coachees | CoacheesController::index, coachees/index, _karte | vorhanden | |
| Sortierung Naechster Termin, Zuletzt aktiv, Name | lea-coachees | index (sort) | vorhanden | |
| Ampel und Freigegebenes auf der Liste | lea-coachees (lea_ck_*) | Coach/Lage, Coach/Neues | vorhanden | |
| Menschen suchen, auch ohne Kurs und Gespraech (Newsletter-Leute, Rechnungskundinnen), mit Einordnung | lea-coachee-suche | Suchfeld filtert nur Lage::alle() | teilweise | nur aktive Mitgliedschaften mit Rolle member oder client; Gaeste (Klarheitsgespraech) und Kontakte fehlen in Liste und Suche; keine Einordnung "in Begleitung / nur Konto" |
| Neue Person anlegen | (neu) | CoacheesController::anlegen | vorhanden | besser als alt |
| Dossier-Kopf: Zuletzt hier, Mail, WhatsApp, Anrufen | lea-coachees | coachees/show | vorhanden | Gruss ("Liebe X,") in Mail und WhatsApp ist nicht vorbelegt |
| Dossier Kennzahlen (naechster Termin, Wochenaufgaben, bei den Calls, sie schreibt) | lea-coachees | coachees/show | vorhanden | |
| Reiter Gespraech mit Schnellantwort, "Als gelesen" | lea-coachees | dossier/_gespraech, DossierController::gelesen | vorhanden | |
| Reiter Termine: kommt/war, 1:1-Marke, Aufzeichnung, abgemeldet, live dabei mit Minuten | lea-coachees | dossier/_termine, _termin_zeile | teilweise | Minuten "live dabei" und ausklappbare Zusammenfassung fehlen |
| Reiter Kurs (Stand je Programm) | lea-coachees (lea_ck_ansicht) | dossier/_kurs | vorhanden | |
| Reiter Aufgaben (geben, sehen, Kommentar) | lea-coachees | dossier/_aufgaben, DossierController::aufgabe | vorhanden | "jeden Tag" nicht im App-Formular (nur Filament) |
| Reiter "Von ihr freigegeben" mit Antworten | lea-coachees | dossier/_geteilt, Coach/Kommentare | vorhanden | |
| Reiter Projekte | lea-coachees | nichts | fehlt | siehe Projekte unten |
| Reiter Meine Notizen (privat, mehrere, loeschen) | lea-coachees | dossier/_notizen | vorhanden | dazu Anheften |
| Reiter Workbook (Antworten im Arbeitsbuch der Person) | lea-coachees (lea_wb_coach_ansicht) | kein eigener Reiter; Antworten nur wenn freigegeben | teilweise | Umfang nicht geprueft (Arbeitsbuch gehoert zu anderem Bereich) |
| Reiter Vorbereitung (KI, 3 Tage gemerkt, neu erstellen) | lea-coachees (lea_co2_analyse) | dossier/_vorbereitung, Jobs/VorbereitungErstellen | vorhanden | |
| Auskunft "Frag mich etwas zu deinem Betrieb" (Personen, Orte, Inhalte, Beispiele) | lea-steuerpult | Ai/Assistent::antwort, coachees/index, Assistent-Seite | vorhanden | |
| Auskunft kennt Rechnungen (bexio) und Bestellungen | lea-steuerpult | Fakten enthalten Zugaenge und Buchungen, aber keine Rechnungen | teilweise | Frage "ist die Rechnung verschickt" nicht beantwortbar (Dossier-Reiter Rechnungen gibt es) |
| Assistent Reiter Fragen (Wissen, Vorschlaege, Diktat) | lea-assistent | Filament Pages/Assistent, AssistentController, Wissen | vorhanden | besser: Wissensspeicher, MCP; Diktat im Feld nicht geprueft |
| Assistent Reiter Themen pruefen ("Passt", "Aendern", Art-Filter, Zaehler) | lea-assistent | Filament Assistent (passt(), offeneThemen) | vorhanden | |
| Assistent Reiter Werkzeuge (Liste, neu anlegen) | lea-assistent | Filament Assistent, ToolResource | vorhanden | |
| Assistent Reiter Geteiltes (Sammlungen, Empfaenger, "gesehen") | lea-assistent | Filament Assistent::sammlungen | vorhanden | |
| Team antwortet im Namen von Lea, Rolle Redaktion mit Rechten | lea-rollen | Role::Team, canManageCurrentTenant | teilweise | Rolle ja, "antwortet als Lea" nein (siehe Chat) |
| Zahlen und Auswertung: Einnahmen nach Art, Ausgaben, Gewinn, Ampel Richtwerte, Ziele, Jahresgrafik, KI "Blick von aussen" | lea-auswertung | nichts; /coach/buchhaltung ist nur Einrichtung von bexio | fehlt | in docs/04 als "spaeter" vermerkt |
| Ausgaben erfassen, Rechnungen einer Art zuordnen, Aufwand aus bexio | lea-auswertung | nichts | fehlt | gehoert zur Auswertung |
| Messung Gratiskurs "Der Anfang" (gesehen, eingetragen, beendet) | lea-messung | nichts | Website | Zaehlung sitzt auf der WordPress-Seite; App zeigt keine Kennzahl |
| Kaeuferinnen in Mailster-Liste "Kunden" | lea-kunden-liste | nichts | Website | Woo und Mailster bleiben in WordPress |
| Leads-CRM (Leads, Reminder, Business, Engagement, Kandidatinnen-Signal) | .lea-crm-*.disabled | nichts | Altlast | seit August abgeschaltet; ein Teil davon (Aktivitaet, 1:1-Kandidatin) steckt heute in der Ampel |
| .lea-coach.php.disabled | .lea-coach.php.disabled | nichts | Altlast | Datei ist leer |
| **BEGLEITUNG / JOURNAL** | | | | |
| Einzelbegleitung mit Kontingent "x von y offen", Balken, "Naechsten Termin buchen" | lea-begleitung | Profil (Meine Buchungen), Gespraech, kurse/show, Lage::kontingent | vorhanden | |
| Gemeinsames Journal: Notiz, Reflexion, Aufgabe, Material in einem Strang, Termine und Aufzeichnungen eingewoben, beide kommentieren | lea-begleitung | Mein Journal (Aufgaben, Notizen, Reflexion je Person) plus Teilen mit Coachin, Dossier-Reiter, Coach/Kommentare | teilweise | kein gemeinsamer Strang mit eingewobenen Terminen; Lea kann kein "Material" in den Strang legen |
| Eintrag mit Foto oder Handschrift und Link | lea-begleitung | nichts in Notizen/Reflexion (Foto nur im Chat) | fehlt | |
| Aufgabe mit Faelligkeit, jeden Tag mit sieben Wochentagen abhaken | lea-begleitung (lea_jr_tage) | Task.is_daily, AufgabenController::tag | vorhanden | |
| Benachrichtigung an Teilnehmerin bei Eintrag von Lea, Mail an Lea bei Antwort | lea-begleitung | TaskObserver, Coach/Kommentare | vorhanden | |
| Projekte (Name, Farbe, Symbol) und neun Prozessschritte je Eintrag, Filter im Journal | lea-begleitung (lea_pj, lea_ps) | nichts; Import legt projekt/schritt nur in journal_entries.settings ab | fehlt | Rubrik "Deine Projekte und Schritte" ganz weg |
| Aufgaben und Reflexionen aus dem Journal auch unter Aufgaben/Reflexion | lea-begleitung (Shortcodes) | Aufgaben und Reflexion sind selbst die Quelle | vorhanden | |
| **PROFIL** | | | | |
| Profilkacheln, Abmelden, Hilfe und Technik | lea-profil | ProfilController, profil.blade | vorhanden | eine Seite statt Kacheln |
| Profilbild hochladen | lea-teilnehmerprofil | AvatarController | vorhanden | |
| Kurzbio, Telefon | lea-teilnehmerprofil | Profil (ueber_mich 300 Zeichen, phone) | vorhanden | |
| Website-Feld, Schalter "E-Mail zeigen" und "Telefon zeigen" | lea-teilnehmerprofil | nichts | fehlt | |
| Teilnehmerliste der eigenen Kurse (Bio, Schreiben, Telefon, Website) | lea-teilnehmerprofil | FragenController::leute, community-leute | teilweise | nur Name, Worte, Kurse; keine Kontaktdaten und keine Website |
| Name, E-Mail und Passwort aendern | lea-profil (WooCommerce-Formular) | Profil: Name, Passwort | teilweise | E-Mail-Adresse aendern fehlt |
| Meine Kaeufe: Bestellungen mit Rechnungs-PDF | lea-profil | Profil "Deine Rechnungen" (bexio, PDF) | teilweise | Bestellliste und Details aus dem Shop fehlen |
| Deine Buchungen (Zugaenge, Laufzeit, Woche x von y, Abo verwalten) | lea-profil (lea_mg_block) | Profil "Meine Buchungen" | vorhanden | |
| Push einrichten und Stand | lea-profil | Profil, app.js [data-push] | vorhanden | |
| Newsletter an- und abmelden | lea-profil (Mailster Liste 2) | nichts | fehlt | Mailster bleibt in WordPress (Roadmap Etappe 11) |
| Kalender-Abo im Profil | lea-profil | Profil | vorhanden | |
| Drei Schalter "Was dich erreicht" | lea-was-kommt-an | ProfilController::notifications, Notifier::wants | vorhanden | |
| Anmeldemethoden im Profil (Google, Apple, Passkeys) | lea-profil | Passkeys im Profil; Google/Apple nur beim Login | teilweise | Verknuepfen im Profil fehlt |
| Telegram verbinden | (Plugin novamira-telegram) | Profil, TelegramController | vorhanden | nicht Teil meiner Dateien |
| WooCommerce-Konto-Layout | lea-konto | Profil ersetzt es | Altlast | reine WordPress-Optik |

## Wichtigste Luecken

1. **Team schreibt "fuer Lea" fehlt.** Nachrichten von Andrea stehen unter ihrem Namen und Bild, der Push sagt "Andrea hat dir geschrieben". Alt: Auftritt als Lea mit Hinweis "Team Lea", Lea sieht "geschrieben von Andrea". Fuer Lea und die Teilnehmerinnen der sichtbarste Unterschied im Alltag.
2. **Gaeste und Kontakte fehlen in Coachees-Liste und Suche.** `Lage::alle()` nimmt nur Rolle member und client. Wer ein Klarheitsgespraech bucht (Rolle guest), taucht dort nicht auf; die Menschen-Suche findet nicht "jeden mit Konto". Kein "kam ueber ..." im Dossier.
3. **Termin aus Vorschlag und "Termin eintragen" sind nur halbe Buchungen.** Kein Booking (Verschieben und Absagen fehlen), kein Google-Kalendereintrag, keine Bestaetigungsmail mit Kalenderdatei, keine Pruefung gegen Doppelbelegung. Zeiten vorschlagen ohne Liste der freien Zeiten aus dem Kalender und ohne Knopf "drei freie Zeiten".
4. **Sprachnachrichten ohne Transkript.** Weder Browser-Transkript beim Aufnehmen noch Whisper noch "Transkript erstellen". Es braucht einen Audio-Dienst (Anthropic kann das nicht), Entscheid noetig.
5. **Buchungsbestaetigung ohne Kalenderdatei**, Vorab-Antworten der Person nur im Filament-Dossier, nicht im Dossier der App-Huelle; Anhaenge beim Buchen ("Mitgegeben") und Herkunft der Buchung fehlen.
6. **Kalender-Feed ist einfacher als alt:** keine Erinnerung 15 Minuten vorher, abgesagte Termine verschwinden statt "abgesagt" zu zeigen, kein Feed je Kurs, kein Google- und Outlook-Link.
7. **Terminliste:** Aufgaben mit Datum fehlen, Filter "Was" und Suche fehlen, "Nicht dabei" und die Namen/Kuerzel der Fehlenden nur auf der Terminseite. Lea und Team bekommen keine Termin-Erinnerung.
8. **Journal und Projekte:** kein gemeinsamer Strang, Projekte und die neun Prozessschritte samt Filter und Dossier-Reiter Projekte fehlen, kein Foto/Handschrift und kein Link im Eintrag.
9. **Chat-Kleinigkeiten:** Trenner "Neu" und Hervorhebung ungelesener Nachrichten, sofortige Vorschau und Doppelsende-Schutz, Team-Liste ohne Textvorschau, Marke "wartet" und "gelesen", eigener Player (ein Player gleichzeitig, Fehlermeldung), Emoji-Auswahl ohne Neuladen.
10. **Kontingent nur aus 1:1-Programmen.** Hybrid- oder Gruppenprogramme mit enthaltenen Einzelsitzungen zaehlen nicht (Feld "Sitzungen im Paket" nur bei Typ 1:1 sichtbar).
11. **Auskunft kennt keine Rechnungen und Bestellungen**, dadurch bleibt "Ist die Rechnung von Anke verschickt?" unbeantwortet.
12. **Zahlen und Auswertung** (Einnahmen, Ausgaben, Richtwerte, Ziele, KI-Blick) fehlen ganz; laut docs/04 bewusst spaeter.
13. **Profil:** E-Mail-Adresse aendern, Website und Kontakt-Freigabe fuer die Teilnehmerliste, Newsletter an/aus (haengt an Mailster, Etappe 11), Google/Apple im Profil verknuepfen, Bestellliste.
14. **Aufzeichnungs-Uebersicht:** Filter "noch nicht angeschaut", Vorschaubild, Zuruecknehmen von "angeschaut"; Zoom-Nachlauf stuendlich statt alle 10 Minuten; Zeile "Zugang" und Pille "Heute/Morgen" auf der Terminseite.
15. **"Was ist neu"** ohne Antworten auf eigene Eintraege und ohne Instagram; keine ungelesen/gelesen-Unterscheidung je Karte.
