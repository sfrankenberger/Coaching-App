# 03 Datenmodell (Zielbild)

Alle Tabellen ausser `users`, `tenants`, `tenant_domains` tragen `tenant_id`. `legacy_id` (nullable, string) überall dort, wo aus WordPress importiert wird, damit der Import wiederholbar ist (updateOrCreate über `tenant_id + legacy_id`).

## Kern (angelegt)

- `tenants` - slug, name, locale, timezone, currency, settings (json), branding (json), is_active
- `tenant_domains` - tenant_id, domain (unique), is_primary
- `users` - plattformweit: name, email, password (nullable nutzbar), phone, avatar_path, is_platform_admin
- `memberships` - tenant_id, user_id, role, status, legacy_id, joined_at

## Angebote und Zugang

Ersetzt WooCommerce Memberships und die Relation "Teilnehmer zu Kurse" (JetEngine rel 13).

- `offers` - Was man kaufen oder bekommen kann: Einzelkurs, Club (Abo), Hybrid-Coaching, 1:1, Gratis-Einstieg. Felder: title, type, is_free, settings
- `offer_products` - Zuordnung externer Produkte zu Angeboten: source (`woocommerce`, `stripe`, `manual`), external_id
- `entitlements` - Person hat Zugang zu Angebot: user_id, offer_id, source, source_ref (Bestell-/Abo-ID), starts_at, ends_at, status
- `offer_program` - welche Programme ein Angebot freischaltet (n:m)

Zugriffsprüfung an genau einer Stelle: `Gate::define('view-program', ...)` bzw. eine Policy. Entspricht dem heutigen `lea_zugang_darf()`.

## Programme (ein Kursmodell statt drei)

Aus der Übergabe vom 19.09.: **Kurs, Schritte, Übungen wie im Workbook. Unterschied nur in der Taktung.**

- `programs` - title, slug, type (`hybrid`, `selfpaced`, `one_on_one`, `workbook`), pacing (`weekly`, `all`, `none`), starts_at, settings, cover. Heute: CPT `kurs` (13), Tutor `courses`, Workbook
- `program_steps` - program_id, position, title, unlocks_at (bei Wochentaktung), summary. Heute: CPT `modul` (63), Wochen
- `units` - step_id, position, title, body (HTML), video_url, duration. Heute: CPT `lektion` (136), `lesson`
- `exercises` - unit_id oder step_id, prompt, type (text, liste, skala, auswahl), settings. Heute: Workbook-Übungen, `frage`
- `progress` - user_id, unit_id, completed_at
- `answers` - user_id, exercise_id, value (json), shared_with_coach (bool). Heute: Workbook-Antworten, Reflexion
- `program_members` - program_id, user_id, role_in_program, cohort. Für Kohorten bei Hybrid-Kursen

## Begleitung

- `events` (Termine) - program_id nullable, title, starts_at, ends_at, location/zoom_url, recording_url, type (`group_call`, `one_on_one`, `qa`, `webinar`). Heute: CPT `termin` (69) + Relationen 19 bis 22
- `event_attendees` - event_id, user_id, status, attended (Zoom-Anwesenheit)
- `resources` - title, type (pdf, audio, link, text), file_path/url, body. Heute: CPT `ressource` (23)
- `resourceables` - polymorph: Ressource an Programm, Schritt, Einheit, Termin (ersetzt Relationen 12, 16, 17, 18)
- `tasks` (Aufgaben) - user_id, assigned_by, title, due_at, source (`manual`, `ai_summary`, `exercise`, `launch_date`), done_at. Heute: CPT `aufgabe` + `lea-aufgaben*.php`
- `notes` - user_id, notable (polymorph), body, is_private. Heute: CPT `notiz`, Workbook-Notizen
- `reflections` - user_id, program_id, week, body, shared_at. Heute: CPT `reflexion`
- `journal_entries` - heute CPT `journal`

## Kommunikation

- `conversations` - type (`direct`, `group`), program_id nullable
- `conversation_participants` - conversation_id, user_id, last_read_at
- `messages` - conversation_id, user_id, body, audio_path (Sprachnachricht), attachment_path, read tracking über `last_read_at`, `reactions` (json oder eigene Tabelle). Heute: CPT `chat` + JetEngine Messenger-Tabellen
- `notifications` (Laravel Standard, mit tenant_id-Spalte ergänzen)
- `push_subscriptions` - user_id, endpoint, keys
- `telegram_links` - user_id, chat_id, active

## Inhalte

- `posts` (Impulse) - title, body, image, published_at, visibility (alle, Programm, Rolle), channels (App, Push, Mail). Heute: WP-Posts in Impuls-Kategorien 5 bis 9
- `podcast_episodes` - show, title, audio_url, published_at, transcript, summary. Heute: CPT `podcast` (100), eigener Podcast plus zwei RSS-Spiegel
- `topics` + `taggables` - Themenfinder, polymorph über alle Inhalte. Heute: Taxonomie `thema`, CPT `topics`
- `bookmarks` - user_id, bookmarkable (polymorph). Heute: `lea-gemerkt.php`, Schlüssel wie `ressource-2475`

## Sonstiges

- `leads` - Freebie-Anmeldungen ohne Konto (heute CPT `lea_lead`)
- `ai_summaries` - summarizable (polymorph, z. B. Termin), body, model, tokens
- `settings_user` - Schalter "Was kommt an" (termin, abendmail, aufgaben)
