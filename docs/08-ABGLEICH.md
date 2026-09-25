# 08 Abgleich: alter Mitgliederbereich gegen neue App

Stand 25.09.2026. Gelesen wurden alle 190 aktiven Dateien in `wp-content/novamira-sandbox` (rund 35 000 Zeilen) und die eigenen Plugins `novamira-coaching` (Buchung), `novamira-aufzeichnungen` (Vimeo), `novamira-zoom-anwesenheit` und `novamira-telegram`. Dazu kommen Bildschirmfotos beider Seiten mit dem Demokonto. Die Detailberichte liegen in `docs/abgleich/`: je Funktion eine Zeile mit Status (gleich, vereinfacht, besser, fehlt, bewusst weg), die Fundstelle in der App und der Weg zur Umsetzung.

| Bereich | Bericht |
|---|---|
| Hülle, Startseite, Navigation, Anmeldung, Design | [huelle-design.md](abgleich/huelle-design.md), mit vollständiger Design-Spezifikation |
| Kursraum, Kurse, Lektionen, Workbook, Woche | [kursraum.md](abgleich/kursraum.md) |
| Begleitung und Coach-Werkzeuge | [begleitung.md](abgleich/begleitung.md) |
| Chat, Benachrichtigungen, Zoom, Vimeo, Buchung, Telegram | [kommunikation.md](abgleich/kommunikation.md) |
| Impulse, Podcast, Shop, Verwaltung, übrige Dateien | [inhalte-shop.md](abgleich/inhalte-shop.md) |

## Ergebnis in einem Satz

Das Gerüst ist vollständig und an vielen Stellen besser: Datenmodell, Zugänge, Chat, Push, Telegram, Rollen, Mandantenfähigkeit und Coach-Bereich. Aber die Optik weicht deutlich ab. Einiges aus dem Alltag fehlt oder ist vereinfacht, vor allem in der Wochenseite, beim Workbook, bei den Coach-Werkzeugen und bei der Automatik rund um Aufzeichnungen und Buchungen.

## Design: was anders ist

Die wichtigsten Werte des alten Designs stehen nicht in den Dateien, sondern in WordPress-Optionen (`lea_design_css`, `lea_karten_css` und weitere). Sie sind in `abgleich/huelle-design.md` wörtlich festgehalten.

1. **Schrift:** alt Oxygen Mono für den Text, Lora für die Titel, Cutive Mono für die Wortmarke. Neu war es eine Systemschrift, der grösste sichtbare Unterschied.
2. **Farben:** alt Seite `#FAF8F3` (Desktop `#EFEDE7`), Leiste `#FAF8F3`, rund 20 Farbtöne. Neu waren es 10 Töne und `#F6F2EA`.
3. **Kopf:** alt Burger, rundes Portrait und Wortmarke "Mitgliederbereich", dazu ein Menü von links mit allen Punkten, Abmelden, Impressum und Datenschutz. Neu nur der Name. Mobil fehlten Abmelden, Material, Impulse und Gespräch.
4. **Hierarchie:** alt stehen Abschnittsköpfe als kleine Grossbuchstaben mit Terracotta-Icon und Zähler über den Karten, Kartentitel in Textschrift halbfett. Neu stand ein Lora-Titel in jeder Karte.
5. **Bausteine:** Filter-Pills aktiv dunkel statt Terracotta, schlichtes Lesezeichen, gefüllte Icons (Font Awesome Solid), Felder 16 px (sonst zoomt das iPhone), Bottom-Sheets.
6. **Startseite:** "Was ist neu" als klickbare Zeilen (drei und "mehr"), farbige Karte "Diese Woche" in Kursfarbe, Termin-Karte mit "Jetzt / Heute / Morgen".

Die Bottom-Navigation der neuen App bleibt, sie ist eine echte Verbesserung. Dazu kommt oben der Burger mit dem vollen Menü wie früher.

## Fehler, die beim Abgleich aufgefallen sind

- Importierte Zusammenfassungen (HTML) stehen auf der Terminseite als Text mit Tags.
- Das Dossier zeigt Titel privater Aufgaben. Früher wurden sie nur gezählt.
- Der WhatsApp-Knopf macht aus "079 ..." eine ungültige Nummer.
- Die Terminseite zeigt allen die Vornamen der Abgemeldeten. Früher waren es nur Kürzel.
- Das Aufgabenformular kennt "angeheftet", hat aber keinen Haken dafür.
- Beim Kauf auf Rechnung (Woo-Status `on-hold`) entsteht kein Zugang.
- Im 1:1-Gespräch steht "Gespräch mit" und dann das erste Teammitglied statt der Coachin.
- Gratiskurse (`kurs_gratis`) stehen früher allen Mitgliedern offen, in der App nur mit Kauf.
- Drei Einladungsmails zu Gruppencalls kommen als Impulse mit.
- Sprachnachrichten aus Chrome (webm) laufen auf iPhone und Mac nicht. Früher wurden sie nach m4a gewandelt.

## Plan

Reihenfolge nach Wirkung für Lea und die Teilnehmerinnen. Jeder Block wird einzeln gebaut, getestet und auf den Server gebracht.

1. **Design angleichen** nach der Spezifikation: Branding-Werte, Kopf mit Menü, Bausteine, Icons, Startseite, Profil, Pull-to-Refresh, Installieren und Push als Sheet, Offline-Seite.
2. **Fehler beheben** (Liste oben).
3. **Kursraum:**
   - Schrittseite als Wochenseite mit Call, Aufgaben, Material und Reflexion.
   - Material in der Einheit.
   - Diktat per Mikrofon.
   - Videoposition merken, ab 80 % gesehen.
   - Kapitel-Sprungmarken.
   - Fehlende Workbook-Bausteine samt Import.
   - Fragen an die Coachin mit Status.
4. **Coach-Werkzeuge:**
   - Wer wartet, Ampel.
   - Auf Geteiltes antworten.
   - Private Notizen zur Person.
   - Sitzungskontingent.
   - Terminvorschlag im Chat.
   - KI-Vorbereitung.
   - Rundnachricht an Einzelne.
   - Wochencheck fürs Team.
5. **Integrationen:**
   - Aufzeichnungen von Vimeo mit Freigabe und Mail.
   - Neue Termine melden.
   - Zoom-Anwesenheit.
   - Coaching-Buchung mit Google-Kalender.
   - Buchungen und Rechnungen im Profil.

## Stand der Umsetzung (25.09.2026, abends)

Alle fünf Blöcke sind gebaut, getestet (129 Tests) und auf dem Server.

- **Design, Fehler, Kursraum:** wie im Plan. Zusätzlich gefunden und behoben: Die App zeigte alle Zeiten in UTC (ein Call um 20:00 stand als 18:00 da), der Coach-Bereich speicherte eingegebene Zeiten als UTC, und der Import las Zeiten aus WordPress als UTC. Modelle lesen jetzt in der Zeitzone des Mandanten, gespeichert wird UTC (`App\Tenancy\Concerns\Ortszeit`, `App\Support\Database\UtcBindings`).
- **Chat:** Jede Nachricht löste zwei Benachrichtigungen aus (Listener doppelt registriert). Behoben. Im 1:1 bekommt nur noch die Person Bescheid, wenn das Team schreibt, nicht die Kolleginnen.
- **Coach-Werkzeuge:**
  - Ampel auf dem Dashboard und in der Personenliste (`App\Coach\Lage`): wer wartet, wer still ist, verpasste Calls, überfällige Aufgaben, Sitzungen offen. "Zuletzt da" kommt im Parallelbetrieb auch aus WordPress (`lea_zuletzt_da`, `wc_last_active`, `lea_last_login`).
  - Kommentare auf Reflexion, Notiz, Aufgabe und Übungsantwort, in der App und im Dossier.
  - Private Notizen der Coachin (`coach_notes`) und Sitzungskontingent (`programs.settings.sitzungen_gesamt` plus `program_members.settings.sitzungen_extra`).
  - Terminvorschlag im Chat (antippen bucht).
  - KI-Vorbereitung im Dossier, nur aus Geteiltem.
  - Rundnachricht an Einzelne und persönlich ins 1:1.
  - Wochencheck mit frei einstellbaren Haken (`settings.wochencheck.haken`).
- **Integrationen:**
  - Aufzeichnungs-Wache alle 15 Minuten (`aufzeichnungen:wache`): Vimeo-Video über die Zeit im Zoom-Titel (UTC) finden, Abschrift aus der Textspur, Zusammenfassung mit Kapiteln "(ab MM:SS)", Meldung an die Coachin. Freigabe per Knopf am Termin mit Mail (Zusammenfassung im Text) und Push, an alle im Kurs. Kursvideos im selben Vimeo-Ordner werden nicht zugeordnet (Upload-Zeit nur mit `recordings.match_upload_time`). Gegen die echten Videos geprüft: alle Zuordnungen wie in WordPress.
  - Neue Termine werden sofort gemeldet.
  - Zoom-Anwesenheit stündlich (`zoom:anwesenheit`), Zuordnung über Mail, Namen, Vor- oder Nachname, Zoom-Name wird gemerkt. Gegen die echten Calls geprüft.
  - Buchung (`/buchen`): freie Zeiten aus Google-Kalender-Blöcken mit Stichwort, Buchungsarten im Coach-Bereich, Vorbereitungsfragen, Antworten im Dossier, Kalendereintrag, Absagen bis zur Frist, Kontingent. **Noch ausgeschaltet** (`settings.booking.enabled`), weil jede Buchung einen echten Eintrag in Leas Kalender macht. Einstellungen und Arten sind aus WordPress übernommen.
  - Profil: "Meine Buchungen" mit Zugängen, Laufzeit, Kurswoche, Sitzungen und Link "Abo verwalten".

Noch offen: Rechnungen aus bexio (die App dürfte nicht denselben OAuth-Zugang wie WordPress erneuern, sonst bricht dort die Verbindung; besser später über einen kleinen Endpunkt in WordPress), Verschieben einer Buchung (heute: absagen und neu buchen), Buchung für Gäste ohne Konto, Material mit Vimeo-Abschrift.

Bewusst in WordPress bleiben die öffentliche Website, Kasse, Preise, Mailster und Newsletter, der Blog, Weiterleitungen, Admin-Kosmetik und die bexio-Buchhaltung (die App liest dort später nur Rechnungen).

Offene Entscheidungen, die nicht die Technik betreffen, stehen am Ende von `abgleich/huelle-design.md` (Gratis-Tür in die App?) und `abgleich/kommunikation.md` (Mailster-Listen nach dem Umschalten).
