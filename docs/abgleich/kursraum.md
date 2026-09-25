# Abgleich Kursraum, Kurse, Lektionen, Workbook, Woche

Stand 25.09.2026. Verglichen: 38 Dateien `lea-*.php` aus `novamira-sandbox/` mit der Laravel-App (`KursController`, `resources/views/kurse/*`, `app/Programs/*`, Filament `Programs`, `Tasks`, `Posts`, `Materials`, `Dossier`, `TermineController`, `GespraechController`, Import unter `app/Import/WordPress`).

Kurzbild: Das Grundgerüst (Programm, Schritt, Einheit, Übungsteil, Antwort, Fortschritt, Freigabe, Notiz je Einheit, Coach-Dossier) ist sauberer als vorher. Was fehlt, ist vor allem das, was die **Woche lebendig** gemacht hat: Call, Aufzeichnung, Wochenaufgaben, Material und Reflexion direkt im Schritt, dazu die **Workbook-Sonderbausteine**, das **Mikrofon-Diktat**, die **Videoposition** und die **Fragen an Lea mit Status**. Ausserdem verliert der Import heute Daten (siehe Punkt 1 der Liste).

---

## 1. Tabelle

Status: `gleich`, `vereinfacht`, `besser`, `fehlt`, `bewusst weg`.

### Kursraum (Fragen und Austausch je Kurs)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Frage stellen mit Titel und Text, Ziel wählen: Allgemein, ein Kurs oder nur Lea | lea-kursraum | fehlt | Es gibt nur den Gruppenchat `kurse.austausch` (`GespraechController::gruppe`). Keine Fragenliste, kein Einzelthread. |
| Fragenstatus offen, kommt in den Call, von Lea beantwortet, im Call besprochen, abgeschlossen; Lea setzt ihn per Auswahl, Chip in der Liste | lea-kursraum, lea-kursraum-reaktionen | fehlt | Kein Modell. Wichtig für Leas Montagscall. |
| Antworten mit Unterantworten, Lea-Antwort hervorgehoben, "beste Antwort", lange Antworten "Weiterlesen" | lea-kursraum | fehlt | Chat ist flach. |
| Frage folgen (Glocke), Benachrichtigung an Folgende, Fragestellerin, Antwortende | lea-kursraum | fehlt | |
| @-Erwähnung mit Namensvorschlägen beim Tippen | lea-kursraum | fehlt | |
| Neue Antworten alle 25 s prüfen, Knopf "2 neue Antworten anzeigen" | lea-kursraum | vereinfacht | Gespräch lädt neu (`gespraech.neu`), für Fragen entfällt es mangels Fragen. |
| Austausch-Chat je Kurs mit Blasen links/rechts, Enter sendet, lädt nach | lea-kursraum | gleich | `gespraech/show.blade.php`, Link auf `kurse/show.blade.php`. |
| "Lea privat fragen" öffnet 1:1 | lea-kursraum | gleich | Direktgespräch, schwebender Chat-Knopf. |
| Etwas an die Frage anhängen (Aufgabe, Notiz, Reflexion, Termin, Material) mit Suchfeld | lea-kursraum | vereinfacht | Gespräch kann Datei und Sprachnachricht, aber keinen Verweis auf ein eigenes Element. |
| Vorbefüllt aus Impuls "In der Community teilen" (frage_ref, frage_titel) | lea-kursraum | fehlt | |
| Entwurf der Frage im Browser merken | lea-kursraum | fehlt | Kein localStorage-Entwurf in Views. |
| Filter, Suche, Sortierung (neu, alt, meiste Antworten, Callwunsch) | lea-kursraum | fehlt | |
| Geteilte Notizen, Reflexionen, Aufgaben (Sicht "Kurs") im selben Strom | lea-kursraum | fehlt | `visibility=program` existiert an Notes/Tasks, wird aber nirgends als Strom gezeigt. |
| Donnerstag 9 Uhr Push "Heute ist Fragentag" an den Kurs | lea-kursraum | vereinfacht | Fragentag ist Termin-Art `question_day` (`Event::TYPES`), eigene Erinnerung mit Aufruf zur Frage gibt es nicht. |
| Donnerstag Sammelmail an Lea: offene Fragen für den Montagscall | lea-kursraum | fehlt | |
| Reaktionen 👍 🙋 🎯 ❤️ direkt in der Liste, Zähler, Hinweis an Autorin | lea-kursraum-reaktionen | vereinfacht | `Reaction::EMOJIS` nur an Chatnachrichten; 🎯 "Bitte im Call besprechen" fehlt (ersetzt durch 🙌). |
| "3× für den Call gewünscht" in der Liste | lea-kursraum-reaktionen | fehlt | |
| Frage löschen durch Autorin oder Lea | lea-kursraum-reaktionen | fehlt | Keine Löschroute für Nachrichten. |
| Knopf "Wer ist dabei" mit Anzahl | lea-kursraum | fehlt | Andere Baustelle (Teilnehmerliste), hier nur erwähnt. |
| Bestehende Fragen und Antworten übernehmen | lea-kursraum | fehlt | Import kennt CPT `frage` nicht (`app/Import/WordPress/*` liest termin, ressource, aufgabe, notiz, reflexion, journal, chat, post, podcast, kurs). |

### Kursseite, Kopf, Übersicht

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Bildband mit Kursbild, Titel und Untertitel weiss auf dunklem Verlauf | lea-kurs-kopf | fehlt | `cover_url` im `ProgramForm`, aber in `kurse/show` und `kurse/index` nie ausgegeben. |
| Kursfarbe färbt Balken, Tags, Punkte | lea-kurs-kopf | gleich | `programs.color` als `--kc` in `kurse/index`, `kurse/show`, `home`. |
| Hinweis "In diesem Kurs sind 3 Einzelsitzungen enthalten, 2 offen" + "Termin buchen" | lea-kurs-kopf | fehlt | `sitzungen_gesamt` landet nur in `programs.settings` (ProgramsImport Z. 131). |
| Kursbeschreibung unter dem Kopf | lea-meine-kurse, lea-woche | gleich | `description` in `kurse/show`. |
| "Infos von Lea" im Kurs: drei neueste kursgebundene Beiträge, "2 neu", Punkt für ungelesen | lea-kurs-infos | fehlt | `Post::VISIBILITIES['program']` existiert, erscheint aber nur unter Impulse und "Neu für dich" auf der Startseite. |
| Stand "x von y erledigt" + Knopf "Weitermachen/Los geht es" | lea-kurs-module, lea-club-lektion | gleich | `ProgressTracker::summary`, `kurse/show`. |
| Module mit Lektionen, Zähler je Modul "2/5" | lea-kurs-module, lea-club-lektion | gleich | Schritte-Liste mit "x von y erledigt". |
| Modul mit einer Lektion wird flach als Weg gezeigt (01, 02 ...), "Hier gehts weiter"-Marke, aktiver Schritt mit Ring | lea-kurs-module | vereinfacht | "Jetzt dran" nur für Wochen; Einheiten ohne Nummer und ohne Weiter-Marke. |
| Modulbeschreibung unter dem Modultitel | lea-kurs-module | vereinfacht | `summary` nur auf der Schrittseite, nicht in der Übersicht. |
| Videoanzahl je Lektion in der Zeile | lea-kurs-module, lea-club-lektion | besser | `_einheit-zeile`: Art, Kern, Videos, Fragen, Minuten. |
| Kurs ohne Module zeigt "Nächste Termine" (Coffee & Coaching) | lea-meine-kurse | fehlt | `pacing=none` zeigt nur Einheiten. |
| Abschnitte Live-Termine, Aufzeichnungen, Material zum Kurs auf der Kursseite | lea-club-lektion | fehlt | Nur getrennt unter `/termine` und `/material`. |
| Coach-Stift direkt auf der Kursseite (Titel, Einleitung, Wochenaufgaben mit Art und Tag), Bottom-Sheet | lea-kurs-edit | vereinfacht | Filament `StepsRelationManager` (Titel, Einleitung, Frei ab). Kein Sprung aus der Teilnehmeransicht, Wochenaufgaben nicht am Schritt pflegbar. |
| Elementor-Vorlagenstyles | lea-club-kurs-template-css | bewusst weg | Nur Optik für Elementor-Listings, siehe Designnotizen. |
| Bereiche an/aus (Projekte, Zeitleiste, Community, Ressourcen) ohne Löschen | lea-module | fehlt | Keine Schalter in `Einstellungen`. Für weitere Mandanten sinnvoll. |

### Meine Kurse

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Kursliste der freigeschalteten Programme mit Balken | lea-meine-kurse | gleich | `kurse/index`. |
| Gliederung nach Zugang: Dein Programm, Coffee & Coaching, Im Club, Programme mit Begleitung | lea-meine-kurse | vereinfacht | Eine flache Liste. |
| Grosse Hero-Karte für das laufende Programm: Bild 21:9, Badge "Dein Programm", "Diese Woche: …", Balken, nächster Call, CTA | lea-meine-kurse | vereinfacht | Startseite hat Karte "Diese Woche" ohne Bild und ohne Call. |
| Kurskarten mit Bild 40:21, Badge, Schloss, Hover-Anheben, "Freigeschaltet bis 12. März 2027" | lea-meine-kurse | fehlt | Karten ohne Bild und ohne Zugangsende (`entitlements.ends_at` wäre da). |
| Gesperrte Kurse mit Preis, Kauf- oder Club-Link, "Kommt bald" | lea-meine-kurse | fehlt | `offers` nur im Coach-Bereich. Niedrige Priorität. |
| Leerer Zustand "Gerade läuft kein Programm für dich" + Knopf "Sag mir Bescheid" (Mail an Lea) | lea-meine-kurse | vereinfacht | Leertext ohne Knopf. |
| Öffentliche Kursübersicht mit Gratis-Einstieg | lea-meine-kurse | bewusst weg | Website, nicht App. |

### Woche und Zeitstrahl (Hybrid-Coaching)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Wochen mit Freischaltung | lea-woche, lea-kurs-zeitstrahl | besser | Explizite `program_steps` mit `unlocks_at`, `week_number`; alt wurden Wochen aus Call-Titeln erraten. |
| Karte "Nächster Gruppencall" oben im Kurs: Tag gross, "Heute/Morgen/In 3 Tagen", dunkel wenn heiss, "Zoom öffnen" | lea-kurs-zeitstrahl, lea-woche | fehlt | Kursseite ohne Termine; Startseite zeigt nur den nächsten Termin allgemein. |
| Schnellwege Frage stellen, Reflexion schreiben, Meine Aufgaben | lea-kurs-zeitstrahl | fehlt | |
| Kalender-Abo je Kurs | lea-kurs-zeitstrahl | vereinfacht | Ein Abo für alles im Profil (`kalender.abo`). |
| Schritt-Punkte als Sprungleiste (✓ grün vergangen, Kursfarbe aktuell, blass kommend) | lea-kurs-zeitstrahl, lea-kurs-kopf | vereinfacht | Liste mit Nummernkreis, grün erst bei allem erledigt, Schloss für kommende. |
| Aufklappbare Schrittkarte, aktuelle offen, Tag "Diese Woche" | lea-kurs-zeitstrahl | vereinfacht | "Jetzt dran" in der Liste, Klick führt auf die Schrittseite. |
| Pro Woche: Call-Zeile (vorher Zoom, danach Aufzeichnung, "Aufzeichnung kommt, du bekommst eine Mail") | lea-kurs-zeitstrahl, lea-woche | fehlt | `events.step_id` existiert (EventResource), `kurse/schritt` zeigt nur Einheiten. |
| Pro Woche: Wochenaufgaben von Lea abhaken | lea-kurs-zeitstrahl, lea-wochenaufgaben, lea-woche | fehlt | Aufgaben liegen in `tasks` mit `step_id` (Import), werden im Schritt nicht gezeigt. |
| Pro Woche: Fragentag und Reflexion mit Status "Geschrieben" | lea-kurs-zeitstrahl | fehlt | |
| Eigene Reflexion und eigene Fragen der Woche direkt im Kurs | lea-kurs-zeitstrahl | fehlt | |
| Material der Woche | lea-kurs-zeitstrahl, lea-woche | fehlt | `resourceables` kann `step`, Schrittseite zeigt es nicht. |
| Fortschritt in Schritten "Schritt 3 von 8, diese Woche" | lea-kurs-zeitstrahl | vereinfacht | Fortschritt über erledigte Einheiten. |
| Wochenliste mit Zeitraum "14. bis 20. September", "3 von 5 erledigt", Haken wenn alles | lea-woche | vereinfacht | "ab 14. September", Stand der Einheiten. |
| Call-Chip je Woche: Call steht an, live dabei, Aufzeichnung gesehen, "Aufzeichnung ansehen (weiter ab 12:30)", Aufzeichnung folgt | lea-woche | fehlt | Status liegt in `event_attendees`, wird in der Liste nicht gezeigt. |
| Wochenband horizontal, aktive Woche zentriert, Wischen links/rechts wechselt die Woche | lea-woche | fehlt | Nur Pfeil-Knöpfe in `kurse/schritt`. |
| Knopf "Zur aktuellen Woche 4" | lea-woche | fehlt | |
| "Von Lea" mit Avatar über der Wochen-Einleitung | lea-woche, lea-lektion-seite | fehlt | Einleitung ohne Absender. |
| Reflexionstermin leitet in die Reflexion | lea-kurs-zeitstrahl | fehlt | Termin-Art `reflection_day` öffnet die Terminseite. |
| Coach sieht "Neuer Termin / Neue Ressource" direkt im Kurs | lea-kurs-zeitstrahl | vereinfacht | Filament. |

### Meine Woche, Aufgaben

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Eigene To-dos je Woche, optional "jeden Tag" mit 7 Tagesfeldern "An welchen Tagen hat es geklappt? 3 von 7" | lea-kurs-zeitstrahl, lea-meine-woche | vereinfacht | `tasks.is_daily` + `Task::daysDone()` in `aufgaben/_karte`. Zuordnung nur zum Programm, nicht zur Woche (`AufgabenController::store` ohne `step_id`). |
| Seite Meine Woche: "Von Lea" und "Von dir" getrennt, gruppiert nach Woche | lea-meine-woche | vereinfacht | `/aufgaben` mit Quelle, ohne Wochengruppen. |
| Aufgabe verschieben in andere Woche, täglich umschalten, Text bearbeiten | lea-meine-woche | vereinfacht | Bearbeiten ja, Woche nein. |
| Alte persönliche To-dos übernehmen (`lea_meine_todos`) | lea-meine-woche | fehlt | Import liest die Usermeta nicht. Datenverlust. |
| Lea trägt Wochenaufgaben pro Woche ein, alle sehen und haken sie ab | lea-wochenaufgaben, lea-werkstatt-aufgaben | vereinfacht | Coach-Aufgabe "An alle im Programm" kopiert je Person (`TaskResource`). Ohne Woche im Formular, ohne festen Wochentag (`af_tag`), wer später einsteigt, bekommt sie nicht. |

### Lektion bzw. Einheit

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Kopf mit Kurs und Zurück, "Lektion 3 von 12 · 2 Videos · erledigt" | lea-club-lektion, lea-lektion-seite | gleich | `kurse/einheit`. |
| Band mit allen Lektionen (Nummern, Haken), Pfeile | lea-lektion-seite | vereinfacht | Nur "x von y" und Weiter/Zurück. |
| Videoblock, ab zwei Videos Playlist mit Nummern, aktives hervorgehoben | lea-club-lektion | gleich | `.video-wahl` in `einheit` + `app.js`. |
| Video merkt sich die Position ("Weiter ab 12:30, noch 40:00"), Balken | lea-stand | fehlt | Weder für Einheiten noch Aufzeichnungen noch Podcast. |
| Kapitelliste unter dem Video aus der KI-Zusammenfassung, Klick springt Vimeo an die Stelle | lea-lektion-kapitel | fehlt | Units haben kein Zusammenfassungsfeld; nur Podcast-Folgen haben Kapitel (`impulse/folge`). |
| "Zum Nachlesen, worum es ging" aufklappbar | lea-lektion-kapitel | fehlt | |
| Einleitung und Text | lea-club-lektion, lea-lektion-seite | gleich | `intro`, `body`. |
| Arbeitsblatt als PDF eingebettet, Audio mit Player inline, "Gross ansehen/Herunterladen" | lea-lektion-seite | fehlt | Material an Einheiten (`MaterialResource` → `units`) erscheint in `kurse/einheit` gar nicht. |
| Material als kompakte Zeilen mit Typ-Icon, Dauer, runder Download-Knopf | lea-club-lektion | fehlt | wie oben. |
| "Termin dazu" (an die Lektion gehängter Call) | lea-club-lektion | fehlt | Events kennen nur `step_id`. |
| Aufgaben der Lektion einzeln abhaken, "2 von 4 erledigt" | lea-club-lektion | gleich | Übungsteil `checkbox`, Import aus `lea_todos_`. |
| Seitenspalte "Dein Fortschritt 5 von 12" neben dem Inhalt | lea-club-lektion | vereinfacht | Fortschritt nur auf der Kursseite. |
| Erledigt-Knopf, "Nächste Lektion", vorige Lektion als Link | lea-club-lektion, lea-lektion-seite | gleich | |
| Lektion als Bottom-Sheet aus dem Zeitstrahl | lea-lektion-popup | bewusst weg | Eigene Seite ist in der App klarer, Zurück-Taste funktioniert. |
| Übungen des Kursarbeitsbuchs direkt in der passenden Lektion | lea-lektion-uebung | vereinfacht | Modell kann es (Übungsteile an jeder Einheit, besser). Der Import hängt Arbeitsbuch-Übungen aber als eigene Schritte hinten an und ignoriert die Zuordnung `lektion` (ProgramsImport `importBookSteps`). |
| Notiz zur Lektion | lea-workbook-notizen | gleich | `kurse.notiz`, erscheint unter Notizen. |
| Merken, Themen | (neu) | besser | `x-merken`, Themen-Chips. |

### Workbook und Arbeitsbücher

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Mehrere Arbeitsbücher, an einen Kurs gebunden, Zugang über den Kurs | lea-arbeitsbuecher | besser | Programm-Typ `workbook`, gleicher Zugang, gleiche Freigabe. |
| Autosave beim Tippen (1,2 s) und beim Verlassen, "gespeichert" im Feld | lea-workbook | gleich | `KursController::antwort`, `app.js`. |
| Frage, Notizfeld, Skala 1 bis 10, Werte zum Anklicken mit Zähler, Zwischentitel, Hinweis kursiv | lea-workbook | gleich | `Exercise::TYPES`. |
| Kern- und Vertiefungs-Badge | lea-workbook | gleich | `is_core`, "Kern" in der Zeile. |
| Übersicht mit Ring "62 %", "41 von 88 Feldern · 5 von 9 Kernübungen" | lea-workbook | vereinfacht | Balken der erledigten Einheiten, kein Kern-Zähler. |
| Schrittkarten mit "01", "3 Übungen, davon 2 Kern", Ring je Schritt | lea-workbook | vereinfacht | "x von y erledigt". |
| Übungen blättern (Pillen "Übung 1, 2, 3", Vorige/Nächste) | lea-workbook | gleich | Weiter/Zurück je Einheit. |
| Filter "nur Kern" | lea-workbook | fehlt | Niedrig. |
| Lebendes Lebensrad (Radar aus den Skalen, zeichnet sich live) | lea-workbook | fehlt | |
| Ikigai-Grafik | lea-workbook | vereinfacht | Bild über `body` möglich, Import verwirft `grafik`. |
| Bilder hochladen in einer Übung, privat ausgeliefert | lea-workbook | fehlt | Import verwirft `sub.datei`. |
| Baustein Liste (Zeile für Zeile, neue Zeile entsteht beim Tippen) | lea-workbook-bausteine | fehlt | Import verwirft `liste` samt Antworten. |
| Baustein Liste2 (zwei Spalten Gedanke/Umkehrung) | lea-workbook-bausteine | fehlt | Import verwirft `liste2`. |
| Baustein Spiegel (zeigt, was in einem anderen Schritt steht) | lea-workbook-bausteine | fehlt | |
| Baustein Brieffeld (grosses Dokumentfeld 16 Zeilen) | lea-workbook-bausteine | fehlt | `note` hat 6 Zeilen; Import verwirft `brieffeld` samt Text (Liebesbriefe!). |
| Baustein Aufnahme: Text einsprechen, anhören, Vorlage zum Ablesen | lea-workbook-bausteine | fehlt | Aufnahmelogik gibt es schon im Gespräch (`app.js` MediaRecorder). |
| Übung selbst abhaken "Du musst nicht alles ausfüllen", zählt voll | lea-workbook-erledigt | gleich | Einheit "Als erledigt markieren". Alte Haken (`lea_wb_antworten_erledigt`) werden nicht importiert. |
| Einmal am Anfang: "Darf Lea mitlesen?" alles oder nichts, Ausnahmen je Übung | lea-workbook-freigabe | gleich | `share_mode` + `kurse.teilen`. Alter Modus `lea_wb_freigabe` wird nicht importiert. |
| Teilen je Übung auch in Kursbüchern | lea-kurs-teilen | gleich | Teilen-Knopf je Einheit. |
| Link "Frage an Lea und die anderen" unter der Übung | lea-kurs-teilen | fehlt | |
| Notizen zur Übung als echte Notizen, mehrere, mit Sichtbarkeit, Diktat | lea-workbook-notizen | vereinfacht | Eine private Notiz je Einheit. |
| "Daraus eine Aufgabe machen" (Titel, Datum), Liste der entstandenen Aufgaben, Rückweg zur Übung | lea-workbook-aufgaben | fehlt | `Task::SOURCES['exercise']` vorgesehen, kein Knopf. |
| Fertige KI-Prompts aus der Sammlung mit Kopierknopf, "Es fehlt noch: …", Launch-Datum wird Aufgabe | lea-workbook-prompts | fehlt | Sehr buchspezifisch, als allgemeiner Baustein "Vorlage mit Platzhaltern" machbar. |
| "Das hast du früher schon geschrieben", Übernehmen | lea-workbook-prompts | fehlt | Wie Spiegel, mit Knopf. |
| 21 Tage Liebesbrief-Praxis: Start legt tägliche Aufgabe an, Punkte "Tag 7 von 21", Hinweis an Tag 7 bis 10 | lea-liebesbrief-praxis | fehlt | Import verwirft `praxis`. |
| Coach sieht geteilte Antworten mit Frage, "geteilt am", Fortschrittssatz | lea-workbook | vereinfacht | `Dossier` "Geteilte Antworten" ohne Datum und ohne Kern-Stand. |
| Mikrofon-Diktat (Web Speech, de-CH, pulsierender Knopf) in allen Feldern | lea-kursraum, lea-lektion-uebung, lea-workbook-* | fehlt | `reflexion/index` verspricht im Platzhalter "Schreib oder diktiere …", es gibt aber keinen Knopf. |

### Aufzeichnung, Dabei, Player

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Ein Abspielfenster überall (Kurs, Journal, Ressourcen) ohne Seitenwechsel | lea-player | vereinfacht | Terminseite `termine/show` mit Video. |
| Zusammenfassung mit "(ab 26:38)" als Sprungknöpfe | lea-player | fehlt | Zusammenfassung als reiner Text. |
| "Dazu passend": Material zum Termin | lea-player | gleich | "Material zum Termin". |
| Knopf wird "Nochmal ansehen" + Fortschrittsbalken, wenn angefangen | lea-player, lea-stand | fehlt | |
| Automatisch "gesehen" ab 80 % | lea-dabei | fehlt | Nur Knopf "Gesehen". |
| Live dabei oder Aufzeichnung gesehen gilt als erledigt | lea-dabei | gleich | `event_attendees.status` attended/watched. |
| Teilnahme automatisch aus Zoom, "In Zoom haben wir dich nicht gefunden. Warst du trotzdem dabei?" | lea-dabei | vereinfacht | Nur manueller Knopf "Ich war live dabei". |
| "War ich doch nicht" zurücknehmen | lea-dabei | fehlt | Niedrig. |
| Aufzeichnung eingetragen: Hinweis an den Kurs per Push/Mail | lea-kurs-automatik | gleich | `EventObserver` "Aufzeichnung ist da". |
| Termin-Erinnerung am Tag | lea-kurs-automatik | gleich | `Runden::terminErinnerungen` (alt schon stillgelegt). |
| Kursbeiträge nur für Kursteilnehmerinnen | lea-kurs-automatik | gleich | `Post` visibility `program`. |
| Kopie jeder Kurs-Mail an Lea und Team | lea-kurs-automatik | fehlt | Niedrig. |
| Passwort-Links 14 Tage gültig | lea-kurs-automatik | bewusst weg | Magic Link statt Passwort. |

### Coach-Werkzeuge, Werkstatt

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Wochencheckliste für Andrea: Zoom-Link da, Einleitung da, Aufgaben mit Tag, Reflexionstermin, Aufzeichnung/Zusammenfassung/Freigabe, wer die Reflexion geschrieben hat, offene Fragen, drei manuelle Haken je Kalenderwoche | lea-wochencheck | fehlt | Dashboard hat `UeberblickWidget`, `NeuesWidget`, keine Wochenprüfung. |
| Werkstatt: Ressource hochladen (Datei oder Link, Ordner, Kurs, Woche) | lea-werkstatt, lea-werkstatt-aufgaben | besser | Filament `MaterialResource` (Programme, Einheiten). Woche/Schritt fehlt im Formular. |
| Werkstatt: Wochenaufgabe anlegen (Kurs, Woche, Art, Tag) | lea-werkstatt-aufgaben | vereinfacht | siehe Wochenaufgaben. |
| Nachricht oder Beitrag: Sichtbarkeit, Kanäle, sofort/geplant/Entwurf | lea-werkstatt-aufgaben | gleich | `PostResource` (published_at, visibility, notify_channels), `Rundnachricht`. |
| "Erst nur an mich, zum Anschauen" (Testmail) | lea-werkstatt-aufgaben | vereinfacht | Globaler Testbetrieb in `Einstellungen`, keine Einzel-Testmail. |
| KI-Bild im Stil der Website erzeugen | lea-werkstatt | fehlt | Niedrig; `image_url` nur als URL. |
| Fortschritt aller Teilnehmerinnen eines Programms auf einen Blick | (implizit in Wochencheck) | vereinfacht | `MembersRelationManager` zeigt "Zuletzt da" und Freigabe, keinen Fortschritt; Fortschritt nur je Person im Dossier. |

### Übrige

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Projekte mit Farbe, Icon, neun Prozessschritten als Treppe, Einträge zuordnen, teilen | lea-projekte | fehlt | War im alten System standardmässig ausgeschaltet (`lea_module` projekte=0). Niedrig. |
| Journal-Zeitleiste: alles chronologisch, Monat, klebende Wochenzeile, Art-Icon, Filter, Aufgaben direkt abhaken, "Als Nächstes" | lea-timeline | fehlt | `journal/index` ist ein Hub mit Zählern. Alt standardmässig aus (zeitleiste=0). |
| Selbsttests mit Ampel und Weiterleitung (Gratiskurs, Klarheitsgespräch) | lea-selbsttest | bewusst weg | Öffentlicher Funnel ohne Anmeldung, gehört auf die Website. |

---

## 2. Fehlt und ist wichtig für die Teilnehmerin oder Lea

Nach Wichtigkeit sortiert.

1. **Import verliert Daten.** `ProgramsImport::importBookParts` verwirft `liste`, `liste2`, `brieffeld`, `aufnahme`, `praxis`, `grafik` und `sub.datei` samt Antworten; nicht übernommen werden ausserdem `lea_meine_todos`, `lea_wb_*_erledigt`, `lea_wb_freigabe`, `lea_lb_start` und der CPT `frage` mit Kommentaren. Umsetzung: erst die neuen `Exercise`-Typen aus Punkt 5 anlegen, dann im `match` von `importBookParts` zuordnen, in `BegleitungImport` eine Methode `importMyWeekTodos()` ergänzen (je Eintrag eine `Task` mit `step_id` aus `stepMap`, `is_daily`, `settings.days`), Erledigt-Haken als `Progress` der Einheit übernehmen und `lea_wb_freigabe` auf `program_members.share_mode` abbilden.

2. **Die Schrittseite wird zur Wochenseite.** In `KursController::schritt` zusätzlich laden: Events mit `step_id` (über `Begleitung::eventsQuery`), eigene Tasks mit `step_id`, Material über `resourceables` (step und units des Schritts) und die Reflexion der Woche (`reflections.event_id` bzw. die Woche). In `kurse/schritt.blade.php` oben die Call-Karte (Zoom vorher, Aufzeichnung danach, Dabei-Status), dann Einheiten, Aufgaben zum Abhaken mit einem Feld "Was nimmst du dir diese Woche vor?" (legt eine `Task` mit `step_id` an), Material, Reflexion/Fragentag mit Status.

3. **Wochenaufgaben von Lea als Vorlage statt Kopie.** Heute legt `TaskResource` je Person eine Kopie an, Nachzüglerinnen gehen leer aus. Umsetzung: `TaskResource` bekommt ein Feld Schritt (`step_id`) und einen Wochentag; beim Speichern mit "an alle" zusätzlich ein Listener auf `ProgramMember::created`, der offene Schritt-Aufgaben nachträgt (oder ein eigenes Modell `step_tasks` mit Haken in `tasks`). Test für Mandantentrennung nicht vergessen.

4. **Material in der Einheit.** `Unit` bekommt eine Relation auf `Resource` über `resourceables` (type `unit`), `kurse/einheit.blade.php` zeigt PDFs eingebettet (`<iframe src="…#view=FitH">`, Höhe `min(75vh,760px)`), Audio mit `<audio controls preload="none">`, sonst eine Zeile mit Typ-Icon und rundem Download-Knopf. Auslieferung über die vorhandene Route `material.datei`.

5. **Workbook-Bausteine nachziehen.** `Exercise::TYPES` um `list`, `pairs`, `mirror` (options.quelle = exercise_id), `letter`, `audio`, `images`, `wheel` (Lebensrad aus den Skalen der Einheit) und `practice` (Tage-Zähler) erweitern, `Exercise::ANSWERABLE` anpassen, `KursController::antwort` für Arrays und Dateien erweitern (Uploads nach `storage/app/tenants/{tenant_id}/answers/{user_id}`, privat ausgeliefert), Darstellung als neue `@case` in `kurse/einheit.blade.php`, Bedienung in `public/js/app.js`. Formular dazu im `UnitsRelationManager` (Tab Übungsteile).

6. **Mikrofon-Diktat überall.** Ein generischer Knopf `data-diktat` neben jedem `textarea.feld` in `public/js/app.js` (Web Speech API, `lang` aus `tenants.locale`, z. B. de-CH, Knopf pulsiert beim Zuhören, versteckt ohne Browser-Unterstützung). Die Reflexion verspricht es heute schon im Platzhalter.

7. **Video und Aufzeichnung merken sich die Position.** Neue Tabelle `media_positions` (tenant_id, user_id, key wie `unit-12-0` oder `event-45`, seconds, duration, updated_at, unique tenant_id+user_id+key) mit Modell `MediaPosition` (BelongsToTenant). In `app.js` Vimeo Player API bzw. `<video>`/`<audio>` `timeupdate` alle 10 s posten, beim Laden an die Stelle springen, ab 80 % automatisch `termine.gesehen` bzw. Einheit erledigt. In Listen ein dünner Balken "weiter ab 12:30".

8. **Kapitel und Sprungmarken.** Eine kleine Klasse `App\Support\Kapitel` wandelt "(ab 12:34)" und `<h3>… (ab 4:11)</h3>` in Sprungknöpfe; nutzen in `termine/show` (Zusammenfassung) und, mit neuem Feld `units.summary` (neue Migration), in `kurse/einheit`. Der Summarizer (`app/Ai/Summarizer.php`) kann dasselbe Format für Einheiten liefern.

9. **Fragen an Lea mit Status (Kursraum).** Neues Modell `Question` (tenant_id, program_id nullable, user_id, title, body, status offen/call/beantwortet/besprochen/zu, visibility alle/programm/coach, answered_at) plus Antworten über das vorhandene `Comment`-Modell, Reaktionen über `Reaction` (🎯 "Bitte im Call besprechen" wieder aufnehmen, zählt als Callwunsch). Views `kurse/fragen.blade.php` und `fragen/show`, Folgen über eine Tabelle `question_followers`, Filament `QuestionResource` mit Status-Auswahl und Filter "kommt in den Call". Donnerstags-Lauf in `Runden` (Erinnerung "Heute ist Fragentag" an Programme mit `question_day`-Termin und Sammelmail der offenen Fragen an die Coachin). Import des CPT `frage` samt Kommentaren.

10. **Nächster Call und Call-Status in Kurs und Wochenliste.** `KursController::show` lädt den nächsten Event des Programms und je Schritt den Call mit dem eigenen `EventAttendee`-Status; `kurse/show.blade.php` bekommt oben die dunkle Call-Karte und in jeder Schrittzeile einen Chip (steht an, live dabei, gesehen, ansehen, folgt).

11. **Wochencheckliste für Andrea.** Filament-Widget `app/Filament/Coach/Widgets/WochencheckWidget.php`: für jedes Programm mit `pacing=weekly` den aktuellen und nächsten Schritt prüfen (Zoom-Link am Call, `summary` gesetzt, Aufgaben vorhanden, Reflexionstermin, Aufzeichnung/Zusammenfassung da, wer die Reflexion geschrieben hat, offene Fragen). Manuelle Haken in `tenants.settings.wochencheck.{KW}` speichern, die Texte der Haken ebenfalls in den Settings (nichts Lea-Spezifisches im Code).

12. **"Daraus eine Aufgabe machen" unter Übungen.** Kleines Formular (Titel, freiwilliges Datum) unter dem Teilen-Knopf in `kurse/einheit.blade.php`, neue Route `kurse.einheit.aufgabe` legt `Task` mit `source=exercise`, `unit_id`, `program_id` an; in `aufgaben/_karte` ein Rückweg-Link zur Einheit.

13. **Kursbild und reichere Kurskarten.** `cover_url` in `kurse/show` als Bildband (Titel auf Verlauf) und in `kurse/index` als Bild 40:21 bzw. 21:9 für das laufende Programm ausgeben, dazu "Freigeschaltet bis …" aus `entitlements.ends_at`.

14. **Infos von Lea im Kurs.** In `kurse/show` die drei neuesten Posts mit `visibility=program` und `program_id` des Kurses, ungelesene mit Punkt (Lesestatus über `notifications` oder eine kleine `post_reads`-Tabelle).

15. **Kontingent der 1:1-Sitzungen.** `programs.settings.sitzungen_gesamt` minus Anzahl 1:1-Events der Person im Programm (`events.user_id`), Hinweis mit Link auf die Buchung (URL aus `tenants.settings`).

16. **Fortschritt aller im Programm für Lea.** Spalte "Stand" im `MembersRelationManager` (ProgressTracker::summary je Mitglied), dazu "letzte Reflexion" und "offene Aufgaben".

17. **Leerer Zustand mit "Sag mir Bescheid".** In `kurse/index` ein Knopf, der eine Notiz an die Coachin schickt (Notifier) und am `Membership.settings` merkt, dass Interesse besteht.

18. **Bereiche je Mandant an und aus.** `tenants.settings.bereiche` (community, journal, material, projekte …) prüfen in Navigation und Routen (Middleware), Schalter im `Einstellungen`-Formular.

---

## 3. Designnotizen aus dem alten Code

Die App hat die Grundfarben schon richtig im Seeder (`TenantSeeder`: primary `#B4795F`, text `#2E2D29`, text_soft `#4A473F`, muted `#86816F`, bg `#F6F2EA`, card `#FFFDF8`, border `#E4DFD2`, success `#6E8B74`, Lora für Überschriften). Was das alte Design darüber hinaus schön macht:

### Farben (zusätzlich zu den Grundfarben)

| Rolle | Wert | Wo |
|---|---|---|
| Fläche (Karten zweiter Ebene, Eingaben) | `#FAF8F3` | Kursraum-Karten, Workbook-Kopf, Textareas im Workbook |
| Warme Hinterlegung (Hinweis, Spiegel, Vorlage) | `#F6F2E9` | `.lea-wbs`, `.lea-gold`, `.lea-mk-bald`, PDF-Rahmen |
| Rosé-Hinterlegung (Freigabe-Frage) | `#F3EDE6`, Rand `#E0CDBE` | `.lea-wbf`, aktive Reaktion |
| Chip-Grau | `#EFEDE7` | Mikrofon-Knopf, Badge Vertiefung, Nummernfelder |
| Innere Trennlinie | `#F0EDE4`, `#F1ECE1`, `#F0EBDF` | zwischen Zeilen in Karten |
| Hover-Fläche | `#F5F1E7`, `#F2EDE1`, aktiv `#EFE7D8` | Lektionszeilen, Playlist |
| Leise Icons und Pfeile | `#C9C3B5`, `#C6C0AE`, `#C9C2B2` | Chevrons, Löschen-X |
| Rahmen für ruhige Knöpfe | `#D8D2C4` | `.btn.ruhig`, Eingaben in Formularen |
| Sekundärtext hell | `#A9A395`, `#B0AB9C` | Datum rechts, "Du musst nicht alles ausfüllen" |
| Akzent Hover | `#9C6650` | Knopf-Hover |
| Dunkle Karte | `#2E2D29` mit Text `#FAF8F3`, Unterzeile `#C9C3B5`/`#D9D4C7` | "Nächster Call", Coffee & Coaching, dunkle Knöpfe |
| Dunkelrot "jetzt" / Stopp | `#8C3B2E` | Aufnahme-Stopp, heutiger Praxis-Tag |
| Braun Icon-Kreis | `#8C6A4F` | Coffee & Coaching |
| Salbei hell (Vorschlag) | Fläche `#F3F5F1`, Rand `#DCE5DD`, Text `#4F7357` | "Das hast du früher schon geschrieben" |
| Ampel gelb | `#C9A227` | Selbsttest, Warnungen Wochencheck |
| Weitere Kursfarben | `#6E8B74`, `#7C8C9A`, `#B8607A`, `#8C6A4F`, `#5F6F7F` | Vorgaben je Kurs |

Farbtöne werden oft über `color-mix(in srgb, var(--c) 14%, transparent)` bzw. `rgba(var(--kurs-rgb), .06 bis .14)` abgeleitet (Status-Chips, aktiver Teil, Erwähnung). Die App nutzt `color-mix` schon, das passt.

### Radien

- 22px: Hero-Karte, Bildband, Bottom-Sheet oben (`22px 22px 0 0`), Selbsttest-Karte
- 18px: grosse Abschnitte (Schrittkarte Zeitstrahl, "Nächster Call", Fragenformular, Club-Wahl)
- 16px: Standardkarte (App: `radius 16` passt)
- 14px: kleinere Karten, Videorahmen, Playlist, Infos-Karten
- 12px: Eingabefelder, Material-Rahmen, Hinweiskästen
- 10px: Zeilen mit Hover, Wert-Knöpfe klein
- 9px: Skalen-Knöpfe 32x32; 7px: eigene Checkbox 24x24; 8px: Wochentags-Knöpfe 34x30
- 999px: alle Knöpfe, Chips, Filter, Badges

### Schatten und Ringe

- Aktiver Schritt/aktive Lektion: kein Schatten, sondern Ring `box-shadow: 0 0 0 3px rgba(180,121,95,.12)` (bzw. `.14`) plus Rahmen in Kursfarbe. Das ist das Markenzeichen für "hier bist du".
- Karten-Hover: `transform: translateY(-2px); box-shadow: 0 8px 20px rgba(46,45,41,.08)`, Übergang `.15s`.
- Aufklappmenü: `0 8px 24px rgba(46,45,41,.12)`.
- Schwebende Karte (Selbsttest): `0 2px 30px rgba(50,49,45,.07)`.
- Text auf Bild: `text-shadow: 0 1px 8px rgba(0,0,0,.35)`, Verlauf `linear-gradient(180deg, rgba(0,0,0,0) 30%, rgba(30,28,24,.72) 100%)`.
- Sonst flach: Rahmen 1px `#E4DFD2` statt Schatten.

### Schrift

- Überschriften Lora, Gewicht 400 (Karten) bis 500 (Seitentitel). Grössen: Seitentitel 36px (Tablet 30, Handy 25 bis 26), Bildband 30px/25px, Abschnitt 24px, Karte 19 bis 21px, Listentitel 17px, Fragenkarte 17px. Zeilenhöhe 1.15 bis 1.3.
- Fliesstext 15.5 bis 18px, Zeilenhöhe 1.55 bis 1.65, Lesebreite `max-width: 62ch` bis `68ch`.
- Kicker/Label: 11 bis 12px, `text-transform: uppercase`, `letter-spacing: .1em` bis `.16em`, Gewicht 600 bis 700, oft in Akzentfarbe (`Schritt 03`, `Diese Woche · Woche 4`).
- Meta: 12.5 bis 13.5px in `#86816F`, Trennpunkt `·` mit `opacity: .45`.
- Zahlen und Zeiten mit `font-variant-numeric: tabular-nums` (Kapitel, Zähler "2/5", Aufnahmezeit).
- Grosse Zahl im Stand: 22 bis 26px, Gewicht 600, daneben "von 12 Lektionen" 14px leise.
- Einleitungen und Hinweise im Workbook: Lora kursiv 15 bis 16px in `#6E6B63`, mit linkem Strich `2px solid #E4DFD2` und `padding-left: 14px`.
- Eingaben immer 16px (kein iOS-Zoom).

### Karten und Muster

- Karte: Fläche `#FFFFFF` oder `#FAF8F3`, Rahmen `#E4DFD2`, Radius 16, Polster 14 bis 20px.
- Nummernpunkt: Kreis 30 bis 38px, Rand 2px `#E4DFD2`, Ziffer 13px fett `#86816F`; erledigt grün gefüllt mit ✓, aktuell Kursfarbe gefüllt, kommend `opacity: .55`. Workbook-Nummer als Quadrat 42px, Radius 12, Lora 17px in Akzent.
- Fortschritt: Balken 7 bis 8px hoch, Spur `#E4DFD2` oder `#F1ECE1`, Füllung Kursfarbe; im Workbook SVG-Ring 44px (gross 64px), Strich 4px, Spur `#E4DFD2`, Füllung `#B4795F`, `stroke-linecap: round`, Prozentzahl mittig 12px.
- Status-Chip: `color-mix(--c 14%)` als Fläche, Text in der Farbe, 11.5px, Grossbuchstaben, Icon davor.
- Knöpfe: gefüllt Akzent, ruhig weiss mit Rahmen `#D8D2C4`, dunkel `#2E2D29`; Mindesthöhe 38 (in Zeilen) bis 46px, Polster `12px 22px`, Schrift 14 bis 15px, 600.
- Teilen-Knopf: Schloss-Icon `#C9C2B2` wenn privat, Auge in Akzent wenn geteilt, Fläche `#F6F2E9`.
- Leerer Zustand: gestrichelter Rahmen `1px dashed #E4DFD2`, Fläche `#FAF8F3`, Text leise 14.5px.
- Reaktions-Pille: weiss, Rahmen, 32px hoch, Emoji 15px, Zähler 12.5px fett; aktiv Fläche `#F3EDE6`, Rahmen Akzent.
- Lea-Antwort: Hinterlegung `rgba(Kursfarbe,.06)`, Radius 14, kleines Badge "LEA" in Akzent 10.5px.
- Aufgaben-Haken: runder 26px Kreis mit 2px Rand, erledigt grün gefüllt, Text durchgestrichen `#86816F`; Lektions-Checkbox eckig 24px Radius 7, gefüllt in Akzent.
- Tagesfelder bei täglichen Aufgaben: 7 Knöpfe 34x30, Radius 8, an = grün.
- Datumsblock: Tag in Lora 24 bis 30px, darunter Monat 11 bis 12px uppercase leise, Breite 52 bis 64px.

### Icons

Font Awesome solid, klein (11 bis 19px), fast immer in Akzentfarbe: `circle-play` (Aufzeichnung), `video` (Call), `book-open` (Lektion), `list-check` / `clipboard-check` (Aufgaben), `pen-to-square` (Reflexion), `comments` (Frage), `feather` (Notiz), `folder-open` (Material), `file-pdf`, `headphones`, `lock` / `eye` (Freigabe), `seedling` (bald, leerer Zustand), `mug-hot`, `bell`, `rotate` (täglich), `arrow-right` / `chevron-right` in `#C9C3B5`. Die App nutzt Inline-SVG mit Strich 1.8; gleiche Motive wählen und die Icons in Akzentfarbe statt `text-muted` setzen.

### Animationen

- Bottom-Sheet: `transform: translateY(103%)` nach `none`, `.3s cubic-bezier(.4,0,.2,1)`; Schleier `rgba(46,45,41,.5)` blendet in `.25s` ein; ab 900px mittig als Dialog mit Fade und `translate(-50%,-42%)` nach `-50%`, Radius 20, `max-height: 86vh` bis `88vh`; `body` ohne Scroll solange offen.
- Mikrofon aktiv: Hintergrund Akzent, `@keyframes puls {50% {transform: scale(1.1)}}` 1s endlos.
- Karte erscheint: `opacity 0, translateY(10px)` nach sichtbar in `.35s ease`.
- Option-Hover: `translateX(3px)`; Karten-Hover `translateY(-2px)`.
- Aufklappen: Pfeil `rotate(180deg)` in `.2s`; "›" vor Details dreht `90deg`.
- Balkenbreite `transition: width .3s`; Checkbox `.14s`; Zeilen-Hover `background .14s`.

### Mobil

- Umbruch bei 640px (teils 600px): Knöpfe werden `width: 100%`, Raster einspaltig, Kartenkopf mit Status-Chip rutscht unter den Titel, Hero-Bild 16:9 statt 21:9, Titel 21 bis 25px.
- Bei 980px wandert die Seitenspalte (Aufgaben, Fortschritt) über den Inhalt (`order: -1`, nicht mehr sticky).
- Bottom-Sheets mit `padding-bottom: calc(24px + env(safe-area-inset-bottom))`, `max-height: 92vh`, scrollbar.
- Wochenband: horizontal scrollbar, aktive Woche beim Laden mittig (`scrollLeft = offsetLeft - clientWidth/2 + width/2`), Wischen über 70px wechselt die Woche.
- Treffflächen mindestens 44px (Knöpfe 44 bis 46px, Zeilen 54 bis 56px).
- In der App-Hülle reicht das Bildband randlos bis an den Rand (`margin: -1px -18px 18px`, Radius nur unten `0 0 22px 22px`).
