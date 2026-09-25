# Abgleich Begleitung und Coach-Werkzeuge: WordPress (alt) gegen Laravel-App (neu)

Stand: 25.09.2026. Gelesen: alle 33 genannten `lea-*.php` vollständig. Gegengeprüft in `app/`, `resources/views`, `routes/web.php`, `app/Filament/Coach`, `app/Import/WordPress`, `app/Notifications`.

Kurzbild: Die neue App hat das Fundament sauber (Personen, Dossier, Termine, Aufgaben, Notizen, Reflexion, Material, Merkliste, Rundnachricht, Erinnerungen, KI-Zusammenfassung von Aufzeichnungen, Kalender-Abo). Es fehlen vor allem die **Arbeitswerkzeuge der Coachin im Alltag** (wer wartet, Ampel, eigene Notizen zu einer Person, Kontingent, Terminvorschlag, KI-Vorbereitung) und das **Zurückschreiben auf Geteiltes** (Kommentare). Die Teilnehmerin vermisst am meisten das Diktieren, die Wochenaufgaben mit Aktion und das gemeinsame 1:1-Journal.

---

## 1. Tabelle

### Coachees, Suche, Dossier (lea-coachees, lea-coachee-suche, lea-coach-kurs)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Liste aller begleiteten Menschen (Kurs oder Chat), Arbeitskonten ausgenommen | lea-coachees | vereinfacht | `Filament/Coach/Resources/Memberships` (Personen): alle Mitgliedschaften mit Filter Rolle/Status. Fehlt: Trennung "in Begleitung" gegen "weitere Kontakte" (eingeklappt) |
| Karte je Person: Avatar, nächster Termin, Sitzungen offen, Aufgaben x von y, zuletzt da | lea-coachees | fehlt | `MembershipsTable` zeigt Name, Mail, Telefon, Rolle, Status, Dabei seit. Kein Termin, keine Aufgabenquote, kein "zuletzt da" in der Liste |
| Marke "wartet" (letzte Nachricht von ihr, ungelesen), Wartende immer oben, Kopfzeile "x warten auf deine Antwort" | lea-coachees | fehlt | Nur Gesamtzahl "Ungelesene Nachrichten" in `Widgets/UeberblickWidget.php` |
| Sortierung nach nächstem Termin, zuletzt aktiv, Name | lea-coachees | vereinfacht | Nur Name und "Dabei seit" sortierbar |
| Marken "Test" und "Kontakt" | lea-coachees | fehlt | Testbetrieb nur global in `Pages/Einstellungen.php` |
| Spur "zuletzt da" (alle 5 Min.) | lea-coachees | gleich | `memberships.last_seen_at`, im Dossier und in `MembersRelationManager` |
| Dossier-Kopf mit Avatar, "zuletzt hier", Herkunft ("kam über ...") | lea-coachees | vereinfacht | `resources/views/filament/coach/dossier.blade.php` Abschnitt Person. Ohne Avatar, ohne Herkunft |
| Kontaktknöpfe Mail, WhatsApp, Anrufen mit vorausgefüllter Anrede "Liebe Vorname," und Umwandlung 079 in 4179 | lea-coachees | vereinfacht | `Memberships/Pages/Dossier.php` Header-Actions. Ohne Anrede. **Fehler:** `wa.me/` bekommt nur die Ziffern, aus 079 457 23 87 wird `0794572387`, der Link geht ins Leere |
| Gebuchte Pakete mit "seit Monat Jahr" und "x von y Sitzungen offen" (inkl. dazugekaufter) | lea-coachees, lea-begleitung | fehlt | `sitzungen_gesamt` wird in `ProgramsImport` nur in die Programm-Einstellungen geschrieben, nirgends berechnet oder angezeigt |
| Kennzahlen im Dossier: nächster Termin, Wochenaufgaben, Calls dabei oder angeschaut, "Sie schreibt" (30 Tage, davon freigegeben) | lea-coachees, lea-coach-kurs | vereinfacht | Dossier zeigt Programme mit Balken (done/total). Keine Call-Quote, keine Schreib-Aktivität |
| Dossier mit Reitern (Gespräch, Termine, Kurs, Aufgaben, Freigegeben, Projekte, Notizen, Workbook, Vorbereitung, Zahlen) | lea-coachees | vereinfacht | Eine lange Seite mit Abschnitten, keine Reiter |
| Gespräch direkt im Dossier | lea-coachees | vereinfacht | Knopf "Gespräch" führt nach `/gespraech/{id}` (Frontend) |
| Reiter Termine: "Kommt" und "War", 1:1-Marke, Aufzeichnung, "live dabei · 47 Min" (Zoom-Abgleich), "Aufzeichnung gesehen", abgemeldet | lea-coachees | vereinfacht | Dossier "Termine" mit Status (eingeladen, abgesagt, live dabei, gesehen). Ohne Trennung kommend/vergangen, ohne Zoom-Minuten |
| "Was sie vorab geschrieben hat" (Vorbereitungsfragen beim Buchen), "selbst gebucht am ..." | lea-coachees | fehlt | Keine Buchung in der App |
| KI-Zusammenfassung der 1:1-Sitzung aufklappbar im Dossier | lea-coachees | vereinfacht | `events.summary`, sichtbar auf der Terminseite und in `EditEvent`, im Dossier nicht |
| Zeiten vorschlagen: freie Blöcke ankreuzen, eigene Zeit, Satz dazu, geht in den Chat | lea-coachees | fehlt | |
| Termin für die Coachee eintragen | lea-coachees, lea-terminvorschlag | vereinfacht | `EventResource` mit Art 1:1 und Person. Nicht aus dem Dossier heraus, keine Zeile im Chat, keine sofortige Nachricht an sie |
| "Meine Notizen zu ihr": private Notizen der Coachin je Person, mit Diktat | lea-coachees | fehlt | Nichts Vergleichbares, alte Notizen (`lea_co2_notizen`) werden nicht importiert |
| KI-Vorbereitung auf ein Gespräch (Wo sie steht, Das fällt auf, Fragen, Woran denken), 3 Tage gemerkt, "Neu erstellen" | lea-coachees, lea-coach-kurs | fehlt | `App\Ai\Anthropic` ist da, wird nur für Termine, Podcast, Themen genutzt |
| Nachricht an mehrere: alle, ein Kurs oder Einzelne, landet im persönlichen Chat jeder Person, Diktat | lea-coachees | vereinfacht | `Pages/Rundnachricht.php`: alle oder ein Programm, Push/Mail, optional ins Gruppengespräch. Fehlt: Einzelne wählen, Zustellung in den 1:1-Chat |
| Nachfassen: ungelesene 1:1-Nachricht nach 20 Min. per Mail | lea-coachees | gleich | `Notifications/Runden.php::nachfassen` |
| Menschen suchen über alle Konten, mit Einordnung (in Begleitung, im Kurs, x Sitzungen offen, Gespräch, nur Newsletter, nur Konto) | lea-coachee-suche | vereinfacht | Tabellensuche Name/Mail. Ohne Einordnungsmarken; wer keine Mitgliedschaft hat, ist nicht auffindbar |
| Reiter Kurs: Woche für Woche aufklappbar, aktuelle Woche offen, Call-Stand je Woche, Wochenaufgaben mit Haken, Fragen im Kursraum mit Antwortzahl | lea-coach-kurs | vereinfacht | Dossier "Programme": nur Gesamtfortschritt und Freigabemodus |
| Einzelbegleitung: Sitzungen als Verlauf mit Stand | lea-coach-kurs | vereinfacht | Dossier "Termine" |
| Ampel über alle: rot/gelb/grün mit Grund (wartet, seit x Tagen nicht da, war noch nie da, Calls verpasst, Aufgaben offen), sortiert nach Dringlichkeit | lea-coach-kurs | fehlt | |
| "kurz nachfragen": Chat öffnet mit vorbereitetem Entwurf passend zum Grund | lea-coach-kurs | fehlt | |
| Block "Für dich freigegeben" mit "x neu" seit dem letzten Blick | lea-coach-kurs | besser | `Widgets/NeuesWidget.php` sammelt geteilte Antworten, Reflexionen, Erledigtes, Absagen, Nachrichten der letzten 7 Tage. Fehlt nur der "neu seit zuletzt"-Zähler |
| Reiter Aufgaben: Kursaufgaben je Woche (offen, überfällig, erledigt), eigene freigegebene Aufgaben, Zahl der privaten | lea-coach-kurs | vereinfacht | Dossier "Aufgaben" (30 Stück). **Datenschutz:** zeigt auch Titel privater Aufgaben ("· privat"); alt blieb Privates privat und wurde nur gezählt |
| Reiter Freigegeben: Reflexionen mit Kommentarfuss, geteilte Notizen, Fragen im Kursraum, "x hat sie für sich behalten" | lea-coach-kurs | vereinfacht | Dossier zeigt geteilte Reflexionen, Notizen und Übungsantworten. Fehlt: darauf antworten (Modell `Comment` existiert, keine Oberfläche), Zahl der privaten |

### Coaching zu zweit und 1:1-Journal (lea-coaching, lea-begleitung)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Nächster Termin mit "Jetzt", "Heute", "In 3 Tagen" und Zoom-Knopf | lea-coaching | vereinfacht | `home.blade.php` Karte "Nächster Termin", ohne Countdown-Label |
| Kontingent "3 von 5 Sitzungen offen" mit Balken, "Termin buchen", "Weitere Sitzungen anfragen", "Erstgespräch buchen" | lea-coaching, lea-begleitung | fehlt | |
| Persönlicher Strang mit der Coachin | lea-coaching | besser | `/gespraech`: Text, Sprachnachricht, Anhang, Reaktionen, Push/Telegram |
| "Eure Sitzungen" nummeriert, KI-Auszug, Aufgaben aus der Sitzung | lea-coaching | vereinfacht | Terminliste und `termine/show.blade.php` mit Zusammenfassung und "Als Aufgabe übernehmen"; keine nummerierte Sitzungsliste |
| Einzelbegleitung als eigener Kurstyp mit Sitzungskontingent | lea-begleitung | fehlt | Programmtyp `one_on_one` existiert, aber ohne Kontingent |
| Gemeinsames Journal: beide schreiben chronologisch (Notiz, Reflexion, Aufgabe und Material nur Coachin), Termine und Aufzeichnungen erscheinen automatisch im Strang | lea-begleitung | fehlt | Alte Einträge landen als `journal_entries` nur lesend unter "Frühere Einträge" (`journal/index.blade.php`) |
| Foto oder Handschrift anhängen, Link | lea-begleitung | fehlt | nur im Chat möglich |
| Einander kommentieren, Mail an die Coachin bei Antwort | lea-begleitung | fehlt | `comments`-Tabelle wird importiert, nicht angezeigt |
| Projekte der Teilnehmerin (Name, Farbe, Symbol), "steht bei Schritt ..." | lea-begleitung | fehlt | nur `settings.projekt` im Import |
| Neun Prozessschritte als farbige Chips (Intention bis Es trägt), Filter nach Projekt und Schritt, Erklärfenster | lea-begleitung | fehlt | |
| Tägliche Aufgabe "An welchen Tagen hat es geklappt? 4 von 7" | lea-begleitung, lea-aufgaben | gleich | `Task::is_daily`, Route `aufgaben.tag`, `aufgaben/_karte.blade.php` |
| Journal-Aufgaben unter "Meine Aufgaben", Journal-Reflexionen unter "Reflexion", Journal-Material unter "Ressourcen" | lea-begleitung | vereinfacht | Aufgaben der Coachin stehen direkt in den Aufgaben, direkt geteiltes Material im Material; Reflexionen aus dem Journal gibt es nicht mehr |

### Profil und Teilnehmerliste (lea-teilnehmerprofil)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Eigenes Profilbild hochladen (ersetzt Gravatar überall) | lea-teilnehmerprofil | fehlt | `users.avatar_path` vorhanden, keine Oberfläche |
| Kurzbio, Telefon, Website | lea-teilnehmerprofil | vereinfacht | `profil.blade.php`: Name und Handynummer |
| Schalter: in der Teilnehmerliste erscheinen, Mail zeigen, Telefon zeigen | lea-teilnehmerprofil | fehlt | |
| Teilnehmerliste je Kurs (Pillen, Coach-Marke, "du", "3 weitere ohne Profil") | lea-teilnehmerprofil | fehlt | |

### Aufgaben (lea-aufgaben, lea-aufgaben-plus)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Eigene Aufgabe: Titel, Notiz, bis, Uhrzeit, täglich, Kurs, Freigabe (nur ich, Coachin, Kurs), Suche, erledigt eingeklappt | lea-aufgaben | gleich | `AufgabenController`, `aufgaben/index.blade.php`. Anpinnen wird gespeichert, im Formular fehlt aber das Häkchen |
| Anhänge und Verweise auf andere Einträge | lea-aufgaben | fehlt | |
| Projekt zuordnen | lea-aufgaben | fehlt | |
| Freigegebenes erscheint im Chat mit der Coachin | lea-aufgaben | fehlt | |
| Reaktionen und Kommentare unter der Aufgabe | lea-aufgaben | fehlt | `Task::comments()/reactions()` ohne Oberfläche |
| Aufgabe der Coachin an alle im Kurs, jede hakt für sich ab | lea-aufgaben | gleich | `Tasks/Pages/CreateTask.php` legt je Teilnehmerin eine Aufgabe an |
| Aufgaben geben im Coach-Bereich mit Filter Person, Programm, Quelle, Erledigt | (neu) | besser | `Filament/Coach/Resources/Tasks/TaskResource.php` |
| Wochenaufgaben mit Art (abhaken, Notiz, Reflexion, Frage, Aufzeichnung ansehen, Termin buchen); der Knopf öffnet das passende Formular, Speichern hakt ab | lea-aufgaben-plus | fehlt | `tasks` hat keine Art; Übungen in Einheiten decken einen Teil ab |
| Wochentag als Fälligkeit in der Kurswoche, "heute dran", "morgen dran", "seit Montag offen" | lea-aufgaben-plus | vereinfacht | Datum und rotes "bis ..." bei Überfälligkeit |
| Fragentag und Wochenreflexion als automatische Aufgaben, "Diese Woche keine Frage" | lea-aufgaben-plus | fehlt | Reflexions- und Fragentage gibt es als Termin (`Event::ALL_DAY_TYPES`), aber nicht als Aufgabe |
| "Aus deinem Kurs" oben in Meine Aufgaben, dazu "Noch offen aus früheren Wochen" inkl. verpasster Calls | lea-aufgaben-plus | vereinfacht | Coach-Aufgaben stehen in der Liste; kein Wochenblock, kein Rückstand |
| Erinnerung 9 Uhr Push und Mail, 18 Uhr nur Offenes, Lea und Team lesen bei Kursaufgaben per Bcc mit | lea-aufgaben-plus | vereinfacht | `Runden::aufgabenHinweis` 8 und 18 Uhr, nur Push/Telegram, keine Mail, kein Mitlesen |
| Aufgaben mit Uhrzeit: Push genau zur Zeit | lea-aufgaben-plus | fehlt | `due_time` wird gespeichert, kein Lauf nutzt es |
| Erinnerungen im Profil abschaltbar | lea-aufgaben-plus | gleich | `profil.benachrichtigungen` |

### Notizen, Reflexion, Journal (lea-notizen, lea-reflexion, lea-reflexion-rueckblick, lea-journal-start)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Notiz mit Titel, Kurs, Freigabe, Pin, Suche | lea-notizen | gleich | `NotizenController`, `notizen/index.blade.php` |
| Formatierung (fett, kursiv, Überschrift, Listen, Zitat), Einfügen aus Word wird gesäubert | lea-notizen | fehlt | reiner Text |
| Diktieren per Mikrofon (Web Speech, de-CH) | lea-notizen, lea-reflexion, lea-fundus | fehlt | nur Platzhalter "Schreib oder diktiere" in der Reflexion |
| Foto und Link anhängen | lea-notizen | fehlt | |
| "Aus deiner Begleitung" unter den Notizen | lea-notizen | fehlt | |
| Freigabe "In der Community" | lea-notizen | vereinfacht | Wert `all`/`program` wird gespeichert, aber keine Ansicht zeigt es den anderen |
| Wochenreflexion: drei Fragen mit Symbol und Tipp | lea-reflexion | gleich | `ReflexionController::FRAGEN` |
| Angefangene Reflexion weiterschreiben | lea-reflexion | gleich | |
| "Für welche Kurswoche?" (Mo und Di schlägt die vergangene Woche vor) | lea-reflexion | fehlt | immer die laufende Woche |
| Mail an die Coachin, sobald geteilt | lea-reflexion | fehlt | kein Observer; nur im Dashboard-Widget sichtbar |
| Nachtrag an geteilte Reflexion | lea-reflexion | gleich | Route `reflexion.nachtrag` |
| Teilen zurücknehmen | (neu) | besser | "Nicht mehr teilen" |
| Suche in den Reflexionen (auch in den Antwortfeldern) | lea-reflexion | fehlt | |
| Rückblick über dem Formular: "Was hattest du dir diese Woche vorgenommen?" mit Stand (Kurs und eigene, täglich x von 7) | lea-reflexion-rueckblick | fehlt | |
| Journal-Einstieg mit drei Karten und Zahlen | lea-journal-start | gleich | `journal/index.blade.php` |

### Termine, Kalender, Terminseite (lea-termine, lea-termin-edit, lea-termin-anhaenge, lea-termin-erinnerung, lea-terminvorschlag, lea-kalender, lea-club-termin)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Terminliste mit Monatsüberschrift, Tageskachel, "Läuft gerade", Zoom oder Ansehen | lea-termine | gleich | `termine/index.blade.php` |
| Filter Zeit und Kurs | lea-termine | gleich | |
| Filter "Was" (Termine, Aufgaben) und Suche | lea-termine | fehlt | |
| Aufgaben mit Datum stehen im Terminkalender | lea-termine | fehlt | |
| Für einen Gruppencall abmelden, "Doch dabei" | lea-termine | gleich | Route `termine.dabei` |
| Wer fehlt: Teilnehmerinnen sehen Kürzel, die Coachin die Namen | lea-termine | vereinfacht | `termine/show.blade.php` zeigt allen die Vornamen (weniger Schutz); Coach-Bereich zählt Absagen |
| Knopf "Termin mit Lea buchen", 1:1 umbuchen oder absagen | lea-termine | fehlt | |
| Termin anlegen und bearbeiten | lea-termin-edit | besser | `EventResource`: Art, Programm, Woche, Person, ganztägig, Aufzeichnung, Abschrift, Themen, KI-Zusammenfassung |
| Material anlegen (Datei oder Link, Ordner, an eine Woche hängen) | lea-termin-edit | besser | `MaterialResource`: an Programme, Einheiten, Termine oder direkt an Personen |
| Vorgaben beim Anlegen (20 Uhr, 90 Min., fester Zoom-Link) | lea-termin-edit | fehlt | kleiner Komfort |
| Beim Buchen etwas mitgeben (Foto, Datei, Notiz aus der App), im Dossier "Mitgegeben" | lea-termin-anhaenge | fehlt | keine Buchung |
| Erinnerung am Tag um 9 Uhr und 1 Std. vorher, Push sonst Mail | lea-termin-erinnerung | gleich | `Runden::terminErinnerungen`, alle 10 Min. |
| Coachin wird bei 1:1 ebenfalls erinnert | lea-termin-erinnerung | fehlt | `EventObserver::recipients` liefert nur die Person |
| Vorgeschlagene Zeit im Chat antippen = gebucht, Bestätigungszeile in beide Richtungen, Nachricht an die andere Seite | lea-terminvorschlag | fehlt | |
| Persönliches Kalender-Abo (ICS), Link nur für dich | lea-kalender | gleich | `KalenderController::abo`, Profil "Termine im eigenen Kalender" |
| Abo nur für einen Kurs | lea-kalender | fehlt | |
| Abo-Fenster Apple, Google, Outlook, Link kopieren (unten aufgleitendes Blatt) | lea-kalender | vereinfacht | webcal-Knopf und "Link kopieren", kein Google-Weg |
| Einzeltermin in den Kalender (Google- und Outlook-Link, ICS) | lea-kalender, lea-club-termin | vereinfacht | nur .ics |
| Erinnerung 15 Min. vorher im Kalenderabo (VALARM) | lea-kalender | fehlt | `Support/Ics.php` ohne VALARM |
| Status-Pille "Heute", "Morgen", "In 3 Tagen", "Läuft gerade", "Aufzeichnung verfügbar" | lea-club-termin | vereinfacht | nur "Läuft gerade" |
| Faktenkarte Wann, Wo, Länge, Zugang | lea-club-termin | vereinfacht | Zeile unter dem Titel |
| Aufzeichnung eingebettet (Vimeo, YouTube) | lea-club-termin | gleich | `Support\Video::embed` |
| "Die Aufzeichnung wird vorbereitet" | lea-club-termin | gleich | |
| Zusammenfassung mit anklickbaren Zeitmarken "(ab 38:00)", springt im Video an die Stelle | lea-club-termin | vereinfacht | Zusammenfassung als Text, ohne Sprünge |
| Material zum Termin | lea-club-termin | gleich | |
| Öffentliche Gratis-Termine, Sperrkasten "Platz sichern", Weg zum Klarheitsgespräch | lea-club-termin | bewusst weg | Verkauf und Website, gehört nach leawernli.ch |

### Material, Ressourcen (lea-ressourcen-v2, lea-ressourcen-v3, lea-ressourcen-teilen, lea-club-ressourcen, lea-club-ressourcen-karte)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Ein Regal: Kursmaterial, Aufzeichnungen, Material aus der Begleitung | lea-ressourcen-v3 | gleich | `MaterialController`, `material/index.blade.php` |
| Filterpillen je Kurs, Aufzeichnungen, Gemerkt (mit Zahl), Suche | lea-ressourcen-v3 | gleich | Kurspillen ohne Kursfarbe und Symbol |
| Filter nach Art mit Zählern, nach Herkunft, Sortierung Titel A bis Z | lea-ressourcen-v2 | fehlt | |
| Abspielfenster in der App für Aufzeichnungen und Videos, Direktlink `?zeige=` startet sofort | lea-ressourcen-v3 | vereinfacht | Aufzeichnungen auf der Terminseite eingebettet, Material öffnet im neuen Tab |
| "Als angeschaut markieren", grünes Häkchen in der Liste | lea-ressourcen-v2, -v3 | vereinfacht | "Gesehen" nur bei Terminen, nicht bei Material, kein Häkchen in der Liste |
| Merken und Teilen im Abspielfenster und auf Ressourcen-Seiten | lea-ressourcen-teilen | vereinfacht | Merken ja (`/merken`), Teilen fehlt |
| Ordner je Kurs, Sichtbarkeit nach gebuchtem Kurs | lea-club-ressourcen | besser | polymorphe Zuordnung `resourceables` (Programm, Schritt, Einheit, Termin, Person) |
| Einzelseite einer Ressource (Typ-Badge, Dauer, Dateiname, Text, "Gehört zu") | lea-club-ressourcen | fehlt | |
| Karten mit Vorschaubild oder Farbfläche, Typ-Symbol und Dateiendung, Typ-Badge | lea-club-ressourcen-karte | vereinfacht | Liste mit Typ-Kürzel als Text; `resources.image_url` wird nicht angezeigt |
| Alte To-do-Übersicht "Meine offenen Schritte" | lea-ressourcen-v2 | bewusst weg | durch Aufgaben abgelöst |

### Zahlen, Auskunft, Startseite der Coachin (lea-auswertung, lea-zahlen, lea-steuerpult, lea-start-lea)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Auswertung: Einnahmen nach Angebotsart, Anteil passiv, Ausgaben aus bexio plus eigene, Gewinn, Jahresziele, Richtwerte-Ampel, Jahres- und Monatsgrafik, KI-Blick von aussen | lea-auswertung | fehlt | Geschäftsauswertung, kein Coaching; bexio-Anbindung fehlt ganz |
| Zahlen-Kacheln über der Coachee-Liste (Umsatz Jahr, Monat, offen) | lea-zahlen | fehlt | |
| Finanzreiter im Dossier (Umsatz, Rechnungen mit PDF und Zahllink, Rang, Hinweissatz "seit einem Jahr keine Rechnung") | lea-zahlen | fehlt | |
| Steuerpult: Frage in Worten ("Ist Ankes Rechnung verschickt?"), Fakten aus App, Shop, bexio, KI macht einen Satz, Dossier-Link | lea-steuerpult | fehlt | |
| Startseite als Arbeitsliste: wartet auf Antwort (mit "als gelesen"), mit dir geteilt seit dem letzten Blick, Fragen ohne Antwort, als Nächstes (grosser nächster Termin mit Zoom), "Alles ruhig: ..." in einem Satz | lea-start-lea | vereinfacht | Coach-Dashboard mit `UeberblickWidget` und `NeuesWidget`. Fehlt: Liste der Wartenden je Person, "als gelesen", Hero-Termin mit Zoom, Ruhe-Satz, Testkonten ausblenden |

### Inhalte und Community (lea-prozessschritte, lea-fundus, lea-coach-podcast, lea-community, lea-community-geteilt)

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| Seite "Prozessschritte" mit zehn Videos, Sprungleiste 0 bis 9 in Schrittfarben | lea-prozessschritte | fehlt | Inhalt steht hart im Code; in der App als Programm (selbstgesteuert, zehn Einheiten mit `video_url`) anlegbar, kein Code nötig |
| Themensuche über Blog, Podcast, Kurse, Lektionen, Material | lea-fundus | vereinfacht | `ThemenController`, Suche nur im Themennamen |
| Frage in eigenen Worten, KI wählt 3 bis 5 Treffer mit "warum" | lea-fundus | fehlt | `FinderProfile` (kurz, hilft, Stichworte) wäre die Grundlage |
| Themen nach Gruppen, Diktat im Suchfeld | lea-fundus | fehlt | `topics` ohne Gruppe |
| "Meine Suchen" (Verlauf mit Treffern) | lea-fundus | fehlt | |
| Mein Archiv (Gemerktes) | lea-fundus | gleich | `/merkliste` |
| Vorschaufenster mit "Hilft, wenn ...", Themen, Fundort im Kurs | lea-fundus | vereinfacht | Themenseite listet Inhalte |
| Gesperrte Inhalte mit Tür: "Diesen Kurs hast du noch nicht. Kurs ansehen" | lea-fundus | fehlt | |
| Sammlungen: Coachin wählt aus, benennt, schreibt einen Gruss, schickt in 1:1-Chats oder erzeugt einen Link | lea-fundus | fehlt | |
| Einzelnen Inhalt in den Chat schicken, Link kopieren, Teilen des Handys | lea-fundus | fehlt | |
| Podcast "Impulse für Coaches" mit Player und Leer-Hinweis | lea-coach-podcast | gleich | Impulse mit Filter je Sendung (`ImpulseController`, `podcast:<show>`) |
| Ein Ort "Von Lea" und "Fragen und Austausch", mit Kurswahl | lea-community | vereinfacht | Impulse plus Gruppengespräch je Programm (`/kurse/{slug}/austausch`) |
| Fragen im Kursraum als Threads mit Antworten und Status | lea-community | vereinfacht | Gruppenchat statt Threads; alte Fragen (CPT `frage`) werden nicht importiert |
| Für den Kurs freigegebene Notizen, Aufgaben, Reflexionen der anderen, mit Avatar, Reaktionen, Kommentaren | lea-community-geteilt | fehlt | Sichtbarkeit `program` wird gespeichert, aber nirgends angezeigt |

---

## 2. Fehlt und ist wichtig für Lea oder die Teilnehmerin (nach Wichtigkeit)

1. **Wer wartet, Ampel, Kennzahlen in der Personenliste.** In `Memberships/Tables/MembershipsTable.php` berechnete Spalten (nächster Termin, Aufgaben x von y, zuletzt da, Badge "wartet" aus `Chat::directFor` und `last_read_at`) plus ein neues `Filament/Coach/Widgets/AmpelWidget.php`, das je Person Farbe und Grund bestimmt und mit "kurz nachfragen" auf `gespraech.show?entwurf=aufgaben|call|still` verlinkt; `gespraech/show.blade.php` füllt den Entwurf ins Textfeld.
2. **Auf Geteiltes antworten.** Das Modell `Comment` gibt es schon: Blade-Partial `components/kommentare.blade.php` unter Reflexion, Notiz und Aufgabe (Route `POST /kommentar`), im Dossier je geteilter Reflexion eine Filament-Action "Antworten", Benachrichtigung über `Notifier`.
3. **Private Notizen der Coachin zu einer Person.** Neue Tabelle `coach_notes` (tenant_id, user_id der Person, author_id, body) mit `BelongsToTenant` und Mandantentest; im Dossier (`Dossier.php` plus Abschnitt in `dossier.blade.php`) ein Formular und die Liste, sichtbar nur für Rollen owner/team.
4. **Sitzungskontingent und Buchen/Anfragen.** Feld `sessions_total` an `offers` oder `entitlements` (Migration), Zusatzsitzungen je Person in `program_members.settings`, Berechnung in `app/Programs/Kontingent.php` aus 1:1-Events; Anzeige im Dossier, in der Personenliste und auf der Teilnehmerseite (Karte auf `home.blade.php` mit "Termin anfragen", das eine Nachricht ins 1:1-Gespräch legt).
5. **Terminvorschlag im Chat.** Dossier-Action "Zeiten vorschlagen" (Repeater mit DateTimePicker und Satz) schickt über `Chat::send` eine `Message` mit `settings.vorschlaege`; `gespraech/_nachricht.blade.php` zeigt Knöpfe, Route `POST /nachricht/{id}/termin` legt das 1:1-`Event` an und schreibt die Bestätigungszeile; freie Blöcke können später dazukommen.
6. **Wochenaufgaben mit Art und Aktion.** Spalte `kind` (haken, notiz, reflexion, frage, aufzeichnung, termin) und `weekday` an `tasks`; in `kurse/schritt.blade.php` ein Block "Deine Aufgaben diese Woche", dessen Knopf das Notiz- oder Reflexionsformular inline öffnet und beim Speichern `aufgaben.haken` setzt; Reflexions- und Fragentage erzeugen die Aufgabe automatisch; Rückstand früherer Wochen oben in `aufgaben/index.blade.php`.
7. **Erinnerungen vervollständigen.** In `Notifications/Runden.php`: Aufgaben-Mail für Personen ohne Push (Morgen, Abend nur Offenes), neuer Lauf für `due_time` (alle 5 Min.), Coachin bei 1:1 in `EventObserver::recipients`, dazu ein `ReflectionObserver`, der die Coachin beim Teilen benachrichtigt.
8. **Diktieren.** Blade-Komponente `components/diktat.blade.php` (Mikrofonknopf, Web Speech API `de-CH`, Hinweisfenster) für Reflexion, Notizen, Aufgaben, Gespräch und die Coach-Notizen.
9. **KI-Vorbereitung im Dossier.** `App\Ai\Summarizer::vorbereitung(User)` mit nur freigegebenen Daten (Stand im Programm, geteilte Reflexionen, letzte Nachrichten), gespeichert in `ai_summaries` (summarizable = membership, kind `vorbereitung`), Filament-Action "Vorbereitung (KI)" mit "Neu erstellen".
10. **Gemeinsames 1:1-Journal mit Projekten und Prozessschritten.** Eine Zeitleiste für Einzelbegleitungen (Blade `kurse/journal.blade.php` und Abschnitt im Dossier), die 1:1-Events, Coach-Aufgaben, geteiltes Material, geteilte Notizen und Kommentare mischt; Projekte als Tabelle `projects` (tenant_id, user_id, name, color, icon), Prozessschritte als Liste in `tenants.settings.prozessschritte`, Zuordnung über `settings` an Notiz und Aufgabe.
11. **Rundnachricht an Einzelne und ins 1:1-Gespräch.** In `Pages/Rundnachricht.php` Option "Einzelne" (Mehrfachauswahl) und Schalter "ins persönliche Gespräch", zugestellt über `Chat::directFor` und `Chat::send`.
12. **Reflexion: Wochenwahl und Rückblick.** In `ReflexionController::index` Wochenvorschlag (Mo/Di vergangene Woche) und Auswahl der Kurswochen aus `program_steps`; darüber ein aufklappbarer Block "Was hattest du dir vorgenommen" aus `tasks` der Woche.
13. **Kurs-Feed des Geteilten, Profil und Teilnehmerliste.** In `kurse/show.blade.php` Abschnitte "Aus dem Kurs geteilt" (Notes, Tasks, Reflections mit `visibility = program`) und "Wer dabei ist"; `ProfilController` bekommt Avatar-Upload nach `storage/app/tenants/{id}/avatare`, Kurzbio, Website und die Sichtbarkeitsschalter in `memberships.settings.profil`.
14. **Fundus: KI-Suche und Sammlungen.** `ThemenController::frage` wählt über `FinderProfile` 3 bis 5 Treffer mit Begründung; für die Coachin eine Filament-Action "An Person schicken" an Material, Einheit und Impuls, die `Resourceable` für die Person anlegt und eine Nachricht mit Link ins Gespräch legt.
15. **Material: Abspielen in der App, "angeschaut", Karten.** Modal-Player in `material/index.blade.php` mit `Support\Video::embed`, Bookmark-ähnliche Tabelle oder `Progress` für angeschautes Material, Karten mit `image_url` oder Farbfläche je Typ, Filter nach Art und Sortierung.
16. **Notizen mit Formatierung und Anhang.** Kleiner Editor (Tiptap aus Filament oder `contenteditable` mit Säuberung wie `lea_nz_saeubern`), Bild und Link in `notes.settings` bzw. Datei unter `storage/app/tenants/{id}/notizen`.
17. **Termine-Feinheiten.** Aufgaben mit Datum in `termine/index.blade.php` einmischen, Suche, Absagen für Teilnehmerinnen als Kürzel, Google- und Outlook-Links in `termine/show.blade.php`, Abo je Programm (`/kalender/{token}-{programm}.ics`), VALARM 15 Min. in `Support/Ics.php`.
18. **Zeitmarken in Zusammenfassungen klickbar.** In `termine/show.blade.php` "(ab mm:ss)" per Regex zu Sprunglinks, Vimeo-Player-API wie in `lea-club-termin.php`.
19. **Prozessschritte-Videos.** Als Programm im Coach-Bereich anlegen (zehn Einheiten, Vimeo-Links aus `lea-prozessschritte.php`), kein Code.
20. **Zahlen, Finanzreiter, Steuerpult.** Eigenes Modul `app/Finanzen` mit bexio-Zugang je Mandant in `tenants.settings.bexio`, eigene Filament-Seite nur für owner; zuletzt, weil es Lea nicht in der Begleitung hilft.

**Auffälligkeiten, die sofort behoben werden sollten**
- Dossier zeigt Titel privater Aufgaben (`Dossier.php`, Abfrage `aufgaben` ohne Filter auf `visibility`). Alt galt: Privates bleibt privat, es wird nur gezählt.
- WhatsApp-Knopf im Dossier: `preg_replace('~\D+~', '', $phone)` macht aus `079 ...` eine ungültige Nummer. Normalisierung wie `lea_co2_wanummer` (0 durch Landesvorwahl aus `tenants.settings` ersetzen, 00 und + entfernen).
- `termine/show.blade.php` zeigt allen Teilnehmerinnen die Vornamen der Abgemeldeten; alt sahen sie nur Kürzel.
- Aufgabenformular: `is_pinned` wird validiert, es gibt aber kein Häkchen dafür.

---

## 3. Designnotizen: was das alte Design schön macht

Die Grundfarben sind schon im Lea-Branding (`database/seeders/TenantSeeder.php`: primary `#B4795F`, text `#2E2D29`, text_soft `#4A473F`, muted `#86816F`, card_bg `#FFFDF8`, card_border `#E4DFD2`, success `#6E8B74`, danger `#B5544F`, Lora, Radius 16). Was die alte Oberfläche darüber hinaus warm und ruhig macht:

**Farben**
- Zwei Kartenflächen im Wechsel: warmes Papier `#FAF8F3` für Listen- und Filterkarten, `#FFFDF8` bzw. `#fff` für hervorgehobene; aktuelle Woche `#FDF8F4`.
- Feine Abstufung der Grautöne: Labels `#A9A395`, Symbole und Pfeile `#C9C3B5`, Trennlinien in Karten `#F0ECE3` / `#EFEDE7`, Flächen für Chips und Nummernkreise `#EFEDE7`.
- Zustandsfarben als weiche Paare (Hintergrund plus Schrift): dabei `#E3EDE5` / `#4F6E56`, offen `#F5F3EC` / `#A9A395`, Anhang `#F0E4DA`, Hinweisfläche `#F3EFE6`.
- Material-Typfarben (Karte `lea-club-ressourcen-karte`): Video `#7C6A8A`, Audio `#B4795F`, PDF `#6E8B74`, Text `#8A8375`, Link `#5F7F8F`.
- Prozessschritte und Projekte haben eigene Farben: `#B4795F`, `#8C6A4F`, `#B5544F`, `#9A6A7A`, `#6E8B74`, `#5F8C6A`, `#C89A4A`, `#7C8C9A`, `#5F6F7F`; Projektpalette `#B4795F #6E8B74 #7C8C9A #B8607A #C89A4A #5F6F7F`.
- Jeder Kurs hat eine Farbe (`--kurs`), die Balken, Nummernkreis der laufenden Woche, Rahmen und Filterpille färbt.
- Terminseite mit eigener, kühlerer Palette: Knopf `#7C86A2`, Labels salbeigrün `#8A9A8B`, Flächen `#F8F6F1` / `#E6E3D8`, Pillen "läuft" `#7C86A2`, "Aufzeichnung" `#8B9A86`.

**Karten und Abstände**
- Karten: Rahmen 1px `#E4DFD2`, Radius 14 bis 18px (Listen 16, Profil- und Kennzahlkarten 18, Sheets 20 bis 22), Innenabstand 12 bis 16px vertikal, 14 bis 18px horizontal, Abstand zwischen Karten 8 bis 10px.
- Karten-Hover: Rahmen wird Akzent `#B4795F`; Materialkarten heben sich mit `box-shadow: 0 8px 24px rgba(50,49,45,.10)` und `translateY(-2px)`.
- Symbolkachel links in Listenkarten: 40 bis 44px, Radius 12px, Fläche `#EFEDE7` oder `#fff` mit Rahmen, Symbol in `#B4795F`.
- Terminkarte mit Tageskachel (grosse Tageszahl in Lora, Wochentag klein darunter), beim Coach 56px breit mit Uhrzeit in Lora 17px.
- Wochen-Akkordeon: Nummernkreis 28px `#EFEDE7`, aktuelle Woche in Kursfarbe mit weisser Zahl, fertige Woche grün `#6E8B74`; rechts "3/5" fett in `#86816F`.
- Fortschrittsbalken 6px hoch, Radius 3px, Spur `#E4DFD2`, Füllung in Kursfarbe.
- Leere Zustände als warmer Satz statt leerer Kästen; auf der Startseite der Coachin werden alle leeren Abschnitte zu einem Satz "Alles ruhig: niemand wartet auf eine Antwort, nichts Neues geteilt und ...".

**Schrift**
- Lora (400) für Überschriften, Kartentitel (18 bis 19px), Reflexionsfragen (22px), Kopf "Guten Tag, Lea" (26px), Zahlen in Tageskacheln.
- Kleine Überschriften und Feldlabels in Versalien: 11px, `letter-spacing: .12em`, Gewicht 700, Farbe `#86816F` bzw. `#A9A395`.
- Fliesstext 15 bis 16px, in der Reflexion 18px mit Zeilenhöhe 1.6; Metazeilen 12 bis 13.5px `#86816F`.
- Zahlen mit `font-variant-numeric: tabular-nums` (Kennzahlen, Beträge).

**Knöpfe, Pillen, Marken**
- Alles rund: Knöpfe und Filter mit Radius 999px. Hauptknopf `#B4795F` oder dunkel `#2E2D29`, 600er Gewicht, Mindesthöhe 46px (Reflexion 52px, volle Breite).
- Leiser Knopf: transparent mit 1.5px `#E4DFD2`; Anhangknöpfe gestrichelt 1.5px `#D8D2C4` auf `#FFFDF8`, Hover in Akzent.
- Filterpillen: 1.5px `#E4DFD2` auf `#FAF8F3`, 9px 16px, 14px 600, Mindesthöhe 40px; aktiv dunkel `#2E2D29` mit weisser Schrift; Kurspillen mit Kursfarbe und Kurssymbol.
- Kleine Marken ("wartet", "Test", "1:1", "Aufzeichnung", "Coach", "du"): 10 bis 11px, Versalien, `letter-spacing .06em` bis `.1em`, Radius 999px; Zähler als weisse Zahl auf `#B4795F`.
- Ampelpunkt je Zeile (rot, gelb, grün) mit Grund in Worten, nicht nur Farbe.

**Symbole**
- Font Awesome 6 durchgehend und bedeutungstreu: Kalender, `fa-list-check` für Aufgaben, `fa-ticket` für Sitzungen, `fa-right-to-bracket` für "zuletzt da", `fa-feather` Notiz, `fa-pen-to-square` Reflexion, `fa-circle-play` Aufzeichnung, `fa-headphones` Audio, `fa-file-pdf`, `fa-bookmark` merken, `fa-share-nodes` teilen, `fa-whatsapp`. Die neue App hat nur einzelne Inline-SVGs und zeigt beim Material Text-Kürzel; ein kleiner, einheitlicher Symbolsatz (z. B. Heroicons als Blade-Komponente) würde viel ausmachen.

**Mobil**
- Kalender- und Teilen-Fenster als Blatt von unten: Radius 22px oben, Schleier `rgba(46,45,41,.45)`, Übergang `.3s cubic-bezier(.4,0,.2,1)`, Innenabstand unten mit `env(safe-area-inset-bottom)`; ab 900px mittiges Fenster.
- Eingabefelder mit 16px Schrift (kein Zoom auf iOS), Tippflächen mindestens 40 bis 46px.
- Raster brechen unter 600 bis 760px auf eine Spalte; unwichtige Knöpfe (z. B. "Öffnen" im Materialregal unter 640px) verschwinden, die ganze Karte ist Link.
- Mikrofonknopf 40px rund oben rechts im Textfeld, pulsiert beim Aufnehmen in Akzentfarbe (`scale(1.1)`, 1s).
- Liste der Coachees und Startseite max. 640px breit, Profil 560px, Reflexion 680px: ruhige Lesespalte statt Tabellenbreite.

**Ton**
- Kurze, warme Mikrotexte: "Nichts offen. Schreib auf, was als Nächstes dran ist.", "Ein paar Minuten reichen. Und wenn heute nicht der Tag dafür ist, ist das auch in Ordnung.", "Das ist kein Versäumnis, sie hat es für sich behalten." Die neue App trifft diesen Ton bereits gut; im Coach-Bereich (Filament) ist er noch nüchterner.
