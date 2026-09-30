# G1 Kursraum und Lektionen: Lueckenliste alt gegen neu

Stand 30.09.2026. Alle Aussagen zur neuen App sind am Code geprueft (Routen, Controller, Views, Modelle, `public/js/app.js`, Migrationen, Import). Der fruehere Abgleich `docs/abgleich/kursraum.md` ist teils ueberholt (Fragen, Statusmodell, Callwunsch, Material an Einheiten, Videoposition, Wochenseite gibt es inzwischen) und wurde nicht uebernommen, ohne nachzuschauen.

## Gelesene Dateien (alt)

| Datei | Zweck |
|---|---|
| lea-kursraum.php | Fragen, Austausch, Sichtbarkeit, Mails |
| lea-kursraum-plus.incphp | Antworten, Herz, Folgen, beste Antwort |
| lea-kursraum-reaktionen.php | Emoji-Reaktionen, Status, Frage loeschen |
| lea-lektion-kapitel.php | KI-Kapitel unter dem Lektionsvideo |
| lea-lektion-popup.php | Lektion als Ueberlagerung im Kurs |
| lea-lektion-seite.php | Lektionsseite Selbstlernkurs, Band, Material |
| lea-lektion-uebung.php | Arbeitsbuch-Uebungen in der Lektion |
| lea-kurs-automatik.php | Termin-Erinnerung, Aufzeichnungs-Hinweis, Kurs-Beitraege |
| lea-kurs-edit.php | Woche direkt auf der Kursseite bearbeiten |
| lea-kurs-infos.php | Infos von Lea im Kurs |
| lea-kurs-kopf.php | Bildband, Kursfarbe, Einzelsitzungs-Hinweis |
| lea-kurs-module.php | Kursseite Selbstlernkurs: Module, Stand |
| lea-kurs-shop.php | Zugang ueber Plan, Ablauf, Preise |
| lea-kurs-teilen.php | Uebung teilen, Frage an Lea |
| lea-kurs-zeitstrahl.php | Wochen-Zeitstrahl, Call, Meine Woche |
| lea-module.php | Bereiche an und aus |
| lea-meine-kurse.php | Kursuebersicht nach Zugangsart |
| lea-player.php | Abspielfenster, Sprungmarken, Angeschaut |
| lea-club-lektion.php | Lektions- und Kursseite, Aufgaben |
| lea-coach-kurs.php | Coach-Reiter Kurs, Ampel, Freigaben |
| lea-ausbildung-bau.php | Elementor-Seitenbaukasten (Hilfscode) |
| lea-prozessschritte.php | Zehn Prozessschritt-Videos |
| lea-liebesbrief-praxis.php | 21-Tage-Praxis nach dem Brief |
| lea-anfang-abschluss.php | Abschlussmail Goldnuggets, Push an Lea |
| lea-anfang-anmeldung.php | Gratiskurs: Konto per Einmal-Link |
| lea-anfang-strecke.php | Haenger-Mails nach 2 und 7 Tagen |
| lea-selbsttest.php | Ampel-Selbsttests (Freebie-Trichter) |
| lea-brief-mitnehmen.php | Brief als PDF und Text mitnehmen |

JS aus WordPress-Optionen, dazu gelesen: `lea_ke_js.js` (Wochen-Editor, gehoert zu lea-kurs-edit), `lea_pl_js.js` (Abspielfenster, gehoert zu lea-player). Zusaetzlich `lea_pm_js.js` (Drei-Punkte-Menue), `lea_tl_js.js` (Mehr lesen), `lea_c1_js.js` (Coaching-Seite), `lea_aufbau_js.js` (Formulare einklappen): laut `get_option` gehoeren sie nicht zu meinen PHP-Dateien (`lea-coaching.php`, `lea-timeline.php`, `lea-seitenaufbau.php`), nur `lea_aufbau_js` und `lea_pm_js` greifen auch in den Kursraum, dort ist es unten vermerkt.

Neue App, dagegen geprueft: `routes/web.php`, `KursController`, `EinheitController`, `UebungController`, `FragenController`, `MedienController`, `TermineController`, `MaterialController`, `GespraechController`, `KaufenController`, `resources/views/kurse/*`, `fragen/show`, `community*`, `termine/show`, `material/show`, `home`, `app/Programs/*`, `app/Models/{Unit,Exercise,Progress,MediaPosition,Program,ProgramStep,Question,Task,Reaction}`, `app/Filament/Coach/Resources/Programs/*` und `Tasks`, `app/Notifications/Runden`, `app/Recordings/*`, `app/Shop/*`, `app/Coach/*`, `app/Support/{Kapitel,Video}`, `app/Import/WordPress/{ProgramsImport,BegleitungImport}`, `public/js/app.js`.

Status: vorhanden, teilweise, fehlt, Website, Altlast.

## Tabelle

### A. Kursraum: Fragen und Austausch

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Frage stellen mit Titel und Text | lea-kursraum | `FragenController::store`, `kurse/fragen.blade.php` | vorhanden | |
| Wohin die Frage geht: Allgemein, ein Kurs, nur Lea | lea-kursraum | Feld `visibility` program oder coach, Frage gehoert immer zu genau einem Programm | teilweise | "Allgemein" (alle Kurse der Community sehen sie) gibt es nicht. Selbstlernkurse haben keinen eigenen Raum, "In der Community" meint dort nur dieselben Teilnehmerinnen des Programms. |
| Etwas anhaengen (Aufgabe, Notiz, Reflexion, Termin, Material) mit Suchfeld | lea-kursraum | `<x-anhang-wahl>`, `App\Support\Anhaenge` | vorhanden | |
| Vorbefuellte Frage aus "In der Community teilen" (frage_titel, frage_text, frage_ref) und aus "Frage an Lea und die anderen" (betreff) | lea-kursraum, lea-kurs-teilen | nichts | fehlt | Das Frageformular liest keine Adress-Parameter, in Impulsen und Uebungen gibt es keinen solchen Knopf. |
| Entwurf der Frage im Browser merken | lea-kursraum | `form[data-entwurf]` in `app.js` | vorhanden | |
| Diktieren per Mikrofon in Frage und Antwort | lea-kursraum | `app.js` "Diktieren" an `textarea.feld` | vorhanden | |
| Formular "Neue Frage" eingeklappt, klappt bei Klick auf | lea_aufbau_js | `<details>` in `kurse/fragen.blade.php` | vorhanden | |
| Fragenliste mit Autorin, Zeit, Antwortzahl, Status-Chip | lea-kursraum | `kurse/fragen`, `community.blade.php` | vorhanden | Avatar der Autorin fehlt in der Liste. |
| Filter und Sortierung der Liste (Art, Status, meine, privat, Callwunsch, Neue Kommentare; Sortierung neu, alt, meiste Antworten; Suchfeld) | lea-kursraum | Pillen Alle, Offen, Fuer den Call, Beantwortet | teilweise | Keine Suche, keine Sortierung, kein Filter "meine Fragen" oder "Neue Kommentare". |
| Geteilte Notizen, Reflexionen, Aufgaben (Sicht Kurs) im selben Strom wie die Fragen | lea-kursraum | Sicht `program` gibt es an Notiz, Reflexion, Aufgabe, aber kein Strom | fehlt | Andere Teilnehmerinnen sehen nie, was jemand mit dem Kurs teilt. `community.blade.php` listet nur Fragen. |
| Status in fuenf Zustaenden, Lea setzt per Auswahl, Chip | lea-kursraum-reaktionen | `Question::STATUS`, `FragenController::status` | vorhanden | |
| Status springt auf "beantwortet", wenn die Coachin antwortet | lea-kursraum-plus | `FragenController::antworten` | vorhanden | Gilt auch fuer Team. |
| Callwunsch "Bitte im Call besprechen" mit Zaehler, "3x fuer den Call gewuenscht" in der Liste | lea-kursraum-reaktionen | `FragenController::call`, `Question::CALLWUNSCH` | vorhanden | |
| Emoji-Reaktionen (Sehe ich auch so, Die Frage habe ich auch, Das bewegt mich) an der Frage, mit Hinweis an die Autorin | lea-kursraum-reaktionen | `Reaction::EMOJIS` nur an Chat-Nachrichten | fehlt | An Fragen gibt es nur den Callwunsch. |
| Antworten auf eine Frage | lea-kursraum | `FragenController::antworten` | vorhanden | |
| Antwort auf eine Antwort (eine Ebene) | lea-kursraum-plus | nichts | fehlt | Antworten sind flach. |
| Herz an einer Antwort mit Hinweis an die Autorin | lea-kursraum-plus | nichts | fehlt | |
| Lea markiert "Das ist die Antwort" | lea-kursraum-plus | nichts | fehlt | |
| Eigene Antwort bearbeiten (15 Minuten) und loeschen | lea-kursraum-plus | `antwortLoeschen`, `CommentPolicy` | teilweise | Loeschen durch Autorin und Team geht jederzeit, Bearbeiten gibt es nicht. |
| Antwort der Coachin hervorgehoben | lea-kursraum-plus | Chip "Team" und Hintergrund in `fragen/show` | vorhanden | |
| Lange Antworten einklappen, Links im Text klickbar | lea-kursraum-plus | `{{ $a->body }}` als reiner Text | fehlt | Weder Weiterlesen noch Verlinkung. |
| Antworten sortieren (aelteste, neueste, meist geherzt), neue seit dem letzten Besuch hervorheben | lea-kursraum-plus | nichts | fehlt | |
| Live nachladen mit Knopf "2 neue Antworten anzeigen" | lea-kursraum-plus | nichts (nur der Gruppenchat laedt nach) | fehlt | |
| Frage folgen und stummschalten | lea-kursraum-plus | nichts | fehlt | Meldung geht nur an Fragestellerin, bisherige Antwortende und Team. |
| @-Erwaehnung mit Namensvorschlaegen und Meldung an die Erwaehnte | lea-kursraum | nichts, auch nicht im Chat | fehlt | |
| Frage abschliessen, danach keine Antworten mehr | lea-kursraum-plus | Status "Abgeschlossen" setzbar | teilweise | `antworten()` prueft den Status nicht, das Antwortfeld bleibt offen. |
| Frage loeschen durch Autorin oder Lea | lea-kursraum-reaktionen | `FragenController::destroy` | vorhanden | |
| Meldung bei neuer Frage an Kurs bzw. Community bzw. nur Lea | lea-kursraum | `store` meldet nur dem Team | teilweise | Die Gruppe erfaehrt von neuen Fragen nur ueber die Liste, nicht per Push. |
| Meldung bei Antwort: Fragestellerin, Antwortende, Erwaehnte | lea-kursraum | `antworten` (ohne Erwaehnte) | vorhanden | Erwaehnte und Folgende siehe oben. |
| Donnerstag 9 Uhr Push "Heute ist Fragentag" an den Kurs | lea-kursraum | nichts | fehlt | Fragentag ist nur ein Ganztagstermin (`question_day`), `Runden::terminErinnerungen` nimmt Ganztagstermine aus. |
| Donnerstag Sammelmail an Lea: offene Fragen fuer den Call | lea-kursraum | `Runden::fragenSammelmail` (Tag einstellbar, nach Callwuenschen sortiert) | vorhanden | |
| Fragentag-Knopf "Frage stellen" | lea-kurs-zeitstrahl | `kurse/schritt` und `home` fuehren zu `gespraech.index` | teilweise | Fuehrt ins 1:1-Gespraech statt zu den Kursfragen. |
| Austausch-Chat der Gruppe je Kurs | lea-kursraum | `kurse.austausch` → `Chat::groupFor` | vorhanden | Laedt alle 5 Sekunden oder per Reverb nach. |
| "Lea privat fragen" und Bereich "Private Fragen" | lea-kursraum, lea-kursraum-plus | 1:1-Gespraech, Sicht "Nur Coachin" | vorhanden | |
| "Wer ist dabei" mit Anzahl | lea-kursraum | `community.leute` | vorhanden | Anzahl im Knopf fehlt. |
| Alte Fragen und Antworten uebernehmen | lea-kursraum | `BegleitungImport::importQuestions` | vorhanden | Nicht importiert: Callwuensche (`lea_kr_reaktionen`), Herzen, "beste Antwort", Folgen, Antwort-Baum. |
| Aktionen Bearbeiten und Loeschen hinter Drei-Punkte-Menue | lea_pm_js | Menue nur an Aufgaben (`aufgaben/_karte`) | teilweise | Bei Fragen und Antworten stehen offene Textknoepfe. Nur Optik. |

### B. Kursuebersicht und Kursseite

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Liste meiner Kurse mit Bild, Titel, Untertitel, Balken | lea-meine-kurse | `kurse/index.blade.php` | vorhanden | |
| Gliederung: Dein Programm, Coffee & Coaching, Im Club enthalten, Programme mit Begleitung | lea-meine-kurse | Gruppen Kurse, Arbeitsbuecher, Begleitungen | teilweise | Keine Gliederung nach Zugangsart, kein eigener Block Coffee & Coaching oder Club. |
| Grosse Karte fuers laufende Programm (Bild, "Diese Woche", Balken, naechster Call, Knopf) | lea-meine-kurse | `home.blade.php` "Diese Woche im Kurs" | teilweise | Der naechste Call steht nicht in der Karte, sondern als eigener Block darunter. |
| Gesperrte Kurse mit Schloss, Preis, Kauf- oder Club-Link, "Kommt bald" | lea-meine-kurse | `/angebote` (`AngeboteController`) | teilweise | Kaufbare Angebote stehen auf eigener Seite, nicht als gesperrte Karten in der Kursliste, kein "Kommt bald". |
| "Freigeschaltet bis ..." an der Kurskarte, "Dein Club ist aktiv bis" | lea-meine-kurse | `profil.blade.php` zeigt "Zugang bis" | teilweise | Nicht an der Kurskarte. |
| Leerer Zustand mit Knopf "Sag mir Bescheid" (Mail an Lea) | lea-meine-kurse | `x-leer` in `kurse/index` | teilweise | Text ohne Knopf, keine Interessensmeldung. |
| Kurse in Vorbereitung nur fuers Team sichtbar | lea-meine-kurse | `Program::is_internal`, `ProgramAccess` | vorhanden | Die Ausnahme "angekuendigte Kurse allen zeigen" fehlt. |
| Kurs ohne Module (Coffee & Coaching) zeigt "Naechste Termine" | lea-meine-kurse | `kurse/show`: nur ein naechster Call | teilweise | Keine Liste der naechsten acht Termine. |
| Bildband mit Kursbild, Titel, Untertitel | lea-kurs-kopf | `kurse/show` (`bildband`) | vorhanden | Titelbild nur als URL-Feld, kein Upload. |
| Kursfarbe fuer Balken, Tags, Punkte | lea-kurs-kopf | `programs.color`, `--kc` | vorhanden | |
| Kursbeschreibung unter dem Kopf | lea-meine-kurse | `description` in "Worum es geht" | vorhanden | Standardmaessig eingeklappt. |
| Stand "x von y erledigt" mit "Weitermachen" oder "Los geht es" | lea-kurs-module, lea-club-lektion | `ProgressTracker::summary`, `kurse/show` | vorhanden | |
| Module mit Lektionen und Zaehler je Modul | lea-kurs-module | Schritte mit Einheiten, "2/5" | vorhanden | |
| Modul mit einer Lektion als flacher Weg "01, 02 ...", Marke "Hier gehts weiter" | lea-kurs-module | `kurse/show` Schrittabschnitte | teilweise | Keine Nummerierung, keine Weiter-Marke bei Einheiten. |
| Modulbeschreibung unter dem Modultitel | lea-kurs-module | `summary` nur auf `kurse/schritt` | teilweise | Nicht in der Uebersicht. |
| Anzahl Videos je Lektion in der Zeile | lea-kurs-module | `_einheit-zeile` | vorhanden | |
| Hinweis "3 Einzelsitzungen im Kurs enthalten, 2 offen" plus "Termin buchen" | lea-kurs-kopf | `Lage::kontingent`, `kurse/show` | teilweise | Nur fuer 1:1-Programme. `sitzungen_gesamt` ist im Programmformular nur bei Art 1:1 sichtbar, ein Hybrid-Kurs mit Einzelsitzungen kann das nicht. |
| Infos von Lea im Kurs (3 neueste, "2 neu", Punkt, "Alle ansehen") | lea-kurs-infos | `kurse/show` Block "Infos von ..." | teilweise | Kein Ungelesen-Zaehler und -Punkt, kein "Alle ansehen". |
| Kurs-Beitraege nur fuer Teilnehmerinnen des Kurses | lea-kurs-automatik | `Post` Sicht `program`, `Inhalte::postsQuery` | vorhanden | |
| Karte "Naechster Gruppencall" oben im Kurs | lea-kurs-zeitstrahl | `x-termin-karte` in `kurse/show` | vorhanden | |
| Schnellwege Frage stellen, Reflexion schreiben, Meine Aufgaben | lea-kurs-zeitstrahl | Zeilen "Fragen an ..." und "Austausch" | teilweise | Reflexion und Aufgaben fehlen als Schnellweg auf der Kursseite. |
| Kalender-Abo je Kurs | lea-kurs-zeitstrahl | `kalender.abo` (Termine, Profil) | teilweise | Ein Abo fuer alles, nicht auf der Kursseite und nicht je Kurs. |
| Live-Termine, Aufzeichnungen und Material des Kurses auf der Kursseite | lea-club-lektion | `material.index` mit Kursfilter | teilweise | Kursseite selbst zeigt nur Wochen und Einheiten. |
| Schritt-Punkte als Sprungleiste, aufklappbare Schrittkarten, Tag "Diese Woche" | lea-kurs-zeitstrahl | Wochenliste mit Chip "Jetzt dran" | teilweise | Keine Punkteleiste, keine aufklappbaren Karten, jede Woche ist eine eigene Seite. |
| Wochenband zum Wischen, Knopf "Zur aktuellen Woche" | lea-woche (aus lea-kurs-zeitstrahl) | Pfeile in `kurse/schritt` | fehlt | |
| Wochen schalten sich zeitlich frei | lea-kurs-zeitstrahl | `ProgramStep::unlocks_at`, `isUnlocked` | vorhanden | |
| Fortschritt "Schritt 3 von 8, diese Woche" | lea-kurs-zeitstrahl | "Woche x von y", Balken ueber Einheiten | teilweise | |
| Coach bearbeitet Titel, Einleitung, Wochenaufgaben direkt auf der Kursseite (Stift, Sheet) | lea-kurs-edit, lea_ke_js | Filament `StepsRelationManager`, `TaskResource` | teilweise | Kein Stift in der Teilnehmeransicht. Art der Aufgabe (Abhaken, Notiz, Reflexion, Frage, Aufzeichnung, Termin) und Wochentag sind im Datenmodell nicht vorhanden (`tasks` ohne Art und Tag), damit auch kein passender Knopf auf der Karte. |
| Coach legt Termin und Ressource direkt im Kurs an | lea-kurs-zeitstrahl | Filament `EventResource`, `MaterialResource` | teilweise | Nur im Coach-Bereich. |

### C. Woche im Kurs (Hybrid)

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Call der Woche: vorher Zoom, nachher Aufzeichnung, "Aufzeichnung kommt" | lea-kurs-zeitstrahl | `kurse/schritt` mit `x-termin-karte` | vorhanden | |
| Wochenaufgaben der Coachin und eigene Aufgaben abhaken | lea-kurs-zeitstrahl | `kurse/schritt` (Tasks mit `step_id`), Formular "Was nimmst du dir vor" | vorhanden | Im Wochenformular gibt es kein "taeglich" (nur unter Aufgaben). |
| Eigene Aufgaben taeglich mit sieben Tagesfeldern | lea-kurs-zeitstrahl | `aufgaben/_karte`, `Task::daysDone` | vorhanden | |
| Alte persoenliche To-dos uebernehmen (`lea_meine_todos`) | lea-kurs-zeitstrahl | `ProgramsImport` liest sie nicht | fehlt | Datenverlust beim Umschalten. |
| Reflexionstag: Zeile mit Status "Geschrieben", Reflexion der Woche direkt unter der Zeile | lea-kurs-zeitstrahl | Zeile mit Link zu `reflexion.index` | teilweise | Kein Status, die eigene Reflexion der Woche wird nicht gezeigt. |
| Eigene Fragen der Woche direkt in der Woche | lea-kurs-zeitstrahl | nichts | fehlt | |
| Material der Woche und ihrer Lektionen | lea-kurs-zeitstrahl | `kurse/schritt` "Material" | vorhanden | |
| Reflexionstermin leitet direkt in die Reflexion | lea-kurs-zeitstrahl | `termine/show` zeigt die Terminseite | teilweise | Weiterleitung fehlt, die Schrittseite verlinkt aber. |

### D. Lektion bzw. Einheit

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Kopf mit Kurs, Zurueck, "Lektion 3 von 12, 2 Videos, erledigt" | lea-club-lektion, lea-lektion-seite | `kurse/einheit.blade.php` | vorhanden | |
| Band mit allen Schritten des Kurses (Nummern, Haken) plus Pfeile | lea-lektion-seite | nichts | fehlt | Nur Zurueck und Weiter sowie "n von m". |
| Video, ab zwei Videos Playlist | lea-club-lektion | `.video-wahl` in `einheit` und `app.js` | vorhanden | |
| Video merkt die Stelle ("Du warst bei 12:30"), ab 80 Prozent erledigt | lea-stand (gebraucht in lea-player) | `MedienController::position`, `MediaPosition` | vorhanden | Stelle gilt je Einheit, nicht je Video der Playlist. |
| Kapitelliste unter dem Lektionsvideo aus der KI-Zusammenfassung, Klick springt Vimeo an | lea-lektion-kapitel | `x-kapitel` nur bei Termin, Material, Podcast | fehlt | `Unit` hat kein Zusammenfassungsfeld, `MaterialVideo` und `Wache` bedienen nur Material und Termine. |
| "Zum Nachlesen, worum es ging" (aufklappbar) | lea-lektion-kapitel | wie oben | fehlt | |
| Einleitung "Von Lea" mit Avatar | lea-lektion-seite | `unit.intro` mit Avatar | vorhanden | |
| Text der Lektion | lea-club-lektion | `unit.body` | vorhanden | |
| Arbeitsblatt als PDF eingebettet, Audio mit Player, "Gross ansehen" | lea-lektion-seite | `kurse/_material.blade.php` | vorhanden | |
| Material als kompakte Zeilen mit Dauer und Download | lea-club-lektion | `_material` als Karten | vorhanden | |
| "Termin dazu" an der Lektion | lea-club-lektion | `events.unit_id` existiert, wird nirgends gesetzt oder gezeigt | fehlt | |
| Aufgaben einzeln abhaken mit Zaehler | lea-club-lektion | Uebungsteil `checkbox`, Zaehler `data-uebung-voll` | vorhanden | |
| Seitenspalte "Dein Fortschritt" neben dem Inhalt | lea-club-lektion | nichts in der Einheit | teilweise | Fortschritt nur auf der Kursseite. |
| Erledigt-Knopf, "Naechste Lektion", vorige Lektion | lea-club-lektion | `kurse/einheit` unten | vorhanden | |
| Lektion als Popup im Zeitstrahl | lea-lektion-popup | eigene Seite `kurse.einheit` | vorhanden | Bewusst eigene Seite statt Ueberlagerung. |
| Uebungen des Kursarbeitsbuchs direkt in der passenden Lektion | lea-lektion-uebung | Uebungsteile an jeder Einheit moeglich | teilweise | Der Import legt Arbeitsbuecher als eigenes Programm (`workbook`) an und wertet die Zuordnung `lektion` nicht aus. |
| Uebung pro Einheit mit Lea teilen, einmalige Freigabe "alles oder einzeln" | lea-kurs-teilen | `EinheitController::teilen`, `KursController::freigabe` | vorhanden | |
| "Frage an Lea und die anderen" direkt an der Uebung, Titel vorbefuellt | lea-kurs-teilen | nichts | fehlt | Einheit hat keinen Link zu den Kursfragen. |
| Notiz zur Lektion | lea-workbook-notizen | `EinheitController::notiz` | vorhanden | |
| 21-Tage-Praxis: Knopf, taegliche Aufgabe, Tag x von 21, Hinweis Tag 7 bis 10 | lea-liebesbrief-praxis | Uebungsteil `practice`, `UebungController::praxis` | teilweise | Tag-21-Hinweis und Link auf die Lektion "Wenn es zaeh wird" fehlen. |
| Brief als PDF und Text mitnehmen (Titel "von Name", Datum, Fusszeile) | lea-brief-mitnehmen | Uebungsteil `takeaway` (Kopieren, Drucken oder als PDF) | teilweise | Sammelt alle Listen-, Paar- und Briefantworten des ganzen Programms statt gezielt Brief, Standards, Ankersaetze. Kein Titel mit Name, kein Datum, keine Fusszeile, kein PDF-Download vom Server (nur Browser-Druck). |
| Goldnuggets mitnehmen (Anfang-Kurs) | lea-brief-mitnehmen | wie oben | teilweise | Gleiche Einschraenkung. |

### E. Abspielfenster und Aufzeichnungen

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Abspielfenster ueberall (Kurs, Journal, Ressourcen) mit Video, Fakten, Zusammenfassung | lea-player | `termine/show`, `material/show` als Seite | vorhanden | Seite statt Fenster. |
| Sprungmarken "(ab 12:34)" und Kapitelliste, Markierung laeuft mit | lea-player, lea_pl_js | `Kapitel::spruenge`, `x-kapitel`, `medien:zeit` | vorhanden | |
| Automatisch "angeschaut" ab 80 Prozent | lea_pl_js | `MedienController` fuer Termin und Einheit | teilweise | Bei Material-Videos wird nur die Stelle gespeichert, kein Status. |
| Knopf "Als angeschaut markieren", "Nochmal ansehen" | lea-player | `termine.gesehen` ("Gesehen", "Ich war live dabei") | teilweise | Fehlt bei Material. Beschriftung "Nochmal ansehen" gibt es nicht. |
| Weiter ab gemerkter Stelle | lea_pl_js | Termin und Einheit | teilweise | `material/show` liest die Stelle nicht, obwohl sie gespeichert wird. |
| Stand-Balken am Ansehen-Knopf in Listen | lea-player | `MediaPosition::prozent()` ungenutzt | fehlt | |
| "Dazu passend": Material zum Termin unter der Aufzeichnung | lea-player | `termine/show` "Material zum Termin" | vorhanden | |
| Video-Formate Vimeo, YouTube, Bunny | lea-player | `App\Support\Video::embed` | vorhanden | |
| Zugriff nur fuer Personen des Kurses | lea-player | `Begleitung::canViewEvent`, `canViewResource` | vorhanden | |

### F. Automatik, Zugang, Bereiche

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Termin-Erinnerung am Tag (9 Uhr) und eine Stunde vorher | lea-kurs-automatik | `Runden::terminErinnerungen` | vorhanden | |
| "Aufzeichnung ist da" mit Wahl Mail und Push, mit Zusammenfassung | lea-kurs-automatik | `Recordings\Freigabe` | vorhanden | |
| Kopie der Kurs-Mails an Lea und Team | lea-kurs-automatik (`lea_ka_mitlesen`) | nichts | fehlt | |
| Passwort-Link 14 Tage gueltig | lea-kurs-automatik | Magic Link | Altlast | Kein Passwort noetig. |
| Hinweis in der Beitragsliste "Automatische Erinnerung" | lea-kurs-automatik | nichts | Altlast | |
| Kurse ueber Mitgliedschaftsplan freischalten (Plan → Kurse) | lea-kurs-shop | `Offer.programs`, `Entitlement`, `WooCommerceController` | vorhanden | |
| Ablauf oder Kuendigung nimmt den Kurs wieder weg | lea-kurs-shop | `ProgramAccess` mit `Entitlement::current()` | teilweise | Wird ein Angebot ueber `Verkaufen` (Dossier, Kasse, Assistent) vergeben, legt `programmeGeben` eine direkte `ProgramMember`-Zeile an. Die bleibt nach Ablauf des Zugangs bestehen und gibt weiter Zugriff. Nur Woo-Zugaenge ueber Entitlement laufen sauber ab. |
| Manueller Zugang von Hand | lea-kurs-shop | `Verkaufen`, `ZugangGeben`, Dossier | vorhanden | |
| Preise Einzelkurs und Club, Waehrung | lea-kurs-shop, lea-meine-kurse | `Offer::preise`, `/kaufen/{slug}` | vorhanden | |
| Bereiche an und aus (Projekte, Zeitleiste, Community, Ressourcen) ohne Loeschen | lea-module | Pennant-Feature `community` definiert, aber nirgends abgefragt, kein Schalter | fehlt | Menue zeigt Community immer. |

### G. Coach-Seite zum Kurs

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Reiter "Kurs und Aufgaben" je Person, Woche fuer Woche (Call mit Anwesenheit, Aufgaben, Reflexion, Frage) | lea-coach-kurs | `coachees/dossier/_kurs`, `_termine`, `_aufgaben` | teilweise | Kurs-Reiter zeigt nur "x von y Einheiten". Anwesenheit steht in der Terminzeile, aber kein Wochenblick mit Aufgabenstand und Fragen der Person. |
| Ampel ueber alle mit Grund, Zahlen, Kurz nachfragen | lea-coach-kurs | `Lage`, `AmpelWidget`, `coachees` | vorhanden | Schwellen weichen ab: eine offene Aufgabe oder ein verpasster Call macht nicht mehr gelb, erst zwei. Zahlen "3/5 Aufgaben, 2/3 Calls (1 live, 1 Aufzeichnung)" fehlen in der Zeile. |
| Entwurfstexte fuer "kurz nachfragen" | lea-coach-kurs | `Lage::entwurf` | vorhanden | |
| "Fuer dich freigegeben" mit "neu"-Zaehler | lea-coach-kurs | `Arbeitsliste` "Mit dir geteilt", `dossier/_geteilt` | vorhanden | Notizen fehlen in der Arbeitsliste (nur Antworten, Reflexionen, Aufgaben). |
| Einzelsitzungs-Kontingent je Person | lea-coach-kurs, lea-kurs-kopf | `Lage::kontingent` | vorhanden | |

### H. Gratiskurs, Selbsttest, Sonstiges

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| Gratiskurs holen: E-Mail eingeben, Konto entsteht, Einmal-Link kommt | lea-anfang-anmeldung | `/kaufen/{slug}` fuer kostenlose Angebote (`KaufenController`, `Verkaufen`, Willkommensmail) | teilweise | Name und AGB-Haken sind Pflicht, sonst gleich. Wie lange der Link gilt und ob er mehrfach geht: nicht geprueft. |
| Bremse gegen Massenanfragen | lea-anfang-anmeldung | `throttle:6,10`, Honigtopf | vorhanden | |
| Newsletter-Haken mit Bestaetigungsmail (Mailster) | lea-anfang-anmeldung | nichts | fehlt | Kein Newsletter-Anschluss in der App. |
| Haenger-Check: Mail nach 2 Tagen, letzter Anstoss nach 7 Tagen, Stopp-Link | lea-anfang-strecke | nichts | fehlt | |
| Abschlussmail mit den drei Goldnuggets, Klarheitsgespraech, Hinweis auf festen Zugang | lea-anfang-abschluss | nichts | fehlt | |
| Push an Lea "Jemand hat den Anfang durch" | lea-anfang-abschluss | nichts | fehlt | |
| Hinweis auf der letzten Seite "Deine Goldnuggets kommen per Mail" | lea-anfang-abschluss | nichts | fehlt | |
| Selbsttests (Gedankensturm, ADHS) mit Ampel und Ergebnis mit Lea teilen | lea-selbsttest | nichts | Website | Trichter der oeffentlichen Seite. Was fehlt: Uebergabe des Ergebnisses an ein App-Konto und Push an Lea. |
| Zehn Prozessschritt-Videos (0 bis 9) | lea-prozessschritte | Kurs mit Einheiten und Vimeo-Videos ist moeglich | teilweise | Die zehn Videos sind im Code nicht angelegt. Ob sie als Programm in der Datenbank stehen: nicht geprueft. |
| Elementor-Seitenbaukasten | lea-ausbildung-bau | nichts | Altlast | Reiner WordPress-Hilfscode. |

## Wichtigste Luecken

1. **Zugang laeuft ab, Kurs bleibt offen.** Alles, was ueber `Verkaufen` vergeben wird (Dossier, Kasse, Assistent), legt eine direkte `ProgramMember`-Zeile an, die nie entzogen wird. Bezahlte Inhalte bleiben nach Ablauf zugaenglich.
2. **Kapitel und Zusammenfassung fuer Lektionsvideos.** `Unit` hat kein Zusammenfassungsfeld, Vimeo-Abschrift und KI laufen nur fuer Termine und Material. Kapitelspruenge und "Zum Nachlesen" fehlen in Lektionen.
3. **Wochenaufgaben pflegen wie vorher.** Kein Stift auf der Kursseite, Aufgaben ohne Art (Notiz, Reflexion, Frage, Aufzeichnung, Termin) und ohne Wochentag, damit auch kein passender Knopf an der Karte und keine Erinnerung am richtigen Tag.
4. **Lebendige Fragen.** Fehlen: Folgen und Stummschalten, @-Erwaehnung, Herz, "beste Antwort", Antwort auf Antwort, Bearbeiten, Emoji-Reaktionen, Live-Nachladen, Weiterlesen und klickbare Links.
5. **Fragentag.** Kein Push am Donnerstag, und der Knopf "Frage stellen" am Fragentag fuehrt ins 1:1-Gespraech statt zu den Kursfragen. Abgeschlossene Fragen nehmen weiter Antworten an.
6. **Gratiskurs-Strecke.** Haenger-Mails nach 2 und 7 Tagen, Abschlussmail mit Goldnuggets und der Push an Lea sind nicht da, ebenso der Newsletter-Haken. Das war die Bindung zwischen Freebie und Klarheitsgespraech.
7. **Gemeinsamer Strom in der Community.** Was Teilnehmerinnen mit dem Kurs teilen (Notizen, Reflexionen, Aufgaben), sehen andere nie. Dazu Suche, Sortierung und Filter in der Fragenliste sowie "Allgemein" ueber alle Kurse.
8. **Die Woche im Kurs vollstaendig.** Eigene Reflexion und eigene Fragen der Woche, Status "Geschrieben", Schnellwege, Wochenband zum Wischen, Sprung zur aktuellen Woche.
9. **"Frage an Lea und die anderen" aus Uebung und Impuls**, vorbefuellt (Titel, Bezug). Damit fehlt der kurze Weg von der Uebung in die Community.
10. **Materialvideos.** Stelle wird gespeichert, aber nicht wieder aufgenommen; kein Angeschaut-Status, kein Balken in Listen (`MediaPosition::prozent()` ungenutzt).
11. **Einzelsitzungen in Hybrid-Kursen.** Anzeige "3 enthalten, 2 offen" und "Termin buchen" gibt es nur fuer 1:1-Programme; `sitzungen_gesamt` ist im Formular nur dort einstellbar.
12. **Meine Kurse als Schaufenster.** Gliederung nach Zugangsart, Karte mit dem naechsten Call, "Kommt bald", gesperrte Kurse mit Kauflink, "Freigeschaltet bis", "Sag mir Bescheid" bei leerem Stand.
13. **Alte persoenliche To-dos (`lea_meine_todos`)** werden nicht importiert.
14. **"Termin dazu" an der Lektion** (`events.unit_id` ist da, wird aber nie gesetzt oder gezeigt) und das Lektionsband mit allen Schritten.
15. **Kleinigkeiten:** Kopie der Kurs-Mails an Lea und Team, gezielter "Brief mitnehmen" mit Name, Datum und PDF, Schalter fuer Bereiche an und aus (Pennant `community` existiert ohne Wirkung), Tag-21-Hinweis der Praxis.
