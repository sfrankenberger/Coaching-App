# G4 Community, Club, Inhalte, Startseite, Huelle, Hilfe: Lueckenliste

Stand der Pruefung: 30.09.2026. Alter Code aus `alt/novamira-sandbox/`, neue App `/home/user/Coaching-App` (Stand f337175). Jede Aussage am Code geprueft (grep und Lesen), was nicht geprueft werden konnte, ist als "nicht geprueft" vermerkt.

## Gelesene Dateien

Alte PHP-Dateien (alle ganz gelesen, reine CSS-Dateien nur auf Logik durchsucht):
- `lea-community.php`: Reiter Von Lea und Fragen, Kursauswahl
- `lea-community-geteilt.php`: geteilte Eintraege im Kurs zeigen
- `.lea-community.php.disabled`: leer, stillgelegt
- `lea-club.php`: Kurs-, Lektions-, Terminseite, Embeds, Erledigt
- `lea-club-a-schalter.php`: Schalter Elementor-Vorlagen
- `lea-club-callbacks.php`: JetEngine-Callbacks (Termin, Ressource, News)
- `lea-club-dashboard.php`: Tabs, Naechstes, Kacheln
- `lea-club-kurs-template-css.php`, `lea-club-listings-css.php`, `lea-club-template-css.php`: nur CSS, keine Logik
- `lea-club-macros.php`: Makros, Gratiskurse, Vorschau intern
- `lea-club-mailster.php`: Mailster bei Buchung, News, Termin
- `lea-club-ressourcen-karte.php`: Ressourcen-Karte Bibliothek
- `lea-club-ressourcen.php`: Ressourcen-Ordner, Einzelseite
- `lea-club-visibility.php`: JetEngine-Sichtbarkeitsbedingungen
- `lea-coffee-coaching.php`: Termin-Teaser worum es ging
- `lea-impulse.php`: Impulse-Flaeche Blog Podcast Instagram
- `lea-impulsbild.php`: KI-Beitragsbilder erzeugen
- `lea-impuls-instagram-ki.php`: Instagram einordnen per KI
- `lea-podcast-archiv.php`, `lea-podcast-callbacks.php`, `lea-podcast-extras.php`: oeffentliche Podcast-Seiten
- `lea-podcast-import.php`: RSS-Import Podcast
- `lea-podcast-ki.php`: Kapitel, FAQ, Transkript per KI
- `lea-podcast-lauf.php`: Sammellauf Podcast-KI
- `lea-coach-podcast.php`: Shortcode Coach-Folgen
- `lea-themen.php`: Thema, Werkzeuge, KI-Verschlagwortung
- `lea-fundus.php`: Nachschlagen, Sammlungen, Verlauf
- `lea-ressourcen-teilen.php`: Merken und Teilen bei Ressourcen
- `lea-ressourcen-v2.php`, `lea-ressourcen-v3.php`: Ressourcen-Regal, Angeschaut, To-dos
- `lea-neuigkeiten.php`: Infos von Lea, gelesen
- `lea-start-lea.php`: Arbeitsliste fuer Lea
- `lea-start2.php`: Startseite Teilnehmerin
- `lea-startseite-schalter.php`: Startseite per Konstante
- `lea-willkommen.php`: Einfuehrung in Schritten
- `lea-hilfe.php`: Technik-Meldung, Passwort-Kurzweg
- `lea-infofenster.php`: Beitraege im Fenster ueberall
- `lea-app.php`: PWA, Manifest, Service Worker, Push- und Installfenster
- `lea-app-modus.php`, `lea-app-vorschau.php`: App-Modus, Handyrahmen Website
- `lea-seiten.php`, `lea-seitenaufbau.php`, `vorlagen/lea-seite.php`: eigene Seitenhuelle
- `lea-seite-angebot.php`, `lea-bereich.php`, `lea-vorschau.php`: Website-Seiten
- `lea-mobilmenue.php`, `lea-menue-ordnung.php`: Menue
- `lea-ballast.php`, `lea-neu.php`: Performance, Neu-Punkte
- `lea-sichtbarkeit-erweiterung.php`, `lea-sichtbarkeit-hinweis.php`: Sichtbarkeit im WP-Backend
- `LIESMICH.md`: Bausteine-Uebersicht

JS aus WordPress-Optionen (`wpjs/`): `lea_aufbau_js.js` (gehoert zu `lea-seitenaufbau.php`, gelesen), `lea_fi_js.js` (Filterleiste einklappen, von `lea-filter.php`, gelesen wegen Ressourcen-Filter). `lea_ap_js.js` (Wochenaufgaben) und `lea_pl_js.js` (Player) gehoeren zu anderen Gruppen (`lea-aufgaben-plus.php`, `lea-player.php`), nur ueberflogen und hier nicht bewertet.

Neue Dateien geprueft: `routes/web.php`, `HomeController`, `ImpulseController`, `ThemenController`, `NachschlagenController`, `MaterialController`, `MerklisteController`, `WillkommenController`, `SucheController`, `FragenController`, `ProfilController@hilfe`, `KommentarController`, `resources/views/home.blade.php`, `community*.blade.php`, `impulse/*`, `themen/*`, `nachschlagen/*`, `material/*`, `hilfe.blade.php`, `willkommen.blade.php`, `components/layouts/app.blade.php`, `components/inhalt-zeile.blade.php`, `app/Content/*` (Inhalte, Fundus, FeedImport), `app/Coach/Arbeitsliste.php`, `Neues.php`, `app/Notifications/Runden.php`, `app/Observers/PostObserver.php`, `EventObserver.php`, `app/Shop/Zugang.php`, `app/Ai/Summarizer.php`, `app/Jobs/PrepareEpisode.php`, `app/Support/Video.php`, `app/Models/Post`, `PodcastEpisode`, `Program`, `Note`, `Reflection`, Filament `Posts`, `Podcast`, `Topics`, `Pages/Assistent`, `public/js/app.js`, `public/sw.js`, `routes/console.php`.

Hinweis zur Zuordnung: Kursraum, Termine, Aufgaben, Journal, Player und Gast gehoeren zu anderen Gruppen. Hier stehen nur die Zeilen, die in meinen Dateien vorkommen, ohne deren Feinheiten zu bewerten.

## Tabelle

Status: vorhanden, teilweise, fehlt, Website, Altlast.

| Funktion | Alt (Datei) | Neu (Ort) | Status | Was fehlt oder abweicht |
|---|---|---|---|---|
| **Community** | | | | |
| Community-Seite: alle Fragen aus meinen Kursen, Kursauswahl bei mehreren Kursen | lea-community.php | `/community`, `FragenController@community`, `community.blade.php` (Kurs-Pillen, Filter Offen/Call/Beantwortet) | vorhanden | Kurs-Pillen nur fuer Programme mit eigenem Raum (`Program::gemeinschaft()`: Hybrid, Club). Selbstlernkurse werden nur vermerkt. Gestern so geaendert, gewollt. |
| Zwei Reiter an einem Ort: "Von Lea" (Neuigkeiten) und "Fragen und Austausch" | lea-community.php | Neuigkeiten in `/impulse` (Filter Neuigkeiten), auf der Kursseite (`KursController` `infos`), Start "Was ist neu" | teilweise | Kein gemeinsamer Ort mit Reiter. Kursbeitraege von Lea erscheinen nicht in der Community. |
| Community/Fragen im Kurs eingebettet, auf diesen Kurs beschraenkt | lea-community.php (`kursraum=1`) | `/kurse/{slug}/fragen`, `kurse/fragen.blade.php` | vorhanden | |
| Leerer Zustand ("Sobald du in einem Kurs bist ...") | lea-community.php | `community.blade.php` (`x-leer`) | vorhanden | |
| Geteilt aus dem Kurs: was jemand fuer den Kurs freigibt (Notiz, Aufgabe, Reflexion) erscheint fuer die anderen, mit Karte, Reaktionen, Kommentaren, ohne Beitraege von Lea, neueste 12 | lea-community-geteilt.php | Sichtbarkeit "Im Kurs sichtbar / In der Community" wird gespeichert (`Note::VISIBILITIES`, `ReflexionController`, `AufgabenController`) | fehlt | Nirgends eine Ansicht fuer andere Teilnehmerinnen. Geteiltes sieht nur das Team (Dossier, Arbeitsliste). Der Text in `community.blade.php` verspricht es aber ("alles, was jemand aus den Kursen teilt"). `Comment` und `Reaction` gibt es schon, es fehlt Liste und Karte. |
| Filter "Anzeigen" nach Kurs bei den Neuigkeiten | lea-neuigkeiten.php | Impulse-Liste ohne Kursfilter | fehlt | Nur ueber die Kursseite erreichbar. |
| Reiter-Datei stillgelegt | .lea-community.php.disabled | | Altlast | Datei ist leer. |
| **Club: Kurs-, Lektions-, Terminseiten (Details in Gruppe Kursraum/Termine)** | | | | |
| Kursseite: Start-/Weiter-Knopf, Fortschrittsbalken, Module mit Lektionen, Live-Termine, Aufzeichnungen, Referenzen | lea-club.php | `KursController@show`, `kurse/show.blade.php`, `ProgressTracker` | vorhanden | Feinheiten nicht geprueft (Kursraum-Gruppe). |
| Lektion: mehrere Videos mit Titel, Info, To-Dos, Termin dazu, Material, "Als erledigt markieren", Vorherige/Naechste | lea-club.php | `EinheitController`, `kurse/einheit.blade.php`, Route `kurse.erledigt` | vorhanden | Feinheiten nicht geprueft (Kursraum-Gruppe). |
| Terminseite: Datum, Ort, Zoom, Aufzeichnung nach dem Termin, Material "Bis zum Termin" | lea-club.php | `TermineController@show`, `termine/show.blade.php` | vorhanden | Feinheiten nicht geprueft (Termine-Gruppe). |
| Video-Einbettung: Vimeo (mit Hash und `#t=`), YouTube nocookie, Bunny, mp4; Audio-Einbettung | lea-club.php (`lea_club_video_embed`, `lea_club_audio_embed`) | `App\Support\Video::embed`, `Video::isAudio` | vorhanden | |
| Leere Abschnitte samt Ueberschrift ausblenden, Login-Hinweis, Marketing-Bloecke im Bereich ausblenden | lea-club.php | Blade-Bedingungen, `/anmelden` | Altlast | JetEngine-/Elementor-Technik, in der App von selbst so. |
| Elementor/PHP-Gerueest umschalten, JetEngine-Sichtbarkeitsbedingungen, Relations-Makros | lea-club-a-schalter.php, lea-club-visibility.php, lea-club-macros.php (Makros) | | Altlast | Reine WordPress-Technik. |
| Gratiskurse fuer alle Angemeldeten (`kurs_gratis`) | lea-club-macros.php (`lea_gratis_kurse`) | `ProgramAccess` (`settings.gratis`) | vorhanden | |
| Nicht freigeschaltete Kurse nur fuer Lea/Team/Technik (`lea_nur_intern`) | lea-club-macros.php | `Program.is_internal`, `is_published`, `ProgramAccess` | vorhanden | |
| Alle Termine eines Kurses (ueber Module und Lektionen), 1:1-Termine nur fuer die Person | lea-club-macros.php, lea-club-dashboard.php | `Begleitung::eventsQuery` | vorhanden | Nicht im Detail geprueft. |
| **Navigation, Huelle, Menue** | | | | |
| Navigation mit Icons: Uebersicht, 1:1 Coaching, Termine, Nachschlagen, Mein Journal (Aufgaben, Notizen, Reflexionen), Kurse, Ressourcen, Community, Impulse, Profil | lea-club-dashboard.php (`lea_club_tabs`), lea-app.php (Drawer) | `components/layouts/app.blade.php` (Drawer, Hamburger) | vorhanden | |
| Menuepunkte "Meine Zeitleiste" und "Meine Projekte" | lea-club-dashboard.php | | fehlt | Kein Menuepunkt. `/journal` (`JournalController`) gibt es, ist aber nur ueber Rueck-Links auf Aufgaben/Notizen/Reflexion erreichbar. Projekte gibt es in der App gar nicht (Journal-Gruppe). |
| Menue fuer Lea/Team: Admin mit Coachees, Assistent, Werkzeuge | lea-club-dashboard.php (`nur_lea`) | Arbeitsplatz-Menue im Layout, `/coachees`, `/assistent`, `/coach` | vorhanden | |
| Kurse als Unterpunkte im Menue, ohne Einzelbegleitung, Kurzname | lea-app.php (`lea_app_menue`) | Layout (`$meineKurse`, ohne `one_on_one`) | vorhanden | Kursname nicht gekuerzt (`lea_kursname`), nur Titel. |
| Ordnung im Menue: kuerzere Namen, Hilfe unten, leere Gruppen weg | lea-menue-ordnung.php | Layout (fest verdrahtet: Meine Daten, Meine Buchungen, Benachrichtigungen, Hilfe) | vorhanden | |
| Neu-Punkte im Menue (Zahl bei Neuigkeiten, Aufzeichnungen, Ressourcen; weg nach Besuch; Community-Beitraege zaehlen) | lea-neu.php | Layout zeigt nur Zaehler bei Gespraeche und Glocke | fehlt | Kein Zaehler an Impulse, Ressourcen, Community, Termine. Ersatz nur: Start "Was ist neu" mit "Alles gesehen". |
| Mitteilungen (Glocke) | (lea-nachrichten, nicht meine Datei) | `/mitteilungen`, `MitteilungenController` | vorhanden | |
| Adminleiste ausblenden, viewport-fit, Ballast, Emojis entfernen, eigene Seitenhuelle statt Elementor, Rewrite-Wächter | lea-app.php, lea-ballast.php, lea-seiten.php, vorlagen/lea-seite.php | | Altlast | WordPress-Technik. |
| App-Modus: Website-Header/Footer ausblenden, 640-px-Spalte, `?web=1` | lea-app-modus.php | Layout hat eigene Huelle (`seite-schmal`) | Altlast | |
| Mobiles Vollbild-Menue der Website | lea-mobilmenue.php | | Website | Header der oeffentlichen Seite bleibt in WordPress. |
| Bereichsweiche Coaching/Ausbildung, Vorschau-Link Ausbildung, Seite "Mit Lea arbeiten" | lea-bereich.php, lea-vorschau.php, lea-seite-angebot.php | | Website | |
| App-Vorschau im Handyrahmen mit Einstiegs-Formular | lea-app-vorschau.php | | Website | Gehoert auf die Website. Der Einstieg dahinter (Magic Link) existiert in der App. |
| Startseite per Konstante umschalten (`LEA_STARTSEITE`) | lea-startseite-schalter.php | | Altlast | Umschaltung passiert ueber DNS/Deploy. |
| Sichtbarkeit sichtbar machen im WP-Backend (Spalte, Warnung "nirgendwo sichtbar", Taxonomie fuer Termin/Club-News) | lea-sichtbarkeit-hinweis.php, lea-sichtbarkeit-erweiterung.php | `PostResource` (Spalte Sichtbar, Vorgabe "Alle Mitglieder") | Altlast | Warnung entfaellt, weil es keine Beitraege ohne Sichtbarkeit gibt. |
| Filterleiste einklappbar ("Filter (n)", Zurücksetzen, merkt Zustand) | lea_fi_js.js (lea-filter.php) | Pillen und Suche immer sichtbar (`material/index`, `impulse/index`) | teilweise | Kein Einklappen, kein "Zuruecksetzen"-Knopf. Reine Bequemlichkeit. |
| Schreib-Anstoss oben, klappt beim Antippen auf (Neue Notiz/Aufgabe/Eintrag/Frage) | lea-seitenaufbau.php, lea_aufbau_js.js | `<details class="baustein">` mit `knopf-anstoss` (z. B. `kurse/fragen`, Notizen, Aufgaben) | vorhanden | Schliesst nicht bei Klick daneben oder Escape. Nur kosmetisch. |
| **Startseite** | | | | |
| Start Teilnehmerin: Hallo, "Diese Woche im Kurs" mit Bild, Fortschritt, Als Naechstes, Zur Woche | lea-start2.php | `HomeController`, `home.blade.php` | vorhanden | |
| "Was ist neu" (Nachrichten, Beitraege, Podcast, Aufzeichnungen, Termine, Material, Aufgaben), drei Zeilen, Rest aufklappbar, "Alles gesehen" | lea-start2.php, lea-neu.php | `Runden::neuesFuer`, `HomeController@gesehen`, `neu.gesehen` | vorhanden | |
| "Lea hat geantwortet" (Antworten auf eigene Eintraege) im Strom | lea-start2.php (`lea_st2_von_lea`) | `Kommentare::schreiben` schickt Mitteilung und Push | teilweise | Steht nicht in der Liste "Was ist neu", nur in der Glocke. |
| Naechster Termin: Jetzt/Heute/Morgen/In n Tagen, ganztaegig, Knopf je Art (Zoom, Reflexion schreiben, Frage stellen) | lea-start2.php, lea-club-callbacks.php | `home.blade.php` | teilweise | Fragentag fuehrt in den Chat (`gespraech.index`), im Alten in den Kursraum "Frage stellen". Haken "Geschrieben" nach erledigter Reflexion fehlt. |
| Offene Aufgaben (4, "Alle n ansehen") | lea-start2.php | `home.blade.php` | vorhanden | |
| Block "Meine Projekte" | lea-start2.php | | fehlt | Projekte gibt es in der App nicht (Journal-Gruppe). |
| Start Gast (`lea_gast_start`) | lea-start2.php | | nicht geprueft | Gruppe Gast. |
| Hallo, schoen bist du da / Naechster Termin / Weiterlernen (erste offene Lektion) / Neu von Lea als drei Karten | lea-club-dashboard.php (`lea_club_next`) | `home.blade.php` in anderer Form | vorhanden | "Weiterlernen" nur fuer Wochenkurse mit laufender Woche, nicht fuer Selbstlernkurse. Im Alten war der Baustein nirgends mehr eingebunden. |
| Icon-Kacheln mit Zaehlern (Kurse begonnen, Termine diese Woche, Aufzeichnungen neu ...) | lea-club-dashboard.php (`lea_club_kacheln`) | | Altlast | Im Code nicht mehr eingebunden (nur evtl. in Elementor-Seite 765, nicht geprueft). |
| Umschalter Arbeitsliste / "Wie eine Teilnehmerin" | lea-start2.php | Route `/ansicht`, `AnsichtController`, `Coach\Ansicht` | vorhanden | |
| Arbeitsliste Lea/Andrea: wartet auf Antwort (mit "als gelesen"), mit dir geteilt, Fragen ohne Antwort, wartet auf Freigabe, als Naechstes, "Alles ruhig"-Satz, Testkonten ausgeblendet | lea-start-lea.php | `Coach\Arbeitsliste`, `arbeitsplatz/heute.blade.php`, `coachees.gelesen` | vorhanden | |
| **Einfuehrung, Hilfe, Installation** | | | | |
| Einfuehrung in Schritten beim ersten Besuch, jederzeit wieder, Telefonnummer, Push einschalten | lea-willkommen.php | `/willkommen`, `WillkommenController`, `willkommen.blade.php`, Weiterleitung in `HomeController` | vorhanden | Texte je Mandant in `settings.onboarding.steps`. Unterscheidung Einzel/Gruppe nur teilweise (Schritt 1). |
| Hilfe: Einfuehrung ansehen, Technik-Meldung mit Seite/Geraet/Browser/Kurse, WhatsApp | lea-hilfe.php | `/hilfe`, `ProfilController@hilfe`, `hilfe.blade.php` (`support.email`, `support.whatsapp`) | vorhanden | |
| Passwort-vergessen-Kurzweg | lea-hilfe.php | | Altlast | Anmeldung per Magic Link (Gruppe Anmeldung). |
| Beitraege von Lea oeffnen im Fenster ueberall | lea-infofenster.php | eigene Seite `/impulse/{slug}` | teilweise | Kein Overlay, dafuer eigene Seite mit Zurueck. Funktional gleichwertig. |
| Manifest, Icons, Service Worker, Push, Installations-Hinweis (Android, iPhone-Anleitung), Push-Fenster | lea-app.php | `Branding::manifest`, `/manifest.webmanifest`, `public/sw.js`, `app.js` (`beforeinstallprompt`, Sheet), `PushController` | vorhanden | |
| Offline-Seite bei fehlender Verbindung | lea-app.php (Service Worker) | `public/sw.js` (kein Fetch-Handler) | fehlt | Ohne Netz zeigt der Browser seine eigene Fehlerseite. Klein. |
| Abmelden mit einem Klick | lea-app.php | Layout, `POST /abmelden` | vorhanden | |
| **Neuigkeiten (Infos von Lea)** | | | | |
| Liste der Beitraege fuer meine Kurse und Club, nach Monaten gruppiert | lea-neuigkeiten.php | `/impulse?f=neuigkeit`, Kursseite `infos` | teilweise | Flach nach Datum, nicht nach Monat gruppiert. |
| Neu/gelesen-Markierung je Beitrag, Zaehler | lea-neuigkeiten.php (`lea_news_gelesen`) | nur "Alles gesehen" am Start | teilweise | Kein Gelesen-Zustand je Beitrag. |
| Zielgruppe eines Beitrags: alle oder mehrere bestimmte Kurse | lea-neuigkeiten.php, lea-club-macros.php | `Post.visibility` (Alle / ein Programm / Team), `program_id` | teilweise | Nur ein Programm je Beitrag statt mehrerer. |
| Lesen im Fenster mit Vor/Zurueck, Direktlink `?beitrag=` | lea-neuigkeiten.php | `/impulse/{slug}` | teilweise | Kein Vor/Zurueck. Direktlink ist die Beitragsseite. |
| Platzhalter `{firstname}` ersetzen | lea-neuigkeiten.php | `Post::bodyFor` | vorhanden | |
| Blog-Kategorien "rein/raus", 120-Tage-Grenze fuer Blog | lea-neuigkeiten.php | Import nur Sichtbarkeit Free, Sichtbarkeit je Beitrag | Altlast | |
| **Ressourcen** | | | | |
| Bibliothek: nur Ordner/Material meiner gebuchten Kurse, Ordner auf Entwurf-Kurs unsichtbar | lea-club-ressourcen.php, lea-ressourcen-v2.php, lea-ressourcen-v3.php | `/material`, `MaterialController@index`, `Begleitung::resourcesQuery` | vorhanden | |
| Filter nach Kurs (Pillen), Aufzeichnungen, Gemerkt, Suche | lea-ressourcen-v3.php | `material/index.blade.php` | vorhanden | |
| Filter nach Art (PDF, Video, Audio, Link, Bild) | lea-ressourcen-v2.php | | fehlt | |
| Filter Herkunft: Von Lea / Aus meiner Begleitung | lea-ressourcen-v2.php | Nur Vermerk "Fuer dich geteilt" in der Zeile | fehlt | |
| Sortierung: Neueste zuerst / Titel A-Z | lea-ressourcen-v2.php | fest neueste zuerst | fehlt | |
| Material aus der Begleitung (im Journal Geteiltes) im Regal | lea-ressourcen-v2.php, -v3.php | `Begleitung::resourcesQuery` (Vermerk "geteilt") | vorhanden | |
| Aufzeichnungen im Regal, Abspielen | lea-ressourcen-v3.php | `Begleitung::recordings`, Link auf `termine.show` | vorhanden | Eigene Seite statt Abspielfenster. |
| Karte mit Vorschaubild, Typ-Badge, Beschreibung, Dateiname, Dauer, Ordner | lea-club-ressourcen-karte.php | Zeilenliste in `material/index.blade.php` | teilweise | Zeile zeigt Titel, Typ, Kurs, Datum, Dauer. Kein Vorschaubild, keine Beschreibung, kein Dateiname. |
| Einzelseite: Player (Video/Audio), PDF/Link-Knopf, Text, Beschreibung, Rueck-Link | lea-club-ressourcen.php | `/material/{id}`, `material/show.blade.php`, `datei` | vorhanden | |
| "Gehoert zu" (Lektionen, in denen das Material haengt) auf der Einzelseite | lea-club-ressourcen.php | | fehlt | |
| Merken/Vergessen auf Ressource | lea-ressourcen-teilen.php | `x-merken`, `POST /merken` | vorhanden | |
| Teilen einer Ressource (Team, Fenster: Chat oder Link) im Abspielfenster und auf der Ressource | lea-ressourcen-teilen.php | Teilen nur im Nachschlagen (Vorschau, Auswahlleiste) | teilweise | Kein Teilen-Knopf auf `material/show` oder im Regal. |
| "Als angeschaut markieren" bei Aufzeichnung und Ressource, gruene Marke | lea-ressourcen-v2.php, -v3.php | `MedienController@position` (ab 80 %), `termine.gesehen` | teilweise | Aufzeichnung: Knopf "Gesehen" und automatisch. Ressource: Position wird gespeichert, aber keine Marke und kein Knopf in der Liste. |
| To-do-Uebersicht "Meine offenen Schritte" (Wochen-Todos aus Begleitung) | lea-ressourcen-v2.php | | nicht geprueft | Gehoert zu Aufgaben/Journal (`lea_meine_todos`). |
| Reflexion: Block "Aus deiner Begleitung" | lea-ressourcen-v2.php | | nicht geprueft | Journal-Gruppe. |
| Ordner-zu-Kurs fest im Code, JetSmartFilters | lea-club-ressourcen.php | | Altlast | |
| **Impulse** | | | | |
| Impulse-Flaeche: Blog, Podcast, Instagram gemischt, neueste zuerst, Bildkarten | lea-impulse.php | `/impulse`, `ImpulseController@index`, `components/inhalt-zeile` | teilweise | Blog und Podcast da, Instagram fehlt (siehe unten). |
| Instagram-Feed (letzte 30 Beitraege, Video-Vorschaubild, Link) | lea-impulse.php | | fehlt | Keine Instagram-Quelle in `FeedImport` oder sonstwo (`grep instagram` findet nichts). |
| Instagram einordnen: Thema, Schlagworte, Verkaufs-Kennzeichen, per KI oder von Hand, Admin-Seite | lea-impuls-instagram-ki.php | | fehlt | Haengt an Instagram-Quelle. |
| Filter nach Typ (Alles/Blog/Podcast) | lea-impulse.php | Pillen Alles, Impulse, Neuigkeiten, Podcast je Sendung | vorhanden | |
| Filter Thema und Schlagwort per Auswahlliste | lea-impulse.php | Pille "Nach Thema" fuehrt zu `/themen` | teilweise | Kein Thema-/Schlagwort-Filter in der Liste. Themen haben eine eigene Seite. |
| Suche im Feed | lea-impulse.php | Suchfeld `impulse/index` | vorhanden | |
| Ansicht im Fenster (Blog, Podcast mit Player, Instagram) | lea-impulse.php | `/impulse/{slug}`, `/impulse/folge/{id}` | vorhanden | Eigene Seiten statt Fenster. |
| Podcast im Feed: Kapitel mit Sprung, Transkript mit Zeitmarken (Klick springt) | lea-impulse.php | `impulse/folge.blade.php` (`data-springe`, `abschrift [data-start]`) | vorhanden | Springen im Transkript klappt nur, wenn die Abschrift als HTML mit `data-start` vorliegt (Import ja, von Hand eingetippte Abschrift nein). |
| Transkript-Symbol an Karte/Liste | lea-impulse.php, lea-podcast-callbacks.php | | fehlt | Klein. |
| Merken an Karte | lea-impulse.php | `x-merken` | vorhanden | |
| "Frage dazu": Lea im 1:1-Chat fragen, vorbefuellt mit Titel und Link | lea-impulse.php | `inhalt-zeile`: Link `gespraech.index?entwurf=Frage zu «Titel»:` | teilweise | Vortext ohne Link zum Beitrag. Knopf erscheint auch fuer das Team (im Alten nicht). |
| "Frage dazu": In der Community teilen (Frage vorbefuellt, mit Bezug auf den Beitrag) | lea-impulse.php (`frage_titel`, `frage_ref`) | | fehlt | Frageformular (`kurse/fragen`) nimmt keine Vorbelegung an. |
| Team sieht Entwuerfe/Geplantes/Nur-Team, Hinweis am Beitrag | lea-impulse.php (vorerst nur Team) | `ImpulseController` (`versteckt`: Nur Team, Ausgeschaltet, Geplant) | vorhanden | |
| Reine Kampagnen-Beitraege aus dem Feed nehmen | lea-impulse.php | Sichtbarkeit/Veroeffentlicht je Beitrag, Import nur Free | vorhanden | |
| Blog-Beitraege aus WordPress holen | lea-impulse.php | `FeedImport`, `import:wordpress`, `inhalte:feeds` (stuendlich) | vorhanden | |
| Beitragsbild per KI erzeugen (Satz, Motiv, Bild, Kategoriefarbe) | lea-impulsbild.php | | Website | Werkstatt-Werkzeug der Website. In der App nicht vorgesehen. |
| Neuer Beitrag meldet an Mitglieder (Push/Mail) | lea-club-mailster.php | `PostObserver`, Kanaele im `PostResource` | vorhanden | |
| **Podcast** | | | | |
| Podcast per RSS holen, idempotent, Audio extern | lea-podcast-import.php | `FeedImport::episode` (guid), `inhalte:feeds` | vorhanden | MP3 wird nicht in eigene Ablage geladen (gewollt). |
| Kapitel, Zusammenfassung, FAQ, Schlagworte per KI | lea-podcast-ki.php | `Summarizer::episode`, `PrepareEpisode`, Knopf "Aufbereiten (KI)" in `EditPodcastEpisode` | vorhanden | Setzt vorhandene Abschrift voraus. |
| Lesbares Transkript aus Rohtext (Absaetze mit Zeitmarke, Sprechernamen bei Gespraech) | lea-podcast-ki.php | Import uebernimmt `lea_transkript_html` (`InhalteImport`) | teilweise | Fuer neue Folgen gibt es keinen Bau aus Rohdaten, keine Sprechererkennung, keine "Stimmen" in der Info. |
| Abschrift aus dem Audio erzeugen | (ausserhalb: Rohtranskript kam von extern) | | fehlt | Neue Folgen brauchen von Hand eingefuegte Abschrift, sonst keine Kapitel. |
| Sammellauf: alle Folgen ohne Kapitel aufbereiten | lea-podcast-lauf.php | nur pro Folge im Coach-Bereich | fehlt | Kein Befehl/Sammelknopf. Klein. |
| Einzelfolge: Player, Kapitel, Shownotes, Themen, FAQ, Abschrift | lea-podcast-extras.php | `impulse/folge.blade.php` | vorhanden | |
| Vorherige/Naechste Folge, verwandte Folgen (gleiches Thema) | lea-podcast-extras.php | | fehlt | |
| Abo-Knoepfe (Apple, Spotify, RSS), CTA "Erstgespraech", Fremdpodcast-Hinweis | lea-podcast-extras.php | | Website | Oeffentlicher Podcast bleibt in WordPress (docs/04). |
| Podcast-Uebersicht, Serien-/Themenarchiv, Filterliste (Suche, Podcast, Thema) | lea-podcast-archiv.php | `/impulse?f=podcast:Sendung` | Website | In der App nur Pillen je Sendung, kein Themen-Filter (Themen ueber Themenfinder). |
| Coach-Folgen-Shortcode | lea-coach-podcast.php | | Website | |
| Feeds fremder Serien sperren, Subdomain-Umleitung, schema.org, JetEngine-Callbacks | lea-podcast-extras.php, -callbacks.php | | Altlast | |
| **Themen und Fundus (Nachschlagen)** | | | | |
| Ein Themen-System fuer alle Inhalte (Blog, Podcast, Kurse, Lektionen, Material, Werkzeuge) | lea-themen.php | `Topic`, `HasTopics`, `Taggable`, `TopicResource` | vorhanden | |
| Werkzeuge fuer die Ausbildung mit sieben Feldern, nur mit Kennzeichen sichtbar | lea-themen.php | `Tool::FELDER`, `ToolResource`, `/werkzeuge`, Reiter im Nachschlagen | vorhanden | |
| KI verschlagwortet (Themen, zwei Saetze, "Hilft, wenn", Stichworte) | lea-themen.php | `Summarizer::finder`, `themen:profil`, Knoepfe an Beitrag und Folge | teilweise | Kein automatischer Durchlauf im Hintergrund ueber alles (alt: alle 2 Minuten bis fertig, mit Fortschritt). Knopf nur bei Impuls und Folge, sonst Befehl auf dem Server. Nicht im Scheduler (`routes/console.php`). |
| Pruefansicht: KI-Themen ansehen, "Passt" abhaken, Filter Art, Fortschritt | lea-themen.php | Coach-Seite Assistent, Reiter "Themen pruefen" (`Pages/Assistent`) | teilweise | Zeigt nur noch nicht Geprueftes. Kein Wechsel auf "Geprueft"/"Alle". |
| Interne Inhalte vom Verschlagworten ausnehmen | lea-themen.php (`lea_th_intern`) | `Fundus::sichtbar` (nicht intern, veroeffentlicht) | vorhanden | Fuer `themen:profil` selbst nicht geprueft. |
| Themenfinder fuer Teilnehmerinnen: Themenliste mit Suche, Inhalte je Thema | lea-fundus.php | `/themen`, `/themen/{slug}`, `ThemenController` | vorhanden | |
| Nachschlagen: ein Feld, Wort sucht (Titel, Stichworte, Kurztext), Satz fragt KI mit Begruendung | lea-fundus.php | `/nachschlagen`, `Fundus::los`, `wort`, `frage` | vorhanden | |
| Themen-Auswahlliste nach Gruppen | lea-fundus.php | `Fundus::themenNachGruppen`, `Topic.group` | vorhanden | |
| Diktieren ins Suchfeld | lea-fundus.php | `app.js` (Mikrofon an `textarea.feld`) | vorhanden | |
| Trefferkarten mit Herkunft, Kurztext, Themen, Tuer bei gesperrten Inhalten | lea-fundus.php | `nachschlagen/_karte`, `Fundus::karte`, `tuer` | vorhanden | |
| Vorschau im Fenster mit Oeffnen, Merken, Teilen, Link | lea-fundus.php | `NachschlagenController@vorschau`, `_vorschau` | vorhanden | |
| Meine Suchen (Verlauf, letzte 40, Leeren) und Mein Archiv (Gemerktes) | lea-fundus.php | `SearchHistory`, Reiter Verlauf/Archiv, `verlaufLeeren` | vorhanden | |
| Sammlung zusammenstellen: auswaehlen, benennen, Gruss, an Personen in den 1:1-Chat, nur Link erzeugen | lea-fundus.php | `NachschlagenController@teilen`, `Sammlung`, `_leiste` | vorhanden | |
| Sammlung ansehen per Link mit Schluessel, "gesehen" merken | lea-fundus.php | `/sammlung/{id}/{key}`, `Sammlung::gesehenVon` | vorhanden | |
| Einzelne Karte teilen (ein Klick, Gruss) | lea-fundus.php | Vorschau-Fenster (`data-fu-senden`) | vorhanden | |
| Merkliste ueber alle Inhalte | lea-gemerkt (nicht meine Datei), lea-impulse.php | `/merkliste`, `MerklisteController`, `Bookmark` | vorhanden | |
| Sammlungen im Backend einsehen | lea-fundus.php (`sammlung` Post-Typ) | Coach-Assistent, Reiter "Geteiltes" | vorhanden | |
| **Termin-Anzeige und Mails aus dem Club** | | | | |
| Termin-Datum lang/kompakt, ganztaegig, Status-Badge (Live-Termin, Vorbei, Aufzeichnung) | lea-club-callbacks.php | `termin-karte`, `termine/index`, `Event::isLive/isPast/hasRecording` | vorhanden | |
| Teaser "worum es ging" unter der Aufzeichnung (35 Woerter aus Kurzbeschreibung oder KI-Zusammenfassung) | lea-coffee-coaching.php | `termine/index.blade.php` (35 Woerter aus `summary`) | vorhanden | Kein eigenes Feld "Kurzbeschreibung", nur `summary`. |
| Oeffentliche Terminliste und Highlight-Karte (Zugriffsart kostenlos/Mitglieder/kostenpflichtig, CTA-Link, Datumsblock) | lea-club-callbacks.php | | Website | In der App keine oeffentliche Termin-Schnittstelle (`/api/angebote` gibt es, Termine nur mit Token unter `/api/v1/termine`). Nur noetig, wenn die Website die Terminliste weiter aus der App speisen soll. |
| Willkommensmail bei Buchung (pro Kurs einmal, Text Einzel/Gruppe) | lea-club-mailster.php | `Zugang::welcomeAutomatic`, `WillkommenMail` (aus `WooCommerce`) | teilweise | Mail mit Anmeldelink vorhanden. Text nach Einzel/Gruppe nicht unterschieden (nicht im Detail geprueft), Mailster-Liste/Tag entfaellt (Newsletter-Anmeldung: Gruppe Mails). |
| Neue Club-News als Kampagne an Zielgruppe, zuerst pausiert zur Pruefung | lea-club-mailster.php | `PostObserver` (Push/Telegram und Mail wenn kein Push, nur wenn Kanaele gewaehlt) | vorhanden | Keine Pausen-/Pruefstufe, geht sofort raus. Dafuer bewusst nur mit Haken. |
| Neuer Termin meldet an Kurs-Teilnehmerinnen | lea-club-mailster.php | `EventObserver::created` | vorhanden | |
| Termin-Seitenleiste bei Aufzeichnung ausblenden, Modul "kommt" Hinweise | lea-coffee-coaching.php, lea-club-callbacks.php | | Altlast | Elementor-Layout. |

## Wichtigste Luecken

1. **Geteiltes aus dem Kurs fuer andere sichtbar machen.** Notizen, Aufgaben und Reflexionen mit "Im Kurs sichtbar / In der Community" werden gespeichert, aber nur das Team sieht sie. Es fehlt die Liste "Geteilt aus dem Kurs" in der Community (Karte, Reaktionen, Kommentare, ohne Beitraege der Coachin). Der Community-Text verspricht es bereits.
2. **Neu-Punkte im Menue.** Zaehler bei Impulse, Ressourcen, Community und Termine seit dem letzten Besuch. Heute nur Gespraeche und Glocke.
3. **"Frage dazu" in der Community.** Bei Impuls und Folge fehlt die zweite Wahl "In der Community teilen" (Frage vorbefuellt mit Bezug auf den Beitrag). Beim Chat-Weg fehlt der Link im Vortext.
4. **Abschrift und Kapitel fuer neue Podcastfolgen.** Es gibt keine Abschrift aus dem Audio, keinen Bau der Zeitmarken-Absaetze und keine Sprechernamen. Neue Folgen bekommen nur Kapitel, wenn jemand die Abschrift von Hand einfuegt. Ein Sammellauf fuer Folgen ohne Kapitel fehlt.
5. **Themenfinder automatisch fuellen.** Es gibt keinen Durchlauf im Hintergrund fuer alle Inhalte ohne Profil (alt: alle 2 Minuten bis fertig). Der Befehl `themen:profil` steht nicht im Scheduler, der Knopf existiert nur bei Impuls und Folge. Die Pruefansicht kennt nur "noch nicht geprueft".
6. **Ressourcen-Regal: Art, Herkunft, Sortierung.** Filter nach Art (PDF, Video, Audio, Link, Bild) und Herkunft (Von Lea / Aus meiner Begleitung) sowie Sortierung nach Titel fehlen. Karten ohne Vorschaubild, Beschreibung und Dateiname.
7. **Ressource teilen und "Gehoert zu".** Der Teilen-Knopf (Chat oder Link) fehlt auf der Ressourcen-Seite und im Regal, nur im Nachschlagen vorhanden. Ebenso "Gehoert zu" (Lektionen mit diesem Material) und eine sichtbare "Angeschaut"-Marke bei Ressourcen.
8. **Instagram als Impuls-Quelle.** Feed, Einordnung (Thema, Schlagworte) und Verkaufs-Kennzeichen fehlen ganz. Nur noetig, wenn Lea es weiter will.
9. **Fragentag fuehrt in den Chat.** Start und Kursschritt schicken bei "Frage stellen" in `gespraech.index` statt zur Fragen-Seite des Kurses. Der Haken "Geschrieben" nach erledigter Reflexion fehlt.
10. **Neuigkeiten-Feinheiten.** Gruppierung nach Monat, Gelesen-Zustand je Beitrag, mehrere Kurse als Zielgruppe (heute nur ein Programm), Kursfilter, Vor/Zurueck. Dazu "Lea hat geantwortet" in der Liste "Was ist neu" (heute nur Glocke).
11. **Menue: Zeitleiste und Projekte.** "Meine Zeitleiste" und "Meine Projekte" haben keinen Menuepunkt. `/journal` ist nur ueber Rueck-Links erreichbar. Projekte fehlen als Funktion (Journal-Gruppe).
12. **Podcast-Einzelfolge: Nachbarn und Verwandte.** Vorherige/Naechste Folge und "Mehr zu diesen Themen" fehlen. Dazu Sprecher-Anzeige ("Stimmen") und Transkript-Symbol an der Liste.
13. **Impulse-Liste: Thema und Schlagwort als Filter.** Heute nur Pille "Nach Thema", die auf eine eigene Seite fuehrt.
14. **Offline-Seite im Service Worker.** Ohne Netz zeigt der Browser seine Fehlerseite statt eines freundlichen Hinweises. Klein.
15. **Oeffentliche Terminliste als Schnittstelle** (nur falls die Website Termine weiter aus der App holen soll): Zugriffsart, CTA-Link, Highlight-Text fehlen im Datenmodell und in der API.
