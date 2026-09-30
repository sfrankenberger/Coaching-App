# G2 Journal, Elemente, Arbeitsbuch, Woche, Projekte

Stand der Prüfung: neue App im Repo `/home/user/Coaching-App` (Commit f337175), alle Aussagen am Code geprüft (grep, gelesen). Der frühere Abgleich in `docs/abgleich/*.md` ist teils überholt (z. B. zeigt die Schrittseite inzwischen Aufgaben, Material und Termine).

## Gelesene Dateien (alt, `alt/novamira-sandbox`)

- `lea-elemente.php`: Freigabe, Kommentare, Reaktionen, Menü, Filter
- `lea-elemente-ui.php`: lädt CSS und JS der Elemente
- `lea-notizen.php`: Notizen schreiben, pinnen, suchen
- `lea-aufgaben.php`: eigene Aufgaben als Elemente
- `lea-aufgaben-plus.php`: Wochenaufgaben, Arten, Erinnerungen
- `lea-aufgaben-aus-zusammenfassung.php`: Knopf "als Aufgabe" je Zeile
- `lea-reflexion.php`: Wochenreflexion, drei Fragen
- `lea-reflexion-rueckblick.php`: Rückblick auf Vorhaben
- `lea-journal-start.php`: Einstieg mit drei Karten
- `.lea-journal-start.php.disabled`, `.lea-meine-woche.php.disabled`: leer (0 Byte)
- `lea-meine-woche.php`: alte To-do-Liste je Kurswoche
- `lea-woche.php`: Kurs in Wochen, Wochenseite
- `lea-wochenaufgaben.php`: Aufgaben pro Modul (Altformat)
- `lea-wochencheck.php`: Checkliste fürs Team
- `lea-projekte.php`: Projekte, neun Prozessschritte
- `lea-diktieren.php`: Mikrofon an Textfeldern
- `lea-eingabe-zweizeilig.php`: nur CSS, zweizeilige Eingabe
- `lea-werkstatt.php`, `lea-werkstatt-aufgaben.php`: Bearbeiten-Seite für Lea und Team
- `lea-timeline.php`: Journal-Zeitleiste
- `lea-karten.php`: einheitliche Karte, Kursname kürzen
- `lea-filter.php`: Filter einklappen (nur CSS/JS aus Option)
- `lea-gemerkt.php`: Lesezeichen und Merkliste
- `lea-arbeitsbuecher.php`: mehrere Arbeitsbücher, Zugriff
- `lea-workbook.php`: Workbook-Anzeige, Speichern, Bilder, Coach-Ansicht
- `lea-workbook-aufgaben.php`: Aufgabe aus Übung
- `lea-workbook-bausteine.php`: Liste, zwei Spalten, Spiegel, Aufnahme, Brief
- `lea-workbook-erledigt.php`: Übung abhaken
- `lea-workbook-freigabe.php`: Gesamtfreigabe "Darf Lea mitlesen"
- `lea-workbook-notizen.php`: freie Notizen als echte Notizen
- `lea-workbook-prompts.php`: fertige KI-Texte, Vorschläge, Launch-Datum

JS aus `wpjs/` (per grep get_option zugeordnet und gelesen): `lea_el_js`, `lea_el_boxen_js`, `lea_el_ziel_js`, `lea_nz_js`, `lea_af_js`, `lea_ap_js` (zu aufgaben-plus), `lea_refl_js`, `lea_mw_js`, `lea_pj2_js`, `lea_mic_js`, `lea_fi_js`, `lea_tl_js` (Zeitleiste), `lea_wk_js` (gehört zur Werkstatt, nicht zum Workbook), `lea_jr_js` (gehört zu `lea-begleitung.php`, das nicht in meiner Liste steht; nur die Journal-Funktionen daraus sind unten kurz erfasst, Details bei der Begleitungs-Gruppe).

## Gegenstück in der neuen App (gelesen)

`NotizenController`, `AufgabenController`, `ReflexionController`, `JournalController`, `KommentarController`, `KursController`, `EinheitController`, `UebungController`, `MerklisteController`, `TermineController::aufgabe`, `DossierController` (Auszüge), `app/Coach/Wochencheck.php`, `Kommentare.php`, `Neues.php`, `Arbeitsliste.php`, `app/Notifications/Runden.php`, `Observers/ProgramMemberObserver.php`, `TaskObserver.php`, `app/Support/Anhaenge.php`, Modelle `Note`, `Task`, `Reflection`, `JournalEntry`, `Bookmark`, `Comment`, `Reaction`, `Anhang`, `Exercise`, Ansichten `notizen`, `aufgaben`, `reflexion`, `journal`, `kurse/*`, `components/kommentare|anhang-wahl|merken`, Filament `TaskResource`, `MaterialResource`, `PostResource`, `Rundnachricht`, `public/js/app.js`, Import `BegleitungImport`, `ProgramsImport`.

## Tabelle

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| **Gemeinsames Fundament der Einträge** | | | | |
| Freigabe je Eintrag: nur ich, Lea (1:1), Kurs, Community | lea-elemente (`el_sicht`) | `Note.visibility` (private, coach, program, all); Aufgabe und Reflexion nur private, coach, program | teilweise | Aufgabe und Reflexion kennen "Community allgemein" nicht (`AufgabeRequest` und `ReflexionController::store` erlauben nur private, coach, program). Kurs-Auswahl nur wenn der Kurs eine Gemeinschaft ist |
| Freigabe nachträglich ändern (Menü "Teilen mit") | lea-elemente (`lea_el_teilen`) | Notiz und Aufgabe über "Bearbeiten" (Formular), Reflexion über `reflexion.teilen` | teilweise | Kein Schnellwechsel im Menü. Reflexion nur "Coachin oder nur ich", nicht Kurs |
| Freigabe-Chip an der Karte ("Mit Lea geteilt", Kursname mit Farbe und Symbol) | lea-elemente (`lea_el_sicht_chip`) | Text in der Metazeile (`Note::VISIBILITIES`) | teilweise | Kein farbiger Chip, Kursfarbe und -symbol fehlen am Eintrag |
| Wer darf was sehen: Kursmitglieder und Community lesen Geteiltes der anderen | lea-elemente (`lea_el_darf_sehen`, Timeline, Notizliste "fremd") | Nur Person und Team (`Kommentare::darf`). Community-Seite zeigt nur Fragen | fehlt | Notiz oder Reflexion "Im Kurs sichtbar" oder "In der Community" sieht niemand ausser Coachin. Die Auswahl verspricht mehr, als die App hält. Community-Text sagt sogar "was jemand aus den Kursen teilt" |
| Kommentare an Notiz, Aufgabe, Reflexion, Antwort (Person, Lea, Team) | lea-elemente (`lea_el_kommentar`, `lea_el_fuss`) | `KommentarController`, `Coach\Kommentare`, `components/kommentare` | vorhanden | Gegenseite bekommt Nachricht (Notifier). Kursmitglieder können nicht kommentieren. Kein "weitere Kommentare anzeigen" (nur Kleinigkeit) |
| Kommentar benachrichtigt alle Beteiligten (Autorin, frühere Kommentierende, Lea) | lea-elemente | `Kommentare::schreiben`: nur Eigentümerin bzw. Team | teilweise | Frühere Kommentierende im Kurs werden nicht benachrichtigt (weil Kursmitglieder nicht kommentieren) |
| Reaktionen (Sehe ich auch so, Kenne ich auch, Stark, Das bewegt mich) | lea-elemente (`lea_el_reaktion`) | `Reaction` mit denselben vier Emojis, nur an Fragen und Nachrichten | fehlt | Keine Oberfläche an Notiz, Aufgabe, Reflexion. `Task::reactions()` existiert ohne Ansicht. Der Import legt Reaktionen an (`BegleitungImport`), sie sind nirgends sichtbar |
| Dreipunkt-Menü: Bearbeiten, Kommentar, Teilen, Anpinnen, Löschen | lea-elemente (`lea_el_menue`, lea_el_js) | Notiz und Aufgabe: Bearbeiten und Löschen. Reflexion: "Nicht mehr teilen" und Löschen | teilweise | Kein Anpinnen, Teilen, Kommentieren im Menü. Kein "In ein Projekt verschieben" |
| Anpinnen | lea-elemente (`lea_el_pin`) | Kontrollkästchen im Formular, `is_pinned`, Sortierung pinned zuerst | vorhanden | Nur über Bearbeiten, nicht mit einem Tipp |
| Eintrag löschen mit Rückfrage | lea-elemente (`wp_trash_post`) | `destroy` in allen drei Controllern, `confirm()` | vorhanden | Endgültig, die Modelle haben kein SoftDeletes (alt Papierkorb) |
| Einheitliche Filterleiste: Art, Zeit, Projekt, Kurs, Status (offen, erledigt, neue Kommentare), Suche | lea-elemente (`lea_el_filterleiste`, `lea_el_passt`) | Nur ein Suchfeld in Notizen und Aufgaben (Titel und Text) | fehlt | Art, Zeit, Projekt, Kurs, Status und "Neue Kommentare" gibt es nicht. Reflexion und Journal haben nicht einmal die Suche |
| Filter einklappen mit Zusammenfassung ("Alles wird gezeigt"), Zurücksetzen | lea-filter (`lea_fi_js`) | – | fehlt | Ohne Filter nichts einzuklappen. Kommt mit der Filterleiste |
| Suche in Reflexionen über die Antwortfelder | lea-reflexion | – | fehlt | Reflexionsliste (30 Stück) ohne Suche |
| Etwas anhängen (Aufgabe, Notiz, Reflexion, Termin, Aufzeichnung, Material) | lea-elemente (`lea_el_anhangbox`, lea_el_boxen_js) | `Support\Anhaenge`, `components/anhang-wahl`, `AnhaengeController` | vorhanden | Gestern neu gebaut, zusätzlich Lektion. Auswahl, Suche, Chips, Prüfung "nur Eigenes oder Gemeinsames" da |
| Foto oder Datei an eine Notiz hängen (Grossansicht) | lea-notizen (`notiz_bild`), Anhangbox "Foto oder Datei" | – (kein Upload-Feld an Notiz, Aufgabe, Reflexion) | fehlt | Import übernimmt `notiz_bild` nicht (grep ohne Treffer), Fotos der alten Notizen gehen verloren |
| Link an eine Notiz (Anzeige mit Domain) | lea-notizen (`notiz_url`) | – (Import hängt die URL an den Text) | fehlt | Kein eigenes Linkfeld, keine Linkkarte |
| Eintrag einem Projekt zuordnen | lea-notizen, lea-aufgaben (`el_projekt`, `lea_pj2_wahl`) | – | fehlt | Siehe Projekte. Import übernimmt `el_projekt` nicht (grep ohne Treffer) |
| Sprung zum Eintrag mit Hervorheben (`?el=ID`) | lea-elemente (`lea_el_url`, lea_el_ziel_js) | Fragment `#notiz-ID`, `#aufgabe-ID`, `#reflexion-ID` (`Kommentare::urlFuerPerson`); Team-Links ins Dossier | teilweise | Der Eintrag wird angesprungen, aber nicht kurz hervorgehoben (kein `:target`-Stil). Links in Benachrichtigungen funktionieren |
| Teilen mit Lea legt eine Chat-Nachricht an ("Ich teile eine Notiz mit dir") und fasst nach 20 Minuten nach | lea-elemente (`lea_el_in_chat`) | – | fehlt | Weder Chat-Hinweis noch Nachfassen beim Teilen von Notiz, Aufgabe, Reflexion |
| Lea und Team bekommen Bescheid, wenn etwas geteilt wird | lea-elemente (`lea_kr_benachrichtigen`) | Reflexion und Antworten stehen in `Coach\Neues` und `Arbeitsliste` ("geteilt") | teilweise | Geteilte Notiz und Aufgabe (coach) tauchen nirgends auf. Kein Push oder Mail beim Teilen (`NotizenController` und `AufgabenController` rufen keinen Notifier) |
| Community nur, wo es einen Kursraum gibt; 1:1 ohne Kurs- und Community-Wahl | lea-elemente (`lea_el_in_gruppe`, `lea_el_ist_einzel`) | `Program::gemeinschaft()` steuert die Optionen | vorhanden | |
| Beschriftung "Nur Lea, im 1:1" nur wenn es ein 1:1 gibt | lea-elemente (`lea_el_hat_einzel`) | Feste Beschriftung "Meine Coachin" | teilweise | Nur Wortlaut |
| **Notizen** | | | | |
| Notiz schreiben (Titel optional, sonst aus dem Text) | lea-notizen | `NotizenController@store`, `notizen/index` | vorhanden | Titel wird nicht aus dem Text abgeleitet, Karte zeigt dann keinen Titel (harmlos) |
| Formatieren: fett, kursiv, Überschrift, Liste, Zitat, Format entfernen | lea-notizen (contenteditable, lea_nz_js) | Einfaches Textfeld, Anzeige `whitespace-pre-line` | fehlt | Nur Klartext. Das Einfügen-Bereinigen (Word, Outlook) entfällt damit |
| Diktieren an der Notiz | lea-notizen, lea-diktieren | `public/js/app.js` (Web Speech an `textarea.feld`) | vorhanden | |
| Notiz bearbeiten, löschen | lea-notizen | `notizen.update`, `notizen.destroy` (Bearbeiten über `?bearbeiten=ID`) | vorhanden | |
| Notizen anpinnen und danach sortieren | lea-notizen | `is_pinned`, `orderByDesc('is_pinned')` | vorhanden | |
| Notizen durchsuchen | lea-notizen | Suchfeld `?q=` | vorhanden | |
| Notiz einem Kurs zuordnen (Kursname in der Karte) | lea-notizen (`notiz_kurs`) | `program_id`, Anzeige in der Metazeile | vorhanden | |
| Notizen aus der Begleitung erscheinen mit ("Aus deiner Begleitung") | lea-notizen (`lea_jr_alle`) | Importierte Notizen liegen in `notes`, alte Journal-Einträge in "Frühere Einträge" (`journal/index`) | vorhanden | Andere Darstellung, kein Link "Im Journal ansehen" |
| Notiz zu einer Kurseinheit ("Deine Notiz dazu") | lea-workbook-notizen, Lektion | `EinheitController@notiz`, erscheint in Notizen als "zu «Einheit»" | vorhanden | Siehe Arbeitsbuch: nur eine Notiz je Einheit, nicht teilbar |
| **Aufgaben** | | | | |
| Aufgabe anlegen: Titel, Notiz, Datum, Uhrzeit, "jeden Tag", Kurs | lea-aufgaben (`lea_af_speichern`) | `AufgabenController@store`, `AufgabeRequest`, `aufgaben/index` | vorhanden | |
| Abhaken, Erledigt-Bereich einklappbar, Bearbeiten, Löschen | lea-aufgaben (`lea_af_haken`) | `haken`, `update`, `destroy`, `<details>` "n erledigt" | vorhanden | |
| Tagesfelder Mo bis So "diese Woche n von 7" | lea-aufgaben (`lea_af_tag`) | `AufgabenController@tag`, `Task::daysDone()` | vorhanden | Erscheinen nur bei offenen Aufgaben |
| Aufgaben von Lea oder Assistenz sehen, Abhaken gehört der Person | lea-aufgaben (`lea_af_fremd_haken`) | Eine Kopie je Person (`assigned_by`), `TaskObserver` meldet neue Aufgabe | vorhanden | |
| "Von Lea" und "Von dir" getrennt anzeigen, "Aus deinem Kurs" mit Zähler | lea-aufgaben-plus (`lea_ap_kursblock`), lea-meine-woche | Eine Liste, "Von X" und Kursname in der Metazeile | teilweise | Keine Gruppen, kein Zähler "n von m erledigt" auf der Aufgabenseite |
| Wochenaufgaben pflegen (Lea, Andrea), an alle im Kurs, Spätere bekommen sie | lea-werkstatt-aufgaben, lea-wochenaufgaben (Textfeld pro Modul) | Filament `TaskResource` ("An alle im Programm", Woche wählbar), `ProgramMemberObserver` | teilweise | Kein "eine pro Zeile"-Schnellfeld. Art der Aufgabe und Wochentag fehlen (siehe nächste Zeilen) |
| Art der Wochenaufgabe: Abhaken, Notiz, Reflexion, Frage, Aufzeichnung, Termin, mit Aktionsknopf | lea-aufgaben-plus (`lea_ap_arten`, `af_art`) | – (`tasks` hat kein Feld für Art) | fehlt | Import übernimmt `af_art` und `af_tag` nicht (grep ohne Treffer) |
| Formular direkt in der Woche (Notiz, Reflexion, Frage) mit "Speichern und abhaken", Diktat | lea-aufgaben-plus (`lea_ap_tun`, lea_ap_js) | – | fehlt | Nur Link "Reflexion schreiben" oder "Frage stellen" ohne Abhaken |
| Wochentag der Aufgabe (fällig am Dienstag) | lea-aufgaben-plus (`af_tag`, `lea_ap_faellig`) | Nur Datum `due_at` | fehlt | Ohne festen Wochentag keine Wochenlogik. `Wochencheck` meldet "ohne Datum (keine Erinnerung)" |
| Fragentag und Wochenreflexion erscheinen als Aufgaben mit Haken | lea-aufgaben-plus (`lea_ap_woche`, `lea_ap_frage_gestellt`, `lea_ap_reflexion_da`) | `kurse/schritt`: Link-Zeile je Reflexions- und Fragentag | fehlt | Kein Status "geschrieben oder gestellt", kein Haken, keine "Diese Woche keine Frage"-Taste |
| Fälligkeit in Worten ("heute dran", "morgen dran", "seit Montag offen", "bis Freitag um 19 Uhr"), farbig | lea-aufgaben-plus (`lea_ap_wann`) | "bis 3. Oktober, 19:00 Uhr", überfällig rot | teilweise | Kein "heute dran" und "morgen dran" |
| Rückstand aus früheren Wochen (bis 4 Wochen, verpasste Calls, ungeschriebene Reflexion) | lea-aufgaben-plus (`lea_ap_rueckstand`) | – | fehlt | |
| Aufgabe im Kurs verlinkt zurück auf ihre Woche | lea-elemente (`lea_el_url`) | Kein Link von Aufgabe zur Woche (nur "zur Übung") | teilweise | Aufgabe mit `step_id` verweist nicht auf die Wochenseite |
| Aufgabe einer Kurswoche zuordnen oder verschieben | lea-meine-woche (`verschieben`), Kurs-Block | `AufgabeRequest` kennt `step_id`, Formular hat kein Wochen-Feld (nur Wochenseite setzt es versteckt) | teilweise | Nachträglich keine Woche wählbar oder ändern |
| Aufgaben aus der KI-Zusammenfassung (Knopf je Zeile, bei 1:1) | lea-aufgaben-aus-zusammenfassung | Coach: Aktion in `EditEvent` (Auswahl), Person: "Als Aufgabe" je Vorschlag in `termine/show` (`TermineController@aufgabe`, `Summarizer::createTasks`) | vorhanden | Auswahlfenster statt Knopf je Zeile im Coach-Bereich |
| Aus einer Übung eine Aufgabe machen | lea-workbook-aufgaben | "Daraus eine Aufgabe machen" in `kurse/einheit` (`unit_id`, Rücksprung) | vorhanden | Die entstandenen Aufgaben stehen nicht unter der Übung. Zurück zur Übung nur als Link in der Aufgabe |
| Erinnerungen 09:00 und 18:00 als Push und Mail, nur bei Offenem, Lea und Team lesen bei Kursaufgaben mit (Bcc) | lea-aufgaben-plus (`lea_ap_erinnern`) | `Runden::aufgabenHinweis` (08:00 und 18:00), nur Push oder Telegram, `mailWennKeinPush: false` | teilweise | Personen ohne Push bekommen keine Aufgabenerinnerung, nur Sammelmail für neue Aufgaben. Kein Bcc an Lea. Überfällige Aufgaben und "jeden Tag"-Aufgaben melden täglich weiter, auch wenn der Tag schon abgehakt ist |
| Punktgenaue Push zur eingetragenen Uhrzeit ("Jetzt dran", alle 5 Minuten) | lea-aufgaben-plus (`lea_ap_punkt`) | – (`due_time` wird gespeichert, aber nirgends ausgelöst) | fehlt | |
| Erinnerungen im Profil an- und ausschalten | lea-aufgaben-plus (`lea_ap_profil_block`) | `profil.blade.php` (`notifications.aufgaben`) | vorhanden | |
| Erinnerungsmodus aus, test, an | lea-aufgaben-plus (Option `lea_ap_erinnerung`) | Testbetrieb im `Notifier` (`allowedInTestMode`) | vorhanden | |
| Meine Woche: To-dos im Profil, je Kurswoche, "jeden Tag" | lea-meine-woche (`lea_meine_todos`), `.disabled`-Datei leer | Ersetzt durch Aufgaben | Altlast | Alt schon abgelöst durch lea-aufgaben. Datenübernahme aus `lea_meine_todos` im Import nicht gefunden (grep ohne Treffer), nicht geprüft ob noch Daten da sind |
| Wochenaufgaben im Altformat (Textzeilen am Modul, `lea_wa_erledigt`) | lea-wochenaufgaben | Ersetzt durch Task-Kopien | Altlast | |
| **Reflexion** | | | | |
| Wochenreflexion mit drei Fragen und Hinweisen | lea-reflexion | `ReflexionController`, `reflexion/index` (gleiche Fragen und Tipps) | vorhanden | |
| Angefangene Reflexion weiterschreiben | lea-reflexion (`lea_refl_entwurf`) | `refl_id` und `?refl=`, privat und jünger als 6 Tage | vorhanden | |
| Kurswoche wählen ("Woche 3 · 14. bis 20. September"), Vorschlag Mo und Di: letzte Woche | lea-reflexion (`lea_refl_wochenwahl`, `lea_refl_vorschlag`) | Titel nur "Woche KW (heutiges Datum)" | fehlt | Keine Zuordnung zur Kurswoche. `Wochencheck` zählt Reflexionen nach Erstellzeit statt nach gewählter Woche |
| Rückblick vor dem Schreiben ("Magst du nochmal schauen, was du dir vorgenommen hattest?") | lea-reflexion-rueckblick | – (grep "vorgenommen" ohne Treffer) | fehlt | |
| Diktieren je Feld mit Hinweis "So funktioniert das Diktieren" | lea-reflexion | Mikrofon global (`app.js`) | teilweise | Ohne Hinweistext |
| Beim Teilen geht die Reflexion per Mail an Lea | lea-reflexion (`LEA_REFLEXION_MAIL`) | Keine Mail. Erscheint in `Neues` und der Arbeitsliste | teilweise | Kein Push oder Mail an Lea oder Team |
| Nachtrag an eine geteilte Reflexion | lea-reflexion (`lea_refl_js`, als Kommentar) | `reflexion.nachtrag` (`addendum`) | vorhanden | |
| Teilen und Zurücknehmen, Löschen | lea-reflexion | `reflexion.teilen`, `destroy` | vorhanden | |
| Liste bisheriger Reflexionen mit Filter und Suche | lea-reflexion | 30 Stück, ohne Filter | teilweise | Keine Suche, kein Filter |
| **Journal** | | | | |
| Journal-Einstieg mit drei Karten und Zahlen (offen, Einträge) | lea-journal-start | `JournalController`, `journal/index` | vorhanden | |
| Zeitleiste: ein Strom aus Aufgaben, Notizen, Reflexionen, Begleitung, Terminen, Aufzeichnungen, Material | lea-timeline (`lea_tl_punkte`) | – (Journal ist nur ein Menü plus "Frühere Einträge") | fehlt | |
| Zeitleiste: Monat und Woche als Überschriften, Art-Symbol, Projekt-Farbstreifen, Filter nach Art | lea-timeline | – | fehlt | Teil der Zeitleiste |
| Zeitleiste: Zeile "Als Nächstes: Termin", "Mehr lesen", Zoom öffnen, Aufzeichnung ansehen mit "Angeschaut", Haken in der Zeitleiste | lea-timeline, lea_tl_js | – (Termine und Aufzeichnungen stehen auf `termine.index`) | fehlt | Teil der Zeitleiste |
| Journal der Einzelbegleitung: Eintrag als Notiz, Reflexion, Aufgabe oder Material, mit Projekt und Prozessschritt, Kommentare, "an Lea" | lea-begleitung (`lea_jr_js`, gehört zur Begleitungs-Gruppe) | Getrennt: Notizen, Aufgaben, Reflexion mit Freigabe "Coachin", Gespräch; `JournalEntry` nur als Import-Anzeige | teilweise | Kein gemeinsamer Strom, kein Material-Eintrag, Prozessschritt und Projekt fehlen. Details bei der Begleitungs-Gruppe, hier nicht vertieft |
| Frühere Journal-Einträge aus WordPress anzeigen | lea-begleitung | `journal/index` ("Frühere Einträge", 20 Stück) | vorhanden | Nur die letzten 20 |
| **Projekte** | | | | |
| Projekte anlegen, ändern, löschen (Name, "Worum geht es", Farbe, Symbol) | lea-projekte (`lea_pj2_speichern`, `lea_pj2_weg`) | – (keine Tabelle, kein Modell, keine Route) | fehlt | Import übernimmt `lea_projekte` nicht (grep ohne Treffer) |
| Neun Prozessschritte (Intention bis Es trägt) am Projekt, Erklärfenster | lea-projekte (`lea_ps_schritte`, `lea_pj2_schritt`) | – | fehlt | |
| Ansicht "Wo ich stehe" als Treppe, Projekt per Ziehen oder Tipp verschieben | lea-projekte (`lea_pj2_treppe`) | – | fehlt | |
| Projekt wählen bei Notiz, Aufgabe, Reflexion und danach filtern | lea-projekte (`lea_pj2_wahl`, `lea_pj2_zuordnen`) | – | fehlt | |
| Projekt teilen (nur ich, Lea, Community, Kurs) und am Projekt kommentieren | lea-projekte (`lea_pj2_teilen`, `lea_pj2_traeger`) | – | fehlt | |
| **Arbeitsbuch (Workbook)** | | | | |
| Arbeitsbuch als Struktur aus Schritten und Übungen, mehrere Bücher, Antworten je Buch | lea-arbeitsbuecher, lea-workbook | `Program` (Typ workbook), `Unit`, `Exercise`, `Answer`, `ProgramsImport` | vorhanden | Bücher sind jetzt Programme statt JSON-Dateien |
| Zugriff: kursgebundene Bücher nur mit Kurs, Bücher in Vorbereitung nur fürs Team | lea-arbeitsbuecher (`lea_ab_darf`) | `Gate view` auf Program, `is_published` | vorhanden | |
| Übersicht der Schritte mit Nummer, Zahl der Übungen, "davon n Kern", Fortschrittsring | lea-workbook (`lea_wb_uebersicht`, `lea_wb_ring`) | `kurse/show`: Fortschritt als Balken, "Kern"-Chip in der Zeile | teilweise | Kein Ring je Schritt, keine Kernübungs-Zählung |
| Gesamtfortschritt "n von m Feldern, k von l Kernübungen" | lea-workbook (`lea_wb_fortschritt_gesamt`) | Einheiten erledigt (`ProgressTracker::summary`), je Einheit "n von m" Felder | teilweise | Keine Gesamtzahl der Felder, keine Kernübungs-Zahl |
| Durch die Übungen blättern (Übung 1, 2, ..., Weiter, Zurück, Schrittwechsel) | lea-workbook | `kurse/einheit` (Nummer von Anzahl, Vorher, Weiter) | vorhanden | |
| Textfelder speichern automatisch, Status "gespeichert" | lea-workbook (`lea_wb_save`) | `EinheitController@antwort`, `data-antwort`, `data-status` | vorhanden | |
| Skala 1 bis 10 und lebendes Lebensrad | lea-workbook | `scale`, `wheel` in `einheit.blade.php` | vorhanden | |
| Werteliste zum Anklicken, Auswahl | lea-workbook | `values`, `choice` | vorhanden | |
| Liste Zeile für Zeile, zwei Spalten, Spiegel früherer Antwort, grosses Brieffeld | lea-workbook-bausteine | `list`, `pairs`, `mirror`, `letter` | vorhanden | Kein Mikrofon an den Listenzeilen |
| Aufnahme: eigenen Text ablesen, aufnehmen, anhören (privat) | lea-workbook-bausteine (`lea_wb_ton`) | `UebungController@aufnahme`, `hoeren`, `audio` | vorhanden | Privat unter `storage/app/tenants/...` |
| Bilder zu einer Übung hochladen, geschützt ausgeliefert; Ikigai-Grafik | lea-workbook (`lea_wb_dateifeld`, `lea_wb_bild`) | – (kein Übungstyp für Bilder; Import verwirft `sub` mit `datei` und Nicht-Lebensrad-`grafik`) | fehlt | |
| Gesamtfreigabe "Darf Lea mitlesen?" (Ja alles, Nein alles bleibt bei mir) | lea-workbook-freigabe (`lea_wbf_modus`) | `KursController@freigabe`, `share_mode` alles oder einzeln | teilweise | Wird nur einmal gefragt, danach keine Stelle zum Ändern. Kein "Nein, alles bleibt bei mir" (nur "einzeln") |
| Ausnahme je Übung von der Gesamtentscheidung; "Mit Lea teilen" je Übung | lea-workbook-freigabe, lea-workbook (`lea_wb_teilen`) | `EinheitController@teilen` (Knopf je Einheit) | vorhanden | |
| Lea sieht geteilte Übungen der Person | lea-workbook (`lea_wb_coach_ansicht`) | Dossier-Reiter "Freigegeben", `coachees/_antwort` | vorhanden | Nicht Zeile für Zeile verglichen |
| Übung als erledigt markieren, zählt voll | lea-workbook-erledigt | `EinheitController@erledigt`, Knopf "Als erledigt markieren" | vorhanden | |
| Freie Notizen je Übung als echte Notizen, mehrere, mit Freigabe | lea-workbook-notizen | "Deine Notiz dazu": eine Notiz je Einheit, privat | teilweise | Nur eine Notiz, keine Freigabe-Wahl an der Notiz, kein Anhängen |
| "Das hast du früher schon geschrieben" mit Übernehmen (Sammlung Schritt 11) | lea-workbook-prompts (`lea_wbp_vorschlag_kasten`) | – (grep ohne Treffer) | fehlt | Inhaltlich an Leas Website-Workbook gebunden, für andere Mandanten nur mit Konfiguration |
| Fertige Texte für Claude oder ChatGPT aus der Sammlung, Kopierknopf, Launch-Datum wird zur Aufgabe | lea-workbook-prompts (`lea_wbp_block`, `lea_wbp_launch_aufgabe`) | Übungstyp `takeaway` (kopieren, drucken) | teilweise | Die Prompts und das Launch-Datum fehlen. Lea-spezifisch |
| Filter "Kern oder alle" im Workbook | lea-workbook (CSS und JS vorhanden) | – | Altlast | Im Alt-Markup gibt es keinen Knopf dafür, nur Code |
| **Woche im Kurs** | | | | |
| Wochenübersicht: "Diese Woche" gross, darunter alle Wochen mit "n von m erledigt" und Call-Chip (dabei, Aufzeichnung gesehen, steht an) | lea-woche (`lea_kurs_wochen`, `lea_wo_callchip`) | `kurse/show`: Liste "Alle Wochen" mit Nummer, Datum, Einheiten-Zähler, "Jetzt dran" | teilweise | Keine grosse Karte "Diese Woche", kein Call-Chip, Zähler zählt Einheiten statt Aufgaben |
| Wochenseite mit Kurstitel, Wochenband zum Wischen, Pfeile, "Zur aktuellen Woche" | lea-woche (`lea_woche`) | `kurse/schritt`: Pfeile vorher und nachher | teilweise | Kein Wochenband, kein Wischen, kein "Zur aktuellen Woche" |
| Einleitung "Von Lea" mit Bild je Woche | lea-woche | `schritt.summary` als Karte | teilweise | Ohne Avatar und Absender |
| Call der Woche: Läuft gerade, Zoom öffnen, Aufzeichnung ansehen, "Aufzeichnung kommt", dabei-Zeile | lea-woche | `x-termin-karte` in `kurse/schritt` | vorhanden | Nicht Zeile für Zeile verglichen |
| Material der Woche | lea-woche (`lea_wo_material`) | `kurse/schritt` (Material über `step` oder `unit`) | vorhanden | Im Coach-Formular `MaterialResource` gibt es keine Woche als Ziel (nur Programme, Einheiten, Termine) |
| Wochen nur nach Freischaltung, Wochen aus Call-Titeln abgeleitet | lea-woche (`lea_wo_wochen`) | `ProgramStep` mit `unlocks_at`, `week_number` | vorhanden | Besser gelöst (explizite Schritte) |
| Wochencheck fürs Team: Zoom-Link, Einleitung, Aufgaben mit Tag, Fragentag, Reflexionstermin, Aufzeichnung, Zusammenfassung, Freigabe, Reflexionen n von m, offene Fragen, nächste Woche | lea-wochencheck | `Coach\Wochencheck`, `WochencheckWidget`, `WochenseiteTest`/`WochencheckTest` | vorhanden | Zeile "Material in der Woche" und Fusszeile mit Erinnerungsmodus fehlen. "Tag" ersetzt durch "Datum" |
| Drei freie Haken je Kalenderwoche | lea-wochencheck (`lea_wc_manuell`) | `tenants.settings.wochencheck.haken` | vorhanden | |
| Wochencheck als einzige Box für Andrea (Rolle Redaktion) | lea-wochencheck | – | nicht geprüft | Zugriff für Rolle `team` im Coach-Panel hier nicht untersucht |
| **Werkstatt (Lea und Andrea bearbeiten)** | | | | |
| Eine Seite mit Formularen für Ressource, Wochenaufgabe, Nachricht | lea-werkstatt, lea-werkstatt-aufgaben | Filament-Verwaltung `/coach` (Material, Aufgaben, Beiträge, Rundnachricht) | vorhanden | Anders gebaut, aber alles erreichbar |
| Ressource hochladen (Datei oder Link, Ordner, Kurs, Woche) | lea-werkstatt-aufgaben (`lea_wk_ressource_speichern`) | `MaterialResource` | teilweise | Woche und Ordner fehlen als Ziel |
| Nachricht oder Beitrag: Text, Bild, wer sieht es, Themen, Kanäle, Zeitpunkt, Entwurf | lea-werkstatt-aufgaben (`lea_wk_nachricht_speichern`) | `PostResource` (Sichtbarkeit, Themen, Mail, `published_at` geplant, `is_published`) | teilweise | Bild nur als URL, kein Hochladen |
| Testmail nur an mich vor dem Verschicken | lea-werkstatt-aufgaben (`nur_test`) | – | fehlt | |
| KI-Bild im Stil der Website erzeugen (gpt-image-1) | lea-werkstatt (`lea_wk_ki_bild`, lea_wk_js) | – (grep ohne Treffer) | fehlt | Braucht OpenAI-Schlüssel je Mandant |
| Kurzmail an einen Kurs | lea-werkstatt-aufgaben | `Rundnachricht` (Programm, Push, Mail, Gruppengespräch) | vorhanden | |
| **Merkliste** | | | | |
| Lesezeichen an Karten, Merkliste, Zähler | lea-gemerkt | `Bookmark`, `x-merken`, `MaterialController@merken`, `MerklisteController` | vorhanden | Arten: Material, Termin, Einheit, Beitrag, Folge, Programm, Schritt, Werkzeug |
| **Eingabe und Optik** | | | | |
| Diktieren überall, auch an nachgeladenen Feldern | lea-diktieren (`lea_mic_js`, MutationObserver) | `app.js` (beim Laden an `textarea.feld`) | teilweise | Nur Felder, die beim Laden da sind; Textzeilen (`input`) ohne Mikrofon; Chat hat eigenen Knopf |
| Zweizeilige Eingabe (Feld oben, Knöpfe darunter) | lea-eingabe-zweizeilig (nur CSS) | Formular- und Kommentar-Layout in `app.css` | nicht geprüft | Reine Optik, nicht verglichen |
| Einheitliche Kartenoptik | lea-karten (`lea_karte_aufgabe`) | `x-karte`, Klasse `karte` | vorhanden | |
| Kurzer Kursname ohne "Hybrid-Coaching:" davor | lea-karten (`lea_kursname`) | Voller Programmtitel | fehlt | Nur Anzeige, bei langen Titeln unhandlich |

## Wichtigste Lücken

1. **Geteiltes ist für Kursmitglieder und Community unsichtbar.** Notiz, Reflexion, Aufgabe lassen sich "Im Kurs sichtbar" oder "In der Community" stellen, gelesen, kommentiert oder mit Reaktionen versehen werden sie nur von der Coachin. Die Community-Seite zeigt nur Fragen. Entweder die Optionen streichen oder einen Strom "Geteilt im Kurs" bauen (mit Kommentar und den vier Reaktionen).
2. **Wochenaufgaben ohne Art und ohne Wochentag.** Das Herzstück des Hybrid-Coachings fehlt: Aufgabe mit Aktionsknopf (Notiz, Reflexion, Frage, Aufzeichnung, Termin), Formular direkt in der Woche mit "Speichern und abhaken", Fragentag und Reflexion als abhakbare Aufgaben, "heute dran / seit Montag offen", Rückstand aus früheren Wochen. Der Import verwirft `af_art` und `af_tag`.
3. **Zeitleiste im Journal fehlt.** Das Journal ist nur ein Menü. Ein Strom aus Aufgaben, Notizen, Reflexionen, Terminen, Aufzeichnungen mit Monats- und Wochenüberschriften, "Als Nächstes", direktem Abhaken.
4. **Projekte und die neun Prozessschritte fehlen ganz** (Tabelle, Zuordnung, "Wo ich stehe", Teilen, Kommentar am Projekt). Import von `lea_projekte` und `el_projekt` ebenfalls nicht vorhanden, Daten der Teilnehmerinnen gehen sonst verloren.
5. **Filterleiste fehlt** (Art, Zeit, Projekt, Kurs, Status, "Neue Kommentare"), dazu die einklappbare Zusammenfassung. Nur ein Suchfeld bei Notizen und Aufgaben, bei Reflexion gar keins.
6. **Aufgaben-Erinnerungen sind halb.** Nur Push oder Telegram, keine Mail, kein Bcc an Lea bei Kursaufgaben, keine punktgenaue Uhrzeit-Push ("Jetzt dran"), und "jeden Tag"-Aufgaben melden weiter, obwohl der Tag abgehakt ist. Wer keinen Push hat, hört nichts.
7. **Lea und Team erfahren nicht, wenn Notiz oder Aufgabe geteilt wird.** Nur Reflexionen und Antworten stehen in der Arbeitsliste. Der Chat-Hinweis "Ich teile ... mit dir" mit Nachfassen nach 20 Minuten und die Mail bei geteilter Reflexion sind weg.
8. **Reflexion ohne Kurswoche und ohne Rückblick.** Keine Wahl der Kurswoche (Vorschlag Mo und Di: vorige Woche), kein Block "Was hattest du dir vorgenommen?". Der Wochencheck zählt deshalb nach Erstellzeit.
9. **Foto, Datei und Link an Notizen fehlen**, der Import lässt `notiz_bild` weg. Im Arbeitsbuch fehlt das Bilder-Feld samt geschützter Auslieferung und die Ikigai-Grafik.
10. **Reaktionen an Einträgen** (vier Emojis) haben keine Oberfläche, obwohl das Modell da ist und der Import Reaktionen anlegt.
11. **Dreipunkt-Menü zu dünn.** Anpinnen, Teilen, Kommentar, Verschieben in Projekt oder Woche gehen nur über das Bearbeiten-Formular oder gar nicht. Aufgabe lässt sich nachträglich keiner Kurswoche zuordnen.
12. **Wochenansicht ärmer:** keine grosse Karte "Diese Woche", kein Call-Chip (dabei, gesehen, steht an), kein Wochenband zum Wischen, kein "Zur aktuellen Woche", Zähler nur für Einheiten.
13. **Arbeitsbuch-Freigabe nur einmal.** Die Gesamtentscheidung "Darf Lea mitlesen?" lässt sich später nicht mehr ändern, "Nein, alles bleibt bei mir" fehlt. Freie Notizen je Übung: nur eine, ohne Freigabe.
14. **Werkstatt-Lücken:** Beitragsbild nur als URL (kein Upload, kein KI-Bild), keine Testmail an sich selbst, Material lässt sich im Formular keiner Woche zuordnen, Ordner fehlen.
15. **Notizen nur Klartext** (fett, Listen, Überschriften, Zitat fehlen), Kursname nicht gekürzt, Sprung zum Eintrag ohne Hervorheben. Lea-spezifisch und nur bei Bedarf: Vorschlagskasten "früher geschrieben" und fertige Prompts im Workbook.
