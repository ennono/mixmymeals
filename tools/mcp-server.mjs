#!/usr/bin/env node

/**
 * WordPress REST API – MCP Server (MixMyMeals-Edition v5.1)
 *
 * Umgebungsvariablen:
 *   WP_URL           z.B. https://speisengeplant.de
 *   WP_USER          WordPress-Benutzername
 *   WP_APP_PASSWORD  Application Password
 *
 * Endpunkt-Strategie:
 *   MixMyMeals-Daten  → /wp-json/mixmymeals/v1/  (Plugin-eigene API)
 *   Generisches WP    → /wp-json/wp/v2/           (Posts, Taxonomien, Media)
 *
 * Neu in v5.1:
 *   - Datenkorrektur in Mengen: mmm_bulk_update (Nährwerte, Preis, Diät, Garfaktor für
 *     bis zu 100 Zutaten/Produkte, Antwort nur als Diff, mit dry_run),
 *     mmm_list_missing (schlanker, seitenweiser Lückenfinder) und
 *     mmm_merge_ingredients (Rezepte umhängen, Produkte ziehen mit).
 *   - null löscht in allen Nährwert-Schreibpfaden den Eigenwert, statt 0 zu
 *     schreiben. mmm_update_ingredient setzt own_price_per_100g.
 *
 * Neu in v5.0:
 *   - Bildquelle: image_credit (creator, source, license, url) in
 *     mmm_create_recipe / mmm_update_recipe / mmm_create_meal_plan /
 *     mmm_update_meal_plan / mmm_build_meal_plan. Ausgabe im Theme unter
 *     dem Bild via [mmm_image_credit].
 *
 * Neu in v4.9:
 *   - Bilder: mmm_upload_image lädt eine lokale Datei, eine URL oder ein
 *     vorhandenes Attachment in die Mediathek und setzt es auf Wunsch direkt
 *     als Beitragsbild. featured_image gibt es zusätzlich in
 *     mmm_create_recipe / mmm_update_recipe / mmm_create_meal_plan /
 *     mmm_update_meal_plan / mmm_build_meal_plan.
 *
 * Neu in v4.8:
 *   - Externe Autoren für Rezepte und Pläne: als author-Objekt in
 *     mmm_create_recipe / mmm_update_recipe / mmm_create_meal_plan /
 *     mmm_update_meal_plan / mmm_build_meal_plan sowie separat über
 *     mmm_get_author / mmm_set_author. Ohne Namen zeigt der Beitrag
 *     weiterhin seinen WordPress-Autor. Mit author.image (Attachment-ID)
 *     bekommt ein externer Autor ein Profilbild, das wie der Avatar eines
 *     WordPress-Autors dargestellt wird.
 *
 * Neu in v4.7:
 *   - Pinterest: Status, sofort pinnen und terminieren – für Pläne und
 *     Rezepte. Pins nutzen die Instagram-Bilder und verlinken auf die Seite.
 *     Tools: mmm_pinterest_status / _publish / _schedule / _unschedule.
 *
 * Neu in v4.6:
 *   - KI-Komfort für Plan-Erstellung: mmm_build_meal_plan (Plan + alle
 *     Mahlzeiten in einem Call, Rezepte per Name/ID, Tagesbereich automatisch),
 *     mmm_add_meals (mehrere Mahlzeiten zu bestehendem Plan) und
 *     mmm_find_recipes (Rezeptsuche nach Diät/Kalorien/Zeit/Kategorie).
 *
 * Neu in v4.5:
 *   - Instagram: Status, Bilder generieren, sofort posten, terminieren und
 *     Terminierung abbrechen – für Pläne (Karussell) und Rezepte (Einzelbild).
 *     Tools: mmm_instagram_status / _generate / _publish / _schedule /
 *     _unschedule. Posten erfordert Access Token + öffentlich erreichbare
 *     URLs (nur Live-Site).
 *
 * Neu in v4.4:
 *   - Rezept-Verknüpfungen "Dazu passt" (related_recipes) lesen & schreiben
 *     über mmm_create_recipe / mmm_update_recipe.
 *   - mmm_get_recipe liefert zusätzlich related_recipes ("Dazu passt"),
 *     related_recipes_reverse ("Passend zu") und meal_plans
 *     ("In diesen Plänen enthalten").
 */

import { Server }               from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { readFile } from "node:fs/promises";
import { basename, resolve } from "node:path";

// ── Konfiguration ────────────────────────────────────────────────────────────

const WP_URL = process.env.WP_URL?.replace(/\/$/, "");
const WP_USER = process.env.WP_USER;
const WP_APP_PASSWORD = process.env.WP_APP_PASSWORD;

if (!WP_URL || !WP_USER || !WP_APP_PASSWORD) {
  console.error("Fehlende Umgebungsvariablen: WP_URL, WP_USER, WP_APP_PASSWORD");
  process.exit(1);
}

const AUTH = Buffer.from(`${WP_USER}:${WP_APP_PASSWORD}`).toString("base64");
const WP2  = `${WP_URL}/wp-json/wp/v2`;
const MMM  = `${WP_URL}/wp-json/mixmymeals/v1`;

const HEADERS = {
  Authorization:  `Basic ${AUTH}`,
  "Content-Type": "application/json",
};

const LIST_FIELDS = "id,title,slug,status,type,parent";

// ── HTTP-Helfer ──────────────────────────────────────────────────────────────

async function request(method, url, body) {
  const res = await fetch(url, {
    method,
    headers: HEADERS,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const err = await res.text();
    throw new Error(`API Fehler ${res.status} ${method} ${url}: ${err}`);
  }
  const text = await res.text();
  return text ? JSON.parse(text) : {};
}

function wp(method, path, body, slim = false) {
  const sep  = path.includes("?") ? "&" : "?";
  const full = slim ? `${path}${sep}_fields=${LIST_FIELDS}` : path;
  return request(method, `${WP2}${full}`, body);
}

function mmm(method, path, body) {
  return request(method, `${MMM}${path}`, body);
}

// Multipart-Upload zur Plugin-API. Eigener Pfad, weil request() immer
// JSON serialisiert — hier muss der Body binär bleiben und fetch() setzt den
// Content-Type samt Boundary selbst (deshalb NICHT aus HEADERS übernehmen).
async function mmmUpload(path, filePath, fields = {}) {
  const data = await readFile(filePath);
  const name = basename(filePath);

  const form = new FormData();
  form.set("file", new Blob([data], { type: mimeOf(name) }), name);
  for (const [key, value] of Object.entries(fields)) {
    if (value !== undefined && value !== null) form.set(key, String(value));
  }

  const res = await fetch(`${MMM}${path}`, {
    method:  "POST",
    headers: { Authorization: `Basic ${AUTH}` },
    body:    form,
  });

  const text = await res.text();
  if (!res.ok) throw new Error(`API Fehler ${res.status} POST ${MMM}${path}: ${text}`);
  return text ? JSON.parse(text) : {};
}

// WordPress prüft den MIME-Typ serverseitig; das hier ist nur die Angabe im
// Multipart-Part, damit der Upload nicht als application/octet-stream ankommt.
const MIME_BY_EXT = {
  jpg: "image/jpeg", jpeg: "image/jpeg", png: "image/png",
  gif: "image/gif",  webp: "image/webp", avif: "image/avif",
};

function mimeOf(filename) {
  const ext = filename.split(".").pop()?.toLowerCase() ?? "";
  return MIME_BY_EXT[ext] ?? "application/octet-stream";
}

// ── Autoren-Box ──────────────────────────────────────────────────────────────

// Externe Autorenangabe, identisch bei Rezepten und Plänen. Wird in mehrere
// Tool-Schemas eingehängt, damit ein Rezept/Plan samt Credit in EINEM Aufruf
// entsteht. Es werden nur übergebene Felder geändert; "" löscht ein Feld.
const AUTHOR_FIELDS = {
  name:      { type: "string", description: "Name des externen Autors. Leer = WordPress-Autor des Beitrags wird angezeigt." },
  text:      { type: "string", description: "Freitext / Kurzbio, einfaches HTML erlaubt." },
  url:       { type: "string", description: "Link-Ziel der Autorenbox." },
  link_text: { type: "string", description: "Link-Beschriftung. Leer = Domain des Links." },
  nofollow:  { type: "boolean", description: "Link mit rel=\"nofollow\" ausgeben." },
  social:    { type: "array", items: { type: "string" }, description: "URLs und E-Mail-Adressen; das Icon wird aus der Adresse abgeleitet." },
  image:     { type: "number", description: "Attachment-ID des Profilbilds, 0 entfernt es. Wird wie der Avatar eines WordPress-Autors dargestellt und nur zusammen mit name verwendet." },
};

const AUTHOR_SCHEMA = {
  type: "object",
  description: "Externe Autorenangabe. Nur übergebene Felder werden geändert, \"\" löscht ein Feld. Ohne name fällt der Beitrag auf seinen WordPress-Autor zurück.",
  properties: AUTHOR_FIELDS,
};

// ── Bildquelle ───────────────────────────────────────────────────────────────

// Nachweis für das Beitragsbild, identisch bei Rezepten und Plänen. Bewusst
// strukturiert: Lizenzen wie CC BY verlangen Urheber, Quelle und Lizenz
// getrennt. Es werden nur übergebene Felder geändert; "" löscht ein Feld.
const IMAGE_CREDIT_SCHEMA = {
  type: "object",
  description: "Bildquelle des Beitragsbilds. Nur übergebene Felder werden geändert, \"\" löscht ein Feld. Ausgegeben wird \"Urheber / Quelle (Lizenz)\".",
  properties: {
    creator: { type: "string", description: "Wer das Bild gemacht hat." },
    source:  { type: "string", description: "Woher es stammt, z.B. Bildagentur oder Publikation." },
    license: { type: "string", description: "Lizenz, z.B. \"CC BY 4.0\"." },
    url:     { type: "string", description: "Optionaler Link; verlinkt Urheber und Quelle, nicht die Lizenz." },
  },
};

// ── Nährwerte ────────────────────────────────────────────────────────────────

// Je 100 g. null zieht einen Wert zurück, statt ihn auf 0 zu setzen: „nicht
// angegeben" und „enthält nichts" sind verschiedene Aussagen, und nur bei der
// ersten scheint der Wert des Referenzprodukts durch.
const NULLABLE = (description) => ({ type: ["number", "null"], ...(description ? { description } : {}) });

const NUTRITION_PROPS = {
  calories_100: NULLABLE("kcal je 100 g"),
  fat:          NULLABLE("Fett, g"),
  saturates:    NULLABLE("davon gesättigte Fettsäuren, g"),
  carbs:        NULLABLE("Kohlenhydrate, g"),
  sugar:        NULLABLE("davon Zucker, g"),
  fiber:        NULLABLE("Ballaststoffe, g"),
  protein:      NULLABLE("Eiweiß, g"),
  salt:         NULLABLE("Salz, g"),
};

const INGREDIENT_NUTRITION_PROPS = {
  ...NUTRITION_PROPS,
  weight_item: NULLABLE("Gewicht eines typischen Stücks in g (nur Zutat, nie Produkt)"),
};

const NUTRITION_NULL_NOTE = "null löscht den Eigenwert der Zutat — dann gilt wieder der Wert des Referenzprodukts bzw. „nicht angegeben\". 0 ist eine Aussage („enthält nichts\") und kein Löschen. Ein weggelassener Schlüssel bleibt unverändert. Hinweis: Ein Wert, den das Referenzprodukt selbst angibt, gewinnt ohnehin über den Eigenwert der Zutat.";

// ── Tool-Definitionen ────────────────────────────────────────────────────────

const TOOLS = [

  // ── Generische WP-Tools ──────────────────────────────────────────────────

  {
    name: "wp_get_posts",
    description: "Posts/CPT-Einträge abrufen. Liefert schlanke Listen (id, title, slug, status, parent). Für MixMyMeals-Inhalte lieber mmm_* Tools verwenden.",
    inputSchema: {
      type: "object",
      properties: {
        post_type: { type: "string", description: "z.B. post, page, mmm_ingredient, mmm_equipment", default: "post" },
        per_page:  { type: "number", default: 20 },
        page:      { type: "number", default: 1 },
        search:    { type: "string" },
        status:    { type: "string", description: "publish | draft | any", default: "publish" },
        parent:    { type: "number" },
        orderby:   { type: "string" },
        order:     { type: "string", description: "asc | desc" },
        include:   { type: "string", description: "Kommagetrennte Post-IDs" },
      },
    },
  },

  {
    name: "wp_get_post",
    description: "Einzelnen Post mit allen Feldern und Meta abrufen.",
    inputSchema: {
      type: "object",
      required: ["id", "post_type"],
      properties: {
        id:        { type: "number" },
        post_type: { type: "string" },
      },
    },
  },

  {
    name: "wp_create_post",
    description: "Neuen Post/CPT-Eintrag erstellen. Für MixMyMeals-Inhalte besser mmm_* Tools verwenden.",
    inputSchema: {
      type: "object",
      required: ["title", "post_type"],
      properties: {
        post_type: { type: "string" },
        title:     { type: "string" },
        content:   { type: "string" },
        status:    { type: "string", default: "publish" },
        parent:    { type: "number" },
        meta:      { type: "object" },
      },
    },
  },

  {
    name: "wp_update_post",
    description: "Post aktualisieren. Für MixMyMeals-Inhalte besser mmm_* Tools verwenden.",
    inputSchema: {
      type: "object",
      required: ["id", "post_type"],
      properties: {
        id:        { type: "number" },
        post_type: { type: "string" },
        title:     { type: "string" },
        content:   { type: "string" },
        status:    { type: "string" },
        parent:    { type: "number" },
        meta:      { type: "object" },
      },
    },
  },

  {
    name: "wp_delete_post",
    description: "Post löschen (Papierkorb oder endgültig).",
    inputSchema: {
      type: "object",
      required: ["id", "post_type"],
      properties: {
        id:        { type: "number" },
        post_type: { type: "string" },
        force:     { type: "boolean", default: false },
      },
    },
  },

  {
    name: "wp_get_terms",
    description: "Terms einer Taxonomie abrufen.",
    inputSchema: {
      type: "object",
      required: ["taxonomy"],
      properties: {
        taxonomy:   { type: "string", description: "mmm_recipe_category | mmm_recipe_tag | mmm_ingredient_category | mmm_meal_plan_category | mmm_meal_plan_tag" },
        post_id:    { type: "number", description: "Nur Terms dieses Posts" },
        per_page:   { type: "number", default: 100 },
        search:     { type: "string" },
        hide_empty: { type: "boolean", default: false },
      },
    },
  },

  {
    name: "wp_create_term",
    description: "Neuen Term in einer Taxonomie anlegen.",
    inputSchema: {
      type: "object",
      required: ["taxonomy", "name"],
      properties: {
        taxonomy:    { type: "string" },
        name:        { type: "string" },
        slug:        { type: "string" },
        description: { type: "string" },
        parent:      { type: "number" },
      },
    },
  },

  {
    name: "wp_update_term",
    description: "Term einer Taxonomie aktualisieren (Beschreibung, Name, Slug, Parent).",
    inputSchema: {
      type: "object",
      required: ["taxonomy", "id"],
      properties: {
        taxonomy:    { type: "string" },
        id:          { type: "number" },
        name:        { type: "string" },
        slug:        { type: "string" },
        description: { type: "string" },
        parent:      { type: "number" },
      },
    },
  },

  {
    name: "wp_set_post_terms",
    description: "Terms einem Post zuweisen (ersetzt bestehende).",
    inputSchema: {
      type: "object",
      required: ["id", "post_type", "taxonomy", "term_ids"],
      properties: {
        id:        { type: "number" },
        post_type: { type: "string" },
        taxonomy:  { type: "string" },
        term_ids:  { type: "array", items: { type: "number" } },
      },
    },
  },

  // ── MixMyMeals: Rezepte ──────────────────────────────────────────────────

  {
    name: "mmm_get_recipes",
    description: "Alle Rezepte abrufen (id, title, status, servings, diet_type, Kategorien). Verwendet /mixmymeals/v1/recipes.",
    inputSchema: {
      type: "object",
      properties: {
        status: { type: "string", description: "publish | draft | any", default: "publish" },
      },
    },
  },

  {
    name: "mmm_get_recipe",
    description: "Rezept abrufen inkl. Steps, Nährwerte, Kategorien, Equipment sowie Verknüpfungen: related_recipes (\"Dazu passt\"), related_recipes_reverse (\"Passend zu\") und meal_plans (\"In diesen Plänen enthalten\", nur veröffentlichte Pläne). Verwendet /mixmymeals/v1/recipes/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  {
    name: "mmm_create_recipe",
    description: "Neues Rezept anlegen inkl. Steps, Portionen, Zubereitungszeit, Equipment, verknüpfte Rezepte (\"Dazu passt\") und Kategorien. Verwendet /mixmymeals/v1/recipes.",
    inputSchema: {
      type: "object",
      required: ["title"],
      properties: {
        title:            { type: "string" },
        excerpt:          { type: "string" },
        status:           { type: "string", default: "draft", description: "publish | draft" },
        servings:         { type: "number", default: 1 },
        preparation_time: { type: "number", description: "Minuten" },
        shelf_life_days: { type: ["number", "null"], description: "Haltbarkeit des zubereiteten Gerichts in Tagen. NICHT dieselbe Groesse wie die Haltbarkeit der Zutaten — Kochen verschiebt sie in beide Richtungen. Weglassen heisst \"nicht angegeben\", null loescht den Wert, 0 heisst \"muss am Zubereitungstag gegessen werden\". 0 und weglassen sind verschiedene Aussagen." },
        storage_type: { type: "string", enum: ["room_temperature", "cool_dry", "refrigerated", "frozen", ""], description: "Lagerung des zubereiteten Gerichts. NICHT dieselbe Angabe wie die Lagerung der Zutaten — Mehl liegt im Schrank, eine Mehlschwitze im Kuehlschrank. Leer = nicht angegeben. Gehoert zu shelf_life_days: eine Haltbarkeit ohne Lagerbedingung ist keine Aussage." },
        priority:         { type: "number", description: "Priorität / Sortierreihenfolge (0 = Standard)" },
        equipment:        { type: "array", items: { type: "number" }, description: "Equipment Post-IDs" },
        related_recipes:  { type: "array", items: { type: "number" }, description: "\"Dazu passt\": Post-IDs verknüpfter Rezepte (gerichtet; die Gegenseite erscheint dort automatisch als \"Passend zu\")" },
        categories:       { type: "array", items: { type: "number" }, description: "mmm_recipe_category Term-IDs" },
        tags:             { type: "array", items: { type: "number" }, description: "mmm_recipe_tag Term-IDs" },
        author:           AUTHOR_SCHEMA,
        featured_image: { type: "number", description: "Attachment-ID des Beitragsbilds, 0 entfernt es. Neue Bilder vorher mit mmm_upload_image hochladen." },
        image_credit:   IMAGE_CREDIT_SCHEMA,
        steps: {
          type: "array",
          description: "Rezept-Schritte",
          items: {
            type: "object",
            required: ["title", "description"],
            properties: {
              title:       { type: "string" },
              description: { type: "string" },
              image:       { type: "number", default: 0, description: "Attachment-ID oder 0" },
              prep_amount: { type: "number", default: 0, description: "Vorarbeit: wie viele Tage bzw. Stunden VOR der Mahlzeit dieser Schritt erledigt werden muss (z. B. 2 fuer \"Bohnen 2 Tage zuvor einweichen\"). Nur zusammen mit prep_unit wirksam; 0 = normaler Schritt. Vorarbeiten stehen nach dem Speichern immer am Anfang der Schrittliste, die frueheste zuerst, und erscheinen auf Plan-Seiten und im Planer als Hinweis an dem Tag, an dem sie faellig sind." },
              prep_unit:   { type: "string", enum: ["", "days", "hours"], default: "", description: "Einheit zu prep_amount: days = Tage zuvor, hours = Stunden zuvor, leer = keine Vorarbeit." },
              ingredients: {
                type: "array",
                items: {
                  type: "object",
                  required: ["id", "amount"],
                  properties: {
                    id:        { type: "number", description: "Post-ID des mmm_ingredient" },
                    amount:    { type: "number" },
                    amount_id: { type: "number", default: 0, description: "Post-ID der mmm_ingr_unit (0 = Gramm direkt)" },
                    product_id: { type: "number", default: 0, description: "Post-ID eines mmm_product dieser Zutat. Legt fest, mit welchem konkreten Artikel gekocht wird, und gilt damit ueberall als gesetzt: seine Naehrwerte und sein Preis gehen in Rezept, Mahlzeit und Plan ein, es steht in der Zutatenliste und auf der Einkaufsliste, und es entscheidet die Ernaehrungsform — sind alle Zutaten geklaert, heisst die Pille \"Vegan\" statt \"Vegan tauglich\". Schlaegt das Referenzprodukt der Zutat. 0 = offen, dann entscheidet die Zutat. Ein Produkt, das nicht zu dieser Zutat gehoert, wird beim Speichern verworfen." },
                    optional:   { type: "boolean", default: false, description: "Der Koch darf sie weglassen. Erscheint als \"(optional)\" und schraenkt nicht ein, wofuer das Gericht kochbar ist." },
                    excluded_products: { type: "array", items: { type: "number" }, description: "Post-IDs von Produkten DIESER Zutat, die fuer dieses Gericht nicht in Frage kommen. Sie stehen dann nicht als Spalte im Produktvergleich und tragen die Ernaehrungsform-Pille nicht mit: eine Zutat gilt sonst schon als \"vegan moeglich\", sobald irgendeines ihrer Pakete vegan ist. Beispiel: gesuesster Sojajoghurt in einem herzhaften Gericht. IDs, die zu einer anderen Zutat gehoeren, werden beim Speichern verworfen. Gilt je Zutatenzeile; ein Ausschluss in einer Zeile wirkt fuer das ganze Rezept." },
                  },
                },
              },
            },
          },
        },
      },
    },
  },

  {
    name: "mmm_update_recipe",
    description: "Rezept aktualisieren. Nur übergebene Felder werden geändert. Steps und related_recipes werden komplett ersetzt wenn angegeben. Verwendet /mixmymeals/v1/recipes/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:               { type: "number" },
        title:            { type: "string" },
        excerpt:          { type: "string" },
        status:           { type: "string" },
        servings:         { type: "number" },
        preparation_time: { type: "number" },
        shelf_life_days: { type: ["number", "null"], description: "Haltbarkeit des zubereiteten Gerichts in Tagen. NICHT dieselbe Groesse wie die Haltbarkeit der Zutaten — Kochen verschiebt sie in beide Richtungen. Weglassen heisst \"nicht angegeben\", null loescht den Wert, 0 heisst \"muss am Zubereitungstag gegessen werden\". 0 und weglassen sind verschiedene Aussagen." },
        storage_type: { type: "string", enum: ["room_temperature", "cool_dry", "refrigerated", "frozen", ""], description: "Lagerung des zubereiteten Gerichts. NICHT dieselbe Angabe wie die Lagerung der Zutaten — Mehl liegt im Schrank, eine Mehlschwitze im Kuehlschrank. Leer = nicht angegeben. Gehoert zu shelf_life_days: eine Haltbarkeit ohne Lagerbedingung ist keine Aussage." },
        priority:         { type: "number", description: "Priorität / Sortierreihenfolge" },
        equipment:        { type: "array", items: { type: "number" } },
        related_recipes:  { type: "array", items: { type: "number" }, description: "\"Dazu passt\": Post-IDs verknüpfter Rezepte (ersetzt die bestehende Liste; leeres Array löscht alle Verknüpfungen)" },
        categories:       { type: "array", items: { type: "number" } },
        tags:             { type: "array", items: { type: "number" } },
        author:           AUTHOR_SCHEMA,
        featured_image: { type: "number", description: "Attachment-ID des Beitragsbilds, 0 entfernt es. Neue Bilder vorher mit mmm_upload_image hochladen." },
        image_credit:   IMAGE_CREDIT_SCHEMA,
        steps: {
          type: "array",
          items: {
            type: "object",
            required: ["title", "description"],
            properties: {
              title:       { type: "string" },
              description: { type: "string" },
              image:       { type: "number", default: 0 },
              prep_amount: { type: "number", default: 0, description: "Vorarbeit: Tage/Stunden vor der Mahlzeit (mit prep_unit)." },
              prep_unit:   { type: "string", enum: ["", "days", "hours"], default: "" },
              ingredients: {
                type: "array",
                items: {
                  type: "object",
                  required: ["id", "amount"],
                  properties: {
                    id:        { type: "number" },
                    amount:    { type: "number" },
                    amount_id: { type: "number", default: 0 },
                    product_id: { type: "number", default: 0 },
                    optional:   { type: "boolean", default: false },
                    excluded_products: { type: "array", items: { type: "number" }, description: "Produkte dieser Zutat, die fuer dieses Gericht nicht in Frage kommen." },
                  },
                },
              },
            },
          },
        },
      },
    },
  },

  {
    name: "mmm_get_recipe_nutrition",
    description: "Berechnete Nährwerte eines Rezepts abrufen (pro Portion und pro 100g). Verwendet /mixmymeals/v1/recipes/{id}/nutrition.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  {
    name: "mmm_get_recipe_ingredients",
    description: "Skalierte Zutatenliste eines Rezepts abrufen. scale=1.0 = Originalportionen. Verwendet /mixmymeals/v1/recipes/{id}/ingredients.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:    { type: "number" },
        scale: { type: "number", default: 1.0 },
      },
    },
  },

  // ── MixMyMeals: Zutaten ──────────────────────────────────────────────────

  {
    name: "mmm_get_ingredients",
    description: "Alle Zutaten abrufen (id, title, diet_type, Nährwerte, Einheiten). Verwendet /mixmymeals/v1/ingredients.",
    inputSchema: {
      type: "object",
      properties: {},
    },
  },

  {
    name: "mmm_get_ingredient",
    description: "Einzelne Zutat mit Nährwerten und allen Einheiten abrufen. Verwendet /mixmymeals/v1/ingredients/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  {
    name: "mmm_create_ingredient",
    description: "Neue Zutat anlegen inkl. Nährwerten, Diät-Typ und Kategorie. Verwendet /mixmymeals/v1/ingredients.",
    inputSchema: {
      type: "object",
      required: ["title"],
      properties: {
        title:        { type: "string" },
        status:       { type: "string", default: "publish", description: "publish | draft" },
        diet_type:    { type: "string", description: "Slug einer Ernaehrungsform der Gruppe 'diet' (z. B. vegan, vegetarisch; 'vegetarian' geht weiter) oder leer. Leer loescht die Einstufung ganz — Terme und die alte Meta-Zeile. Ein fehlender Schluessel aendert nichts." },
        shelf_life_days: { type: ["number", "null"], description: "Haltbarkeit der Zutat selbst in Tagen. NICHT dieselbe Groesse wie die Haltbarkeit eines daraus gekochten Gerichts. Weglassen heisst \"nicht angegeben\", null loescht den Wert, 0 heisst \"muss am Einkaufstag verbraucht werden\"." },
        storage_type: { type: "string", enum: ["room_temperature", "cool_dry", "refrigerated", "frozen", ""], description: "Lagerung der Zutat selbst. NICHT dieselbe Angabe wie die Lagerung eines daraus gekochten Gerichts. Leer = nicht angegeben. Gehoert zu shelf_life_days." },
        own_cooking_factor: { type: ["number", "null"], description: "Eigener Garfaktor der Zutat: Gewicht gegart je Gramm roh, z. B. 2.4 fuer trockene Nudeln, 2.7 fuer Reis, 0.75 fuer Hackfleisch, 0.3 fuer Spinat. Aendert keine Naehrwertsumme (Wasser hat keine), nur das Portionsgewicht und alle Werte pro 100 g samt Ampel. Weglassen = unveraendert, null loescht (= keine Korrektur). Ein Produkt mit eigenem Faktor geht vor." },
        default_unit: { type: "string" },
        categories:   { type: "array", items: { type: "number" }, description: "mmm_ingredient_category Term-IDs" },
        nutrition: {
          type: "object",
          description: "Nährwerte pro 100g",
          properties: {
            calories_100: { type: "number" },
            fat:          { type: "number" },
            saturates:    { type: "number" },
            carbs:        { type: "number" },
            protein:      { type: "number" },
            sugar:        { type: "number" },
            fiber:        { type: "number" },
            salt:         { type: "number" },
            weight_item:  { type: "number", description: "Gewicht eines typischen Stücks in g" },
          },
        },
      },
    },
  },

  {
    name: "mmm_update_ingredient",
    description: "Zutat aktualisieren (Titel, Diät-Typ, Nährwerte, eigener Preis, Kategorie). diet_type und own_price_per_100g sind direkt setzbar. Für viele Zutaten auf einmal mmm_bulk_update verwenden. Verwendet /mixmymeals/v1/ingredients/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:           { type: "number" },
        title:        { type: "string" },
        diet_type:    { type: "string", description: "Slug einer Ernaehrungsform der Gruppe 'diet' (z. B. vegan, vegetarisch; 'vegetarian' geht weiter) oder leer. Leer loescht die Einstufung ganz — Terme und die alte Meta-Zeile. Ein fehlender Schluessel aendert nichts." },
        shelf_life_days: { type: ["number", "null"], description: "Haltbarkeit der Zutat selbst in Tagen. NICHT dieselbe Groesse wie die Haltbarkeit eines daraus gekochten Gerichts. Weglassen heisst \"nicht angegeben\", null loescht den Wert, 0 heisst \"muss am Einkaufstag verbraucht werden\"." },
        storage_type: { type: "string", enum: ["room_temperature", "cool_dry", "refrigerated", "frozen", ""], description: "Lagerung der Zutat selbst. NICHT dieselbe Angabe wie die Lagerung eines daraus gekochten Gerichts. Leer = nicht angegeben. Gehoert zu shelf_life_days." },
        own_cooking_factor: { type: ["number", "null"], description: "Eigener Garfaktor der Zutat: Gewicht gegart je Gramm roh, z. B. 2.4 fuer trockene Nudeln, 2.7 fuer Reis, 0.75 fuer Hackfleisch, 0.3 fuer Spinat. Aendert keine Naehrwertsumme (Wasser hat keine), nur das Portionsgewicht und alle Werte pro 100 g samt Ampel. Weglassen = unveraendert, null loescht (= keine Korrektur). Ein Produkt mit eigenem Faktor geht vor." },
        default_unit: { type: "string" },
        categories:   { type: "array", items: { type: "number" } },
        own_price_per_100g: { type: ["number", "null"], description: "Eigener Preis der Zutat je 100 g. null oder 0 löscht ihn. Hat die Zutat ein Referenzprodukt mit Packungspreis und -gewicht, gilt dessen Preis und dieser nur als Rückfall." },
        nutrition: {
          type: "object",
          description: "Nährwerte je 100 g. " + NUTRITION_NULL_NOTE,
          properties: INGREDIENT_NUTRITION_PROPS,
        },
      },
    },
  },

  {
    name: "mmm_update_ingredient_nutrition",
    description: "Nährwerte einer Zutat setzen, inkl. saturates (gesättigte Fettsäuren). " + NUTRITION_NULL_NOTE + " Für viele Zutaten auf einmal mmm_bulk_update verwenden. Verwendet /mixmymeals/v1/ingredients/{id}/nutrition.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
        ...INGREDIENT_NUTRITION_PROPS,
      },
    },
  },

  {
    name: "mmm_update_ingredient_price",
    description: "Eigenen Preis pro 100g einer Zutat setzen. Hat die Zutat ein Referenzprodukt mit Packungspreis und -gewicht, gilt dessen Preis und dieser nur als Rückfall — die Antwort nennt dann den aufgelösten Wert. Verwendet /mixmymeals/v1/ingredients/{id}/price.",
    inputSchema: {
      type: "object",
      required: ["id", "price_per_100g"],
      properties: {
        id:             { type: "number" },
        price_per_100g: { type: ["number", "null"], description: "null oder 0 löscht den Eigenpreis." },
      },
    },
  },

  // ── MixMyMeals: Einheiten ────────────────────────────────────────────────

  {
    name: "mmm_get_ingr_unit",
    description: "Einzelne Einheit abrufen (Gewicht, Verkaufseinheit, Teilbarkeit). Verwendet /mixmymeals/v1/units/{id}. divisor gibt die Teilbarkeit an: 1 = nur ganze, 2 = halbierbar, 0 = beliebige Menge. step_divisor ist derselbe Wert und wird für die Mengenrundung verwendet; is_indivisible ist die veraltete Kurzform für divisor === 1.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  {
    name: "mmm_create_ingr_unit",
    description: "Neue Einheit für eine Zutat anlegen (z.B. 'Dose', 'TL'). Verwendet /mixmymeals/v1/units.",
    inputSchema: {
      type: "object",
      required: ["ingredient_id", "title", "weight_g"],
      properties: {
        ingredient_id:  { type: "number", description: "Post-ID des mmm_ingredient" },
        title:          { type: "string", description: "Name der Einheit" },
        weight_g:       { type: "number", description: "Gewicht in g" },
        is_indivisible: { type: "boolean", default: false, description: "Veraltet — entspricht divisor 1 (true) bzw. 0 (false). Bevorzugt divisor verwenden; wird divisor mitgesendet, gewinnt divisor." },
        divisor:        { type: "number", default: 0, description: "Teilbarkeit der Einheit: 1 = nur ganze (z.B. ein Ei), 2 = halbierbar (z.B. eine Dose), 4 = Viertel. 0 oder weglassen = beliebige Menge. Dies ist die maßgebliche Angabe." },
      },
    },
  },

  {
    name: "mmm_update_ingr_unit",
    description: "Einheit einer Zutat aktualisieren (Gewicht, Teilbarkeit, Titel). Verwendet /mixmymeals/v1/units/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:             { type: "number", description: "Post-ID der mmm_ingr_unit" },
        title:          { type: "string" },
        weight_g:       { type: "number", description: "Gewicht in g" },
        is_indivisible: { type: "boolean", description: "Veraltet — entspricht divisor 1 (true) bzw. 0 (false). Bevorzugt divisor verwenden; wird divisor mitgesendet, gewinnt divisor." },
        divisor:        { type: "number", description: "Teilbarkeit der Einheit: 1 = nur ganze (z.B. ein Ei), 2 = halbierbar (z.B. eine Dose), 4 = Viertel. 0 oder weglassen = beliebige Menge. Dies ist die maßgebliche Angabe." },
      },
    },
  },

  // ── MixMyMeals: Produkte ─────────────────────────────────────────────────

  {
    name: "mmm_get_ingredient_products",
    description: "Alle Produkte einer Zutat abrufen. Verwendet /mixmymeals/v1/ingredients/{id}/products.",
    inputSchema: {
      type: "object",
      required: ["ingredient_id"],
      properties: { ingredient_id: { type: "number", description: "Post-ID des mmm_ingredient" } },
    },
  },

  {
    name: "mmm_get_product",
    description: "Einzelnes Produkt abrufen. Verwendet /mixmymeals/v1/products/{id}. is_reference sagt, ob die Zutat ihre Nährwerte über dieses Produkt auflöst.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: { id: { type: "number" } },
    },
  },

  {
    name: "mmm_create_product",
    description: "Produkt zu einer Zutat anlegen — ein konkreter Artikel, als den die Zutat gekauft werden kann (z.B. 'Sahnejoghurt 10%'). Die Zutat bleibt die Abstraktion mit Planungswerten; lohnt sich nur, wo Produkte stark abweichen (Joghurt, Hackfleisch, Käse, Brot). Verwendet /mixmymeals/v1/products.",
    inputSchema: {
      type: "object",
      required: ["ingredient_id", "title", "brand"],
      properties: {
        ingredient_id:  { type: "number", description: "Post-ID des mmm_ingredient" },
        title:          { type: "string", description: "Produktname" },
        brand:          { type: "string", description: "Marke oder Hersteller. Pflicht — zwei Produkte einer Zutat unterscheiden sich oft nur darüber, und ohne Marke laesst sich im Laden nichts wiederfinden." },
        package_weight: { type: "number", description: "Gewicht einer Packung in Gramm" },
        price:          { type: ["number", "null"], description: "Preis einer Packung in der Shop-Währung. Der Preis je 100 g ergibt sich daraus und aus package_weight — ohne Packungsgewicht wirkt der Preis nicht. Ist das Produkt die Referenz, ersetzt er den Preis der Zutat in allen Kostenrechnungen. null löscht ihn." },
        cooking_factor: { type: ["number", "null"], description: "Garfaktor dieses Artikels (gegart je Gramm wie gekauft), wo er von dem der Zutat abweicht — Schnellkochreis quillt weniger, frische Nudeln kaum. null/leer: es gilt der Faktor der Zutat." },
        source:         { type: "string", description: "Woher die Angaben stammen, z. B. 'Packung, 08/2026'. Diese Werte veralten — ohne Quelle unterscheidet später niemand eine aktuelle von einer alten Angabe." },
        nutrition:      { type: "object", description: "Nährwerte je 100 g als Objekt, z. B. { calories_100: 131, fat: 10 }. Erlaubte Schlüssel: calories_100, fat, saturates, carbs, protein, sugar, fiber, salt. Nur mitgesendete Schlüssel werden angefasst; null löscht einen Wert. Weglassen, was die Packung nicht angibt — leer ist NICHT dasselbe wie 0, und die Zutat liefert für fehlende Werte weiterhin ihre eigenen." },
        diet_type:      { type: "string", description: "Slug einer Ernaehrungsform der Gruppe 'diet' (z. B. vegan, vegetarisch; 'vegetarian' geht weiter). Einstufung dieses Artikels. Leer = nicht geprüft, dann gilt die Einstufung der Zutat. Sobald gesetzt, überschreibt sie diese — Nudeln mit Ei sind nicht vegan, auch wenn die Zutat es ist. Wirkt nur über die Referenz." },
        is_reference:   { type: "boolean", description: "Dieses Produkt zur Referenz der Zutat machen: die Zutat nutzt dann seine Werte, Wert für Wert — Nährwerte wie Einstufung." },
      },
    },
  },

  {
    name: "mmm_update_product",
    description: "Produkt aktualisieren. Verwendet /mixmymeals/v1/products/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:             { type: "number" },
        title:          { type: "string" },
        brand:          { type: "string" },
        package_weight: { type: "number" },
        price:          { type: ["number", "null"], description: "Preis einer Packung. Wirkt nur zusammen mit package_weight und nur, wenn das Produkt die Referenz ist. null löscht ihn." },
        cooking_factor: { type: ["number", "null"], description: "Garfaktor dieses Artikels (gegart je Gramm wie gekauft), wo er von dem der Zutat abweicht — Schnellkochreis quillt weniger, frische Nudeln kaum. null/leer: es gilt der Faktor der Zutat." },
        source:         { type: "string" },
        nutrition:      { type: "object", description: "Nährwerte je 100 g als Objekt, z. B. { calories_100: 131, fat: 10 }. Erlaubte Schlüssel: calories_100, fat, saturates, carbs, protein, sugar, fiber, salt. Nur mitgesendete Schlüssel werden angefasst; null löscht einen Wert. Weglassen, was die Packung nicht angibt — leer ist NICHT dasselbe wie 0, und die Zutat liefert für fehlende Werte weiterhin ihre eigenen." },
        diet_type:      { type: "string", description: "Slug einer Ernaehrungsform der Gruppe 'diet' (z. B. vegan, vegetarisch; 'vegetarian' geht weiter). Einstufung dieses Artikels. Leer loescht sie ganz — Terme und die alte Meta-Zeile —, dann gilt die der Zutat. Ein fehlender Schluessel aendert nichts." },
        is_reference:   { type: "boolean", description: "true macht es zur Referenz, false hebt die Referenz der Zutat auf." },
      },
    },
  },

  {
    name: "mmm_unit_usage",
    description: "Wo eine Einheit verwendet wird und ob sie gefahrlos geloescht werden kann. Liefert die Rezepte samt Zahl der betroffenen Zeilen, ob sie die Standardeinheit der Zutat ist, und can_delete. Wichtig: Eine benutzte Einheit zu loeschen laesst die Rezeptzeilen mit einer Menge ohne Einheit zurueck — und eine Menge ohne Einheit wird als Gramm gelesen. Aus '1,5 Dosen' werden dann 1,5 g, im Rezept, in jedem Plan und in der Einkaufsliste. Verwendet /mixmymeals/v1/units/{id}/usage.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number", description: "Post-ID der mmm_ingr_unit" },
      },
    },
  },

  {
    name: "mmm_delete_ingr_unit",
    description: "Einheit loeschen. Wird sie noch von Rezepten verwendet, lehnt der Endpunkt mit HTTP 409 ab und nennt die betroffenen Rezepte — erst force:true loescht trotzdem. Vorher mmm_unit_usage aufrufen, wenn unklar ist, was daran haengt. Verwendet /mixmymeals/v1/units/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:    { type: "number", description: "Post-ID der mmm_ingr_unit" },
        force: { type: "boolean", default: false, description: "Auch loeschen, wenn Rezepte sie verwenden. Deren Mengen werden danach als Gramm gelesen." },
      },
    },
  },

  {
    name: "mmm_delete_ingr_units_bulk",
    description: "Mehrere Einheiten auf einmal loeschen. Jede wird einzeln beurteilt: benutzte werden uebersprungen (status skipped_in_use) und mit den betroffenen Rezepten gemeldet, der Rest wird geloescht — die Liste faellt also nicht als Ganzes durch, nur weil eine Einheit noch haengt. Passt zur Gruppe units_unused aus mmm_audit: deren IDs lassen sich unveraendert uebergeben. WARNUNG zu force: eine benutzte Einheit zu loeschen laesst in den Rezeptzeilen Mengen ohne Einheit zurueck, und die werden als Gramm gelesen — aus 1,5 Dosen werden 1,5 g, im Rezept, in jedem Plan und in der Einkaufsliste. Vor jedem force erst dry_run:true aufrufen und affected_recipes ansehen. Verwendet /mixmymeals/v1/units/bulk-delete.",
    inputSchema: {
      type: "object",
      required: ["ids"],
      properties: {
        ids:     { type: "array", items: { type: "number" }, description: "Post-IDs der zu loeschenden mmm_ingr_unit" },
        force:   { type: "boolean", default: false, description: "Auch benutzte Einheiten loeschen. Zerstoerend — siehe Beschreibung." },
        dry_run: { type: "boolean", default: false, description: "Nichts loeschen, nur berichten was passieren wuerde. Status heisst dann would_delete." },
      },
    },
  },

  {
    name: "mmm_set_keeping",
    description: "Haltbarkeit und Lagerung fuer viele Rezepte UND Zutaten in einem Aufruf setzen. Gemischte Liste, der Typ wird je ID selbst ermittelt. Antwort ist bewusst schlank: id, type, ok — plus eine getrennte Fehlerliste. Bis 500 Eintraege pro Aufruf. WICHTIG: Ein weggelassener Schluessel laesst den gespeicherten Wert unveraendert; shelf_life_days:null bzw. storage_type:\"\" loeschen ihn. Wer nur Haltbarkeiten nachtraegt, laesst storage_type also weg, statt ihn leer mitzusenden. Erst mit dry_run:true pruefen, wenn die Liste aus einer Ableitung stammt. Verwendet /mixmymeals/v1/keeping/bulk.",
    inputSchema: {
      type: "object",
      required: ["items"],
      properties: {
        items: {
          type: "array",
          maxItems: 500,
          description: "Eintraege. id ist Pflicht, die beiden Felder sind einzeln optional.",
          items: {
            type: "object",
            required: ["id"],
            properties: {
              id: { type: "number", description: "Post-ID eines mmm_recipe oder mmm_ingredient" },
              shelf_life_days: { type: ["number", "null"], description: "Tage. 0 heisst \"am selben Tag verbrauchen\", null loescht, Weglassen laesst unveraendert." },
              storage_type: { type: "string", enum: ["room_temperature", "cool_dry", "refrigerated", "frozen", ""], description: "Leerer String loescht, Weglassen laesst unveraendert." },
            },
          },
        },
        dry_run: { type: "boolean", default: false, description: "Nichts schreiben, nur berichten was ginge." },
      },
    },
  },

  {
    name: "mmm_audit",
    description: "Datenlücken finden: Rezepte ohne Bild, Nährwerte, Kategorie oder Auszug; Zutaten ohne Nährwerte, Einheit oder Preis und ungenutzte; Plaene mit Platzhaltern oder zu wenig Energie. Rezepte mit nur teilweisen Naehrwerten (recipes_partial_nutrition: die Summe ist aus einem Teil des Gerichts gebaut, weil ein leeres Feld als 0 zaehlt. Feldweise gerechnet: figure nennt den schlechtesten der sieben Werte, covered dessen gedeckten Gewichtsanteil in Prozent — nicht den Durchschnitt, der mehr verspraeche als die Tabelle haelt —, und missing jede Zutat mit figures, den Werten, die ihr fehlen; gefaehrlicher als ganz fehlende Naehrwerte, weil die Zahl fertig aussieht und zu niedrig ist). Dazu ingredients_partial_nutrition: Zutaten, die einige der sieben Werte nennen und andere nicht. Sie duerfen veroeffentlicht sein — eine Packung, die Energie und Fett nennt und sonst nichts, ist eine echte Packung —, aber jedes leere Feld geht in jede Rezeptsumme dieses Werts als 0 ein. Jeder Eintrag nennt figures und die Zahl der Rezepte, die die Zutat verwenden; danach sortiert lohnt die Pflege zuerst. Nachtragen mit mmm_update_ingredient_nutrition. Dazu nutrition_implausible: Angaben, die sich widersprechen — gesaettigte Fettsaeuren ueber dem Gesamtfett, Zucker ueber den Kohlenhydraten, mehr als 100 g in 100 g, oder eine Energieangabe, die nicht aus den Makros folgt (9/4/4/2 kcal je g Fett/KH/Eiweiss/Ballaststoffe, ein Fuenftel Spielraum). Jeder Eintrag nennt kind (ingredient oder product), bei Produkten die zugehoerige Zutat, und warnings als fertige Saetze. Ein Tippfehler hier wandert in jedes Rezept, jeden Plan, jede Mail und das schema.org-Markup, und nachgelagert ist er von einem gehaltvollen Lebensmittel nicht zu unterscheiden. Fuer Produkte zwei eigene Gruppen: ingredients_products_without_reference (Produkte angelegt, aber keins ausgewaehlt — sie aendern dann nichts) und products_without_source (Angaben ohne Herkunft; sie veralten, wenn ein Hersteller die Rezeptur aendert). Dazu ingredients_without_diet: Zutaten, zu denen weder sie selbst noch eines ihrer Produkte eine Ernaehrungsform nennt. Eine einzige davon nimmt jedem Rezept, das sie verwendet, die Pille \"Geeignet fuer …\" — diese Gruppe entscheidet also, wie viele Rezepte ueberhaupt eine tragen koennen. Jeder Eintrag nennt Kategorie und die Zahl der Rezepte, die die Zutat verwenden: danach sortiert lohnt sich die Pflege zuerst. Zum Nachtragen mmm_set_diets, das viele auf einmal schreibt. Dazu units_unused: Einheiten, die kein Rezept verwendet — jeder Eintrag nennt Einheit und zugehoerige Zutat, denn ein blosses Dose sagt nichts. Sie lassen sich gefahrlos mit mmm_delete_ingr_unit entfernen. Die Antwort nennt plugin_version: Jede Gruppe, die diese Version kennt, ist immer enthalten, leere mit count 0. Fehlt ein Schluessel ganz, ist das Plugin auf dem Server aelter als diese Beschreibung — kein Fehler im Endpunkt. Verwendet /mixmymeals/v1/audit.",
    inputSchema: {
      type: "object",
      properties: {
        scope: { type: "string", enum: ["recipes", "plans", "ingredients", "all"], default: "all", description: "Was geprueft wird. Produktgruppen stecken in 'ingredients'." },
      },
    },
  },

  {
    name: "mmm_set_diets",
    description: "Ernaehrungsformen fuer viele Zutaten und Produkte in einem Aufruf setzen. Gegenstueck zur Audit-Gruppe ingredients_without_diet und aus demselben Grund gebaut wie mmm_set_keeping: die Einzelrouten antworten mit der kompletten Serialisierung, eine Bibliothek von 372 Zutaten kostet so 372 Aufrufe zu je rund 500 Token. Hier sind es id und ok je Eintrag plus eine getrennte Fehlerliste. diets ERSETZT immer alles, was an dem Post steht; ein leeres Array loescht. Bekannt ist jeder Term der Taxonomie mmm_diet (eingebaut: vegan, vegetarian, gluten_free, lactose_free). Ein Kindterm impliziert seine Eltern (vegan unter vegetarian), das muss man nicht zusaetzlich setzen. Unbekannte Werte weist der ganze Aufruf zurueck, bevor irgendetwas geschrieben wird; fehlende oder falsche IDs dagegen landen einzeln in errors (not_found, wrong_type, missing_id) und der Rest laeuft durch. WICHTIG fuer die Pillen: eine Zutat, zu der weder sie selbst noch eines ihrer Produkte etwas sagt, nimmt dem ganzen Rezept die Aussage — genau diese Zutaten listet das Audit. Verwendet /mixmymeals/v1/diets/bulk.",
    inputSchema: {
      type: "object",
      required: ["items"],
      properties: {
        items: {
          type: "array",
          maxItems: 500,
          description: "Je Eintrag { id, diets }. diets ersetzt den Bestand; ein leeres Array loescht die Einstufung ganz, samt der alten Meta-Angabe aus der Zeit vor der Taxonomie. Nur die Migration laesst diese Zeile stehen.",
          items: {
            type: "object",
            required: ["id", "diets"],
            properties: {
              id: { type: "number", description: "Post-ID einer mmm_ingredient oder eines mmm_product" },
              diets: { type: "array", items: { type: "string" }, description: "Term-Slugs der Taxonomie mmm_diet (eingebaut: vegan, vegetarian, gluten_free, lactose_free; weitere legt man als Term an). Leeres Array loescht alle Angaben." },
            },
          },
        },
      },
    },
  },

  {
    name: "mmm_migrate_diets",
    description: "Einmalige Umstellung der Ernaehrungsform von Meta auf die Taxonomie mmm_diet. Frueher hielt jede Zutat und jedes Produkt genau einen Wert in _mmm_diet_type bzw. _mmm_product_diet_type; jetzt sind es Terme, damit eine Zutat zugleich vegan und glutenfrei sein kann. Dieser Aufruf verschiebt die alten Werte. Bewusst kein Automatismus beim Update: die Einstufung entscheidet, ob oeffentlich \"vegan\" steht, und das soll jemand mitbekommen. Laeuft standardmaessig als Probelauf (dry_run:true) und aendert dann nichts — erst dry_run:false schreibt. Mehrfach aufrufbar: wer schon Terme hat, wird uebersprungen (skipped_has_terms), die alten Meta-Zeilen bleiben als Rueckweg liegen. Die Antwort nennt unknown_values fuer Werte, die keine bekannte Ernaehrungsform sind — die bleiben liegen und gehoeren angesehen. Verwendet /mixmymeals/v1/diets/migrate.",
    inputSchema: {
      type: "object",
      properties: {
        dry_run: { type: "boolean", default: true, description: "true zeigt nur, was passieren wuerde. Erst false schreibt." },
        limit: { type: "number", default: 200, minimum: 1, maximum: 1000, description: "Hoechstzahl je Aufruf. remaining in der Antwort sagt, ob noch etwas offen ist." },
      },
    },
  },

  {
    name: "mmm_product_impact",
    description: "Was die Wahl eines anderen Produkts ausmacht — fuer ein Rezept, eine Mahlzeit oder einen ganzen Plan. Liefert je Zutat mit mehreren Produkten eine Ankerspalte mit Absolutwerten und je Produkt die Abweichung, dazu die Einstufung vegan/vegetarisch. Ein Rezept rechnet je Portion, ein Plan je Tag und Person; eine Mahlzeit wird auf ihr Rezept aufgeloest. Werte, die ein Produkt nicht angibt, sind null und nehmen an nichts teil. Verwendet /mixmymeals/v1/product-impact.",
    inputSchema: {
      type: "object",
      required: ["post_id"],
      properties: {
        post_id: { type: "number", description: "Post-ID eines mmm_recipe, mmm_meal oder mmm_meal_plan" },
      },
    },
  },

  {
    name: "mmm_delete_product",
    description: "Produkt endgültig löschen (kein Papierkorb). War es die Referenz der Zutat, fällt diese automatisch weg — die Zutat nutzt dann wieder ihre eigenen Werte. Rezeptzeilen, die genau dieses Produkt festgelegt hatten, fallen auf die Referenz bzw. die Zutat zurück. Verwendet /mixmymeals/v1/products/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: { id: { type: "number" } },
    },
  },

  {
    name: "mmm_bulk_update",
    description: "Nährwerte, Preis, Ernährungsform und Garfaktor für bis zu 100 Zutaten und Produkte in einem Aufruf korrigieren. Antwort bewusst schlank: je Eintrag id, title und changes — nur die tatsächlich geänderten Felder als [alt, neu]; Fehler (not_found, wrong_type, forbidden, no_fields, no_package_weight, weight_item_not_on_product) stehen getrennt in errors, der Rest läuft durch. Keine vollen Entitäten. Geschrieben wird bei Zutaten immer der EIGENWERT. Gibt das Referenzprodukt denselben Wert selbst an, gewinnt dessen Wert weiter — solche Felder nennt shadowed, damit eine scheinbar wirkungslose Änderung erklärt ist. " + NUTRITION_NULL_NOTE + " price_per_100g: bei Zutaten der eigene Preis (null/0 löscht), bei Produkten wird daraus der Packungspreis berechnet — das braucht ein Packungsgewicht. Unbekannte Schlüssel (Tippfehler wie \"saturated\") oder unbekannte diet_type-Werte weisen den GANZEN Aufruf ab, bevor etwas geschrieben wird. dry_run:true liefert nur die Diffs. Verwendet /mixmymeals/v1/bulk-update.",
    inputSchema: {
      type: "object",
      required: ["items"],
      properties: {
        items: {
          type: "array",
          maxItems: 100,
          items: {
            type: "object",
            required: ["type", "id"],
            additionalProperties: false,
            properties: {
              type:           { type: "string", enum: ["ingredient", "product"] },
              id:             { type: "number", description: "Post-ID der Zutat bzw. des Produkts" },
              nutrition:      { type: "object", additionalProperties: false, description: "Nur mitgesendete Schlüssel werden angefasst. weight_item nur bei Zutaten.", properties: INGREDIENT_NUTRITION_PROPS },
              price_per_100g: { type: ["number", "null"], description: "Preis je 100 g. null oder 0 löscht." },
              diet_type:      { type: "string", description: "Slug einer Ernährungsform der Gruppe 'diet' (z. B. vegan, vegetarisch; 'vegetarian' geht weiter). Leer löscht die Einstufung." },
              cooking_factor: { type: ["number", "null"], description: "Garfaktor: Gewicht gegart je Gramm roh bzw. wie gekauft (Nudeln ~2.4, Reis ~2.7, Linsen ~2.5, Hackfleisch ~0.75, Spinat ~0.3). Bei Zutaten der Eigenwert, bei Produkten der des Artikels. Weglassen = unverändert, null löscht (Zutat: keine Korrektur mehr; Produkt: es gilt wieder der Faktor der Zutat). Werte ausserhalb von >0 bis 20 weisen den GANZEN Aufruf ab. Hat das Referenzprodukt einen eigenen Faktor, steht cooking_factor bei der Zutat unter shadowed." },
            },
          },
        },
        dry_run: { type: "boolean", default: false, description: "Nichts schreiben, nur die Diffs zurückgeben." },
      },
    },
  },

  {
    name: "mmm_list_missing",
    description: "Lückenfinder: Zutaten oder Produkte, denen ein Feld fehlt — seitenweise und schlank (id, title, own_value, inherited_value, reference_product; bei Produkten zusätzlich ingredient_id). Keine Einheiten, keine Kategorien. Bei Zutaten fragt mode 'effective' (Standard), was der Seite tatsächlich fehlt — Referenzprodukt eingerechnet; mode 'own' listet alles, was die Zutat selbst nicht angibt, auch wenn das Referenzprodukt es liefert (so findet man alte 0-Eigenwerte, die man mit null in mmm_bulk_update aufräumen kann). Bei Produkten zählt nur der eigene Wert; inherited_value ist dann der Eigenwert der Zutat. zero_is_missing (Standard true) zählt 0 als Lücke, weil ältere Versionen leere Felder als 0 gespeichert haben — für Ballaststoffe oder Salz, wo 0 oft stimmt, auf false setzen. Verwendet /mixmymeals/v1/missing.",
    inputSchema: {
      type: "object",
      required: ["field"],
      properties: {
        field:           { type: "string", enum: ["calories_100", "fat", "saturates", "carbs", "sugar", "fiber", "protein", "salt", "price_per_100g", "diet_type", "cooking_factor"], description: "cooking_factor: mode effective findet Zutaten, für die gar kein Garfaktor gilt (weder eigener noch über das Referenzprodukt); mode own die ohne eigenen. Bei Produkten: ohne eigenen Faktor (dann gilt der der Zutat, inherited_value)." },
        type:            { type: "string", enum: ["ingredient", "product"], default: "ingredient" },
        mode:            { type: "string", enum: ["effective", "own"], default: "effective", description: "Nur für Zutaten, siehe Beschreibung." },
        zero_is_missing: { type: "boolean", default: true },
        page:            { type: "number", default: 1 },
        per_page:        { type: "number", default: 50, description: "Höchstens 200." },
      },
    },
  },

  {
    name: "mmm_merge_ingredients",
    description: "Zwei Zutaten zusammenführen: alle Rezeptzeilen von remove_id werden auf keep_id umgehängt, die Produkte von remove_id ziehen mit (festgelegte und ausgeschlossene Produkte in Rezepten bleiben damit gültig), danach wird remove_id samt Einheiten endgültig gelöscht. Einheiten werden über den Titel zugeordnet (\"EL\" → \"EL\"); fehlt beim Ziel eine benutzte Einheit, bricht der Vorgang ohne Änderung ab und nennt sie unter conflicts — dann erst die Einheit bei keep_id anlegen. Weicht das Gewicht einer zugeordneten Einheit ab, steht das unter unit_mapping[].weight_changes. Das Referenzprodukt von remove_id wird NICHT übernommen. Immer zuerst mit dry_run:true aufrufen. Verwendet /mixmymeals/v1/ingredients/{remove_id}/merge.",
    inputSchema: {
      type: "object",
      required: ["keep_id", "remove_id"],
      properties: {
        keep_id:   { type: "number", description: "Zutat, die bleibt" },
        remove_id: { type: "number", description: "Zutat, die aufgeht und gelöscht wird" },
        dry_run:   { type: "boolean", default: false, description: "Nichts ändern, nur berichten, was passieren würde." },
      },
    },
  },

  // ── MixMyMeals: Meal Plans ───────────────────────────────────────────────

  {
    name: "mmm_get_meal_plans",
    description: "Alle veröffentlichten Meal Plans abrufen. Verwendet /mixmymeals/v1/meal-plans.",
    inputSchema: {
      type: "object",
      properties: {},
    },
  },

  {
    name: "mmm_get_meal_plan",
    description: "Meal Plan mit allen Mahlzeiten abrufen. Verwendet /mixmymeals/v1/meal-plans/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  {
    name: "mmm_create_meal_plan",
    description: "Neuen Meal Plan anlegen. Verwendet /mixmymeals/v1/meal-plans.",
    inputSchema: {
      type: "object",
      required: ["title"],
      properties: {
        title:      { type: "string" },
        status:     { type: "string", default: "draft" },
        content:    { type: "string" },
        excerpt:    { type: "string" },
        start_day:  { type: "number", description: "1=Mo … 7=So", default: 1 },
        end_day:    { type: "number", description: "1=Mo … 7=So", default: 7 },
        categories: { type: "array", items: { type: "number" } },
        tags:       { type: "array", items: { type: "number" } },
        author:     AUTHOR_SCHEMA,
        featured_image: { type: "number", description: "Attachment-ID des Beitragsbilds, 0 entfernt es. Neue Bilder vorher mit mmm_upload_image hochladen." },
        image_credit:   IMAGE_CREDIT_SCHEMA,
      },
    },
  },

  {
    name: "mmm_update_meal_plan",
    description: "Meal Plan aktualisieren. Verwendet /mixmymeals/v1/meal-plans/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:        { type: "number" },
        title:     { type: "string" },
        status:    { type: "string" },
        content:   { type: "string" },
        excerpt:   { type: "string" },
        start_day: { type: "number" },
        end_day:   { type: "number" },
        author:    AUTHOR_SCHEMA,
        featured_image: { type: "number", description: "Attachment-ID des Beitragsbilds, 0 entfernt es. Neue Bilder vorher mit mmm_upload_image hochladen." },
        image_credit:   IMAGE_CREDIT_SCHEMA,
      },
    },
  },

  {
    name: "mmm_upload_image",
    description: "Bild in die Mediathek laden und optional als Beitragsbild eines Rezepts oder Meal Plans setzen. Quelle: entweder file (lokaler Pfad auf DIESEM Rechner) oder url (der Server lädt sie selbst) oder attachment_id (Bild liegt schon in der Mediathek). Gibt id, url und Maße zurück; die id ist überall verwendbar, wo Attachment-IDs erwartet werden (steps[].image, author.image). Verwendet POST /mixmymeals/v1/media.",
    inputSchema: {
      type: "object",
      properties: {
        file:          { type: "string", description: "Absoluter oder relativer Pfad zur Bilddatei auf dem Rechner, auf dem dieser MCP-Server läuft." },
        url:           { type: "string", description: "Alternativ: Bild-URL, die WordPress selbst herunterlädt." },
        attachment_id: { type: "number", description: "Alternativ: bereits vorhandenes Bild aus der Mediathek." },
        post_id:       { type: "number", description: "Rezept, Plan oder Equipment, zu dem das Bild gehört (wird Parent des Attachments)." },
        featured:      { type: "boolean", default: false, description: "Bild als Beitragsbild von post_id setzen." },
        title:         { type: "string", description: "Titel in der Mediathek." },
        alt:           { type: "string", description: "Alt-Text. Für Barrierefreiheit und SEO empfohlen." },
      },
    },
  },

  {
    name: "mmm_get_author",
    description: "Autorenbox eines Rezepts oder Meal Plans abrufen. is_external=false heißt: der Beitrag zeigt seinen WordPress-Autor. link_text_display ist die tatsächlich gerenderte Link-Beschriftung (Domain, wenn link_text leer ist), image_url die Vorschau-URL des Profilbilds (nur lesend). Verwendet GET /mixmymeals/v1/{recipes|meal-plans}/{id}/author.",
    inputSchema: {
      type: "object",
      required: ["type", "id"],
      properties: {
        type: { type: "string", enum: ["recipe", "meal_plan"] },
        id:   { type: "number" },
      },
    },
  },

  {
    name: "mmm_set_author",
    description: "Externen Autor eines Rezepts oder Meal Plans pflegen. Nur übergebene Felder werden geändert, \"\" löscht ein einzelnes Feld. clear=true löscht die ganze Box, der Beitrag zeigt dann wieder seinen WordPress-Autor. Verwendet POST bzw. DELETE /mixmymeals/v1/{recipes|meal-plans}/{id}/author.",
    inputSchema: {
      type: "object",
      required: ["type", "id"],
      properties: {
        type:  { type: "string", enum: ["recipe", "meal_plan"] },
        id:    { type: "number" },
        ...AUTHOR_FIELDS,
        clear: { type: "boolean", description: "Ganze Autorenbox löschen (alle anderen Felder werden ignoriert)." },
      },
    },
  },

  {
    name: "mmm_get_shopping_list",
    description: "Berechnete Einkaufsliste für einen Meal Plan, gruppiert nach Kategorie. Verwendet /mixmymeals/v1/meal-plans/{id}/shopping-list.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  // ── MixMyMeals: Planung (KI-Komfort) ─────────────────────────────────────

  {
    name: "mmm_find_recipes",
    description: "Rezepte nach planungsrelevanten Kriterien suchen und als kompakte Liste (Nährwerte/Portion, Diät-Typ, Zubereitungszeit, Kategorien) zurückgeben – ideal, um für einen Plan passende Rezepte auszuwählen, ohne jedes Rezept einzeln zu laden. Verwendet GET /mixmymeals/v1/recipes/search.",
    inputSchema: {
      type: "object",
      properties: {
        search:       { type: "string", description: "Volltext im Titel (Teilstring)." },
        diet:         { type: "string", description: "Nur Rezepte dieses Diät-Typs: Slug einer Ernaehrungsform der Gruppe 'diet' (z. B. vegan, vegetarisch; 'vegetarian' geht weiter)." },
        category:     { type: "number", description: "mmm_recipe_category Term-ID." },
        tag:          { type: "number", description: "mmm_recipe_tag Term-ID." },
        min_calories: { type: "number", description: "Mindest-kcal pro Portion." },
        max_calories: { type: "number", description: "Höchst-kcal pro Portion." },
        max_prep:     { type: "number", description: "Maximale Zubereitungszeit in Minuten." },
        per_page:     { type: "number", default: 50, description: "Maximale Trefferzahl (1–100)." },
      },
    },
  },

  {
    name: "mmm_build_meal_plan",
    description: "Kompletten Meal Plan inkl. aller Mahlzeiten in EINEM Aufruf anlegen. Rezepte je Mahlzeit per recipe_id ODER per Name/Slug (recipe). Fehlen start_day/end_day, werden sie aus den Tagen der Mahlzeiten abgeleitet. Unbekannte Rezepte werden übersprungen und in warnings gemeldet (der Plan schlägt nicht fehl). Verwendet POST /mixmymeals/v1/meal-plans/build.",
    inputSchema: {
      type: "object",
      required: ["title"],
      properties: {
        title:      { type: "string" },
        status:     { type: "string", default: "draft", description: "publish | draft | pending | private" },
        content:    { type: "string" },
        excerpt:    { type: "string" },
        start_day:  { type: "number", description: "1=Mo … 7=So (optional, sonst aus meals abgeleitet)" },
        end_day:    { type: "number", description: "1=Mo … 7=So (optional, sonst aus meals abgeleitet)" },
        categories: { type: "array", items: { type: "number" } },
        tags:       { type: "array", items: { type: "number" } },
        author:     AUTHOR_SCHEMA,
        featured_image: { type: "number", description: "Attachment-ID des Beitragsbilds, 0 entfernt es. Neue Bilder vorher mit mmm_upload_image hochladen." },
        image_credit:   IMAGE_CREDIT_SCHEMA,
        meals: {
          type: "array",
          description: "Mahlzeiten des Plans.",
          items: {
            type: "object",
            properties: {
              recipe_id: { type: "number", description: "Post-ID des Rezepts (Vorrang vor recipe)." },
              recipe:    { type: "string", description: "Alternativ: Rezeptname oder Slug (exakt, sonst Teiltreffer)." },
              servings:  { type: "number", default: 1 },
              type:      { type: "string", enum: ["breakfast", "lunch", "dinner", "snack", "other"] },
              day:       { type: "number", description: "1=Mo … 7=So" },
              status:    { type: "string", enum: ["publish", "draft"], default: "publish" },
              title:     { type: "string", description: "Optional; leer = Rezepttitel." },
              text:      { type: "string", description: "Kurzer Text zu dieser Einplanung; gehört der Mahlzeit, nicht dem Rezept." },
              diet:      { type: "string", description: "Ernährungsform dieser Mahlzeit, nur bei Rezepten mit Wahl (diet_options der Mahlzeit, z. B. \"_none\" = Originalrezept, wie geschrieben, \"vegetarian\", \"vegan\"): jede Zutat wird dann mit einem passenden Produkt gerechnet und eingekauft. Eine Mahlzeit mit Wahl ist immer festgelegt — ohne Angabe oder bei ungültiger Angabe auf die erste Option (das Rezept wie geschrieben), mit warning." },
            },
          },
        },
      },
    },
  },

  {
    name: "mmm_add_meals",
    description: "Mehrere Mahlzeiten in EINEM Aufruf zu einem bestehenden Meal Plan hinzufügen (gleiches meals-Format wie mmm_build_meal_plan). Gibt den aktualisierten Plan und etwaige warnings zurück. Verwendet POST /mixmymeals/v1/meal-plans/{id}/meals/bulk.",
    inputSchema: {
      type: "object",
      required: ["id", "meals"],
      properties: {
        id: { type: "number", description: "Meal-Plan-Post-ID." },
        meals: {
          type: "array",
          items: {
            type: "object",
            properties: {
              recipe_id: { type: "number" },
              recipe:    { type: "string" },
              servings:  { type: "number", default: 1 },
              type:      { type: "string", enum: ["breakfast", "lunch", "dinner", "snack", "other"] },
              day:       { type: "number" },
              status:    { type: "string", enum: ["publish", "draft"], default: "publish" },
              title:     { type: "string" },
              text:      { type: "string", description: "Kurzer Text zu dieser Einplanung; gehört der Mahlzeit, nicht dem Rezept." },
              diet:      { type: "string", description: "Ernährungsform dieser Mahlzeit, nur bei Rezepten mit Wahl (diet_options der Mahlzeit, z. B. \"_none\" = Originalrezept, wie geschrieben, \"vegetarian\", \"vegan\"): jede Zutat wird dann mit einem passenden Produkt gerechnet und eingekauft. Eine Mahlzeit mit Wahl ist immer festgelegt — ohne Angabe oder bei ungültiger Angabe auf die erste Option (das Rezept wie geschrieben), mit warning." },
            },
          },
        },
      },
    },
  },

  // ── MixMyMeals: Mahlzeiten ───────────────────────────────────────────────

  {
    name: "mmm_add_meal",
    description: "Mahlzeit zu einem Meal Plan hinzufügen. Verwendet /mixmymeals/v1/meals.",
    inputSchema: {
      type: "object",
      required: ["plan_id", "recipe_id"],
      properties: {
        plan_id:   { type: "number" },
        recipe_id: { type: "number" },
        title:     { type: "string", description: "Leer = wird aus Rezepttitel abgeleitet" },
        servings:  { type: "number", default: 1 },
        type:      { type: "string", description: "breakfast | lunch | dinner | snack | other" },
        day:       { type: "number", description: "1=Mo … 7=So" },
        status:    { type: "string", default: "publish" },
      },
    },
  },

  {
    name: "mmm_get_meal",
    description: "Einzelne Mahlzeit abrufen. Verwendet /mixmymeals/v1/meals/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  {
    name: "mmm_update_meal",
    description: "Mahlzeit aktualisieren. Verwendet /mixmymeals/v1/meals/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:        { type: "number" },
        recipe_id: { type: "number" },
        servings:  { type: "number" },
        type:      { type: "string" },
        day:       { type: "number" },
        status:    { type: "string" },
        title:     { type: "string" },
        text:      { type: "string", description: "Kurzer Text zu DIESER Einplanung (z. B. \"Doppelte Menge kochen, Hälfte einfrieren\"). Gehört der Mahlzeit, nicht dem Rezept; leer = nichts gesagt." },
        diet:      { type: "string", description: "Ernährungsform dieser Einplanung — eine der diet_options der Mahlzeit (\"_none\" = Originalrezept, wie geschrieben, sonst ein Diät-Slug wie \"vegan\"). Nur Rezepte, die mehrere Formen anbieten, haben Optionen, und dann ist die Mahlzeit immer festgelegt: ein ungültiger Wert setzt die erste Option. Die Mahlzeit wird mit den passenden Produkten gerechnet, die Einkaufsliste führt sie als eigene Zeilen." },
      },
    },
  },

  // ── MixMyMeals: Instagram ────────────────────────────────────────────────

  {
    name: "mmm_instagram_status",
    description: "Instagram-Status für einen Plan oder ein Rezept: Verbindung (Handle/Fehler), Anzahl Slides, bereits generierte Bilder, geplanter Zeitpunkt, letztes Ergebnis und die Standard-Caption. Verwendet GET /mixmymeals/v1/instagram/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number", description: "Post-ID eines mmm_meal_plan oder mmm_recipe" },
      },
    },
  },

  {
    name: "mmm_instagram_generate",
    description: "Instagram-Bilder neu generieren – Plan = Karussell (mehrere Slides), Rezept = Einzelbild. Verwendet POST /mixmymeals/v1/instagram/{id}/generate.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  {
    name: "mmm_instagram_publish",
    description: "Sofort auf Instagram posten. Standardmäßig werden die Bilder vorher frisch generiert. Ohne caption wird die Standard-Caption verwendet (Plan-Übersicht bzw. Rezept-Caption). Erfordert konfigurierten Access Token UND öffentlich erreichbare Bild-URLs – funktioniert daher nur auf der Live-Site. Verwendet POST /mixmymeals/v1/instagram/{id}/publish.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:         { type: "number" },
        caption:    { type: "string", description: "Optional. Leer = Standard-Caption." },
        regenerate: { type: "boolean", default: true, description: "Bilder vor dem Posten neu erzeugen." },
        budget:     { type: "number", default: 120, description: "Max. Sekunden Wartezeit auf Instagrams Bildverarbeitung." },
      },
    },
  },

  {
    name: "mmm_instagram_schedule",
    description: "Instagram-Post terminieren. Zum geplanten Zeitpunkt werden die Bilder automatisch neu generiert und veröffentlicht (per WP-Cron; die genaue Minute kann bei wenig Traffic leicht abweichen). Verwendet POST /mixmymeals/v1/instagram/{id}/schedule.",
    inputSchema: {
      type: "object",
      required: ["id", "when"],
      properties: {
        id:      { type: "number" },
        when:    { type: "string", description: "Zeitpunkt in Website-Zeitzone, z.B. '2026-07-15T18:00' oder ISO-8601. Muss in der Zukunft liegen." },
        caption: { type: "string", description: "Optional. Leer = Standard-Caption zum Planungszeitpunkt." },
      },
    },
  },

  {
    name: "mmm_instagram_unschedule",
    description: "Einen geplanten Instagram-Post abbrechen. Verwendet POST /mixmymeals/v1/instagram/{id}/unschedule.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

  // ── MixMyMeals: Pinterest ────────────────────────────────────────────────

  {
    name: "mmm_pinterest_status",
    description: "Pinterest-Status für einen Plan oder ein Rezept: Verbindung, Board, Standard-Titel/-Beschreibung, Link, geplanter Zeitpunkt und letztes Ergebnis. Verwendet GET /mixmymeals/v1/pinterest/{id}.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number", description: "Post-ID eines mmm_meal_plan oder mmm_recipe" },
      },
    },
  },

  {
    name: "mmm_pinterest_publish",
    description: "Sofort auf Pinterest pinnen. Das Bild wird frisch generiert (Rezept-Bild bzw. Plan-Cover/-Übersicht), der Pin verlinkt auf die Plan-/Rezeptseite. Ohne title/description werden die Standardwerte verwendet. Erfordert Access Token + Board-ID und öffentlich erreichbare Bild-URLs (nur Live-Site). Verwendet POST /mixmymeals/v1/pinterest/{id}/publish.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id:          { type: "number" },
        title:       { type: "string", description: "Optional, max. 100 Zeichen. Leer = Beitragstitel." },
        description: { type: "string", description: "Optional, max. 800 Zeichen. Leer = Auszug/Inhalt." },
      },
    },
  },

  {
    name: "mmm_pinterest_schedule",
    description: "Pinterest-Pin terminieren. Zum geplanten Zeitpunkt wird das Bild neu generiert und der Pin erstellt (per WP-Cron). Verwendet POST /mixmymeals/v1/pinterest/{id}/schedule.",
    inputSchema: {
      type: "object",
      required: ["id", "when"],
      properties: {
        id:          { type: "number" },
        when:        { type: "string", description: "Zeitpunkt in Website-Zeitzone, z.B. '2026-07-15T18:00'. Muss in der Zukunft liegen." },
        title:       { type: "string", description: "Optional. Leer = Beitragstitel." },
        description: { type: "string", description: "Optional. Leer = Auszug/Inhalt." },
      },
    },
  },

  {
    name: "mmm_pinterest_unschedule",
    description: "Einen geplanten Pinterest-Pin abbrechen. Verwendet POST /mixmymeals/v1/pinterest/{id}/unschedule.",
    inputSchema: {
      type: "object",
      required: ["id"],
      properties: {
        id: { type: "number" },
      },
    },
  },

];

// ── Tool-Handler ─────────────────────────────────────────────────────────────

async function handleTool(name, args) {
  switch (name) {

    // ── Generische WP-Tools ────────────────────────────────────────────────

    case "wp_get_posts": {
      const { post_type = "post", per_page = 20, page = 1, search, status = "publish", parent, orderby, order, include } = args;
      const params = new URLSearchParams({ per_page, page, status });
      if (search  !== undefined) params.set("search",  search);
      if (parent  !== undefined) params.set("parent",  parent);
      if (orderby !== undefined) params.set("orderby", orderby);
      if (order   !== undefined) params.set("order",   order);
      if (include !== undefined) params.set("include", include);
      return wp("GET", `/${post_type}?${params}`, undefined, true);
    }

    case "wp_get_post": {
      const { id, post_type } = args;
      return wp("GET", `/${post_type}/${id}`);
    }

    case "wp_create_post": {
      const { post_type, title, content = "", status = "publish", parent, meta } = args;
      const body = { title, content, status };
      if (parent !== undefined) body.parent = parent;
      if (meta)                 body.meta   = meta;
      return wp("POST", `/${post_type}`, body);
    }

    case "wp_update_post": {
      const { id, post_type, title, content, status, parent, meta } = args;
      const body = {};
      if (title   !== undefined) body.title   = title;
      if (content !== undefined) body.content = content;
      if (status  !== undefined) body.status  = status;
      if (parent  !== undefined) body.parent  = parent;
      if (meta    !== undefined) body.meta    = meta;
      return wp("POST", `/${post_type}/${id}`, body);
    }

    case "wp_delete_post": {
      const { id, post_type, force = false } = args;
      return wp("DELETE", `/${post_type}/${id}?force=${force}`);
    }

    case "wp_get_terms": {
      const { taxonomy, post_id, per_page = 100, search, hide_empty = false } = args;
      const params = new URLSearchParams({ per_page, hide_empty });
      if (search  !== undefined) params.set("search", search);
      if (post_id !== undefined) params.set("post",   post_id);
      return request("GET", `${WP2}/${taxonomy}?${params}`);
    }

    case "wp_create_term": {
      const { taxonomy, name, slug, description, parent } = args;
      const body = { name };
      if (slug)        body.slug        = slug;
      if (description) body.description = description;
      if (parent)      body.parent      = parent;
      return request("POST", `${WP2}/${taxonomy}`, body);
    }

    case "wp_update_term": {
      const { taxonomy, id, name, slug, description, parent } = args;
      const body = {};
      if (name        !== undefined) body.name        = name;
      if (slug        !== undefined) body.slug        = slug;
      if (description !== undefined) body.description = description;
      if (parent      !== undefined) body.parent      = parent;
      return request("POST", `${WP2}/${taxonomy}/${id}`, body);
    }

    case "wp_set_post_terms": {
      const { id, post_type, taxonomy, term_ids } = args;
      return wp("POST", `/${post_type}/${id}`, { [taxonomy]: term_ids });
    }

    // ── MixMyMeals: Rezepte ────────────────────────────────────────────────

    case "mmm_get_recipes": {
      const { status = "publish" } = args;
      return mmm("GET", `/recipes?status=${status}`);
    }

    case "mmm_get_recipe":
      return mmm("GET", `/recipes/${args.id}`);

    case "mmm_create_recipe": {
      const { title, excerpt, status = "draft", servings, preparation_time, shelf_life_days, storage_type, priority, equipment, related_recipes, categories, tags, steps, author, featured_image, image_credit } = args;
      const body = { title, status };
      if (author           !== undefined) body.author           = author;
      if (featured_image   !== undefined) body.featured_image   = featured_image;
      if (image_credit     !== undefined) body.image_credit     = image_credit;
      if (excerpt          !== undefined) body.excerpt          = excerpt;
      if (servings         !== undefined) body.servings         = servings;
      if (preparation_time !== undefined) body.preparation_time = preparation_time;
      if (shelf_life_days !== undefined) body.shelf_life_days = shelf_life_days;
      if (storage_type !== undefined) body.storage_type = storage_type;
      if (priority         !== undefined) body.priority         = priority;
      if (equipment        !== undefined) body.equipment        = equipment;
      if (related_recipes  !== undefined) body.related_recipes  = related_recipes;
      if (categories       !== undefined) body.categories       = categories;
      if (tags             !== undefined) body.tags             = tags;
      if (steps            !== undefined) body.steps            = steps;
      return mmm("POST", "/recipes", body);
    }

    case "mmm_update_recipe": {
      const { id, ...fields } = args;
      const body = {};
      const allowed = ["title", "excerpt", "status", "servings", "preparation_time", "shelf_life_days", "storage_type", "priority", "equipment", "related_recipes", "categories", "tags", "steps", "author", "featured_image", "image_credit"];
      for (const key of allowed) {
        if (fields[key] !== undefined) body[key] = fields[key];
      }
      return mmm("POST", `/recipes/${id}`, body);
    }

    case "mmm_get_recipe_nutrition":
      return mmm("GET", `/recipes/${args.id}/nutrition`);

    case "mmm_get_recipe_ingredients": {
      const { id, scale = 1.0 } = args;
      return mmm("GET", `/recipes/${id}/ingredients?scale=${scale}`);
    }

    // ── MixMyMeals: Zutaten ────────────────────────────────────────────────

    case "mmm_get_ingredients":
      return mmm("GET", "/ingredients");

    case "mmm_get_ingredient":
      return mmm("GET", `/ingredients/${args.id}`);

    case "mmm_create_ingredient": {
      const { title, status = "publish", diet_type, shelf_life_days, storage_type, own_cooking_factor, default_unit, categories, nutrition } = args;
      const body = { title, status };
      if (own_cooking_factor !== undefined) body.own_cooking_factor = own_cooking_factor;
      if (diet_type    !== undefined) body.diet_type    = diet_type;
      if (shelf_life_days !== undefined) body.shelf_life_days = shelf_life_days;
      if (storage_type !== undefined) body.storage_type = storage_type;
      if (default_unit !== undefined) body.default_unit = default_unit;
      if (categories   !== undefined) body.categories   = categories;
      if (nutrition    !== undefined) body.nutrition    = nutrition;
      return mmm("POST", "/ingredients", body);
    }

    case "mmm_update_ingredient": {
      const { id, title, diet_type, shelf_life_days, storage_type, own_cooking_factor, default_unit, categories, nutrition, own_price_per_100g } = args;
      const body = {};
      if (own_cooking_factor !== undefined) body.own_cooking_factor = own_cooking_factor;
      if (own_price_per_100g !== undefined) body.own_price_per_100g = own_price_per_100g;
      if (title        !== undefined) body.title        = title;
      if (diet_type    !== undefined) body.diet_type    = diet_type;
      if (shelf_life_days !== undefined) body.shelf_life_days = shelf_life_days;
      if (storage_type !== undefined) body.storage_type = storage_type;
      if (default_unit !== undefined) body.default_unit = default_unit;
      if (categories   !== undefined) body.categories   = categories;
      if (nutrition    !== undefined) body.nutrition    = nutrition;
      return mmm("POST", `/ingredients/${id}`, body);
    }

    case "mmm_update_ingredient_nutrition": {
      const { id, ...fields } = args;
      const body = {};
      const allowed = ["calories_100", "fat", "saturates", "carbs", "protein", "sugar", "fiber", "salt", "weight_item"];
      for (const key of allowed) {
        if (fields[key] !== undefined) body[key] = fields[key];
      }
      return mmm("POST", `/ingredients/${id}/nutrition`, body);
    }

    case "mmm_update_ingredient_price":
      return mmm("POST", `/ingredients/${args.id}/price`, { price_per_100g: args.price_per_100g });

    // ── MixMyMeals: Einheiten ──────────────────────────────────────────────

    case "mmm_get_ingredient_products":
      return mmm("GET", `/ingredients/${args.ingredient_id}/products`);

    case "mmm_get_product":
      return mmm("GET", `/products/${args.id}`);

    case "mmm_create_product": {
      const { ingredient_id, title, brand, package_weight, price, cooking_factor, source, diet_type, nutrition, is_reference } = args;
      const body = { ingredient_id, title };
      if (cooking_factor !== undefined) body.cooking_factor = cooking_factor;
      if (brand !== undefined)          body.brand = brand;
      if (package_weight !== undefined) body.package_weight = package_weight;
      if (price !== undefined)          body.price = price;
      if (source !== undefined)         body.source = source;
      if (diet_type !== undefined)      body.diet_type = diet_type;
      if (nutrition !== undefined)      body.nutrition = nutrition;
      if (is_reference !== undefined)   body.is_reference = is_reference;
      return mmm("POST", "/products", body);
    }

    case "mmm_update_product": {
      const { id, title, brand, package_weight, price, cooking_factor, source, diet_type, nutrition, is_reference } = args;
      const body = {};
      if (cooking_factor !== undefined) body.cooking_factor = cooking_factor;
      if (title !== undefined)          body.title = title;
      if (brand !== undefined)          body.brand = brand;
      if (package_weight !== undefined) body.package_weight = package_weight;
      if (price !== undefined)          body.price = price;
      if (source !== undefined)         body.source = source;
      if (diet_type !== undefined)      body.diet_type = diet_type;
      if (nutrition !== undefined)      body.nutrition = nutrition;
      if (is_reference !== undefined)   body.is_reference = is_reference;
      return mmm("POST", `/products/${id}`, body);
    }

    case "mmm_unit_usage":
      return mmm("GET", `/units/${args.id}/usage`);

    case "mmm_delete_ingr_unit":
      return mmm("DELETE", `/units/${args.id}${args.force ? "?force=true" : ""}`);

    case "mmm_delete_ingr_units_bulk": {
      const body = { ids: args.ids };
      if (args.force !== undefined)   body.force   = args.force;
      if (args.dry_run !== undefined) body.dry_run = args.dry_run;
      return mmm("POST", "/units/bulk-delete", body);
    }

    case "mmm_set_keeping": {
      const body = { items: args.items };
      if (args.dry_run !== undefined) body.dry_run = args.dry_run;
      return mmm("POST", "/keeping/bulk", body);
    }

    case "mmm_audit":
      return mmm("GET", `/audit?scope=${encodeURIComponent(args.scope || "all")}`);

    case "mmm_set_diets":
      return mmm("POST", "/diets/bulk", { items: args.items });

    case "mmm_migrate_diets":
      return mmm("POST", "/diets/migrate", {
        dry_run: args.dry_run !== false,
        limit: args.limit ?? 200,
      });

    case "mmm_product_impact":
      return mmm("GET", `/product-impact?post_id=${args.post_id}`);

    case "mmm_delete_product":
      return mmm("DELETE", `/products/${args.id}`);

    case "mmm_bulk_update": {
      const body = { items: args.items };
      if (args.dry_run !== undefined) body.dry_run = args.dry_run;
      return mmm("POST", "/bulk-update", body);
    }

    case "mmm_list_missing": {
      const params = new URLSearchParams({ field: args.field });
      for (const key of ["type", "mode", "zero_is_missing", "page", "per_page"]) {
        if (args[key] !== undefined) params.set(key, String(args[key]));
      }
      return mmm("GET", `/missing?${params}`);
    }

    case "mmm_merge_ingredients":
      return mmm("POST", `/ingredients/${args.remove_id}/merge`, {
        to_id:   args.keep_id,
        dry_run: args.dry_run === true,
      });

    case "mmm_get_ingr_unit":
      return mmm("GET", `/units/${args.id}`);

    case "mmm_create_ingr_unit": {
      const { ingredient_id, title, weight_g, is_indivisible = false, divisor = 0 } = args;
      const body = { ingredient_id, title, weight_g };
      if (is_indivisible) body.is_indivisible = true;
      if (divisor) body.divisor = divisor;
      return mmm("POST", "/units", body);
    }

    case "mmm_update_ingr_unit": {
      const { id, title, weight_g, is_indivisible, divisor } = args;
      const body = {};
      if (title          !== undefined) body.title          = title;
      if (weight_g       !== undefined) body.weight_g       = weight_g;
      if (is_indivisible !== undefined) body.is_indivisible = is_indivisible;
      if (divisor !== undefined) body.divisor = divisor;
      return mmm("POST", `/units/${id}`, body);
    }

    // ── MixMyMeals: Meal Plans ─────────────────────────────────────────────

    case "mmm_get_meal_plans":
      return mmm("GET", "/meal-plans");

    case "mmm_get_meal_plan":
      return mmm("GET", `/meal-plans/${args.id}`);

    case "mmm_create_meal_plan": {
      const { title, status = "draft", content, excerpt, start_day, end_day, categories, tags, author, featured_image, image_credit } = args;
      const body = { title, status };
      if (content    !== undefined) body.content    = content;
      if (excerpt    !== undefined) body.excerpt    = excerpt;
      if (start_day  !== undefined) body.start_day  = start_day;
      if (end_day    !== undefined) body.end_day    = end_day;
      if (categories !== undefined) body.categories = categories;
      if (tags       !== undefined) body.tags       = tags;
      if (author     !== undefined) body.author     = author;
      if (featured_image !== undefined) body.featured_image = featured_image;
      if (image_credit   !== undefined) body.image_credit   = image_credit;
      return mmm("POST", "/meal-plans", body);
    }

    case "mmm_update_meal_plan": {
      const { id, ...fields } = args;
      const body = {};
      const allowed = ["title", "status", "content", "excerpt", "start_day", "end_day", "author", "featured_image", "image_credit"];
      for (const key of allowed) {
        if (fields[key] !== undefined) body[key] = fields[key];
      }
      return mmm("POST", `/meal-plans/${id}`, body);
    }

    case "mmm_upload_image": {
      const { file, url, attachment_id, post_id, featured, title, alt } = args;
      const fields = { post_id, featured, title, alt };

      // A local file has to be read here — the server cannot reach the
      // machine this runs on. url/attachment_id are plain JSON.
      if (file) return mmmUpload("/media", resolve(file), fields);

      if (!url && !attachment_id) {
        throw new Error("mmm_upload_image: file, url oder attachment_id angeben.");
      }

      const body = { ...fields };
      if (url)           body.url           = url;
      if (attachment_id) body.attachment_id = attachment_id;
      for (const key of Object.keys(body)) {
        if (body[key] === undefined || body[key] === null) delete body[key];
      }
      return mmm("POST", "/media", body);
    }

    case "mmm_get_author": {
      const { type, id } = args;
      return mmm("GET", `/${type === "recipe" ? "recipes" : "meal-plans"}/${id}/author`);
    }

    case "mmm_set_author": {
      const { type, id, clear, ...fields } = args;
      const path = `/${type === "recipe" ? "recipes" : "meal-plans"}/${id}/author`;
      if (clear) return mmm("DELETE", path);
      const body = {};
      for (const key of ["name", "text", "url", "link_text", "nofollow", "social", "image"]) {
        if (fields[key] !== undefined) body[key] = fields[key];
      }
      return mmm("POST", path, body);
    }

    case "mmm_get_shopping_list":
      return mmm("GET", `/meal-plans/${args.id}/shopping-list`);

    // ── MixMyMeals: Planung (KI-Komfort) ───────────────────────────────────

    case "mmm_find_recipes": {
      const params = new URLSearchParams();
      for (const key of ["search", "diet", "category", "tag", "min_calories", "max_calories", "max_prep", "per_page"]) {
        if (args[key] !== undefined) params.set(key, args[key]);
      }
      const qs = params.toString();
      return mmm("GET", `/recipes/search${qs ? "?" + qs : ""}`);
    }

    case "mmm_build_meal_plan": {
      const { title, status = "draft", content, excerpt, start_day, end_day, categories, tags, meals, author, featured_image, image_credit } = args;
      const body = { title, status };
      if (content    !== undefined) body.content    = content;
      if (excerpt    !== undefined) body.excerpt    = excerpt;
      if (start_day  !== undefined) body.start_day  = start_day;
      if (end_day    !== undefined) body.end_day    = end_day;
      if (categories !== undefined) body.categories = categories;
      if (tags       !== undefined) body.tags       = tags;
      if (meals      !== undefined) body.meals      = meals;
      if (author     !== undefined) body.author     = author;
      if (featured_image !== undefined) body.featured_image = featured_image;
      if (image_credit   !== undefined) body.image_credit   = image_credit;
      return mmm("POST", "/meal-plans/build", body);
    }

    case "mmm_add_meals":
      return mmm("POST", `/meal-plans/${args.id}/meals/bulk`, { meals: args.meals });

    // ── MixMyMeals: Mahlzeiten ─────────────────────────────────────────────

    case "mmm_add_meal": {
      const { plan_id, recipe_id, title, servings = 1, type, day, status = "publish" } = args;
      const body = { plan_id, recipe_id, servings, status };
      if (title !== undefined) body.title = title;
      if (type  !== undefined) body.type  = type;
      if (day   !== undefined) body.day   = day;
      return mmm("POST", "/meals", body);
    }

    case "mmm_get_meal":
      return mmm("GET", `/meals/${args.id}`);

    case "mmm_update_meal": {
      const { id, ...fields } = args;
      const body = {};
      const allowed = ["recipe_id", "servings", "type", "day", "status", "title", "text", "diet"];
      for (const key of allowed) {
        if (fields[key] !== undefined) body[key] = fields[key];
      }
      return mmm("POST", `/meals/${id}`, body);
    }

    // ── MixMyMeals: Instagram ──────────────────────────────────────────────

    case "mmm_instagram_status":
      return mmm("GET", `/instagram/${args.id}`);

    case "mmm_instagram_generate":
      return mmm("POST", `/instagram/${args.id}/generate`, {});

    case "mmm_instagram_publish": {
      const { id, caption, regenerate, budget } = args;
      const body = {};
      if (caption    !== undefined) body.caption    = caption;
      if (regenerate !== undefined) body.regenerate = regenerate;
      if (budget     !== undefined) body.budget     = budget;
      return mmm("POST", `/instagram/${id}/publish`, body);
    }

    case "mmm_instagram_schedule": {
      const { id, when, caption } = args;
      const body = { when };
      if (caption !== undefined) body.caption = caption;
      return mmm("POST", `/instagram/${id}/schedule`, body);
    }

    case "mmm_instagram_unschedule":
      return mmm("POST", `/instagram/${args.id}/unschedule`, {});

    // ── MixMyMeals: Pinterest ──────────────────────────────────────────────

    case "mmm_pinterest_status":
      return mmm("GET", `/pinterest/${args.id}`);

    case "mmm_pinterest_publish": {
      const { id, title, description } = args;
      const body = {};
      if (title       !== undefined) body.title       = title;
      if (description !== undefined) body.description = description;
      return mmm("POST", `/pinterest/${id}/publish`, body);
    }

    case "mmm_pinterest_schedule": {
      const { id, when, title, description } = args;
      const body = { when };
      if (title       !== undefined) body.title       = title;
      if (description !== undefined) body.description = description;
      return mmm("POST", `/pinterest/${id}/schedule`, body);
    }

    case "mmm_pinterest_unschedule":
      return mmm("POST", `/pinterest/${args.id}/unschedule`, {});

    default:
      throw new Error(`Unbekanntes Tool: ${name}`);
  }
}

// ── MCP Server ───────────────────────────────────────────────────────────────

const server = new Server(
  { name: "wordpress-rest", version: "5.1.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;
  try {
    const result = await handleTool(name, args ?? {});
    return {
      content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
    };
  } catch (err) {
    return {
      content: [{ type: "text", text: `Fehler: ${err.message}` }],
      isError: true,
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
