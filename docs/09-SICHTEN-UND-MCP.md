# 09 - Drei Sichten, Arbeitsplatz, Second Brain und MCP

Stand 28.09.2026. Ergaenzt 02-ARCHITEKTUR und 06-ROADMAP (Etappe 9).

## Warum

Der Coachee-Bereich in Filament war zum Einrichten gut, aber nicht fuer den Alltag am Handy. Im alten
WordPress-Bereich hatte Lea eine Arbeitsliste ("Guten Tag, Lea, 3 Dinge warten"), eine Coachee-Seite mit
"Neue Person anlegen" und ein Dossier mit Reitern und "Etwas verkaufen". Das ist jetzt in der App-Huelle
nachgebaut. Dazu war das Teilnehmer-Menue zu lang geworden. Und Lea soll sich mit Claude oder ChatGPT
mit der App verbinden koennen (MCP) und ein zweites Gedaechtnis haben (Wissensspeicher).

## Die drei Sichten

Gesteuert von `App\Coach\Ansicht`, gemerkt in `memberships.settings.ansicht` (nur fuer `owner` und `team`).

| Sicht | Fuer wen | Was | Wo |
|---|---|---|---|
| **Teilnehmerin** | member, client, guest (und Team nach "Wie eine Teilnehmerin") | Start, Meine Kurse (mit Unterpunkten), Termine, Gespraech, Mein Journal, Material, Impulse, Nachschlagen, Profil, Hilfe. Mitteilungen nur ueber die Glocke. | App-Huelle, Drawer und Leiste unten |
| **Arbeitsplatz** | owner, team (Vorgabe) | Heute (Arbeitsliste), Coachees, Gespraeche, Termine, Nachschlagen, Assistent, Kurse, dazu Sprungmarken in die Verwaltung. Umschalter "Wie eine Teilnehmerin". | Dieselbe App-Huelle, gleicher Drawer mit anderem Inhalt |
| **Verwaltung** | owner, team | Kurse einrichten, Termine planen, Angebote, Material, Impulse, Themen, Werkzeuge, Einstellungen, Rundnachricht, Themen pruefen. | Filament unter `/coach` |

Regel: **Alltag in der Huelle, Einrichten in Filament.** Was Lea taeglich am Handy tut (antworten, nachsehen,
verkaufen, Termin eintragen, Notiz), geht ohne Filament. Was Struktur ist (Kurswoche anlegen, Angebot mit
Programmen verknuepfen, Zoom-Link in die Einstellungen), bleibt in Filament.

### Arbeitsplatz: Seiten

- **Heute** (`/`, `HomeController` verzweigt auf `arbeitsplatz.heute`, Daten aus `App\Coach\Arbeitsliste`): Wartet auf deine Antwort (mit "Antworten" und "Gelesen"), Mit dir geteilt, Fragen ohne Antwort, Wartet auf Freigabe (Aufzeichnung mit Zusammenfassung, noch nicht verschickt), Als Naechstes (Termin gross mit Zoom und "Vorbereiten"), Schnell hin. Testkonten (Testbetrieb) bleiben draussen. Ist nichts offen, steht "Alles ruhig: ...".
- **Coachees** (`/coachees`): Auskunft ("Frag mich etwas zu deinem Betrieb"), Nachricht an mehrere, **Neue Person anlegen** (Vorname, Nachname, E-Mail, Telefon, Teilnehmerin oder 1:1-Kundin, Notiz, Willkommensmail), Ampel, Fuer dich freigegeben, Personenkarten mit Suche und Sortierung. Die Karten fuehren ins App-Dossier.
- **Dossier** (`/coachees/{membership}`, `DossierController`): Kopf (Avatar, Name, Kontakt, Zuletzt hier, Lage, Knoepfe Mail, WhatsApp, Anrufen, Einladung, Bearbeiten), **Pakete** mit "seit" und Sitzungskontingent, **Etwas verkaufen** (Angebot, Preis als Notiz, Laufzeit, zusaetzliche 1:1-Sitzungen, Notiz, Willkommensmail; legt Zugang an, betritt die Programme, schreibt eine Coach-Notiz), **Kennzahlen** (Naechster Termin, Wochenaufgaben, Bei den Calls, Sie schreibt), dann Reiter:
  - Gespraech: die letzten 40 Nachrichten, kurze Antwort, "Als gelesen", Link ins volle Gespraech (Sprache, Datei)
  - Termine: Kommt und War (1:1 und Gruppe mit Status), Zeiten vorschlagen (bis drei Zeiten in Ortszeit), Termin eintragen (1:1, Zoom aus den Einstellungen)
  - Kurs: Programme mit Stand und Balken, Kursraum, Einrichten
  - Aufgaben: Aufgabe geben, Liste mit Kommentaren, private Aufgaben nur gezaehlt
  - Von ihr freigegeben: Antworten je Einheit, Reflexionen, Notizen, mit Antwort-Feld (Kommentare, die Person bekommt Bescheid)
  - Meine Notizen: privat fuers Team, mit Diktat
  - Vorbereitung: KI-Zusammenfassung vor dem Gespraech, "Neu erstellen" (Job `VorbereitungErstellen`)
- **"Als gelesen"**: setzt `memberships.settings.gelesen_bis` auf die letzte Nachricht. `Lage::wartende()` zaehlt die Person dann nicht mehr als wartend, ohne dass eine Nachricht rausgeht.
- **Assistent** (`/assistent`): Auskunft, Mein Wissen (merken, suchen, vergessen), Anleitung fuer Claude und ChatGPT.

Zeiten aus Formularen (datetime-local) werden in der Zeitzone des Mandanten gelesen und in UTC gespeichert (`Ortszeit`).

### Teilnehmer-Menue

Vorher: Nachschlagen mit vier Unterpunkten (Themen, Volltext, Gemerkt, Werkzeuge), Journal mit drei, Mitteilungen als Punkt, Coachees und Coach-Bereich dazwischen. Jetzt: Nachschlagen ist ein Punkt mit Reitern **Finden, Themen, Meine Suchen, Mein Archiv** (und Werkzeuge nur fuer die Ausbildung), Journal ein Punkt (die Reiter sind auf der Seite), Mitteilungen ueber die Glocke. Die alten Routen (`/themen`, `/suche`, `/merkliste`, `/werkzeuge`) bleiben erreichbar, nur nicht mehr im Menue.

### Teilnehmer-Sicht 1:1 wie der alte Bereich

Damit die Teilnehmerinnen die Umstellung nicht merken, ist die Teilnehmer-Sicht dem alten Mitgliederbereich
nachgebaut: Kopf nur mit Burger, Bild und "Mitgliederbereich" (die Glocke erscheint nur bei ungelesenen
Mitteilungen), Menue in derselben Reihenfolge (Uebersicht, 1:1 Coaching mit {Coach}, Termine, Nachschlagen,
Meine Sachen mit Aufgaben, Notizen, Reflexionen, Meine Kurse mit den Kursen, Ressourcen, Community, Impulse,
Mein Profil mit Meine Daten, Meine Buchungen, Nachrichten, Mitteilungen, Hilfe, Abmelden), keine Leiste unten,
nur der runde Chat-Knopf. Startseite: Hallo, Diese Woche im Kurs (nur bei laufender Woche), Was ist neu,
Dein naechster Termin, Offene Aufgaben. `/community` sammelt die Fragen aus allen Kursen der Person.

### Ansehen als (Plattform-Admin)

`App\Http\Middleware\AlsAndere`: steht in der Sitzung `als_user_id` und die angemeldete Person ist
Plattform-Admin, laeuft der Request als die andere Person (Lea, Team, jede Teilnehmerin), mit ihren Kursen,
Nachrichten und ihrer Ansicht-Einstellung. Oben steht ein dunkler Balken mit "Wechseln" und "Zurueck zu ...".
Seite `/als` (Menuepunkt "Ansehen als ..."), `POST /als/{user}` startet, `DELETE /als` beendet. Waehrend der
Verkleidung wird "zuletzt hier" der Person nicht veraendert. Gilt fuer die App-Huelle, nicht fuer Filament.

## Second Brain: Wissensspeicher

Tabelle `wissen` (`tenant_id`, `user_id`, `title`, `body`, `tags` json, `source` app|mcp|assistent), Modell `App\Models\Wissen`
mit `BelongsToTenant` und Scout `Searchable`. `Wissen::merken()` legt an, `Wissen::fakten($frage)` liefert passende
Eintraege als Zeilen. `App\Ai\Assistent::antwort()` haengt sie als `gemerktes_wissen` an die Fakten fuer die KI.
Gepflegt wird es auf `/assistent` (Team) oder ueber die Werkzeuge `wissen_merken` und `wissen_suchen` (Claude, ChatGPT).

Beispiele, die dort hingehoeren: Preise und Pakete, Ablaeufe ("Rechnungen schreibt Andrea am Monatsende"),
Regeln ("keine Mails am Wochenende"), Gedanken zu Kampagnen. Personenbezogenes gehoert in die Coach-Notizen
(`coach_notes`), nicht ins Wissen.

## Werkzeuge fuer Assistenten (`app/Ai/Werkzeuge`)

Eine Schicht, die jeder Assistent nutzen kann: heute der MCP-Server, spaeter Leas eigener Assistent (Anthropic Tool Use)
und die Website. Jedes Werkzeug erbt von `Werkzeug` (`name`, `beschreibung`, `schema` als JSON-Schema, `schreibt`,
`ausfuehren(array $args, User $von)`), laeuft im Mandanten des Requests und im Namen der Person aus dem Team, und geht
ueber die bestehenden Dienste (`Zugang`, `Chat`, `Terminvorschlag`, `Lage`, `Assistent`), nie direkt an die Datenbank.
`Werkzeugkasten` kennt alle und liefert `liste()` (MCP tools/list) und `aufrufen()`.

| Werkzeug | Tut |
|---|---|
| `heute` | Arbeitsliste (wartet, geteilt, Fragen, Freigaben, naechste Termine) |
| `personen_suchen` | Menschen nach Name oder Mail, mit `membership_id` |
| `person_fakten` | Alles zu einer Person (wie die Auskunft) |
| `person_anlegen` | Neue Person, ohne Duplikat, optional Willkommensmail |
| `zugang_geben` | Angebot freischalten, Programme betreten, Sitzungen, Notiz |
| `angebote` | Aktive Angebote |
| `nachricht_senden` | Ins 1:1-Gespraech schreiben |
| `aufgabe_geben` | Aufgabe mit Datum |
| `termin_anlegen` | 1:1-Termin in Ortszeit |
| `zeiten_vorschlagen` | Terminvorschlag ins Gespraech |
| `termine` | Kommende oder vergangene Termine |
| `lage` | Ampel |
| `notiz_schreiben` | Private Coach-Notiz |
| `inhalte_suchen` | Lektionen, Impulse, Podcast, Material, Werkzeuge |
| `wissen_suchen`, `wissen_merken` | Second Brain |

Neues Werkzeug: Klasse anlegen, in `Werkzeugkasten::WERKZEUGE` eintragen, Test in `McpTest`.

## MCP-Server

`POST /api/mcp` (`App\Http\Controllers\Api\McpController`), Streamable HTTP ohne Sitzung: jede Anfrage ist ein
JSON-RPC-2.0-Aufruf (auch als Stapel), Antwort sofort als JSON, kein SSE-Strom (`GET` gibt 405). Unterstuetzt
`initialize` (Protokoll 2025-06-18, 2025-03-26, 2024-11-05), `notifications/initialized` (202), `ping`, `tools/list`,
`tools/call`, leere `resources/list` und `prompts/list`. Fachliche Fehler (niemand gefunden, mehrdeutig) kommen als
`isError` im Ergebnis, damit der Assistent nachfragen kann; Protokollfehler als JSON-RPC-Fehler.

Zugang: Sanctum-Token mit Faehigkeit `mcp`, nur fuer `owner` und `team`. Die Person erzeugt ihn selbst im Profil
unter "Schluessel fuer Verbindungen" (einmal sichtbar) oder Sebastian per `php84 artisan api:token <email> --mcp`.
Der Mandant kommt wie immer aus der Domain (`IdentifyTenant`), also `https://app.leawernli.ch/api/mcp`.

### Claude verbinden

Claude (Web, Desktop, Mobile): Einstellungen, Connectors, "Custom connector" hinzufuegen. URL
`https://app.leawernli.ch/api/mcp`, unter "Advanced" den Schluessel als Bearer-Token (OAuth leer lassen).
Dann im Chat die Werkzeuge freigeben und fragen: "Wer wartet auf meine Antwort?", "Leg Anna Muster an,
anna@..., und gib ihr den Jahreskurs", "Schlag Nicole drei Zeiten naechste Woche vor".

Claude Code: `claude mcp add --transport http lea https://app.leawernli.ch/api/mcp --header "Authorization: Bearer <token>"`.

### ChatGPT verbinden

ChatGPT (Developer Mode unter Einstellungen, Connectors) nimmt bei eigenen MCP-Servern nur OAuth oder "keine
Authentifizierung". Bearer-Token direkt geht dort noch nicht. Zwei Wege:
1. OAuth-Anbieter in der App (Laravel Passport oder ein kleiner eigener Authorization-Code-Flow mit PKCE und Dynamic
   Client Registration, wie es MCP vorsieht). Offen in der Roadmap.
2. Uebergangsweise ein Proxy (z. B. Cloudflare Worker), der das Token anhaengt. Nur fuer Tests.

### Sicherheit

Alles laeuft mit dem Konto der Person, jede Aktion ist so, als haette sie es in der App getan (Nachrichten kommen von ihr,
Notizen tragen ihren Namen). Kein Werkzeug loescht etwas. Token loeschen im Profil kappt die Verbindung sofort.
Rate-Limit 240 Anfragen pro Minute. Der Server ist nur so weit "dual use", wie die App selbst es ist: Team-Rechte,
Mandant aus der Domain, Policies wie ueberall.

## Was das fuer spaeter heisst

- **Leas Assistent in der App** bekommt dieselben Werkzeuge ueber Anthropic Tool Use (`App\Ai\Anthropic` um `tools` erweitern, Schleife ueber `tool_use`). Dann geht "Leg Anna an" auch auf `/assistent`, mit Rueckfrage vor jedem Schreiben.
- **Website** (leawernli.ch): das Novamira-Plugin hat eine WordPress-MCP-Schnittstelle. Werkzeuge wie `seite_anlegen`, `kampagne_starten`, `funnel_bauen` waeren Werkzeuge in derselben Schicht, die die WordPress-API aufrufen (Zugangsdaten in `tenants.settings.website`). Umgekehrt kann die Website den MCP der App fragen (Personen, Angebote).
- **Social Media**: gleicher Weg, ein Werkzeug je Kanal, Zugangsdaten je Mandant.
- **Native App (iOS, Android)**: die JSON-API `/api/v1` (lesend) und der MCP (Werkzeuge) sind die Schnittstellen. NativePHP (Mobile) kann die Laravel-App als native Huelle verpacken; die Views in der Huelle sind schon fuers Handy gebaut (Leiste unten, Drawer, Pull-to-refresh). Push ist vorhanden (Web Push), fuer native Push kaeme APNs/FCM als weiterer Kanal in `app/Notifications`.
- **Mandantenfaehig** bleibt alles: `wissen` hat `tenant_id`, die Werkzeuge laufen im Mandanten des Requests, Tokens gehoeren Personen, und `canManageCurrentTenant()` entscheidet je Mandant.

## Tests

`ArbeitsplatzTest` (Sichten, Umschalter, Menue, Nachschlagen-Reiter, Assistent-Seite, Wissen je Mandant),
`DossierAppTest` (Dossier, Nachricht, Notiz, Aufgabe, Termin in Ortszeit, Vorschlag, Verkaufen, Neue Person, Isolation),
`McpTest` (Handschlag, Werkzeugliste, Rechte, Werkzeuge im Mandanten, Fehler als isError, Isolation).
