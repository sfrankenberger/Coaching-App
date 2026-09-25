# Abgleich App-Hülle, Startseite, Navigation, Anmeldung, UI-Bausteine, Design

Stand 25.09.2026. Alter Mitgliederbereich: `wp-content/novamira-sandbox/lea-*.php` (Kopie im Scratchpad). Neue App: `/home/user/Coaching-App`.

**Wichtig zur Quelle:** Ein grosser Teil des alten Designs steht **nicht in den Dateien**, sondern in WordPress-Optionen, die die Dateien nur ausgeben (`get_option('lea_design_css')` usw.). Ich habe sie nur lesend über die Novamira-Verbindung geholt: `lea_design_css` (Tokens, Typo), `lea_karten_css` (eine Karte für alles), `lea_el_css` und `lea_el_boxen_css` (Chips, Menü, Filter, Knöpfe), `lea_aufbau_css`, `lea_fi_css`, `lea_pf_css` (Profil), `lea_st2_css` (Startseite), `lea_ap_css` (Karte "Diese Woche"), dazu das Elementor-Kit (globale Farben und Schriften). Wer das Design nachbaut, braucht diese Werte, die Dateien allein reichen nicht. Die wichtigsten stehen unten wörtlich.

Zwei Farbschichten im Altbestand: Die ältere Elementor-Palette (Tinte `#32312D`, Salbei `#8A9A8B`, Blaugrau `#7C86A2`, Creme `#F8F6F1`) lebt noch in `lea-club.php`, `lea-club-callbacks.php`, Mails und der öffentlichen Website. Der Mitgliederbereich wurde am 16.09.2026 mit `lea-design.php` ("wird zuletzt geladen und gleicht alles an") auf die **warme Terracotta-Palette** gezogen. Massgeblich für den Nachbau ist diese zweite Schicht. `lea-club-dashboard.php` enthält noch eine dritte, überholte Palette (`#C97B5A`, `#DBC5A8`, `#FBF6ED`), die im App-Modus nicht mehr sichtbar war.

---

## 1. Funktionen: alt gegen neu

| Funktion | alte Datei | Status | Fundstelle in der App bzw. Bemerkung |
|---|---|---|---|
| App-Leiste oben: Burger links, rundes Portrait 32/28 px, Wortmarke "Lea Wernli · Mitgliederbereich" in Cutive Mono | lea-app | vereinfacht | `components/layouts/app.blade.php` `.kopf`: nur Name bzw. Logo in Lora, kein Portrait, keine Wortmarke, kein Burger. Mobil halbtransparent mit Blur statt deckend `#FAF8F3`. |
| Seitliches Menü (Drawer von links) mit "Hallo Vorname", Gruppen, Unterpunkten (jeder Kurs einzeln, Profil-Unterseiten), Fuss mit Impressum, Datenschutz, Mail, "Zur Website" | lea-app, lea-menue-ordnung | fehlt | Ersetzt durch Bottom-Nav (5 Punkte) plus Desktop-Leiste. Mobil sind Material, Impulse, Themen, Merkliste, Gespräch, Coach-Bereich und Abmelden nur über Kacheln auf der Startseite bzw. Profil erreichbar. Impressum und Datenschutz fehlen ganz. |
| Menü-Ordnung: Kurznamen, Hilfe ganz unten, leere Gruppen weg | lea-menue-ordnung | bewusst weg | Feste Navigation im Blade-Layout, kein Filter nötig. |
| Neu-Punkt an Menüeinträgen (`lea_neu_punkt`) | lea-app | vereinfacht | Nur Zähler am Gespräch (Nav und Chatknopf). Kein Punkt an Termine, Material, Impulse. |
| Bottom-Navigation | (gab es nicht) | besser | Neu in `app.blade.php` `.leiste`. Alt gab es nur Burger plus Chatknopf. Gestaltung siehe Abschnitt 3. |
| Chatknopf unten rechts mit Zähler, bei Lea und Team Liste aller Gespräche als Schublade | lea-chatknopf (Nachbar), lea-willkommen erwähnt ihn | vereinfacht | `.chat-knopf` verlinkt nur auf `/gespraech`, keine Schublade, 52 statt 56 px. |
| Handy-Weiche `/app/` gegen Desktop, `?web=1` | lea-app, lea-app-modus | bewusst weg | Die App hat eine eigene Domain, es gibt keine Website-Hülle mehr, die man verlassen müsste. |
| App-Modus: Website-Kopf, Fuss, Tabs weg, Inhalt in 640-px-Spalte | lea-app-modus | gleich | Eigene Hülle, Spalte `page_width` 720 aus dem Branding. Alt waren es 640 (Start) bis 720 (Listen). |
| PWA: Manifest, Icons 180/192/512/maskable, `theme-color`, Apple-Meta | lea-app | gleich | `Branding::manifest()`, Route `manifest`, `branding:icons`. `theme-color` alt `#FAF8F3` (Farbe der Leiste), neu `card_bg` `#FFFDF8`. |
| Service Worker mit Offline-Seite "Gerade offline", lädt bei `online` neu | lea-app | vereinfacht | `public/sw.js` ohne Offline-Antwort (leerer `fetch`-Handler). |
| Push: automatischer Dialog als Bottom-Sheet (nach 1,2 s auf der Startseite, pro Tag höchstens einmal "Jetzt nicht") | lea-app | vereinfacht | Push nur in der Einführung (Schritt 3) und im Profil. Kein Sheet, kein automatischer Hinweis. |
| Installieren: `beforeinstallprompt` (Android-Knopf), iPhone-Anleitung in drei nummerierten Schritten, Statuszeile "Als App auf den Home-Bildschirm" | lea-app | fehlt | Nur Text in der Einführung. Kein Install-Knopf, keine Erkennung von `standalone`. |
| Abmelden mit einem Klick, ohne Rückfrage | lea-app | gleich | `POST /abmelden` im Layout, aber nur in der Desktop-Nav. Mobil gibt es keinen Abmelde-Knopf (weder Bottom-Nav noch Profil). **Lücke.** |
| Adminleiste weg, `viewport-fit=cover`, Hintergrund bis unter die Statusleiste | lea-app, lea-design | gleich | Layout setzt `viewport-fit=cover`. Kopf nutzt aber kein `env(safe-area-inset-top)`. |
| App-Vorschau im Handyrahmen mit drei Bildschirmen und Wechsel-Dialog | lea-app-vorschau | bewusst weg | Marketing-Baustein der öffentlichen Website, bleibt in WordPress. |
| Startseite: "Hallo Vorname" | lea-start2 | gleich | `home.blade.php`, dazu Datum und Zähler. |
| Startseite: Karte "Diese Woche im Kurs" (Kursfarbe als Verlauf, Kursbild rund, Balken, "Als Nächstes", "Zur Woche →") | lea-start2 + `lea_ap_css` | vereinfacht | `home.blade.php` "Weiter im Kurs" als weisse Karte mit Balken in Kursfarbe. Kein Verlauf, kein Bild, kein "Als Nächstes". |
| Startseite: "Was ist neu" als einzeilige Karten (Icon 32 px, Herkunft, Titel, Datum), 3 Stück plus "mehr", ungelesene mit Terracotta-Kante, Klick öffnet Infofenster | lea-start2, `lea_design_css` | vereinfacht | "Neu für dich": alle Einträge in einer Karte als Liste, **nicht klickbar**, kein Icon, keine Begrenzung (Bildschirmfoto: 8 Einträge füllen den ersten Bildschirm). |
| Startseite: nächster Termin als Hero (grüner Innenstreifen, "Jetzt" grün gefüllt, "Heute", "Morgen", "In 3 Tagen", Knopf je Art: Zoom, Reflexion schreiben, Frage stellen) | lea-start2 | vereinfacht | Karte "Nächster Termin" mit Datum, "Jetzt beitreten" nur live. Keine relative Zeitangabe, kein Zustand "Jetzt", kein Knopf je Termin-Art. |
| Startseite: offene Aufgaben mit Zählerpille, 4 Karten, "Alle n ansehen" | lea-start2 | gleich | "Deine Aufgaben" mit Haken. Zähler steht oben in der Unterzeile statt am Abschnitt. |
| Startseite: Meine Projekte (Farbstreifen links) | lea-start2 | fehlt | Projekte gibt es in der App nicht (anderer Bereich, prüfen). |
| Startseite: Umschalter "Arbeitsliste / Wie eine Teilnehmerin" für Lea und Team | lea-start2 | bewusst weg | Arbeitsliste lebt im Filament-Panel `coach`. Die Karte "Für dich als Coach" verlinkt dorthin. |
| Startseite: Leertext "Gerade ist nichts offen ..." | lea-start2 | fehlt | Ohne Inhalte bleiben nur die Kacheln. |
| Dashboard-Kacheln mit Icon und Zählern ("2 diese Woche", "3 neu") | lea-club-dashboard | vereinfacht | 8 Kacheln ohne Icon und ohne Zahlen, nur Titel und Untertitel. |
| Reiter-Navigation (Profile Builder) | lea-club-dashboard | bewusst weg | Überholt, schon alt im App-Modus ausgeblendet. |
| Einführung beim ersten Besuch als Fenster über jeder Seite, 8 Schritte, Balken 4 px, Punkte, Bild je Schritt, Icon im Titel, Telefonfeld speichert beim Tippen, mobil Vollbild | lea-willkommen | vereinfacht | `willkommen.blade.php` als eigene Seite (Weiterleitung aus `HomeController`), Karte, Balken 6 px. Keine Bilder, keine Icons, keine Punkte, Telefon erst am Schluss gespeichert. Texte gleich, mandantenneutral. |
| "Einführung ansehen" als Knopf | lea-willkommen, lea-hilfe | gleich | Profil, Karte "Hilfe". |
| Technik-Hilfe: Formular "Wo klemmt es / Was passiert", schickt Seite, Bildschirm, Browser, Kurse mit; WhatsApp-Knopf zu Sebastian | lea-hilfe | fehlt | Einführung sagt "schreib ins Gespräch". Kein Formular, kein Direktweg zur Technik. |
| Kurzweg `/passwort-vergessen/` | lea-hilfe | bewusst weg | Magic Link ersetzt das Zurücksetzen. |
| Infofenster: Beiträge von Lea öffnen als Bottom-Sheet (mobil) bzw. Modal (Desktop), per AJAX, ohne Seitenwechsel | lea-infofenster | fehlt | Impulse öffnen als eigene Seite `impulse.show`. Kein Sheet-Baustein. |
| Ziehen zum Aktualisieren (mobil, Schwelle 72 px, im Chat nur Nachrichten neu) | lea-pull | fehlt | Kein Touch-Code in `public/js/app.js`. Im Standalone-Modus gibt es damit keinen Weg zum Neuladen. |
| Einheitlicher Seitenaufbau: zugeklappter Schreib-Anstoss, der aufgeht, darunter Filterstreifen, darunter Liste | lea-seitenaufbau + `lea_aufbau_css` | vereinfacht | Journal-Seiten haben eigene Formulare, kein einheitlicher "Anstoss" (Journal ist anderer Bereich). |
| Filter eingeklappt hinter einem Knopf mit Zähler, Zeile "gefiltert nach ..." | lea-filter + `lea_fi_css` | fehlt | Termine und Material zeigen Pills plus Suchfeld offen. |
| Filterleiste v2 (Art, Zeit, Projekt, Kurs, Status, Suche) als klebender Streifen | lea-elemente + `lea_el_css` | vereinfacht | Nur Pills und ein Select je Seite, nicht klebend. |
| Elemente-Fundament: Freigabe (nur ich, Lea, Kurs, Community), Sicht-Chip, 4 Reaktionen, Kommentare, Dreipunkt-Menü, Anhänge, Sprungziel `?el=` mit Aufblinken | lea-elemente, lea-elemente-ui | vereinfacht | Teilen und Reaktionen gibt es im Kurs und Gespräch. Das einheitliche Dreipunkt-Menü und die Sicht-Chips fehlen als gemeinsamer Baustein (Journal-Abgleich prüft das im Detail). |
| Eine Karte für alles (`lea_karte_aufgabe`, `lea_karten_css`) | lea-karten | gleich | `.karte` in `app.css` und `x-karte`: Radius 16, Polster 14/16, Abstand 8, Rahmen `#E4DFD2`, Fläche `#FFFDF8` stimmen. Varianten (fertig, neu, offen, heute, fremd) fehlen. |
| Kurzer Kursname ohne Programm-Präfix | lea-karten | bewusst weg | Titel werden sauber gepflegt, kein Abschneiden nötig. |
| Stile bündeln, Schriftgrössen auf 8 Stufen einrasten | lea-css-buendel | besser | Tailwind-Build, `font_scale` im Branding mit derselben Leiter 11 / 12.5 / 13.5 / 15.5 / 17 / 20 / 26 / 32. |
| Gestaltungsregeln als Tokens | lea-design | vereinfacht | Branding kennt 10 Farben. Es fehlen ink-4, zweite Linie, Fläche, Akzent-hell, Akzent-Hover, Neutral, Grün-hell, Schrift der Wortmarke, Zeilenhöhen. |
| Schriften Oxygen Mono (Text), Cutive Mono (Wortmarke), Lora (Titel) | lea-brand-override | fehlt | `font_body` ist `system-ui`. Das ist der grösste sichtbare Unterschied (siehe Abschnitt 2). |
| Mobiles Vollbild-Menü der öffentlichen Website | lea-mobilmenue | bewusst weg | Gehört zur Website. Gestaltung taugt als Vorlage für den fehlenden Drawer. |
| Bereichsweiche Coaching / Ausbildung | lea-bereich | bewusst weg | Website. |
| Termin-Badges (Live · Anwesenheit grün, Aufzeichnung, Aufgabe als Ring), Pills "Alles / Live / Aufgaben", Mobil ohne Überlauf, Monatstrenner, "Weitere n Termine anzeigen" | lea-ui | vereinfacht | `termine/index.blade.php` hat Monatstrenner und Pills. Badge-Stil und "Weitere n anzeigen" fehlen. `overflow-wrap:anywhere` nur in `.prose-app`. |
| Anmeldung mit Einmal-Link (7 Tage gültig, mehrfach nutzbar) | lea-anfang-anmeldung | gleich | `App\Auth\MagicLink`, `anmelden.blade.php`. |
| Gratis-Tür: E-Mail genügt, Konto entsteht, Haken für Newsletter mit Double-Opt-in, Testergebnis wird mitgeschickt | lea-anfang-anmeldung | fehlt | `/anmelden` meldet nur bestehende Konten an (`kein-zugang`). Freebie-Einstieg läuft weiter über WordPress, bis das umgezogen ist. |
| Hänger-Mails nach 2 und 7 Tagen mit Link direkt zum offenen Schritt | lea-anfang-strecke | fehlt | `Runden::nachfassen` meint ungelesene Chat-Nachrichten, nicht den Gratiskurs. |
| Abschluss-Mail mit den eigenen Goldnuggets, Push an Lea | lea-anfang-abschluss | fehlt | |
| Mit Google anmelden, Mit Apple anmelden (nur bestehende Konten) | lea-anmeldedienste, lea-apple-login | gleich | Socialite je Mandant (`anmelden.dienst`). Knöpfe sind neutrale "leise" Pills. Alt: Google offizieller Pill-Knopf, Apple schwarz. |
| Google im Profil verknüpfen und trennen | lea-anmeldedienste | fehlt | Profil hat Passkey und Passwort, keine Verknüpfung mit Google oder Apple. |
| Passkeys (deutsche Texte, Verwaltung im Konto, warme Knöpfe) | lea-login-optionen | besser | Laragear WebAuthn, RP-ID je Mandant, Verwaltung im Profil. |
| Anmeldename ist die Mailadresse | lea-login-name | bewusst weg | Laravel meldet über E-Mail an. |
| Zugang sichern: 4 Wege nach Gratis-Einstieg (Passwort, Passkey, Google, Apple) als Baustein am Kursende | lea-zugang-sichern | vereinfacht | Passkey und Passwort im Profil. Kein Hinweis am Kursende, keine Google/Apple-Verknüpfung. |
| Profil-Startseite: Avatar 64 px, Name, Mail, Kacheln mit Icon und Chevron (Meine Daten, Buchungen, Hilfe, Nachrichten), Abmelden-Link | lea-profil + `lea_pf_css` | vereinfacht | `profil.blade.php` ist eine lange Seite mit 7 Karten. Kein Kopf, kein Profilbild, keine Unterseiten. |
| Profil: Käufe mit Rechnung-PDF, Buchungen, Newsletter an/ab | lea-profil | fehlt | Käufe und Rechnungen bleiben vorerst in WooCommerce bzw. bexio. Newsletter fehlt. |
| Profil: Push, Kalender-Abo, Einzelschalter Erinnerungen | lea-profil | besser | Plus Telegram, drei Einzelschalter. |
| WooCommerce "Mein Konto" zweispaltig | lea-konto | bewusst weg | |
| Rollen: Redaktion (Andrea) antwortet als "Team Lea", intern "geschrieben von" | lea-rollen | vereinfacht | Rolle `team` an `memberships`, Chat kennt Team-IDs. Anzeige "Team Lea" und "geschrieben von" nicht gefunden (Chat-Abgleich prüfen). |
| Mitgliederbereich nur angemeldet, Login-Seite leitet Angemeldete weiter | lea-member-guard | gleich | `auth` plus `EnsureMembership`. |
| Kursinhalte nur mit Zugang, sonst ruhiger Hinweis | lea-zugang, lea-zugang-direkt | gleich | `Gate::authorize('view-program')`, `ProgramAccess`. Alt: freundliche Karte "Dieser Kurs gehört noch nicht zu deinem Programm", neu: nackte 403. |
| Zugang aus Kauf und Abo | lea-zugang-direkt | gleich | `App\Shop\Zugang`, WooCommerce-Webhook. |
| Zugriffsschutz (REST zu, Autor-Seiten zu) | lea-zugriffsschutz | besser | Entfällt technisch, keine offene WordPress-Schnittstelle. |
| Gastzugang: Menü eingedampft, Journal-Seiten leiten zum Gespräch, Begrüssungskarte "Schön, dass du da bist" | lea-gast | vereinfacht | Rolle `guest` existiert. Navigation und Startseite sind für Gäste gleich wie für alle, keine Begrüssung. |
| Kurs-, Lektions-, Terminseiten, Fortschritt, Video-Einbettung, Data Store "erledigt" | lea-club | gleich | Bereich Kurse (`kurse/*`, `ProgressTracker`). |
| JetEngine-Callbacks und Makros | lea-club-callbacks, lea-club-macros | bewusst weg | Eloquent-Beziehungen ersetzen Listings und Makros. |
| Lektionsvorlage zweispaltig, Seitenspalte 310 px klebend | lea-club-template-css | vereinfacht | Bereich Kurse, eine Spalte. |
| Listing-Zeilen Aufgabe, Material, Videokapitel, Haken als 24-px-Kästchen | lea-club-listings-css | vereinfacht | Home-Haken 28 px, Radius 8, ohne Terracotta-Füllung. |
| Eigene Seitenhülle ohne Elementor (`vorlagen/lea-seite.php`, Seitenplan) | lea-seiten | gleich | Blade-Layout. |
| Einmal-Anmeldung Demokonto für Bildschirmfotos | lea-demo-login | fehlt | Unkritisch, für Tests reicht ein Magic Link. Für Lea-Vorführungen wäre ein Demo-Mandant sauberer. |
| Vorschau-Link für private Ausbildungsseiten | lea-vorschau | bewusst weg | Website. |
| 301-Weiterleitungen umgezogener Seiten | lea-umzug | bewusst weg | Website. Beim Umschalten braucht es aber Weiterleitungen `/mitgliederbereich/*` auf die App (Roadmap). |
| Einstieg unter jedem Beitrag (Gratiskurs bzw. Klarheitsgespräch) | lea-einstieg-cta | fehlt | In `impulse/show` für Gäste sinnvoll, sonst bleibt es auf der Website. |
| Merkliste mit Lesezeichen an jeder Karte | lea-gemerkt | gleich | `x-merken`, `merkliste`. Optik anders (siehe Abschnitt 3). |
| Themen über alle Inhalte, KI-Zuordnung | lea-themen | gleich | `themen.*`, `themen:profil`. |

---

## 2. Design-Spezifikation des alten Mitgliederbereichs

Zeichen: **[B]** = Mandanten-Branding (gehört in `tenants.branding`), **[A]** = allgemein (gilt für alle Mandanten, gehört in `app.css`).

### 2.1 Farbpalette

Tokens aus `lea_design_css` (`:root`), ergänzt um die Werte, die in den Bausteinen fest verdrahtet sind.

| Token alt | Hex | Rolle | Neue Variable (Vorschlag) | |
|---|---|---|---|---|
| `--lea-ink` | `#2E2D29` | Titel, Haupttext, dunkle Knöpfe, aktive Pills | `--c-text` | [B] |
| `--lea-ink-2` | `#4A473F` | Fliesstext in Karten | `--c-text-soft` | [B] |
| `--lea-ink-3` | `#86816F` | Meta, Untertitel, Abschnittsköpfe | `--c-muted` | [B] |
| `--lea-ink-4` | `#A9A395` | Eyebrow-Labels, Datum, leise Zähler | `--c-faint` (neu) | [B] |
| (fest) | `#C9C3B5` | Chevrons, Such-Icon, Lesezeichen aus, Ring "Aufgabe" | `--c-ghost` (neu) | [B] |
| `--lea-akzent` | `#B4795F` | Terracotta: Primärknopf, Icons, Balken, Chatknopf, Fokus | `--c-primary` | [B] |
| (fest) | `#9E6850` | Hover Primärknopf (auch `#9C6650`) | `--c-primary-hover` (neu) | [B] |
| (fest) | `#F0E4DA` | Akzent hell: Chip "Mit Lea geteilt", Anhang-Chip | `--c-primary-soft` (neu) | [B] |
| (fest) | `#F3EDE6` | Icon-Kreis in Listenzeilen, Reaktion aktiv | `--c-primary-tint` (neu) | [B] |
| (fest) | `#E0CDBE` | Rahmen "neu", Icon im offenen Filterknopf | `--c-primary-line` (neu) | [B] |
| `--lea-gruen` | `#6E8B74` | Salbeigrün: Live-Termin, erledigt, Status "an" | `--c-success` | [B] |
| (fest) | `#4F7357` | Grün als Text auf Hell | `--c-success-ink` (neu) | [B] |
| (fest) | `#E6EDE7` | Grün hell (Kreis, Zählerpille ok) | `--c-success-soft` (neu) | [B] |
| (fest) | `#B5544F` | Gefahr, überfällig, Badge-Zahl | `--c-danger` | [B] |
| `--lea-linie` | `#E4DFD2` | Kartenrahmen, Trennlinien, Pill-Rahmen | `--c-card-border` | [B] |
| `--lea-linie-2` | `#F0EDE4` | Zarte Trenner in Menüs und Karten | `--c-line-soft` (neu) | [B] |
| (fest) | `#D8D2C4` | Kräftigere Linie: ruhige Knöpfe, gestrichelter Anhang, Eingabe im Datum | `--c-line-strong` (neu) | [B] |
| (fest) | `#EFEDE7` | Neutral: Balken-Spur, Zählerpille, Segment-Hintergrund, Seite am Desktop | `--c-neutral` (neu) | [B] |
| `--lea-flaeche` | `#FAF8F3` | App-Leiste, Drawer, Sheets, Seite mobil, Zweitkarte, Pill-Fläche | `--c-surface` (neu) und `--c-bg` mobil | [B] |
| `--lea-weiss` | `#FFFDF8` | Karten | `--c-card` | [B] |
| (fest) | `#FFFFFF` | Eingabefelder, Suchfeld, Menü-Popover, "neu"-Karten | bleibt `#fff` | [A] |
| (fest) | `#F5F3ED` | Karte erledigt | `--c-done` (neu) | [B] |
| (fest) | `#F5F3EC` | Unterpunkte im Drawer | wie `--c-surface`, 2 % dunkler | [B] |
| (fest) | `rgba(238,236,231,.94)` | Klebender Filterstreifen mit Blur | `color-mix(in srgb, var(--c-neutral) 94%, transparent)` | [A] |
| (fest) | `#7C8C9A` | Vorgabe-Kursfarbe, wenn keine gesetzt | `programs.color` Fallback | [B] |

Seitenhintergrund: mobil (bis 760 px) `#FAF8F3` (`html, body.lea-app-modus{background:#FAF8F3!important}`), Desktop `#EFEDE7` (Body aus `lea-brand-override`). Die neue App nutzt `#F6F2EA`, das kam im Altbestand nicht vor.

Schatten sind immer Tinte mit Deckkraft, nie Schwarz [A]: `rgba(46,45,41,x)` = `color-mix(in srgb, var(--c-text) x%, transparent)`.

Kein Dunkelmodus. Keine einzige `prefers-color-scheme`-Regel im Altbestand.

### 2.2 Schriften

| Rolle | Familie | Gewicht | Quelle | |
|---|---|---|---|---|
| Fliesstext, Knöpfe, Labels, Karten-Titel | **Oxygen Mono**, "Courier New", monospace | 400 (die Schrift hat nur 400, 600 und 700 werden vom Browser fett gerechnet) | `lea-brand-override` `body{font-family:var(--lea-mono-body)}`, Kit "Fliesstext" | [B] |
| Seiten- und Abschnittstitel, Termin-Titel, Drawer-Kopf, Sheet-Titel | **Lora**, Georgia, serif | 400 im Mitgliederbereich (500 auf der Website), kursiv 400/500 für Akzente | `lea_design_css` | [B] |
| Wortmarke in der App-Leiste | **Cutive Mono**, monospace | 400 | `lea_app_css` | [B] |

Google-Fonts-Link [B]: `https://fonts.googleapis.com/css2?family=Oxygen+Mono&family=Cutive+Mono&family=Lora:ital,wght@0,400;0,500;1,400;1,500&display=swap`

Ausnahme: Zahl in Reaktions-Pills `system-ui` [A].

**Grössenleiter** (`lea-css-buendel` rastet jede Angabe darauf ein, `lea_design_css` benennt sie) [A, als Vorgabe in `font_scale`]:

| Stufe | px | Einsatz |
|---|---|---|
| mikro | 11 | Eyebrow, Chips, Zählerpillen, Drawer-Kleingedrucktes |
| klein | 12.5 | Meta unter Karten, Datum |
| neben | 13.5 | Kartentext, Knöpfe, Untertitel in Listen |
| text | 15.5 | Fliesstext, Karten-Titel |
| gross | 17 | Drawer-Einträge, betonte Titel |
| titel | 20 | h3, Termin-Titel, Einführung mobil 22 |
| kopf | 26 | h2, h1 mobil, Einführung |
| seite | 32 | h1, "Hallo Vorname" |

Zeilenhöhen [A]: eng `1.35`, normal `1.6`, weit `1.7`. Titel Lora `1.2` (h1), `1.25` (h2), `1.3` (h3). Karten-Titel `1.4`, Meta `1.5`, Kartentext `1.55`.

Letter-Spacing [A]: Eyebrow `.12em` (Kopfzeilen-Label im Drawer `.14em`), Chips `.02em` bis `.05em`, Badge uppercase `.1em`, Wortmarke `.14em` (mobil `.1em`), sonst `0`.

Typo-Rollen:

```css
.lea-app-inhalt h1{font-family:var(--lea-serif);font-size:32px;font-weight:400;line-height:1.2;color:#2E2D29;margin:0 0 18px}
@media (max-width:760px){.lea-app-inhalt h1{font-size:26px;margin:2px 0 12px}}
.lea-app-inhalt h2{font-family:var(--lea-serif);font-size:26px;font-weight:400;line-height:1.25;margin:26px 0 12px}
.lea-app-inhalt h3{font-family:var(--lea-serif);font-size:20px;font-weight:400;line-height:1.3;margin:20px 0 10px}
/* Eyebrow: Labels über Titeln, Feldbeschriftungen, Trenner */
.eyebrow{font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:#A9A395;line-height:1.4}
/* Karten-Titel: NICHT Lora, sondern Textschrift halbfett */
.karte .t{font-size:15.5px;line-height:1.4;font-weight:600;color:#2E2D29}
.karte .m{font-size:12.5px;line-height:1.5;color:#86816F}
.karte .x{font-size:13.5px;line-height:1.55;color:#4A473F}
input,textarea,select{font-size:16px;font-family:inherit;line-height:1.5}   /* 16 px: kein Zoom auf dem iPhone */
```

### 2.3 Masse, Abstände, Raster [A]

| Wert | alt |
|---|---|
| Radius Karte | 16 px (`--lea-rund`) |
| Radius klein (Felder, Menü-Einträge, Chips eckig) | 12 px (`--lea-rund-klein`), Menüeintrag 10, Popover 14 |
| Radius gross (Bausteine, Hero, Einführung, Sheets) | 18 / 20 / 22 px |
| Pille | 999 px |
| Luft | 16 (`--lea-luft`), 10 (klein), 24 (gross) |
| Kartenpolster | 14 px 16 px, Hero 18 px 20 px, Profilkachel 16 px 18 px |
| Abstand zwischen Karten | 8 px (Listen), 5 bis 6 px (Neu-Strom), 9 px (Startkarten) |
| Abschnitt | `margin:26px 0 10px` für den Kopf, Block `margin-bottom:26px` |
| Spaltenbreite | 720 px Listen, 640 px Startseite, 680 px eigene Seiten, Profil, Infofenster; 560 px Hilfe, Sheet |
| Seitenpolster | Desktop `26px 18px 60px`, mobil `12px 14px 80px`, dazu `padding-bottom:88px` wegen Chatknopf |
| Kachelraster | 2 Spalten, `gap:12px` |
| Mindestgrösse Tippziel | 44 px (Burger, Schliessen, Knöpfe), Pills 40 px, Reaktion 32 px |
| Breakpoints | 760 (Hauptschnitt mobil), 640 (Filter), 900 (Desktop-Leiste, Modal statt Sheet), 520 (Chat-Schublade) |

### 2.4 Icons [B für das Set, A für die Regeln]

Font Awesome 6.5.2 Free, Solid (`fas`), per cdnjs. Gefüllte Glyphen, keine Strichstärke. Regeln:

- Führendes Icon in Terracotta vor jedem Menüpunkt (Breite 22 px, zentriert), jedem Abschnittskopf (12 bis 12.5 px), jeder Profilkachel (18 px), jedem Filterknopf (12 px).
- Chevron rechts `fa-chevron-right` 13 px in `#C9C3B5`.
- In Popover-Menüs Icons 13 px in `#86816F`, bei Gefahr rot, aktiv Terracotta.
- Lesezeichen: `far fa-bookmark` / `fas fa-bookmark`, 17 px, ohne Rahmen.
- Kurse haben eigenes Icon (`kursicon`) und Farbe (`kursfarbe`), eingesetzt über `--kc`.

Verwendete Symbole in Navigation: house, calendar, graduation-cap, user-group, magnifying-glass, book-open, list-check, note-sticky, pen-to-square, diagram-project, timeline, folder-open, circle-play, comments, lightbulb, bell, user, life-ring, arrow-right-from-bracket.

Die neue App nutzt eigene Outline-SVGs mit `stroke-width:1.8` (Heroicons-Art). Das wirkt kühler als die gefüllten FA-Glyphen.

### 2.5 Übergänge und Animationen [A]

| Was | Wert |
|---|---|
| Drawer, Infofenster mobil | `transform .28s cubic-bezier(.4,0,.2,1)`, `visibility 0s linear .28s` beim Schliessen |
| Bottom-Sheet (Push, Install) | `transform .3s ease`, Start `translateY(110%)` |
| Schleier | `opacity .25s` (Drawer), `.2s` (Infofenster), mit `backdrop-filter:blur(3px)` bei Infofenster und Einführung |
| Infofenster Desktop | Einblenden plus `translate(-50%,-44%)` auf `-50%`, `.2s` |
| Balken Einführung | `width .3s` |
| Karten mit Link | Rahmen auf Terracotta beim Hover, "Diese Woche" `translateY(-1px) .15s` |
| Chatknopf | `transform .2s, opacity .2s`, Hover `scale(1.05)`, beim Scrollen weg `translateY(90px)` |
| Lesezeichen | `color .15s, transform .15s`, Hover `scale(1.1)` |
| Kästchen Aufgabe | `background .14s, border-color .14s` |
| Pills | `background .15s, border-color .15s` |
| Sprungziel | `@keyframes lea-el-blink{0%,45%{box-shadow:0 0 0 3px rgba(180,121,95,.55)}100%{box-shadow:0 0 0 3px rgba(180,121,95,0)}}` 2.6 s |
| Pull-to-Refresh | Ring 17 px dreht `.8s linear infinite`, Leiste `height .2s` |

### 2.6 Safe-Area und Mobil [A]

- `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">`, `theme-color` = Leistenfarbe `#FAF8F3` [B], `apple-mobile-web-app-status-bar-style: default`.
- `html` und `body` mobil in Leistenfarbe, damit unter der Statusleiste kein grauer Balken bleibt.
- App-Leiste: `padding-top:env(safe-area-inset-top)`, `height:calc(52px + env(safe-area-inset-top))`.
- Drawer: `padding-top:env(safe-area-inset-top); padding-bottom:env(safe-area-inset-bottom)`.
- Sheets und Fussleisten: `padding-bottom:calc(26px + env(safe-area-inset-bottom))` bzw. `calc(16px + ...)`.
- Offene Overlays sperren den Hintergrund: `body.lea-app-offen{overflow:hidden}` (ebenso Einführung, Infofenster, Chat-Schublade).
- Mobil kein Überlauf: `overflow-wrap:anywhere; hyphens:auto` für Titel und Text, Tabellen `display:block; overflow-x:auto`, Bilder und iframes `max-width:100%`.
- Eingaben 16 px.
- Filterstreifen klebt unter der Leiste (`top:58px`, mobil `54px`).

### 2.7 Bausteine als CSS

Alle Werte wörtlich aus dem Altbestand, auf Variablen umgestellt. Variablennamen passend zu `app.css`.

**Tokens**

```css
:root{
  /* [B] Branding */
  --c-text:#2E2D29; --c-text-soft:#4A473F; --c-muted:#86816F; --c-faint:#A9A395; --c-ghost:#C9C3B5;
  --c-primary:#B4795F; --c-primary-hover:#9E6850; --c-primary-contrast:#fff;
  --c-primary-soft:#F0E4DA; --c-primary-tint:#F3EDE6; --c-primary-line:#E0CDBE;
  --c-success:#6E8B74; --c-success-ink:#4F7357; --c-success-soft:#E6EDE7; --c-danger:#B5544F;
  --c-card:#FFFDF8; --c-card-border:#E4DFD2; --c-line-soft:#F0EDE4; --c-line-strong:#D8D2C4;
  --c-surface:#FAF8F3; --c-neutral:#EFEDE7; --c-done:#F5F3ED;
  --c-bg:#FAF8F3; --c-bg-wide:#EFEDE7; --c-bar:#FAF8F3;
  --font-body:"Oxygen Mono","Courier New",monospace; --font-heading:Lora,Georgia,serif; --font-mark:"Cutive Mono",monospace;
  /* [A] allgemein */
  --radius:16px; --radius-sm:12px; --radius-lg:20px; --radius-sheet:22px;
  --shadow-pop:0 12px 34px color-mix(in srgb,var(--c-text) 15%,transparent);
  --shadow-drawer:8px 0 36px color-mix(in srgb,var(--c-text) 18%,transparent);
  --shadow-modal:0 18px 50px color-mix(in srgb,var(--c-text) 28%,transparent);
  --ease-drawer:cubic-bezier(.4,0,.2,1);
}
body{background:var(--c-bg);font-family:var(--font-body);line-height:1.6;color:var(--c-text)}
@media (min-width:761px){body{background:var(--c-bg-wide)}}
```

**Kopf (App-Leiste)**

```css
.kopf{position:sticky;top:0;z-index:40;display:flex;align-items:center;gap:12px;
  height:60px;padding:0 18px;background:var(--c-bar);border-bottom:1px solid var(--c-card-border)}
.kopf .burger{width:44px;height:44px;border:0;background:transparent;display:flex;flex-direction:column;
  justify-content:center;gap:5px;padding:10px;cursor:pointer}
.kopf .burger span{display:block;height:2px;background:var(--c-text);border-radius:2px}
.kopf .marke{flex:1;display:flex;align-items:center;justify-content:center;gap:10px;min-width:0;color:var(--c-text);text-decoration:none}
.kopf .marke img{width:32px;height:32px;border-radius:50%;object-fit:cover}           /* Portrait [B] */
.kopf .marke .n,.kopf .marke .z{font-family:var(--font-mark);font-size:15px;letter-spacing:.14em;white-space:nowrap}
.kopf .marke .z{color:var(--c-muted)} .kopf .marke .z:before{content:"\00B7";margin-right:8px;color:var(--c-ghost)}
.kopf .platz{width:44px;flex:0 0 44px}                                                 /* Gegengewicht zum Burger */
@media (min-width:900px){.kopf{height:64px;padding:0 28px}.kopf .marke{justify-content:flex-start}}
@media (max-width:760px){
  .kopf{height:calc(52px + env(safe-area-inset-top));padding:env(safe-area-inset-top) 14px 0;gap:8px}
  .kopf .marke{justify-content:flex-start;gap:8px} .kopf .marke img{width:28px;height:28px}
  .kopf .marke .n,.kopf .platz{display:none}
  .kopf .marke .z{font-size:14px;letter-spacing:.1em;color:var(--c-text)} .kopf .marke .z:before{content:none}
}
```

**Drawer (Menü von links)**

```css
.drawer{position:fixed;top:0;left:0;bottom:0;width:min(84vw,340px);z-index:60;background:var(--c-surface);
  display:flex;flex-direction:column;overflow-y:auto;visibility:hidden;transform:translateX(-102%);
  transition:transform .28s var(--ease-drawer),visibility 0s linear .28s;box-shadow:var(--shadow-drawer);
  padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom)}
.drawer.offen{visibility:visible;transform:none;transition:transform .28s var(--ease-drawer)}
.drawer-kopf{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;
  border-bottom:1px solid var(--c-card-border);font-family:var(--font-heading);font-size:20px}
.drawer-kopf button{width:44px;height:44px;border:0;background:transparent;font-size:30px;line-height:1;color:var(--c-muted)}
.drawer a{display:flex;align-items:center;gap:16px;padding:15px 22px;font-size:17px;color:var(--c-text);
  text-decoration:none;border-bottom:1px solid var(--c-line-soft)}
.drawer a i,.drawer a svg{width:22px;text-align:center;color:var(--c-primary)}
.drawer .gruppe{/* wie a, ohne Link */}
.drawer .unter a{padding-left:46px;font-size:15px;color:var(--c-text-soft);background:color-mix(in srgb,var(--c-surface) 97%,#000)}
.drawer .unter a i{font-size:12px;color:#B0A99A}
.drawer-fuss{padding:16px 22px;font-size:14px} .drawer-fuss a{color:var(--c-muted);border:0;padding:0;display:inline}
.schleier-menue{position:fixed;inset:0;z-index:55;background:color-mix(in srgb,var(--c-text) 35%,transparent);
  opacity:0;visibility:hidden;pointer-events:none;transition:opacity .25s,visibility 0s linear .25s}
.schleier-menue.offen{opacity:1;visibility:visible;pointer-events:auto;transition:opacity .25s}
```

**Bottom-Nav** (gab es alt nicht; so passt sie zur alten Handschrift)

```css
.leiste{background:var(--c-bar);border-top:1px solid var(--c-card-border);padding-bottom:env(safe-area-inset-bottom)}
.leiste a{color:var(--c-muted);font-size:11px;font-weight:600;letter-spacing:.02em;padding:8px 4px 6px;gap:3px}
.leiste a svg,.leiste a i{width:22px;height:22px;font-size:19px}
.leiste a.aktiv{color:var(--c-primary)}
```

**Karte und Varianten**

```css
.karte{background:var(--c-card);border:1px solid var(--c-card-border);border-radius:16px;padding:14px 16px;margin:0 0 8px}
a.karte:hover,.karte.klickbar:hover{border-color:var(--c-primary)}
.karte.flaeche,.karte.fremd{background:var(--c-surface)}
.karte.neu{background:#fff;border-color:var(--c-primary-line);border-left:3px solid var(--c-primary);padding-left:18px}
.karte.fertig{background:var(--c-done);opacity:.7}
.karte.fertig .t{text-decoration:line-through;text-decoration-color:#B8B29F;color:var(--c-muted)}
.karte.offen{border-left:4px solid var(--c-danger)}                     /* überfällig */
.karte.heute{border-color:var(--c-primary);box-shadow:0 0 0 1px var(--c-primary) inset}
/* Baustein mit Überschrift (Schreib-Anstoss, Formulare) */
.baustein{background:#fff;border:1px solid var(--c-card-border);border-radius:18px;padding:16px;margin:0 0 22px;
  box-shadow:0 2px 10px color-mix(in srgb,var(--c-text) 4%,transparent)}
.baustein:before{content:attr(data-titel);display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;
  color:var(--c-faint);font-weight:700;margin:0 0 10px}
```

**Abschnittskopf** (statt Lora-Titel in der Karte)

```css
.abschnitt{display:flex;align-items:center;gap:10px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;
  font-weight:700;color:var(--c-muted);margin:26px 0 10px;font-family:var(--font-body)}
.abschnitt i{color:var(--c-primary);font-size:12.5px}
.abschnitt em{font-style:normal;background:var(--c-neutral);color:var(--c-text-soft);border-radius:999px;
  padding:2px 9px;font-size:11px;font-weight:700;letter-spacing:0}
```

**Listenzeile** (Startkarten, Chat-Liste, Drawer-ähnlich)

```css
.zeile{display:flex;align-items:center;gap:13px;background:var(--c-card);border:1px solid var(--c-card-border);
  border-radius:16px;padding:14px 16px;margin-bottom:8px;text-decoration:none;color:var(--c-text)}
.zeile:hover{border-color:var(--c-primary)}
.zeile .ic{flex:0 0 38px;width:38px;height:38px;border-radius:50%;background:var(--c-primary-tint);color:var(--c-primary);
  display:flex;align-items:center;justify-content:center;font-size:13.5px}
.zeile .tx{flex:1;min-width:0}
.zeile b{display:block;font-size:15.5px;line-height:1.4;font-weight:600}
.zeile .tx span{display:block;font-size:13.5px;line-height:1.5;color:var(--c-muted);margin-top:2px;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.zeile .pf{color:var(--c-ghost);font-size:12.5px}                      /* Chevron */
/* Neu-Strom: eine Zeile je Eintrag */
.neu-zeile{padding:10px 13px;gap:11px} .neu-zeile .ic{flex:0 0 32px;width:32px;height:32px}
.neu-zeile .herkunft{font-size:9.5px;letter-spacing:.08em;text-transform:uppercase}
.neu-zeile b{font-size:14.5px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.neu-zeile .d{font-size:11px;color:var(--c-faint)}
.neu-liste{display:flex;flex-direction:column;gap:5px}
/* Profil-Kachel */
.kachel{display:flex;align-items:center;gap:16px;background:var(--c-surface);border:1px solid var(--c-card-border);
  border-radius:16px;padding:16px 18px;text-decoration:none;color:var(--c-text)}
.kachel>i:first-child{flex:0 0 22px;color:var(--c-primary);font-size:18px;text-align:center}
.kachel b{display:block;font-size:16px} .kachel small{display:block;font-size:13.5px;color:var(--c-muted);margin-top:2px;line-height:1.45}
```

**Hero-Karten der Startseite**

```css
/* Nächster Termin */
.termin-hero{display:block;background:#FFFDF9;border:1px solid var(--c-card-border);border-radius:16px;padding:18px 20px;
  margin:0 0 8px;box-shadow:inset 4px 0 0 var(--c-success)}
.termin-hero .wann{display:inline-block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:var(--c-success);margin-bottom:6px}
.termin-hero .t{display:block;font-family:var(--font-heading);font-size:20px;line-height:1.25}
.termin-hero .m{display:block;font-size:13.5px;color:var(--c-muted);margin-top:4px}
.termin-hero.jetzt{background:var(--c-success);border-color:var(--c-success);box-shadow:none}
.termin-hero.jetzt .wann{color:#FFFDF9} .termin-hero.jetzt .t{color:#fff} .termin-hero.jetzt .m{color:rgba(255,255,255,.75)}
.termin-hero.jetzt .knopf{background:#FFFDF9;color:var(--c-text);border-color:#FFFDF9}
/* Diese Woche im Kurs, Farbe aus dem Kurs */
.woche{position:relative;display:flex;flex-direction:column;gap:4px;border-radius:20px;padding:18px 22px 18px 20px;margin:0 0 22px;
  color:#fff;text-decoration:none;overflow:hidden;transition:transform .15s;
  background:linear-gradient(135deg,color-mix(in srgb,var(--kc,#7C8C9A) 92%,#000 8%),color-mix(in srgb,var(--kc,#7C8C9A) 72%,var(--c-text) 28%))}
.woche:hover{transform:translateY(-1px)}
.woche .lab{font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:600;color:rgba(255,255,255,.82);padding-right:54px}
.woche .t{font-family:var(--font-heading);font-size:23px;line-height:1.25;padding-right:54px}
.woche .k{font-size:13.5px;color:rgba(255,255,255,.78)}
.woche .balken{flex:1;height:6px;border-radius:3px;background:rgba(255,255,255,.18);overflow:hidden}
.woche .balken span{display:block;height:100%;background:#fff;border-radius:3px}
.woche .z{font-size:13px;color:rgba(255,255,255,.9)} .woche .als{font-size:14.5px;color:#FAF8F3;margin-top:6px}
.woche .cta{margin-top:10px;font-weight:600;font-size:14.5px}
.woche .bild{position:absolute;right:14px;top:14px;width:44px;height:44px;border-radius:50%;overflow:hidden;
  background:rgba(255,255,255,.9);box-shadow:0 2px 10px rgba(30,28,24,.18)}
```

**Knöpfe**

```css
.knopf{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:11px 20px;border-radius:999px;
  border:0;background:var(--c-primary);color:#fff;font-family:inherit;font-size:13.5px;font-weight:600;line-height:1.2;cursor:pointer;text-decoration:none}
.knopf:hover{background:var(--c-primary-hover)}
.knopf-gross{padding:13px 24px;font-size:15px;min-height:46px}          /* Formulare, Profil */
.knopf-breit{display:flex;width:100%;padding:15px 20px;font-size:16px}  /* Sheets */
.knopf-dunkel{background:var(--c-text);color:#fff}                      /* Kommentar senden, Technik melden, Fertig */
.knopf-ruhig{background:#fff;color:var(--c-text);border:1.5px solid var(--c-line-strong)}
.knopf-ruhig:hover{border-color:var(--c-primary);color:var(--c-primary);background:#fff}
.knopf-anstoss{background:#fff;border:1.5px solid var(--c-card-border);color:var(--c-text-soft);padding:11px 20px}  /* "Einführung ansehen", Filter */
.knopf-anstoss i{color:var(--c-primary)}
.knopf-text{background:transparent;color:var(--c-muted);padding:12px;font-size:14px}                                /* "Jetzt nicht", "Zurück" */
.knopf-anhang{background:transparent;border:1.5px dashed var(--c-line-strong);color:var(--c-muted);padding:9px 15px}
.knopf-rund{width:32px;height:32px;border-radius:50%;background:transparent;border:0;min-height:0;padding:0}          /* Dreipunkt */
.knopf-rund:hover{background:var(--c-neutral)}
```

**Chips, Badges, Pills**

```css
.chip{display:inline-flex;align-items:center;gap:6px;font-size:11px;letter-spacing:.05em;font-weight:700;line-height:1.4;
  padding:3px 10px;border-radius:999px}
.chip i{font-size:10px}
.chip-coach{background:var(--c-primary-soft);color:var(--c-primary)}                                /* "Mit Lea geteilt" */
.chip-kurs{background:color-mix(in srgb,var(--kc,#6E8B74) 15%,transparent);color:var(--kc,#5F8C6A)}  /* Kursfarbe */
.badge{display:inline-flex;align-items:center;gap:6px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;font-weight:700;
  padding:4px 10px;border-radius:999px;white-space:nowrap}
.badge-live{background:var(--c-success);color:#fff}
.badge-rec{background:var(--c-neutral);color:var(--c-text-soft)}
.badge-aufgabe{background:transparent;border:1.5px solid var(--c-ghost);color:var(--c-muted)}
.badge-aufgabe.ok{border-color:var(--c-success);color:var(--c-success)}
/* Filter-Pills: aktiv DUNKEL, nicht Terracotta */
.pille{display:inline-flex;align-items:center;gap:8px;min-height:40px;border:1.5px solid var(--c-card-border);background:var(--c-surface);
  border-radius:999px;padding:8px 15px;font-size:14px;font-weight:600;color:var(--c-text);text-decoration:none;transition:background .15s,border-color .15s}
.pille.an{background:var(--c-text);border-color:var(--c-text);color:#fff}
/* Segment-Umschalter */
.segment{display:inline-flex;align-items:center;gap:4px;background:var(--c-neutral);border-radius:999px;padding:4px}
.segment a{padding:7px 14px;border-radius:999px;font-size:13px;font-weight:600;color:var(--c-muted);text-decoration:none}
.segment a.an{background:#fff;color:var(--c-text)}
/* Klebender Filterstreifen */
.filterstreifen{position:sticky;top:58px;z-index:6;background:color-mix(in srgb,var(--c-neutral) 94%,transparent);backdrop-filter:blur(8px);
  border-top:1px solid var(--c-card-border);border-bottom:1px solid var(--c-card-border);padding:11px 0;margin:0 0 16px}
@media (max-width:640px){.filterstreifen{top:54px;padding:10px 0}}
```

**Fortschrittsbalken und Haken**

```css
.balken{flex:1;height:6px;border-radius:999px;background:var(--c-neutral);overflow:hidden}
.balken span{display:block;height:100%;border-radius:999px;background:var(--kc,var(--c-primary))}
.balken-label{font-size:13px;font-weight:600;color:var(--c-muted);white-space:nowrap}   /* "3 von 7 erledigt" */
.balken-duenn{height:4px}                                                                /* Einführung, Sheets */
.haken{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border:1.5px solid #C6C0AE;
  border-radius:7px;background:var(--c-surface);color:transparent;transition:background .14s,border-color .14s}
.haken:hover{border-color:var(--c-primary)}
.haken.an{background:var(--c-primary);border-color:var(--c-primary);color:#FFFDF9}
```

**Eingabefeld**

```css
.feld{width:100%;box-sizing:border-box;border:1px solid var(--c-card-border);border-radius:12px;padding:12px 14px;
  font-size:16px;font-family:inherit;line-height:1.5;background:#fff;color:var(--c-text)}
.feld:focus{outline:0;border-color:var(--c-primary)}                     /* alt: nur Rahmen, kein Ring */
.feld-label{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:var(--c-muted);margin-bottom:5px}
.feld-label-weich{font-size:12.5px;font-weight:600;letter-spacing:0;text-transform:none;color:var(--c-text-soft)}
.suche{display:flex;align-items:center;gap:9px;background:#fff;border:1px solid var(--c-card-border);border-radius:999px;padding:4px 14px}
.suche i{color:var(--c-ghost);font-size:13px} .suche input{flex:1;border:0;background:transparent;font-size:15px;padding:8px 0}
.mit-mikro textarea{padding-right:54px}                                  /* Platz fürs Mikrofon */
.feld-pille{border-radius:999px;padding:13px 20px}                       /* E-Mail auf der Gratis-Tür */
```

**Popover-Menü (Dreipunkt), Bottom-Sheet, Chatknopf**

```css
.menue{position:fixed;z-index:70;background:#fff;border:1px solid var(--c-card-border);border-radius:14px;box-shadow:var(--shadow-pop);
  padding:6px;min-width:226px;max-width:min(288px,calc(100vw - 24px));max-height:70vh;overflow-y:auto}
.menue .e{display:flex;align-items:center;gap:11px;width:100%;border:0;background:transparent;padding:11px 13px;border-radius:10px;
  font-size:14.5px;font-weight:600;color:var(--c-text);text-align:left}
.menue .e:hover{background:var(--c-surface)} .menue .e i{width:16px;color:var(--c-muted);font-size:13px}
.menue .e.an,.menue .e.an i{color:var(--c-primary)} .menue .e.gefahr,.menue .e.gefahr i{color:var(--c-danger)}
.menue .trenner{font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--c-faint);padding:10px 13px 5px;
  border-top:1px solid var(--c-line-soft);margin-top:4px}

.sheet{position:fixed;left:0;right:0;bottom:0;z-index:65;transform:translateY(110%);visibility:hidden;
  transition:transform .3s ease,visibility 0s linear .3s}
.sheet.offen{transform:none;visibility:visible;transition:transform .3s ease}
.sheet-in{position:relative;max-width:560px;margin:0 auto;background:var(--c-surface);border-radius:22px 22px 0 0;
  padding:26px 22px calc(26px + env(safe-area-inset-bottom));box-shadow:0 -12px 40px rgba(0,0,0,.18);font-size:16px;line-height:1.55}
.sheet h3{font-family:var(--font-heading);font-size:22px;margin:0 0 10px;padding-right:40px}
.sheet .schritt b{flex:0 0 26px;width:26px;height:26px;border-radius:50%;background:var(--c-text);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:13px}
/* Infofenster: mobil Sheet mit #FFFDF8 und max-height 88dvh, ab 900 px zentriertes Modal Radius 20 */

.chat-knopf{position:fixed;right:18px;bottom:18px;z-index:45;width:56px;height:56px;border-radius:50%;background:var(--c-primary);color:#fff;
  font-size:20px;box-shadow:0 8px 24px color-mix(in srgb,var(--c-text) 22%,transparent);transition:transform .2s,opacity .2s}
.chat-knopf:hover{transform:scale(1.05)}
.chat-knopf .zahl{position:absolute;top:-2px;right:-2px;min-width:20px;height:20px;border-radius:999px;background:var(--c-danger);
  color:#fff;font-size:11px;font-weight:700;line-height:20px;padding:0 5px}
```

**Lesezeichen, Reaktion, Kommentar**

```css
.merken{border:0;background:transparent;color:var(--c-ghost);font-size:17px;line-height:1;padding:6px;border-radius:50%;transition:color .15s,transform .15s}
.merken:hover{color:var(--c-primary);transform:scale(1.1)} .merken.an{color:var(--c-primary)}
.reaktion{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--c-card-border);background:#fff;border-radius:999px;
  padding:5px 11px;font-size:14px;min-height:32px;line-height:1}
.reaktion.an{background:var(--c-primary-tint);border-color:var(--c-primary)}
.reaktion .z{font-size:12.5px;font-weight:700;color:var(--c-text-soft);font-family:system-ui,sans-serif}
.kommentar .t{font-size:14.5px;line-height:1.55;color:var(--c-text-soft)}
.kommentar.vom-coach .t{background:var(--c-surface);border-radius:12px;padding:9px 12px}
```

---

## 3. Unterschiede zur neuen App und was zu ändern ist

### 3.1 Branding und Tokens

| Punkt | neu heute | alt | Ändern in |
|---|---|---|---|
| Textschrift | `system-ui` | Oxygen Mono | Branding-Wert `font_body` des Mandanten Lea und `font_url`. **Achtung:** `TenantSeeder::vorgabenErgaenzen` lässt bestehende Werte stehen, eine Änderung im Seeder erreicht den laufenden Mandanten nicht. Wert im Coach-Bereich (Einstellungen) oder per Kommando setzen. |
| Schrift der Wortmarke | fehlt | Cutive Mono | neuer Schlüssel `font_mark` in `Branding::DEFAULTS` und `cssVariables()` (`--font-mark`), `app.css` `@theme` `--font-mark`. |
| Seitenhintergrund | `#F6F2EA` | `#FAF8F3` mobil, `#EFEDE7` Desktop | Branding `bg`, neuer Schlüssel `bg_wide`. |
| Leistenfarbe, `theme-color` | `card_bg` `#FFFDF8` | `#FAF8F3` | neuer Schlüssel `bar_bg`, im Layout `<meta name="theme-color">` und Manifest `theme_color` darauf umstellen (`app.blade.php`, `auth.blade.php`, `Branding::manifest`). |
| Fehlende Farben | 10 Farben | 20 (Tabelle 2.1) | `Branding::DEFAULTS` um `faint`, `ghost`, `primary_hover`, `primary_soft`, `primary_tint`, `primary_line`, `success_ink`, `success_soft`, `line_soft`, `line_strong`, `surface`, `neutral`, `done` erweitern; neutrale Vorgaben per `color-mix` aus den bestehenden ableiten, damit andere Mandanten nichts pflegen müssen. In `app.css` `@theme inline` als `--color-*` eintragen. |
| Portrait in der Leiste | nur `logo_url` | rundes Portrait 32 px | neuer Schlüssel `avatar_url` (Bild der Coachin), getrennt vom Logo. |
| Zusatz der Wortmarke | fehlt | "Mitgliederbereich" | neuer Schlüssel `mark_suffix` (Text), kein Lea-Text im Code. |
| Zeilenhöhe Text | 1.55 | 1.6 | `app.css` `body{line-height:1.6}`. |
| Kontaktdaten im Drawer-Fuss | fehlen | Impressum, Datenschutz, Mail, Website | `tenants.settings` (`legal.impressum_url`, `legal.datenschutz_url`, `contact.email`, `website_url`). |

### 3.2 Typografie

- `app.css` `h1{font-size:var(--fs-2xl)}` gibt 26 px überall. Alt: 32 px ab 761 px, 26 px mobil. Ändern: `h1{font-size:var(--fs-3xl)}` plus Media-Query auf `--fs-2xl`.
- `h2` ist neu 20 px und steckt als Lora-Titel **in** jeder Karte (`components/karte.blade.php` mit `titel`, Startseite "Neu für dich", "Deine Aufgaben", Profil-Karten). Alt standen Abschnitte als **Eyebrow über** den Karten (11 px, 700, `.12em`, `#86816F`, Terracotta-Icon, Zählerpille), und Karten-Titel waren Textschrift 15.5 px halbfett. Ändern: `x-karte` bekommt `titel` als `.abschnitt` vor der Karte (oder neue Komponente `x-abschnitt`), `h2` in Karten durch `.t` ersetzen (`home.blade.php`, `profil.blade.php`, `kurse/*` prüfen).
- Eyebrow neu: `hinweis uppercase tracking-wider text-xs font-semibold` = `.05em`, 600, `#86816F`. Alt: `.12em`, 700, `#A9A395`. Ändern: eine Klasse `.eyebrow` in `app.css`, die Tailwind-Kette an allen Stellen ersetzen (`home.blade.php`, `willkommen.blade.php`, `material/index.blade.php` u. a.).
- Eingabefelder: `.feld{font-size:var(--fs-base)}` = 15.5 px, **iOS zoomt beim Antippen**. Alt 16 px. Ändern in `app.css` auf `16px`.
- Fokus: neu 3-px-Ring in Primärfarbe, alt nur Rahmenfarbe. Geschmackssache, alt war ruhiger.

### 3.3 Kopf und Navigation

- `app.blade.php` Kopf: Burger links, Portrait plus Wortmarke, Platzhalter rechts; deckend `var(--c-bar)` statt `color-mix(... 88%)` mit Blur; Höhe 52 px + Safe-Area mobil, 60 / 64 px Desktop; `padding-top:env(safe-area-inset-top)` fehlt heute ganz (im Standalone-Modus rutscht der Kopf unter die Statusleiste).
- Drawer neu bauen (Partial `components/drawer.blade.php`, JS in `public/js/app.js`): alle Punkte (Start, Kurse mit jedem Kurs als Unterpunkt, Termine, Material, Impulse, Themen, Merkliste, Journal mit Aufgaben / Notizen / Reflexion, Gespräch, Profil mit Unterpunkten, Coach-Bereich für Owner und Team, Hilfe, Abmelden), Fuss mit rechtlichen Links. Heute fehlen mobil Abmelden, Material, Impulse, Gespräch als Navigationspunkte.
- Bottom-Nav darf bleiben (echte Verbesserung), aber: Hintergrund `var(--c-bar)` statt `var(--c-card)`, Icons wie im Drawer (gefüllt, FA6 oder gleichwertige Solid-SVGs), und ein Punkt "Mehr" (Burger) oder der Burger oben öffnet den Drawer. Entscheidung für Sebastian: Bottom-Nav plus Burger oben (alt vertraut) oder Bottom-Nav mit "Mehr" als fünftem Punkt statt "Profil".
- Chatknopf (`app.css` `.chat-knopf`): 56 px, Schatten `0 8px 24px` Tinte 22 %, Hover `scale(1.05)`, beim Scrollen nach unten ausblenden. Für Owner und Team die Schublade mit allen Gesprächen statt Link.
- Desktop-Nav (`.nav`): alt gab es keine horizontale Leiste, auch am Desktop Burger und Drawer (340 px). Die horizontale Leiste mit 9 Punkten plus Abmelden-Knopf läuft bei 720 px Spaltenbreite eng. Vorschlag: am Desktop Drawer wie alt, oder Leiste auf 5 Punkte plus "Mehr".

### 3.4 Startseite (`resources/views/home.blade.php`, Daten aus `HomeController`)

1. "Neu für dich": einzeilige, **klickbare** Karten (`.neu-zeile`) mit Icon, Herkunft, Datum, höchstens 3, darunter "Alles anzeigen". Ungelesene mit `.karte.neu`. Klick öffnet das Infofenster bzw. das Ziel.
2. "Weiter im Kurs / Diese Woche": farbige Karte `.woche` mit Verlauf aus `program.color`, Kursbild oder Icon rund oben rechts, "Woche n von m", Balken weiss auf Weiss 18 %, "Als Nächstes: ...", "Zur Woche →". Ganze Karte ist der Link.
3. Termin als `.termin-hero` mit relativer Angabe ("Jetzt", "Heute", "Morgen", "In 3 Tagen", sonst "9. Oktober"), Zustand "Jetzt" grün gefüllt, Knopf je Art (Zoom, "Reflexion schreiben", "Frage stellen", sonst ruhiger "Alle Termine").
4. Abschnittsköpfe als `.abschnitt` mit Icon und Zählerpille ("Offene Aufgaben 5").
5. Haken der Aufgaben: 24 px, Radius 7, gefüllt Terracotta mit weissem Häkchen.
6. Kacheln: Icon in Terracotta oben, eine Zahl in Lora wie alt (`lea-club-kachel`), oder weglassen, wenn der Drawer alles trägt. Heute doppeln sie die Navigation ohne Mehrwert.
7. Leertext, wenn nichts offen ist.
8. Für Rolle `guest`: Begrüssungskarte (`.karte` mit Lora-Titel "Schön, dass du da bist, Vorname", Knopf zum Gespräch) statt leerer Blöcke; Text aus `tenants.settings`.

### 3.5 Bausteine (`resources/css/app.css`, Komponenten)

| Baustein | neu | alt | Änderung |
|---|---|---|---|
| `.karte` | Masse gleich | gleich | Varianten `.neu`, `.fertig`, `.offen`, `.heute`, `.flaeche` ergänzen; `a.karte:hover` Rahmen Terracotta (heute nur in den Kacheln per Tailwind). `.karte + .karte{margin-top}` durch `margin-bottom:8px` ersetzen, sonst bricht der Abstand bei gemischten Geschwistern. |
| `.knopf` | Hover `filter:brightness(.94)`, 13.5 px, Rahmen 1.5 px in Primär | Hover `#9E6850`, kein Rahmen | `background:var(--c-primary-hover)`; `.knopf-dunkel`, `.knopf-ruhig`, `.knopf-anstoss`, `.knopf-text`, `.knopf-anhang` ergänzen. |
| `.knopf-leise` | transparent, Rahmen `#E4DFD2` | "ruhig": Fläche `#fff`, Rahmen 1.5 px `#D8D2C4` | Hintergrund und Rahmenfarbe angleichen. |
| Filter-Pills | `.knopf` / `.knopf-leise` mit Inline-Style 36 px, aktiv Terracotta | 40 px, Fläche `#FAF8F3`, aktiv **Tinte** `#2E2D29` | Klasse `.pille` / `.pille.an`, in `termine/index`, `material/index`, weiteren Listen einsetzen; Inline-Styles entfernen. |
| Merken | 36-px-Kreis mit Rahmen | nacktes Lesezeichen 17 px `#C9C3B5`, aktiv Terracotta | `components/merken.blade.php` und die Kopie in `material/index.blade.php` (doppelt gebaut, auf `x-merken` umstellen). |
| Eyebrow | Tailwind-Kette | `.eyebrow` | siehe 3.2. |
| Fortschrittsbalken | `h-1.5 bg-line` (`#E4DFD2`) | 6 px, Spur `#EFEDE7`, Label "n von m erledigt" 13 px 600 | Klasse `.balken`, Spur `--c-neutral`. |
| Feld | 15.5 px, Ring | 16 px, nur Rahmen | siehe 3.2. |
| Meldung | Rahmen farbig, Fläche Karte | Hinweisbox `#F0E4DA` (Fehler) bzw. `#E6ECE6` (ok), Radius 12, ohne Rahmen | `.meldung-gut`, `.meldung-schlecht` anpassen. |
| Chips / Badges | kaum vorhanden | `.chip`, `.badge-*` | ergänzen, Termine und Journal nutzen sie. |
| Schleier | `.schleier` 55 % | 55 % plus `blur(3px)` | `backdrop-filter:blur(3px)` ergänzen. |
| Icons | Outline-SVG 1.8 | FA6 Solid, Terracotta führend | Entweder FA6 (cdnjs) im Layout laden oder ein kleines Blade-Icon-Set mit gefüllten Formen; Farbe der führenden Icons Terracotta. |

### 3.6 Einführung, Infofenster, PWA, Pull

- `willkommen.blade.php`: als Overlay über der Startseite statt eigener Seite (Box 520 px, Radius 22, Schatten Modal, mobil Vollbild), Balken 4 px, Punkte unten (7 px, aktiv Terracotta), Icon je Titel, optional Bild je Schritt aus `tenants.settings.onboarding.steps[].bild`, Telefon speichert beim Tippen. Weiterleitung aus `HomeController` kann bleiben, wenn die Seite so aussieht.
- Infofenster: Komponente `components/sheet.blade.php` plus JS; Einträge mit `data-info="{url}"` laden per `fetch` ein HTML-Fragment (`impulse.show` mit `?fragment=1`). Mobil Bottom-Sheet, ab 900 px Modal.
- Install- und Push-Dialog: Logik aus `lea_app_js()` nach `public/js/app.js` übernehmen (`beforeinstallprompt`, iOS-Erkennung, `display-mode: standalone`, `localStorage` "heute nicht"), Texte mit Coach-Name aus dem Branding. Statuszeile unten auf der Startseite.
- `public/sw.js`: Offline-Antwort für Navigationsanfragen, nur wenn `navigator.onLine === false`, Seite in Branding-Farben.
- Pull-to-Refresh: `lea-pull.php` fast 1:1 in `public/js/app.js` (nur `max-width:760px`, Schwelle 72 px, Dämpfung 0.55, max 90 px; im Gespräch nur `gespraech.neu` holen statt `location.reload()`). Markup `<div class="pull">` im Layout.

### 3.7 Anmeldung (`auth/anmelden.blade.php`, `layouts/auth.blade.php`)

- Hintergrund und Schrift folgen automatisch dem Branding, sobald 3.1 steht.
- Oben rundes Portrait oder Schlüssel-Icon im Kreis (80 px, `#EFEDE7`) statt Logo-Text, darunter Lora-Titel.
- Google-Knopf: offizieller Pill-Knopf (weiss, Umriss) bzw. gleich aussehender Link mit G-Logo; Apple schwarz (`#000`, weiss, Pill, 600). Heute beide "leise".
- Gratis-Tür (Konto aus E-Mail anlegen, Newsletter-Haken) fehlt; braucht Entscheidung, ob der Freebie-Einstieg in die App umzieht.

### 3.8 Profil (`profil.blade.php`)

- Kopf: Avatar 64 px rund, Name Lora 22 px, Mail 14 px `#86816F`.
- Übersicht als `.kachel`-Liste (Meine Daten, Benachrichtigungen, Anmelden, Hilfe) mit Chevron, Unterseiten mit "← Zurück" (14 px `#86816F`), oder zumindest Karten mit `.abschnitt`-Köpfen.
- Abmelden als Textlink unten mittig mit Icon.
- Karte "Hilfe": Technik-Formular (Wo klemmt es / Was passiert, schickt Seite, Bildschirm, Browser mit; Empfänger aus `tenants.settings.support`), optional WhatsApp-Link aus Settings.

### 3.9 Seitenaufbau (`app.css` `.inhalt`, `.seite`)

- Mobil oben 12 px statt 20 px Luft, seitlich 14 px statt 16 px (alt), unten `88px` plus Bottom-Nav.
- Spaltenbreite: Startseite alt 640 px, Listen 720 px. Heute alles 720 px. Für die Startseite `max-w-[640px]` oder Branding `page_width_start`.

---

## 4. Fehlt und ist wichtig

Nach Wirkung auf "sieht aus wie früher" und auf den Alltag der Teilnehmerinnen geordnet.

1. **Schrift und Grundfarbe:** Oxygen Mono als Textschrift, Cutive Mono für die Wortmarke, Seitenhintergrund `#FAF8F3` / `#EFEDE7`, Leiste `#FAF8F3`. Das ist der grösste Hebel, und es sind nur Branding-Werte (Achtung Seeder, siehe 3.1).
2. **Karten-Hierarchie wie alt:** Abschnittsköpfe als Eyebrow mit Terracotta-Icon und Zählerpille über den Karten, Karten-Titel in Textschrift 15.5 px halbfett statt Lora in jeder Karte, Klasse `.eyebrow` mit `.12em` und 700.
3. **App-Leiste mit Portrait und Wortmarke plus Drawer** mit allen Punkten, Abmelden und rechtlichen Links. Mobil fehlen heute Abmelden, Material, Impulse und Gespräch in der Navigation, Impressum und Datenschutz ganz. Dazu Safe-Area oben im Kopf.
4. **Startseite:** "Neu für dich" klickbar und auf 3 Zeilen begrenzt (heute nicht klickbar und bildschirmfüllend), farbige "Diese Woche"-Karte, Termin-Hero mit "Jetzt / Heute / Morgen" und grünem Zustand.
5. **Infofenster (Bottom-Sheet)** als gemeinsamer Baustein für Beiträge, Neu-Einträge, Push- und Install-Dialog.
6. **Installieren und Push aktiv anbieten** (Android-Knopf, iPhone-Anleitung, automatischer Push-Hinweis im Standalone-Modus). Ohne das landen weniger Leute bei Push, und die Abendmail wird zur Regel.
7. **Ziehen zum Aktualisieren:** Im Standalone-Modus gibt es sonst keinen Weg, die Seite neu zu laden.
8. **Filter-Pills aktiv dunkel, Lesezeichen schlicht, Knopf-Hover `#9E6850`, Felder 16 px** (iOS-Zoom).
9. **Technik-Hilfe-Formular** mit automatisch mitgeschickter Seite, Gerät und Browser.
10. **Einführung als Overlay** mit Punkten, Icons und optionalen Bildern.
11. **Profil-Übersicht** mit Avatar, Kacheln und Google-/Apple-Verknüpfung.
12. **Gast-Ansicht:** Begrüssung und reduzierte Navigation für Rolle `guest`.
13. **Freundliche "kein Zugang"-Karte** statt nackter 403 bei Kursen, die nicht gebucht sind.
14. **Offline-Seite** im Service Worker.
15. Später, eigener Entscheid: Gratis-Tür mit Kontoanlage, Hänger-Mails und Goldnugget-Abschlussmail (heute noch WordPress).
