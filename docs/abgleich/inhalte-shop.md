# Abgleich Inhalte, Shop, Verwaltung: alter Mitgliederbereich (WordPress) und neue App

Stand 25.09.2026. Alter Code: `novamira-sandbox/` (nur gelesen). Neue App: `/home/user/Coaching-App`.
Geprüft wurden alle Dateien aus dem Auftrag plus die übrig gebliebenen Dateien (Abschnitt D).

Status: `gleich`, `vereinfacht` (was fehlt), `besser`, `fehlt`, `bewusst weg (Website)`, `bewusst weg` (mit Grund).

---

## 1. Tabelle

### A. Inhalte (Impulse, Podcast, Blog)

| Funktion | alte Datei | Status | Fundstelle in der App / Bemerkung |
|---|---|---|---|
| Impulse-Fläche: Blog, Podcast und Instagram gemischt, neueste zuerst | lea-impulse.php | vereinfacht | `/impulse` (`ImpulseController@index`, `resources/views/impulse/index.blade.php`): Impulse, Neuigkeiten, Podcastfolgen gemischt, Filter als Knöpfe (Alles, Impulse, Neuigkeiten, je Sendung), Suche. Fehlt: Instagram als dritte Quelle, Dropdown-Filter nach Thema und Schlagwort (ersetzt durch Link "Nach Thema" auf `/themen`), Kartenraster mit grossem Bild (App zeigt Zeilen mit 64-px-Vorschaubild, `components/inhalt-zeile.blade.php`), Anzahl je Filter. |
| Kampagnen-Beiträge ausblenden (IDs 2505, 1845, 1843 sind Einladungen, keine Impulse) | lea-impulse.php, lea-blog-styles.php | fehlt | `database/seeders/TenantSeeder.php` setzt `post_exclude_ids => []`. Die drei Verkaufsbeiträge kommen mit dem Import als Impulse in die App. |
| Beitrag lesen (Popup mit ganzem Text, Bild, Datum, Kategorie, "Original öffnen") | lea-impulse.php | gleich | `impulse/show.blade.php`: Bild, Art, Datum, Kategorien, Text, Merken, "Im Web öffnen". Eigene Seite statt Popup, das ist für die App sinnvoll. |
| "Frage dazu": Lea im 1:1-Chat fragen (vorbefüllt mit Titel und Link) oder in der Community teilen | lea-impulse.php | fehlt | Kein Knopf auf `impulse/show` und `impulse/folge`. Gespräch gibt es (`/gespraech`), aber ohne Vorbefüllung aus einem Inhalt. Community gibt es in der App nicht. |
| Merken auf jeder Karte, auch im Popup | lea-impulse.php, lea-gemerkt.php | gleich | `x-merken` in Liste, Beitrag und Folge, Merkliste `/merkliste`, Import der alten Merker (`InhalteImport::importBookmarks`). |
| Hinweis "Transkript verfügbar" (Icon auf Karte, in Listen, auf Folge) | lea-impulse.php, lea-podcast-callbacks.php, lea-podcast-archiv.php | fehlt | Weder Liste noch Folgenkopf zeigen, dass es eine Abschrift gibt. Abschrift selbst ist da (siehe unten). |
| Zugriff: Impulse-Seite vorerst nur fürs Team (Seite 2803) | lea-impulse.php | besser | In der App für alle Mitglieder offen, pro Beitrag Sichtbarkeit `members`, `program`, `team` (`Post::VISIBILITIES`, `Inhalte::postsQuery`). |
| Instagram-Feed über JetElements-Token (letzte 30 Posts, Video-Thumbnail, Original-Link) | lea-impulse.php | fehlt | Keine Instagram-Quelle in `app/Content/FeedImport.php`. Nur RSS/Atom. |
| Instagram einordnen: Thema, Schlagworte, Kennzeichen "Verkauf" (per KI oder von Hand, Admin-Seite "Instagram-Themen") | lea-impuls-instagram-ki.php | fehlt | Hängt am Instagram-Feed. Falls übernommen: Einordnung über `Summarizer::finder` (Anthropic) und ein Feld "Verkauf, nicht zeigen" am Beitrag. |
| Impuls im Mitgliederbereich schreiben und melden | (lea-club-mailster.php, Club-News) | besser | `PostResource`: Art, Termin in der Zukunft, Kurztext, Themen, Sichtbarkeit (auch nur ein Programm), Kanäle Push/Telegram/Mail, einmalige Meldung (`PostObserver`). Einschränkung: Bild nur als URL, kein Upload. |
| Club-interne Beiträge (Sichtbarkeit "Club-intern", Zielgruppe alle oder bestimmte Kurse) | lea-blog-frei.php, lea-club-mailster.php, lea-sichtbarkeit-*.php | fehlt | Import holt nur Beiträge mit Sichtbarkeit "Free" (`InhalteImport::importPosts`, `post_visibility`). Club-intern (Neuigkeiten nur für Mitglieder) und kursbezogene News (`news_zielgruppe`, `news_kurse`) kommen nicht in die App. |
| Beitragsbild erzeugen (KI-Satz, KI-Motiv, gpt-image-1, Imagick-Layout in Kategoriefarbe) | lea-impulsbild.php | bewusst weg (Website) | Werkzeug der Website-Werkstatt für Blogbilder. Die fertigen Bilder kommen per Import (`thumbnail`) oder Feed mit. Für in der App geschriebene Impulse gibt es kein Bildwerkzeug. |
| Podcast-Einzelfolge: Player, Kapitel mit Sprung, Zusammenfassung, Shownotes, FAQ, Transkript mit klickbaren Zeitmarken, Themen | lea-podcast-extras.php | vereinfacht | `impulse/folge.blade.php`: Cover, Sendung, Folge Nr., Datum, Dauer, `<audio>`, Zusammenfassung, Kapitel (Sprung), Shownotes, FAQ (aufklappbar), Abschrift (Sprung über `data-start`, passt zum importierten `lea-tr-abs`-HTML), Themen, Schlagworte, Merken. Fehlt: Kachel "Auf einen Blick" mit Stimmen (Sprechernamen `lea_sprecher` werden nicht importiert), "Mehr zu diesen Themen" (verwandte Folgen), vorherige und nächste Folge der Sendung, Abo-Knöpfe Apple Podcasts, Spotify, RSS, Hinweis "automatisch erstellt, nicht korrigiert" beim Transkript. |
| Hinweis bei gemeinsamen Fremd-Podcasts (Gastgeberinnen, Originalfolge, "Insights Inside läuft ohne Lea weiter") | lea-podcast-extras.php | vereinfacht | Nur Knopf "Zur Folge im Web" (`url` aus `lea_feed_link`). Gastgeberinnen und Einordnung fehlen. |
| CTA "Kostenloses Erstgespräch", schema.org PodcastEpisode und FAQPage | lea-podcast-extras.php | bewusst weg (Website) | Verkauf und SEO gehören auf die öffentliche Seite. |
| Podcast-Übersicht `/podcast/`: Serienblöcke mit Cover, Gastgeberinnen, Folgenzahl, Abo-Links, Filter (Suche, Podcast, Thema), Themenwolke, "Mein geheimes Leben" | lea-podcast-archiv.php | bewusst weg (Website) | Öffentliche Seite bleibt in WordPress. In der App übernimmt `/impulse` mit Filter je Sendung und `/themen` diese Rolle. Ein Sendungskopf (Cover, Beschreibung, Anzahl Folgen) fehlt in der App. |
| Feeds der Fremd-Podcasts sperren, Subdomain podcast.leawernli.ch umleiten | lea-podcast-extras.php | bewusst weg (Website) | Betrifft nur die WordPress-Feeds. |
| JetEngine-Callbacks für Podcast-Listings (Cover, Hörprobe, Metazeile, Transkript-Badge) | lea-podcast-callbacks.php | bewusst weg (Website) | Elementor-Listings der Website. |
| KI-Aufbereitung einer Folge: Kapitel, Zusammenfassung, 3 FAQ, Schlagworte, SEO-Titel, Sprechererkennung, lesbares Transkript aus den Rohdaten | lea-podcast-ki.php | vereinfacht | `Summarizer::episode` (Opus statt Sonnet) mit Knopf "Aufbereiten (KI)" in `EditPodcastEpisode`, Job `PrepareEpisode`: Zusammenfassung, Kapitel, FAQ, Schlagworte. Fehlt: Gespräch ja/nein und Sprechernamen, Bau des Transkript-HTML aus Utterances (Absätze, Zeitmarken, Namen). SEO-Felder sind Website und bewusst weg. |
| Rohtranskript (Utterances mit Sprechern und Wortzeiten) für neue Folgen | lea-podcast-ki.php (Eingang `lea_transkript_roh`) | fehlt | Die App transkribiert nicht. Abschrift kommt aus dem Import oder wird von Hand eingefügt (`transcript`). Im Parallelbetrieb unkritisch, nach dem Umschalten fehlt der Weg vom Audio zur Abschrift. |
| Podcast aus RSS übernehmen (idempotent über GUID, eigene Folgen mit MP3-Download, Fremdfolgen extern) | lea-podcast-import.php | besser | `FeedImport` stündlich (`inhalte:feeds`), Feeds je Mandant im Coach-Bereich (Einstellungen, "Impulse und Podcast per Feed"), idempotent über `guid`, Bild, Dauer, Folgennummer. Audio bleibt immer extern, kein Kopieren der MP3. |
| Stapellauf: alle Folgen ohne Kapitel aufbereiten (WP-CLI, Log) | lea-podcast-lauf.php | vereinfacht | Nur je Folge per Knopf. Für Themen gibt es den Stapel `themen:profil`, für Folgen-Aufbereitung keinen. Im Parallelbetrieb kommen Kapitel und FAQ fertig aus WordPress. |
| Podcast für Coaches (Serie `coach-impulse`, Shortcode mit Leerzustand "erste Folge kommt bald") | lea-coach-podcast.php | vereinfacht | Wird als eigene Sendung angezeigt, sobald der Feed eingetragen ist. Fehlt: Sichtbarkeit je Sendung. `Inhalte::episodesQuery` zeigt allen Mitgliedern alle Folgen, ein Coach-Podcast für die Ausbildung wäre für alle sichtbar. |
| Leas eigener Podcast vorerst allein im Impulse-Feed (Wunsch vom 20.09.) | lea-impulse.php | besser | App zeigt alle Sendungen mit eigenem Filter. Wenn Lea das nicht will: Feed im Coach-Bereich weglassen. |
| Themen-Vokabular Podcast (25 feine Themen) getrennt von Blog-Themen (6 grobe) | lea-impulse.php | vereinfacht | Beide Taxonomien (`thema`, `podcast_thema`) landen im selben Themenfinder (`topics`). Kein Umschalten des Vokabulars je Inhaltsart. |
| Gestaltung Podcast (Karten, Player-Karte, Kapitel, Transkript) | lea-podcast.css | vereinfacht | App nutzt eigene Karten (`.karte`) und Mandantenfarben. Details in Abschnitt 3. |
| Brief mitnehmen: Liebesbrief bzw. Goldnuggets als PDF und als Text zum Kopieren (aus Workbook-Antworten) | lea-brief-mitnehmen.php | fehlt | Kein Baustein "mitnehmen", kein PDF-Export von Antworten (`KursController`, `kurse/einheit.blade.php`). Gehört fachlich zum Workbook. |
| Einstieg ADHS-Mütter unter Blogbeiträgen | lea-adhs-muetter.php | bewusst weg (Website) | Landingpage-Verweis. |
| Blog-Filter als Pillen, Blogseite nur "Free", Blog-Kachel-Optik | lea-blog-filter-css.php, lea-blog-frei.php, lea-blog-styles.php | bewusst weg (Website) | Öffentliche Blogseite. Die Regel "nur Free" steckt im Import (`post_visibility`). |

### B. Shop, Zugänge, Rechnungen

| Funktion | alte Datei | Status | Fundstelle in der App / Bemerkung |
|---|---|---|---|
| Produkt schaltet Kurse frei, Storno und Erstattung entziehen den Zugang | lea-shop-zugang.php | besser | `app/Shop/WooCommerce.php`, `app/Shop/Zugang.php`, `/hooks/woocommerce`, Angebote mit Produktzuordnung (`OfferResource`), Laufzeit, Protokoll (`webhook_logs`), neue Personen mit Willkommensmail. |
| Einzelkurs 1 Jahr, Club als Abo, Kurse aus mehreren Plänen, manueller Zugang zählt mit | lea-kurs-shop.php | besser | `Offer::TYPES`, `access_days`, Club reagiert nur auf Abo-Ereignisse, `entitlements` mit `source` (auch `manual`), Zugriff an einer Stelle (`ProgramAccess`). |
| Kauf auf Rechnung: Zugang schon bei Status "in Wartestellung" (on-hold) | lea-kasse.php | fehlt | `WooCommerce::ORDER_ACTIVE = ['completed', 'processing']`. Eine Bestellung mit Zahlungsart `lea_rechnung` steht auf `on-hold` und gibt in der App keinen Zugang, bis bexio "bezahlt" meldet (bis 30 Tage). Gilt auch für Verkäufe aus dem Verkaufsknopf. |
| Kasse: Zahlungsart Rechnung, AGB und Widerrufsverzicht, Knopf "Zahlungspflichtig bestellen", Mails bei Rechnung | lea-kasse.php | bewusst weg (Website) | Kasse bleibt in WooCommerce (`docs/07-UEBERGANG.md`). |
| Verkaufen in der App: "Neue Person anlegen" (mit oder ohne Willkommensmail, Währung, Telefon) | lea-verkauf.php | vereinfacht | `ListMemberships` "Person anlegen", Einladung per Knopf (`Dossier`, `EditMembership`). Fehlt: Währung, Rechnungskundin-Kennzeichen, Wahl "Mail sofort ja/nein" direkt beim Anlegen. |
| Verkaufen im Dossier: Angebot, Preis, Währung, inkludierte 1:1-Sitzungen, Rechnung oder bezahlt, Bestellung plus bexio-Rechnung plus Zugang in einem Klick | lea-verkauf.php, lea-kasse.php (`lea_verkauf_anlegen`) | fehlt | Im Dossier gibt es keinen Verkauf. Zugang von Hand geht über Angebote und Zugänge, aber ohne Bestellung, Rechnung und Sitzungskontingent. |
| Preis nach Land (CHF oder EUR), Währung pro Kundin, Euro-Preis pro Produkt, Preisformat 1'234.56 | lea-preis.php, lea-waehrung.php, lea-preisformat.php | bewusst weg (Website) | Preise stehen auf der Website und in WooCommerce. `tenants.currency` gibt es, eine Währung pro Person nicht (erst nötig, wenn die App selbst verkauft). |
| Meine Buchungen im Profil: jede Buchung als Karte (Art, Sitzungen x von y mit Balken, Kurswoche x von y, Zugang endet am, gekauft am und Betrag, Knöpfe Rechnung, Bestelldetails, Termin buchen, Zum Programm) | lea-mitgliedschaft-neu.php | fehlt | `profil.blade.php` zeigt Daten, Benachrichtigungen, Kalender, Passkeys, Passwort. Keine Übersicht über eigene Zugänge (`entitlements`) mit Enddatum, kein Kontingent, keine Bestellungen. |
| Laufende Abos mit Status und nächster Zahlung, Link "Abo verwalten oder beenden" | lea-mitgliedschaft-neu.php | fehlt | Kein Abo-Block. Abo-Verwaltung bleibt in WooCommerce, in der App fehlt schon der Link dorthin. |
| Kündigen nur, wo ein Abo dahintersteckt (Schalter je Plan) | lea-mitgliedschaft-kuendigung.php | fehlt | Keine Kündigung in der App. Richtig wäre: Link auf das Woo-Konto (Abos), Regel "kündbar" am Angebot (`offers.settings`). Kaufkurse bleiben nicht kündbar. |
| "Was es bei Lea gibt" und "Was Lea gerade baut" (Ausblick im Profil) | lea-mitgliedschaft-neu.php | fehlt | Reiner Inhalt. Passt als Text in `tenants.settings` oder als Impuls vom Typ Neuigkeit. |
| Rechnungen der Person aus bexio (alle, auch ohne Shop): Datum, Titel, Betrag, Status offen/bezahlt/gemahnt, PDF, "online bezahlen" | lea-bexio-kunden.php | fehlt | Nichts zu bexio in der App (`grep bexio` leer). |
| Stille Konten für alle bexio-Rechnungsempfängerinnen, damit Lea sie in der Übersicht hat | lea-bexio-kunden.php | fehlt | Import legt nur WordPress-Personen an (`UsersImport`). Wer nur in bexio steht, fehlt in der App-Personenliste. |
| bexio als einzige Rechnungsquelle: Kontakt, Rechnung ausstellen, Zahlung buchen, Zahlungsabgleich alle 4 Std., PDF an Bestellmail, Nachholen, OAuth und Token-Warnung | lea-bexio.php | bewusst weg | Bleibt in WordPress, solange WooCommerce verkauft. Die App braucht davon nur die Leseseite (Rechnungen je Person, PDF), siehe oben. |
| Rechnungslayout German Market (Kopfblock, Kundennummer, Spalten) | lea-rechnung.php | bewusst weg (Website) | Rechnungen kommen aus bexio. Kundennummer existiert in der App nicht. |
| Produktseite: kein Versandhinweis, eigener Kaufknopf-Text | lea-produkt-verkauf.php | bewusst weg (Website) | |
| Kasse, Warenkorb, Konto-Optik; Texte in Du-Form und "Strasse" | lea-woo-checkout-design.php, lea-woo-texte.php | bewusst weg (Website) | |
| Danke-Seite mit Link in den Mitgliederbereich | lea-woo-thankyou.php | bewusst weg (Website) | Beim Umschalten die Links `/mitgliederbereich/` und `/login/` auf `https://app.leawernli.ch` ändern. |
| Käuferinnen in Mailster-Liste "Kunden" | lea-kunden-liste.php | bewusst weg (Website) | Newsletter bleibt in Mailster. |

### C. Verwaltung und Werkzeuge

| Funktion | alte Datei | Status | Fundstelle in der App / Bemerkung |
|---|---|---|---|
| Rolle Redaktion (Andrea) mit Rechten für Inhalte, Coachees, Bestellungen | lea-rollen.php | besser | `Role::Team` (`canManage`), Rollen je Mandant an `memberships`, Filament-Panel `coach`. |
| Andrea antwortet "als Lea" (nach aussen "Team Lea"), intern sieht Lea, wer wirklich schrieb | lea-rollen.php | vereinfacht | Team ist in jedem Gespräch dabei (`Chat::teamIds`), Nachrichten zeigen aber den eigenen Vornamen (`gespraech/_nachricht.blade.php`, `$m->user?->vorname()`). Kein Absendername "Team Lea". |
| Coffee & Coaching: Zwei-Satz-Teaser "worum es ging" in der Terminliste (aus Kurzbeschreibung oder KI-Zusammenfassung) | lea-coffee-coaching.php | fehlt | `termine/index.blade.php` zeigt Titel, Zeit, Programm, "Aufzeichnung da", aber keinen Teaser. `events.summary` ist vorhanden und könnte gekürzt angezeigt werden. |
| Automatik Buchung -> Willkommensmail je Kurs | lea-club-mailster.php | besser | `Zugang::grant` meldet "Dein Zugang ist da", neue Personen bekommen `WillkommenMail` mit Anmeldelink, Testbetrieb berücksichtigt. |
| Club-News -> Mail-Kampagne an alle oder an Kursteilnehmerinnen | lea-club-mailster.php | besser | `PostResource` Kanäle plus Sichtbarkeit "nur ein Programm", `Rundnachricht`-Seite. Keine pausierte Kampagne zur Kontrolle nötig. |
| Neuer Termin -> Mail an die Teilnehmerinnen des Kurses | lea-club-mailster.php | vereinfacht | Kein sofortiger Hinweis bei neuem Termin. `EventObserver` meldet nur neue Aufzeichnungen, "Neuer Termin" erscheint nur in der Abendmail (`Runden::neuesFuer`). |
| Mailster-Listen und Tags je Kurs | lea-club-mailster.php | bewusst weg | Newsletter-Segmente bleiben in Mailster. |
| Sichtbarkeit sichtbar machen (Spalte, Warnung "nirgendwo sichtbar"), Taxonomie auf Termine und Club-News | lea-sichtbarkeit-hinweis.php, lea-sichtbarkeit-erweiterung.php | besser | Sichtbarkeit ist Pflichtfeld mit Vorgabe "Alle Mitglieder" (`PostResource`), Termine hängen an Programmen. Ein "unsichtbarer" Beitrag kann nicht entstehen. |
| Bereich "Baukasten Ausbildung" (Elementor-Seiten per Code) | lea-ausbildung-bau.php | bewusst weg (Website) | |
| Admin-Farbschema, Adminleiste aufräumen, Beiträge heissen "Blog / News" | lea-admin-farbschema.php, lea-adminleiste-aufraeumen.php, lea-beitraege-umbenennen.php | bewusst weg | WordPress-Backend. Die App hat eigene Bezeichnungen im Coach-Bereich. |
| Button-Shortcode für Website und Mailster, Knopf im klassischen Editor | lea-button.php, lea-button-mce.js | bewusst weg (Website) | |
| Mail-Template, Beitragsbild für Newsletter, Mail-Debug-Log | lea-mail-template.php, lea-mail-bild.php, lea-mail-debug-log.php | bewusst weg (Website) | App verschickt eigene Mails (`resources/views/mail/`), Debug-Log war temporär. |
| Mailster: Formulare, Optik, Texte, Editor-Toolbar, Doppel-Opt-in, Mailstrecke, Testversand samt Brücke | lea-mailster-*.php, lea-newsletter-doi.php, lea-mailstrecke.php, newsletter-testversand.php, lea-testversand-bruecke.php | bewusst weg (Website) | Newsletter und Freebies bleiben in WordPress. Anmerkung: ein "Testversand an mich" vor dem Veröffentlichen eines Impulses mit Mailkanal gibt es in der App nicht; der Testbetrieb gilt global. |
| Messung Gratiskurs (Seitenaufrufe, Anmeldungen, Abschlüsse, Selbsttests) | lea-messung.php | bewusst weg (Website) | Funnel der öffentlichen Seite. |
| Weiterleitungen alter Adressen, Freebies, Umzug, Startseite umschalten | lea-altlinks-301.php, lea-freebie-redirects.php, lea-umzug.php, lea-startseite-schalter.php | bewusst weg (Website) | Beim Umschalten kommt die 301 `/mitgliederbereich/*` dazu (`docs/07-UEBERGANG.md`, Schritt 4.3). |
| Seite "Mit Lea arbeiten", Kategorien auf Seiten, SVG-Uploads, Elementor-Icons, Font Awesome, externe Bilder, Webinar-Danke-Seite | lea-seite-angebot.php, lea-page-categories.php, lea-svg-uploads.php, lea-elementor-icons.php, lea-fontawesome.php, lea-externe-bilder.php, lea-webinar-danke.php | bewusst weg (Website) | |
| Club-Schalter (PHP oder Elementor), JetEngine-Sichtbarkeitsbedingungen | lea-club-a-schalter.php, lea-club-visibility.php | bewusst weg | Technik des alten Seitenbaus, in der App durch Blade-Views ersetzt. |
| Ballast abwerfen (Skripte im Mitgliederbereich nicht laden) | lea-ballast.php | bewusst weg | App lädt nur eigenes CSS und JS. |
| Brücke zur neuen App (Knopf mit signiertem Einmal-Link) | lea-neueapp-bruecke.php | gleich | `/sso` (`BridgeController`, `App\Auth\Bridge`), `bridge:secret`. |

### D. Übrig gebliebene Dateien (in keiner Liste)

| Funktion | alte Datei | Status | Fundstelle in der App / Bemerkung |
|---|---|---|---|
| 1:1-Begleitung mit Sitzungskontingent und gemeinsamem Journal-Strang (Notiz, Aufgabe bis zum nächsten Call, Ressource, Kommentare, Termine automatisch im Strang) | lea-begleitung.php | vereinfacht | Programmtyp `one_on_one`, Import übernimmt `sitzungen_gesamt` in die Programmeinstellungen (`ProgramsImport`), angezeigt wird das Kontingent nirgends. Journal, Aufgaben, Notizen gibt es getrennt. Details im Abgleich Coachees/Journal. |
| "Coaching mit Lea": nächster Termin, Kontingent "x von y offen", Termin buchen (`buchung_url`) oder anfragen, Fragen an Lea | lea-coaching.php | fehlt | Kein Buchungslink und keine Kontingentanzeige. Nächster Termin und Gespräch gibt es (`/termine`, `/gespraech`). |
| Technik-Hilfe: Formular an Sebastian (Seite, Browser, Bildschirm, Kurse automatisch dabei), WhatsApp-Link | lea-hilfe.php | fehlt | Profil hat nur "Einführung nochmals ansehen". Die Absenderadresse gehört in `tenants.settings`, nicht in den Code. |
| Gastzugang: wer nichts gebucht hat, sieht nur Termin, Gespräch, Profil | lea-gast.php | vereinfacht | Rolle `Guest` existiert (`app/Enums/Role.php`), das Menü (`components/layouts/app.blade.php`) wird für Gäste aber nicht reduziert. |
| Module ein- und ausschalten (Projekte, Zeitleiste, Community, Ressourcen) | lea-module.php | fehlt | Keine Schalter im Coach-Bereich. Wenig dringend, Projekte und Community gibt es in der App nicht. |
| Neu-Punkte im Menü (ungelesene Beiträge, Community, Aufzeichnungen, Ressourcen) | lea-neu.php | vereinfacht | Nur Zähler beim Gespräch und Karte "Neu für dich" auf der Startseite (`home.blade.php`). |
| "Was ist neu"-Strom auf der Startseite | lea-nachrichten.php | vereinfacht | "Neu für dich" auf `/`. Details im Abgleich Neuigkeiten. |
| Journal-Einstieg mit Zahlen der drei Bereiche | lea-journal-start.php | gleich | `/journal` (`JournalController`). |
| Schalter "Was kommt an" (Termin-Erinnerungen, Abendmail, Aufgaben) | lea-was-kommt-an.php | gleich | Profil, Karte "Was dich erreicht" (`ProfilController@notifications`). |
| Sprachnachrichten von Chrome (WebM) für Safari nach m4a umwandeln (ffmpeg) | lea-sprachnachricht-format.php | fehlt | `app/Chat/Chat.php` speichert WebM unverändert. Auf iPhone und Mac nicht abspielbar. |
| Diktieren in jedem Antwortfeld (Browser-Spracherkennung, de-CH) | lea-diktieren.php | vereinfacht | Nur in der Reflexion (`reflexion/index.blade.php`). |
| Ziehen zum Aktualisieren am Handy | lea-pull.php | fehlt | Klein, aber in der PWA spürbar. |
| Doppelte Sendungen verhindern (Knopf sperren, gleiche Anfrage nur einmal) | lea-doppelschutz.php | vereinfacht | Normale Formular-POSTs. Nicht geprüft, ob überall ein Doppelklick-Schutz greift. |
| Konto-Seite WooCommerce (Meine Käufe, Mitgliedschaft, Kontodetails) | lea-konto.php | bewusst weg (Website) | Bleibt bei Woo. Die App bräuchte nur einen Link dorthin (siehe Liste unten, Punkt 3). |
| Einstieg unter jedem Beitrag und jeder Folge (Gratiskurs oder Klarheitsgespräch) | lea-einstieg-cta.php | bewusst weg (Website) | |
| Mobilmenü, Bereichsweiche Coaching/Ausbildung, Vorschau-Link Ausbildung, Mobile-Slider, Brand-Override | lea-mobilmenue.php, lea-bereich.php, lea-vorschau.php, lea-mobile-slider.php, lea-brand-override.php | bewusst weg (Website) | |
| Fundament des alten Bereichs: Seitenhülle ohne Elementor, Member-Guard, CSS-Bündel, Filterleiste, Seitenaufbau, Karten, Menüordnung, Infofenster, Eingabe zweizeilig, Club-Gerüst, Club-Callbacks, Club-Makros, Club-Vorlagen-CSS | lea-seiten.php, vorlagen/lea-seite.php, lea-member-guard.php, lea-css-buendel.php, lea-filter.php, lea-seitenaufbau.php, lea-karten.php, lea-menue-ordnung.php, lea-infofenster.php, lea-eingabe-zweizeilig.php, lea-club.php, lea-club-callbacks.php, lea-club-macros.php, lea-club-kurs-template-css.php, lea-club-listings-css.php, lea-club-template-css.php | bewusst weg | Ersetzt durch `components/layouts/app.blade.php`, Middleware `auth` plus `membership`, `ProgramAccess`, Tailwind. Die Schriftleiter aus `lea_css_einrasten()` (11, 12.5, 13.5, 15.5, 17, 20, 26, 32) steckt als `font_scale` im Branding. |
| Doku und Serverschutz | LIESMICH.md, web.config, index.html | bewusst weg | Nur für den Sandbox-Ordner. |
| Nicht hier bewertet (andere Bereiche) | lea-anmeldedienste.php (Login), lea-arbeitsbuecher.php (Workbook), lea-kursraum-plus.incphp (Kursraum), lea-design.php, lea-elemente-ui.php (Design/UI) | - | Gehören zu den Abgleichen Login, Kursraum/Workbook und Design. |

---

## 2. Fehlt und ist wichtig im Mitgliederbereich (nach Wichtigkeit)

1. **Zugang bei Kauf auf Rechnung (on-hold).** In `WooCommerce::order()` Bestellungen mit Status `on-hold` und Zahlungsart `lea_rechnung` (oder `created_via` `lea_verkauf`) wie `processing` behandeln, damit Käuferinnen auf Rechnung nicht bis zu 30 Tage ohne Zugang dastehen.
2. **Meine Buchungen im Profil.** Neue Karte im Profil aus `entitlements` mit Angebot, Art, Start, "Zugang endet am" oder "dauerhaft", bei 1:1 Sitzungen "x von y gehabt" (aus `programs.settings.sitzungen_gesamt` und vergangenen 1:1-Terminen), bei Gruppenprogrammen "Woche x von y", Knopf "Zum Programm".
3. **Abo verwalten und kündigen.** Im Buchungsblock bei Angeboten vom Typ Club einen Link auf das WooCommerce-Konto (Abos) zeigen, Adresse in `tenants.settings.shop.account_url`, kündbar nur bei Abo-Angeboten (Regel wie `lea_plan_kuendbar`).
4. **Rechnungen aus bexio.** Leseseite als eigener Dienst `app/Shop/Bexio.php` (Token in `tenants.settings.shop.bexio`), Rechnungen je Person über Mail-Adresse bzw. gespeicherte Kontakt-ID suchen, Liste mit Status und PDF-Download über eine eigene, geschützte Route, Link "online bezahlen" bei offenen Rechnungen; Cache 10 Minuten.
5. **Kampagnen-Beiträge ausschliessen und Club-interne Neuigkeiten importieren.** In `TenantSeeder` `post_exclude_ids => [2505, 1845, 1843]` setzen und `InhalteImport::importPosts` so erweitern, dass Sichtbarkeit "Club-intern" als Neuigkeit (`visibility members`) und kursbezogene News (`news_kurse`) als `visibility program` kommen.
6. **Sprachnachrichten für iPhone.** Nach dem Hochladen in `Chat.php` WebM per ffmpeg nach m4a umwandeln (als Job, Original behalten), sonst hören Apple-Nutzerinnen Leas Sprachnachrichten nicht.
7. **"Frage dazu" bei Impulsen und Folgen.** Knopf auf `impulse/show` und `impulse/folge`, der das 1:1-Gespräch öffnet und das Eingabefeld mit "Frage zu «Titel»" plus Link vorbefüllt (Query-Parameter an `gespraech.show`).
8. **Verkaufen im Dossier.** Aktion "Zugang geben" im `Dossier` mit Angebot, Start, optional Sitzungen und Notiz, die `Zugang::grant(..., 'manual', ...)` nutzt; Bestellung und Rechnung bleiben vorerst in Woo und bexio, später Anbindung an `lea_verkauf_anlegen` oder direkt an bexio.
9. **1:1: Kontingent und Termin buchen.** Auf der Programmseite eines `one_on_one`-Programms "x von y Sitzungen offen" mit Balken und Knopf "Termin buchen" (`programs.settings.buchung_url`, sonst Anfrage ins Gespräch).
10. **Podcastfolge vervollständigen.** In `impulse/folge.blade.php` Sprecher (Import von `lea_sprecher`, `lea_gespraech` nach `settings`), vorherige und nächste Folge derselben Sendung, "Mehr zu diesen Themen" (gleiche Topics, 4 Folgen), Transkript-Hinweis, und in Liste und Kopf ein Icon "Abschrift vorhanden".
11. **Sichtbarkeit je Podcast-Sendung.** Feld `visibility` (und `program_id`) an `podcast_episodes` oder je Feed in `tenants.settings.feeds`, damit der Coach-Podcast nur für die Ausbildung sichtbar ist; Test, dass Mandant B nichts sieht, gilt weiterhin.
12. **Neuer Termin sofort melden.** `EventObserver::created` für veröffentlichte Gruppentermine eines Programms: Nachricht "Neuer Termin: Titel am Datum" an die Teilnehmerinnen (Kanäle wie bisher über `Notifier`).
13. **Technik-Hilfe.** Kleines Formular im Profil ("Wo klemmt es?", "Was passiert?"), schickt Seite, Browser und Bildschirm mit an die Adresse aus `tenants.settings.support.mail`.
14. **Teaser in der Terminliste.** In `termine/index.blade.php` bei vergangenen Terminen mit Aufzeichnung die ersten rund 35 Wörter aus `summary` anzeigen.
15. **Transkription neuer Folgen nach dem Umschalten.** Job, der zu einer Folge ohne Abschrift das Audio an einen Transkriptionsdienst schickt und daraus das `lea-tr-abs`-HTML (Absätze, Zeitmarken, Sprecher) baut; bis dahin reicht der Import aus WordPress.
16. **Instagram als Impuls-Quelle.** Nur wenn Lea es will: Feed über die Instagram Graph API in `FeedImport` (Token in `tenants.settings.feeds`), Verkaufsposts per Kennzeichen ausblenden.
17. **Brief mitnehmen.** Baustein am Workbook-Schritt, der ausgewählte Antworten als PDF (dompdf) und als Text zum Kopieren liefert; Titel und Felder je Programm in `programs.settings`.

---

## 3. Designnotizen Impulse und Podcast (aus lea-podcast.css und lea-impulse.php)

### Farben

| Rolle | Wert | Herkunft |
|---|---|---|
| Tinte, Überschriften | `#32312D` (Impulse: `#2E2D29`) | `--ink`, Elementor primary |
| Fliesstext | `#45443F` (Impulse: `#4A473F`) | `--txt` |
| Akzent, Links, Zeitmarken | `#7C86A2` (Lavendel-Blaugrau) | `--cta`, Elementor accent |
| Eyebrow, Sprechername | `#8A9A8B` (Salbei) | `--gruen` |
| Kartenfläche | `#F8F6F1` (Creme) | `--creme` |
| Beige, Icon-Hintergrund | `#E6E3D8` | `--beige` |
| Haarlinie, Kartenrand | `#D8D4C8` | `--haar` |
| Sand, aktive Pille, Hover Zeitmarke | `#CBC5B5` | `--sand` |
| Impulse-Karten | Fläche `#FAF8F3`, Rand `#E4DFD2`, Bildgrund `#F3F0E9`, Metatext `#86816F` | lea-impulse.php |
| Impulse-Akzent | Terracotta `#B4795F` (Hover, Menü-Icons, Transkript-Badge), Hover-Fläche `#F3ECE4` | lea-impulse.php |
| Platzhalter je Typ | Blog `#8A9A8B`, Podcast `#B4795F`, Instagram `#6E8B74` | lea-impulse.php |
| Abo-Logos | Apple `#000`, Spotify `#1DB954`, RSS `#F26522` | lea-podcast.css |

Wichtig: Die Website-Podcastseiten nutzen Lavendel `#7C86A2` als Akzent, die Impulse im Mitgliederbereich Terracotta `#B4795F`. Die App hat für Lea `primary = #B4795F` (TenantSeeder) und liegt damit auf der Linie des Mitgliederbereichs. Die Werte oben gehören in `tenants.branding`, nicht in den Code.

### Schrift

- Serif Lora für Folgentitel (44 px, mobil 30 px), Kartenüberschriften kursiv 26 px, FAQ-Fragen 19 px.
- Mono Oxygen Mono für Eyebrows (11 px, fett, Laufweite 2.2 px, Grossbuchstaben, Salbei), Metazeilen (12 px, 60 % Deckkraft), Kapitelzeiten, Transkript-Zeitmarken.
- Zusammenfassung unter dem Player gross: 19 px, Zeilenhöhe 1.6, Tinte, max. 65 Zeichen breit.

### Karten

- Podcast-Karte (`.lea-karte`): Creme, 1 px Haarlinie, Radius 18 px, Innenabstand 28 x 30 px (mobil 22 x 20), Abstand unten 24 px.
- Impuls-Karte: `#FAF8F3`, Rand `#E4DFD2`, Radius 14 px, Bild im Format 40:21 (1200 x 630), Hover hebt um 2 px mit weichem Schatten `0 8px 20px rgba(46,45,41,.08)`.
- Bei Podcast- und Instagram-Karten wird das quadratische Cover mit `object-fit: contain` gezeigt, dahinter dasselbe Bild unscharf (`blur(20px) saturate(.55)`, 45 % Deckkraft) als Füllung. Gute Lösung für quadratische Cover in breiten Karten, in der App fehlt sie (dort 64-px-Quadrat).
- Typ-Badge oben links im Bild: dunkle Pille `rgba(46,45,41,.72)`, weiss, 11 px, mit Icon; Transkript-Badge darunter als runder Terracotta-Punkt 23 px.
- Raster `repeat(auto-fill, minmax(240px, 1fr))`, Abstand 20 px.
- Filter und Suche als Pillen: Hintergrund Creme, Rand, Radius 999 px; aktive Pille Sand.

### Player und Sprunglogik

- Player in eigener Karte (`.lea-pod-playerkarte`, Innenabstand 22 px), darunter mit Haarlinie getrennt die Abo-Zeile "ABONNIEREN" (Mono-Label) und weisse Pillen mit Plattformlogo.
- Kapitel als Liste mit Trennlinien, jede Zeile mindestens 46 px hoch (gut tippbar), links Zeit in Mono (Akzentfarbe, 80 %), davor ein kleines Play-Dreieck (`\f04b`), rechts Titel 16 px; Hover färbt den Titel im Akzent.
- Kapitel und "Auf einen Blick" stehen nebeneinander (`1fr 300px`), unter 820 px untereinander.
- Transkript in `<details>` eingeklappt, Titel kursiv 26 px mit Pfeil, Hinweis "ganze Folge zum Nachlesen, mit Zeitmarken". Absätze 16 px, Zeilenhöhe 1.7, max. 68 Zeichen breit; Sprechername als Mono-Zeile in Salbei über dem Absatz; Zeitmarke als kleine Pille, beim Hover Sand-Hintergrund.
- Klick auf Kapitel oder Zeitmarke setzt `audio.currentTime`, startet die Wiedergabe und scrollt den Player in die Mitte. In der App scrollt der Sprung nach oben (`window.scrollTo top`), sonst gleich.
- FAQ als zweispaltiges Raster aus Karten (Frage Lora 19 px, Antwort 15 px), mobil einspaltig. In der App als `<details>`-Liste, platzsparender.
- Nachbarn und verwandte Folgen als zwei Spalten kleiner Karten (Radius 14 px, Hover-Rand im Akzent), Serie als Mono-Eyebrow.
- Hinweiskasten Fremd-Podcast: Sand mit 35 % Deckkraft, Radius 14 px.
- CTA-Block dunkel (Tinte), Radius 24 px, Knopf im Akzent (Website, nicht für die App).
