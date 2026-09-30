# 10 Lueckenliste: alter Mitgliederbereich gegen die App, Funktion fuer Funktion

Stand 30.09.2026, App-Stand f337175 auf `main`.

## Warum diese Liste

Der Abgleich in `docs/08-ABGLEICH.md` ging Seite fuer Seite vor und hat Funktionen uebersehen, vor allem solche,
deren Verhalten nicht in den PHP-Dateien liegt, sondern im JavaScript aus den WordPress-Optionen
(`lea_ch_js`, `lea_el_js`, `lea_mic_js` und weitere, 23 Skripte, 89 KB). Diese Liste geht Funktion fuer Funktion
durch alle 225 Dateien von `wp-content/novamira-sandbox/` plus diese Skripte und prueft jede am Code der App.

Fuenf Teilberichte mit den vollstaendigen Tabellen (Funktion, alte Datei, neuer Ort, Status, was fehlt):

| Bereich | Bericht | geprueft | vorhanden | fehlt | teilweise | Website oder Altlast |
|---|---|---:|---:|---:|---:|---:|
| Kursraum und Lektionen | `docs/luecken/kursraum.md` | 130 | 60 | 28 | 38 | 4 |
| Journal, Elemente, Arbeitsbuch, Woche, Projekte | `docs/luecken/journal.md` | 112 | 47 | 31 | 29 | 5 |
| Begleitung, Chat, Termine, Coach, Profil | `docs/luecken/begleitung.md` | 135 | 72 | 24 | 33 | 6 |
| Community, Club, Inhalte, Start, Huelle | `docs/luecken/inhalte.md` | 124 | 64 | 17 | 19 | 24 |
| Mails, Shop, Rechnung, Zugang, Technik | `docs/luecken/mails-shop.md` | 129 | 55 | 26 | 29 | 19 |
| **zusammen** | | **630** | **298** | **126** | **148** | **58** |

Status: vorhanden (gleichwertig da), teilweise (da, ein Teil fehlt), fehlt, Website (bleibt bei WordPress),
Altlast (Reparatur, Backup, Umbauhilfe ohne Nutzen fuer die App).

## Die wichtigsten Luecken quer durch alle Bereiche

Sortiert nach Nutzen fuer Lea und die Teilnehmerinnen. Mehrfach gefundene Punkte sind zusammengefasst.

### A. Das taegliche Arbeiten

1. **Geteiltes aus dem Kurs sieht niemand.** Notizen, Reflexionen und Aufgaben lassen sich "Im Kurs sichtbar" oder
   "In der Community" stellen, gelesen werden sie nur vom Team. Es fehlt der Strom "Geteilt aus dem Kurs" in der
   Community mit Karte, Kommentar und den vier Reaktionen (Reaktionen haben ein Modell, aber keine Oberflaeche).
   Gefunden in drei Berichten. Entweder bauen oder die Optionen streichen.
2. **Wochenaufgaben ohne Art und Wochentag.** Das Herzstueck des Hybrid-Coachings: Aufgabe mit Aktionsknopf
   (Notiz, Reflexion, Frage, Aufzeichnung, Termin), Formular direkt in der Woche mit "Speichern und abhaken",
   Reflexions- und Fragentag als abhakbare Aufgaben, "heute dran / seit Montag offen", Rueckstand aus frueheren Wochen,
   Stift fuer Lea auf der Kursseite. Der Import verwirft `af_art` und `af_tag`.
3. **Team schreibt "fuer Lea".** Andreas Nachrichten stehen unter ihrem Namen, der Push sagt "Andrea hat dir
   geschrieben". Alt: Auftritt als Lea mit Hinweis "Team Lea", Lea sieht "geschrieben von Andrea".
4. **Zugang laeuft ab, Kurs bleibt offen.** `Verkaufen` legt eine direkte Kurs-Mitgliedschaft an, die bei Ablauf
   des Zugangs nie entzogen wird. Bezahlte Inhalte bleiben zugaenglich.
5. **Journal ohne Zeitleiste, Projekte fehlen ganz.** Kein Strom aus Aufgaben, Notizen, Reflexionen, Terminen und
   Aufzeichnungen mit Monats- und Wochenueberschriften; keine Projekte mit den neun Prozessschritten, "Wo ich stehe",
   Teilen, Kommentar am Projekt, Dossier-Reiter. Import von `lea_projekte` und `el_projekt` fehlt, Daten der
   Teilnehmerinnen gehen sonst verloren. Menuepunkte "Meine Zeitleiste" und "Meine Projekte" fehlen.
6. **Lea erfaehrt nicht, wenn eine Notiz oder Aufgabe geteilt wird.** Nur Reflexionen und Antworten stehen in der
   Arbeitsliste; der Chat-Hinweis "Ich teile ... mit dir" mit Nachfassen nach 20 Minuten fehlt.
7. **Filterleiste und Dreipunkt-Menue.** Keine Filter nach Art, Zeit, Projekt, Kurs, Status, "Neue Kommentare";
   Anpinnen, Teilen, Verschieben in Projekt oder Woche nur ueber das Bearbeiten-Formular oder gar nicht.
8. **Aufgaben-Erinnerungen halb.** Nur Push oder Telegram, keine Mail, keine punktgenaue Uhrzeit-Push, "jeden
   Tag"-Aufgaben melden weiter, obwohl der Tag abgehakt ist.

### B. Kursraum und Community

9. **Lebendige Fragen.** Folgen und Stummschalten, @-Erwaehnung, Herz, "beste Antwort", Antwort auf Antwort,
   Bearbeiten, Emoji-Reaktionen, Nachladen, klickbare Links. Abgeschlossene Fragen nehmen weiter Antworten an.
10. **Fragentag fuehrt in den Chat statt zu den Kursfragen** (Startseite und Kursschritt), kein Push am Donnerstag,
    "Frage dazu" aus Uebung, Impuls und Folge nicht vorbefuellt.
11. **Kapitel und Zusammenfassung fuer Lektionsvideos.** `Unit` hat kein Zusammenfassungsfeld, Abschrift und KI laufen
    nur fuer Termine und Material. Materialvideos: Stelle wird gespeichert, aber nicht wieder aufgenommen, kein
    Angeschaut-Status.
12. **Die Woche im Kurs unvollstaendig.** Eigene Reflexion und Fragen der Woche, Status "Geschrieben", grosse Karte
    "Diese Woche", Call-Chip, Wochenband zum Wischen, Sprung zur aktuellen Woche.
13. **Gratiskurs-Strecke.** Haenger-Mails nach 2 und 7 Tagen, Abschlussmail mit Goldnuggets, Push an Lea,
    Newsletter-Haken, "So kommst du wieder herein". Das war die Bindung zwischen Freebie und Klarheitsgespraech.
14. **Meine Kurse als Schaufenster.** Gliederung nach Zugangsart, naechster Call, "Kommt bald", gesperrte Kurse mit
    Kauflink, "Freigeschaltet bis".
15. **Neu-Punkte im Menue** (Impulse, Ressourcen, Community, Termine seit dem letzten Besuch), heute nur Gespraeche
    und Glocke. "Was ist neu" ohne Antworten auf eigene Eintraege.
16. **Einzelsitzungen in Hybrid-Kursen.** Kontingent und "Termin buchen" nur fuer 1:1-Programme; "Sitzungen im
    Paket" nur dort einstellbar.

### C. Begleitung, Termine, Coach

17. **Gaeste und Kontakte fehlen in Coachees-Liste und Suche.** Wer ein Klarheitsgespraech bucht (Rolle guest),
    taucht nicht auf; kein "kam ueber ..." im Dossier. Personen, die nur in bexio stehen, ebenso nicht.
18. **Termin aus Vorschlag und "Termin eintragen" sind halbe Buchungen.** Kein Booking (Verschieben, Absagen),
    kein Google-Kalendereintrag, keine Bestaetigungsmail mit Kalenderdatei, keine Doppelbelegungspruefung,
    kein Knopf "drei freie Zeiten".
19. **Sprachnachrichten ohne Transkript.** Es braucht einen Audio-Dienst (Whisper oder AssemblyAI, der alte Bereich
    hatte einen AssemblyAI-Schluessel), Entscheid noetig.
20. **Buchung:** Bestaetigung ohne Kalenderdatei, Vorab-Antworten nur im Filament-Dossier, Anhaenge beim Buchen
    ("Mitgegeben") und Herkunft fehlen.
21. **Kalender-Feed und Terminliste.** Keine Erinnerung 15 Minuten vorher, Abgesagtes verschwindet, kein Feed je
    Kurs, keine Google- und Outlook-Links; Aufgaben mit Datum, Filter "Was" und Suche fehlen; Lea und Team bekommen
    keine Termin-Erinnerung.
22. **Chat-Kleinigkeiten:** Trenner "Neu", Hervorhebung ungelesener Nachrichten, sofortige Vorschau, Doppelsende-
    Schutz, Textvorschau in der Team-Liste, Marken "wartet" und "gelesen", ein Player gleichzeitig.
23. **Reflexion ohne Kurswoche und Rueckblick** ("Was hattest du dir vorgenommen?"); Wochencheck zaehlt nach
    Erstellzeit.
24. **Foto, Datei und Link an Notizen** (Import laesst `notiz_bild` weg), Notizen nur Klartext; Arbeitsbuch-
    Freigabe laesst sich spaeter nicht aendern.
25. **Auskunft kennt keine Rechnungen und Bestellungen**; Zahlen und Auswertung (Einnahmen, Ziele, KI-Blick) fehlen
    ganz, laut docs/04 bewusst spaeter.

### D. Inhalte

26. **Podcast:** keine Abschrift aus dem Audio fuer neue Folgen, keine Kapitel ohne Handarbeit, keine Nachbarn und
    Verwandten an der Einzelfolge. **Themenfinder** laeuft nicht im Hintergrund (`themen:profil` fehlt im Scheduler).
27. **Ressourcen-Regal:** Filter nach Art und Herkunft, Sortierung, Vorschaubild, Teilen-Knopf, "Gehoert zu",
    Angeschaut-Marke.
28. **Instagram als Impuls-Quelle** (nur, wenn Lea es weiter will). Neuigkeiten: Gruppierung nach Monat, Gelesen-
    Zustand, mehrere Kurse als Zielgruppe.

### E. Verkauf, Zugang, Mails

29. **Kasse rechtlich unvollstaendig.** Kein Widerrufsverzicht mit Zeitstempel, keine Links auf AGB, Datenschutz und
    Widerruf, keine Rechnungsadresse. Vor dem Abschalten von WooCommerce noetig.
30. **Stripe (Karte, Twint), Abos und Kundenportal** fehlen (Etappe 10). Bis dahin nur Kauf auf Rechnung.
31. **Rechnung bei bexio-Ausfall geht verloren.** Kein Nachholen, keine Warnmail, keine Warnung bei ablaufendem Zugang.
32. **Waehrung je Kundin und nach Land** fehlt; im Dossier-Verkauf ist der Preis nicht vorbefuellt, leer heisst
    kostenlos, ohne Sicherheitsabfrage.
33. **Newsletter und Kontakte** (Etappe 11): Formulare, Listen, Einwilligung mit Nachweis, Double-Opt-in, Serien,
    Abmeldung, Testversand. Bis Mailster abgeschaltet wird, bleibt das in WordPress.
34. **Apple- und Google-Anmeldung:** kein Verknuepfen und Trennen im Profil, Apple mit versteckter Mail scheitert,
    Apple-Secret laeuft ab und hat keine Einstellungsseite. E-Mail-Adresse aendern fehlt im Profil.
35. **Alte Mail-Links nach dem Umschalten.** `/mitgliederbereich/...`-Adressen in verschickten Mails landen nur auf
    der Startseite. Zuordnung alt nach neu fehlt in docs/07 und im Code.
36. **Willkommensmail und Abendmail schlichter**, Mailrahmen ohne Logo und Fusszeile, kein Test-Push und keine
    Bestaetigung vor Rundnachrichten. Zahlen fuer Lea (Umsatz Jahr, Monat, offen, beste Kundinnen) fehlen.

## Was bewusst nicht kommt

Website-Anteile (Blog-Stile, Elementor, Menue-Ordnung von WordPress, Woo-Checkout-Design, Altlinks) bleiben bei
WordPress oder entfallen mit Statamic (Etappe 12). Backups, Reparaturen und Umbauhilfen aus dem alten Bereich sind
Altlast. Im Detail in den Teilberichten mit Status "Website" und "Altlast".

## Wie weiter

Vorschlag fuer die Reihenfolge, damit Lea und die Teilnehmerinnen den groessten Unterschied spueren:
1. A1 bis A4 (Geteiltes sichtbar, Wochenaufgaben, Team schreibt fuer Lea, Zugang entziehen).
2. A5 bis A8 und B9 bis B12 (Zeitleiste, Projekte, Fragen, Fragentag, Lektionsvideos, Woche).
3. C17 bis C22 (Gaeste, Buchungen aus dem Chat, Transkript, Kalender, Chat-Feinheiten).
4. E29 bis E32 vor dem Abschalten von WooCommerce, dann Etappe 10 und 11 wie in `docs/06-ROADMAP.md`.
