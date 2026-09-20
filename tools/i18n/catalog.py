#!/usr/bin/env python3
from __future__ import annotations

import argparse
import ast
import datetime as dt
import json
from pathlib import Path
import re
import shutil
import subprocess
import tempfile

I18N_TOOLING_VERSION = "1.1.6"
LOCALES = ("nl_NL", "de_DE", "fr_FR", "es_ES", "it_IT", "pt_PT")

SCRIPT_DIR = Path(__file__).resolve().parent
ROOT = SCRIPT_DIR.parents[1]
DEFAULT_CONFIG_PATH = SCRIPT_DIR / "config.json"

SHARED_TRANSLATIONS = {
    "Core Blueprint": {
        "nl_NL": "Core Blueprint",
        "de_DE": "Core Blueprint",
        "fr_FR": "Core Blueprint",
        "es_ES": "Core Blueprint",
        "it_IT": "Core Blueprint",
        "pt_PT": "Core Blueprint"
    },
    "Bricks Builder": {
        "nl_NL": "Bricks Builder",
        "de_DE": "Bricks Builder",
        "fr_FR": "Bricks Builder",
        "es_ES": "Bricks Builder",
        "it_IT": "Bricks Builder",
        "pt_PT": "Bricks Builder"
    },
    "Lightweight builder-agnostic documentation with native WordPress content, taxonomies and metadata.": {
        "nl_NL": "Lichtgewicht builder-agnostische documentatie met native WordPress-content, taxonomieën en metadata.",
        "de_DE": "Leichtgewichtige, Builder-unabhängige Dokumentation mit nativen WordPress-Inhalten, Taxonomien und Metadaten.",
        "fr_FR": "Documentation légère et indépendante du builder, basée sur le contenu, les taxonomies et les métadonnées natifs de WordPress.",
        "es_ES": "Documentación ligera e independiente del maquetador con contenido, taxonomías y metadatos nativos de WordPress.",
        "it_IT": "Documentazione leggera e indipendente dal builder con contenuti, tassonomie e metadati nativi di WordPress.",
        "pt_PT": "Documentação leve e independente do builder com conteúdos, taxonomias e metadados nativos do WordPress."
    },
    "PHP %1$s or newer is required. This server runs PHP %2$s.": {
        "nl_NL": "PHP %1$s of nieuwer is vereist. Deze server draait PHP %2$s.",
        "de_DE": "PHP %1$s oder neuer ist erforderlich. Auf diesem Server läuft PHP %2$s.",
        "fr_FR": "PHP %1$s ou une version ultérieure est requis. Ce serveur utilise PHP %2$s.",
        "es_ES": "Se requiere PHP %1$s o posterior. Este servidor ejecuta PHP %2$s.",
        "it_IT": "È richiesto PHP %1$s o una versione successiva. Questo server utilizza PHP %2$s.",
        "pt_PT": "É necessário PHP %1$s ou mais recente. Este servidor utiliza PHP %2$s."
    },
    "Core Blueprint must be installed and active.": {
        "nl_NL": "Core Blueprint moet geïnstalleerd en actief zijn.",
        "de_DE": "Core Blueprint muss installiert und aktiviert sein.",
        "fr_FR": "Core Blueprint doit être installé et activé.",
        "es_ES": "Core Blueprint debe estar instalado y activo.",
        "it_IT": "Core Blueprint deve essere installato e attivo.",
        "pt_PT": "O Core Blueprint tem de estar instalado e ativo."
    },
    "Core API %1$s or a newer compatible minor version is required. Available Core API: %2$s.": {
        "nl_NL": "Core API %1$s of een nieuwere compatibele minorversie is vereist. Beschikbare Core API: %2$s.",
        "de_DE": "Core API %1$s oder eine neuere kompatible Minor-Version ist erforderlich. Verfügbare Core API: %2$s.",
        "fr_FR": "Core API %1$s ou une version mineure compatible plus récente est requise. Core API disponible : %2$s.",
        "es_ES": "Se requiere Core API %1$s o una versión menor compatible más reciente. Core API disponible: %2$s.",
        "it_IT": "È richiesta Core API %1$s o una versione minor compatibile più recente. Core API disponibile: %2$s.",
        "pt_PT": "É necessária a Core API %1$s ou uma versão minor compatível mais recente. Core API disponível: %2$s."
    },
    "Required Core Blueprint Base contracts are unavailable.": {
        "nl_NL": "Vereiste Core Blueprint Base-contracten zijn niet beschikbaar.",
        "de_DE": "Erforderliche Core Blueprint Base-Verträge sind nicht verfügbar.",
        "fr_FR": "Les contrats Core Blueprint Base requis ne sont pas disponibles.",
        "es_ES": "Los contratos requeridos de Core Blueprint Base no están disponibles.",
        "it_IT": "I contratti Core Blueprint Base richiesti non sono disponibili.",
        "pt_PT": "Os contratos Core Blueprint Base necessários não estão disponíveis."
    },
    "Bricks is active. Docs dynamic data, queries and conditions are available through the optional adapter while Docs data and access logic remain builder-neutral.": {
        "nl_NL": "Bricks is actief. Dynamische Docs-data, queries en voorwaarden zijn beschikbaar via de optionele adapter, terwijl Docs-data en toegangslogica builder-neutraal blijven.",
        "de_DE": "Bricks ist aktiv. Dynamische Docs-Daten, Abfragen und Bedingungen sind über den optionalen Adapter verfügbar, während Docs-Daten und Zugriffslogik Builder-neutral bleiben.",
        "fr_FR": "Bricks est actif. Les données dynamiques, requêtes et conditions de Docs sont disponibles via l’adaptateur facultatif, tandis que les données et la logique d’accès de Docs restent indépendantes du builder.",
        "es_ES": "Bricks está activo. Los datos dinámicos, las consultas y las condiciones de Docs están disponibles mediante el adaptador opcional, mientras que los datos y la lógica de acceso de Docs siguen siendo independientes del constructor.",
        "it_IT": "Bricks è attivo. Dati dinamici, query e condizioni di Docs sono disponibili tramite l’adattatore opzionale, mentre i dati e la logica di accesso di Docs restano indipendenti dal builder.",
        "pt_PT": "O Bricks está ativo. Os dados dinâmicos, consultas e condições do Docs estão disponíveis através do adaptador opcional, enquanto os dados e a lógica de acesso do Docs permanecem independentes do builder."
    },
    "Bricks is optional. Docs works without a builder through native WordPress content, shortcodes and builder-neutral frontend contracts.": {
        "nl_NL": "Bricks is optioneel. Docs werkt zonder builder via native WordPress-content, shortcodes en builder-neutrale frontendcontracten.",
        "de_DE": "Bricks ist optional. Docs funktioniert ohne Builder über native WordPress-Inhalte, Shortcodes und Builder-neutrale Frontend-Verträge.",
        "fr_FR": "Bricks est facultatif. Docs fonctionne sans builder grâce au contenu WordPress natif, aux shortcodes et aux contrats frontend indépendants du builder.",
        "es_ES": "Bricks es opcional. Docs funciona sin constructor mediante contenido nativo de WordPress, shortcodes y contratos de frontend independientes del constructor.",
        "it_IT": "Bricks è opzionale. Docs funziona senza builder tramite contenuti WordPress nativi, shortcode e contratti frontend indipendenti dal builder.",
        "pt_PT": "O Bricks é opcional. O Docs funciona sem builder através de conteúdo nativo do WordPress, shortcodes e contratos de frontend independentes do builder."
    },
    "Ready": {
        "nl_NL": "Gereed",
        "de_DE": "Bereit",
        "fr_FR": "Prêt",
        "es_ES": "Listo",
        "it_IT": "Pronto",
        "pt_PT": "Pronto"
    },
    "Not active": {
        "nl_NL": "Niet actief",
        "de_DE": "Nicht aktiv",
        "fr_FR": "Inactif",
        "es_ES": "No activo",
        "it_IT": "Non attivo",
        "pt_PT": "Não ativo"
    },
    "Manage documentation health, URL behavior, frontend composition and optional integrations. Documentation content remains native WordPress and is managed from the Docs content screen.": {
        "nl_NL": "Beheer de status van de documentatie, URL-werking, frontendopbouw en optionele integraties. Documentatiecontent blijft native WordPress en wordt beheerd vanuit het Docs-inhoudsscherm.",
        "de_DE": "Verwalte Dokumentationsstatus, URL-Verhalten, Frontend-Aufbau und optionale Integrationen. Dokumentationsinhalte bleiben natives WordPress und werden im Docs-Inhaltsbereich verwaltet.",
        "fr_FR": "Gérez l’état de la documentation, le comportement des URL, la composition frontend et les intégrations facultatives. Le contenu de la documentation reste natif à WordPress et se gère depuis l’écran de contenu Docs.",
        "es_ES": "Gestiona el estado de la documentación, el comportamiento de las URL, la composición del frontend y las integraciones opcionales. El contenido de la documentación sigue siendo nativo de WordPress y se gestiona desde la pantalla de contenido de Docs.",
        "it_IT": "Gestisci lo stato della documentazione, il comportamento degli URL, la composizione frontend e le integrazioni opzionali. I contenuti della documentazione restano nativi di WordPress e vengono gestiti dalla schermata dei contenuti Docs.",
        "pt_PT": "Gira o estado da documentação, o comportamento dos URL, a composição do frontend e as integrações opcionais. O conteúdo da documentação continua nativo do WordPress e é gerido no ecrã de conteúdos do Docs."
    },
    "Overview": {
        "nl_NL": "Overzicht",
        "de_DE": "Übersicht",
        "fr_FR": "Vue d’ensemble",
        "es_ES": "Resumen",
        "it_IT": "Panoramica",
        "pt_PT": "Visão geral"
    },
    "General": {
        "nl_NL": "Algemeen",
        "de_DE": "Allgemein",
        "fr_FR": "Général",
        "es_ES": "General",
        "it_IT": "Generale",
        "pt_PT": "Geral"
    },
    "Integrations": {
        "nl_NL": "Integraties",
        "de_DE": "Integrationen",
        "fr_FR": "Intégrations",
        "es_ES": "Integraciones",
        "it_IT": "Integrazioni",
        "pt_PT": "Integrações"
    },
    "Docs sections": {
        "nl_NL": "Docs-secties",
        "de_DE": "Docs-Bereiche",
        "fr_FR": "Sections de Docs",
        "es_ES": "Secciones de Docs",
        "it_IT": "Sezioni di Docs",
        "pt_PT": "Secções do Docs"
    },
    "Docs at a glance": {
        "nl_NL": "Docs in één oogopslag",
        "de_DE": "Docs auf einen Blick",
        "fr_FR": "Docs en un coup d’œil",
        "es_ES": "Docs de un vistazo",
        "it_IT": "Docs a colpo d’occhio",
        "pt_PT": "Docs em resumo"
    },
    "Published Docs": {
        "nl_NL": "Gepubliceerde Docs",
        "de_DE": "Veröffentlichte Docs",
        "fr_FR": "Docs publiés",
        "es_ES": "Docs publicados",
        "it_IT": "Docs pubblicati",
        "pt_PT": "Docs publicados"
    },
    "Drafts": {
        "nl_NL": "Concepten",
        "de_DE": "Entwürfe",
        "fr_FR": "Brouillons",
        "es_ES": "Borradores",
        "it_IT": "Bozze",
        "pt_PT": "Rascunhos"
    },
    "Categories": {
        "nl_NL": "Categorieën",
        "de_DE": "Kategorien",
        "fr_FR": "Catégories",
        "es_ES": "Categorías",
        "it_IT": "Categorie",
        "pt_PT": "Categorias"
    },
    "Tags": {
        "nl_NL": "Tags",
        "de_DE": "Schlagwörter",
        "fr_FR": "Étiquettes",
        "es_ES": "Etiquetas",
        "it_IT": "Tag",
        "pt_PT": "Etiquetas"
    },
    "Searching…": {
        "nl_NL": "Zoeken…",
        "de_DE": "Suche…",
        "fr_FR": "Recherche…",
        "es_ES": "Buscando…",
        "it_IT": "Ricerca…",
        "pt_PT": "A pesquisar…"
    },
    "Live search is temporarily unavailable. Submit the form to search.": {
        "nl_NL": "Live zoeken is tijdelijk niet beschikbaar. Verstuur het formulier om te zoeken.",
        "de_DE": "Die Live-Suche ist vorübergehend nicht verfügbar. Sende das Formular ab, um zu suchen.",
        "fr_FR": "La recherche en direct est temporairement indisponible. Envoyez le formulaire pour lancer la recherche.",
        "es_ES": "La búsqueda en vivo no está disponible temporalmente. Envía el formulario para buscar.",
        "it_IT": "La ricerca in tempo reale è temporaneamente non disponibile. Invia il modulo per effettuare la ricerca.",
        "pt_PT": "A pesquisa em tempo real está temporariamente indisponível. Envie o formulário para pesquisar."
    },
    "Docs Search": {
        "nl_NL": "Docs zoeken",
        "de_DE": "Docs-Suche",
        "fr_FR": "Recherche Docs",
        "es_ES": "Búsqueda en Docs",
        "it_IT": "Ricerca Docs",
        "pt_PT": "Pesquisa no Docs"
    },
    "Input": {
        "nl_NL": "Invoerveld",
        "de_DE": "Eingabefeld",
        "fr_FR": "Champ de saisie",
        "es_ES": "Campo de entrada",
        "it_IT": "Campo di input",
        "pt_PT": "Campo de entrada"
    },
    "Button": {
        "nl_NL": "Knop",
        "de_DE": "Schaltfläche",
        "fr_FR": "Bouton",
        "es_ES": "Botón",
        "it_IT": "Pulsante",
        "pt_PT": "Botão"
    },
    "Results": {
        "nl_NL": "Resultaten",
        "de_DE": "Ergebnisse",
        "fr_FR": "Résultats",
        "es_ES": "Resultados",
        "it_IT": "Risultati",
        "pt_PT": "Resultados"
    },
    "Live search is powered by the builder-neutral Core Blueprint Docs search service. Bricks only controls presentation.": {
        "nl_NL": "Live zoeken wordt aangestuurd door de builder-neutrale zoekservice van Core Blueprint Docs. Bricks bepaalt alleen de presentatie.",
        "de_DE": "Die Live-Suche wird vom Builder-neutralen Suchdienst von Core Blueprint Docs bereitgestellt. Bricks steuert nur die Darstellung.",
        "fr_FR": "La recherche en direct est fournie par le service de recherche de Core Blueprint Docs, indépendant du builder. Bricks contrôle uniquement la présentation.",
        "es_ES": "La búsqueda en vivo funciona mediante el servicio de búsqueda de Core Blueprint Docs, independiente del constructor. Bricks solo controla la presentación.",
        "it_IT": "La ricerca in tempo reale è gestita dal servizio di ricerca di Core Blueprint Docs, indipendente dal builder. Bricks controlla solo la presentazione.",
        "pt_PT": "A pesquisa em tempo real é fornecida pelo serviço de pesquisa do Core Blueprint Docs, independente do builder. O Bricks controla apenas a apresentação."
    },
    "Placeholder": {
        "nl_NL": "Plaatshouder",
        "de_DE": "Platzhalter",
        "fr_FR": "Texte indicatif",
        "es_ES": "Marcador de posición",
        "it_IT": "Segnaposto",
        "pt_PT": "Marcador de posição"
    },
    "Result limit": {
        "nl_NL": "Resultaatlimiet",
        "de_DE": "Ergebnislimit",
        "fr_FR": "Limite de résultats",
        "es_ES": "Límite de resultados",
        "it_IT": "Limite risultati",
        "pt_PT": "Limite de resultados"
    },
    "Minimum characters": {
        "nl_NL": "Minimum aantal tekens",
        "de_DE": "Mindestanzahl Zeichen",
        "fr_FR": "Nombre minimal de caractères",
        "es_ES": "Mínimo de caracteres",
        "it_IT": "Numero minimo di caratteri",
        "pt_PT": "Número mínimo de caracteres"
    },
    "Live search starts after this many characters.": {
        "nl_NL": "Live zoeken start na dit aantal tekens.",
        "de_DE": "Die Live-Suche startet nach dieser Anzahl von Zeichen.",
        "fr_FR": "La recherche en direct démarre après ce nombre de caractères.",
        "es_ES": "La búsqueda en vivo comienza después de este número de caracteres.",
        "it_IT": "La ricerca in tempo reale inizia dopo questo numero di caratteri.",
        "pt_PT": "A pesquisa em tempo real começa após este número de caracteres."
    },
    "Show excerpts": {
        "nl_NL": "Samenvattingen tonen",
        "de_DE": "Textauszüge anzeigen",
        "fr_FR": "Afficher les extraits",
        "es_ES": "Mostrar extractos",
        "it_IT": "Mostra estratti",
        "pt_PT": "Mostrar excertos"
    },
    "Show category": {
        "nl_NL": "Categorie tonen",
        "de_DE": "Kategorie anzeigen",
        "fr_FR": "Afficher la catégorie",
        "es_ES": "Mostrar categoría",
        "it_IT": "Mostra categoria",
        "pt_PT": "Mostrar categoria"
    },
    "Category slug": {
        "nl_NL": "Categorie-slug",
        "de_DE": "Kategorie-Slug",
        "fr_FR": "Slug de catégorie",
        "es_ES": "Slug de categoría",
        "it_IT": "Slug categoria",
        "pt_PT": "Slug da categoria"
    },
    "Optional. Limit search to one Docs category.": {
        "nl_NL": "Optioneel. Beperk zoeken tot één Docs-categorie.",
        "de_DE": "Optional. Beschränke die Suche auf eine Docs-Kategorie.",
        "fr_FR": "Facultatif. Limitez la recherche à une catégorie Docs.",
        "es_ES": "Opcional. Limita la búsqueda a una categoría de Docs.",
        "it_IT": "Facoltativo. Limita la ricerca a una categoria Docs.",
        "pt_PT": "Opcional. Limite a pesquisa a uma categoria do Docs."
    },
    "Tag slug": {
        "nl_NL": "Tag-slug",
        "de_DE": "Tag-Slug",
        "fr_FR": "Slug d’étiquette",
        "es_ES": "Slug de etiqueta",
        "it_IT": "Slug tag",
        "pt_PT": "Slug da etiqueta"
    },
    "Optional. Limit search to one Docs tag.": {
        "nl_NL": "Optioneel. Beperk zoeken tot één Docs-tag.",
        "de_DE": "Optional. Beschränke die Suche auf einen Docs-Tag.",
        "fr_FR": "Facultatif. Limitez la recherche à une étiquette Docs.",
        "es_ES": "Opcional. Limita la búsqueda a una etiqueta de Docs.",
        "it_IT": "Facoltativo. Limita la ricerca a un tag Docs.",
        "pt_PT": "Opcional. Limite a pesquisa a uma etiqueta do Docs."
    },
    "Typography": {
        "nl_NL": "Typografie",
        "de_DE": "Typografie",
        "fr_FR": "Typographie",
        "es_ES": "Tipografía",
        "it_IT": "Tipografia",
        "pt_PT": "Tipografia"
    },
    "Background": {
        "nl_NL": "Achtergrond",
        "de_DE": "Hintergrund",
        "fr_FR": "Arrière-plan",
        "es_ES": "Fondo",
        "it_IT": "Sfondo",
        "pt_PT": "Fundo"
    },
    "Border": {
        "nl_NL": "Rand",
        "de_DE": "Rahmen",
        "fr_FR": "Bordure",
        "es_ES": "Borde",
        "it_IT": "Bordo",
        "pt_PT": "Contorno"
    },
    "Padding": {
        "nl_NL": "Binnenruimte",
        "de_DE": "Innenabstand",
        "fr_FR": "Marge intérieure",
        "es_ES": "Relleno",
        "it_IT": "Spaziatura interna",
        "pt_PT": "Espaçamento interno"
    },
    "Title typography": {
        "nl_NL": "Typografie titel",
        "de_DE": "Titeltypografie",
        "fr_FR": "Typographie du titre",
        "es_ES": "Tipografía del título",
        "it_IT": "Tipografia del titolo",
        "pt_PT": "Tipografia do título"
    },
    "Category typography": {
        "nl_NL": "Typografie categorie",
        "de_DE": "Kategorietypografie",
        "fr_FR": "Typographie de la catégorie",
        "es_ES": "Tipografía de la categoría",
        "it_IT": "Tipografia della categoria",
        "pt_PT": "Tipografia da categoria"
    },
    "Excerpt typography": {
        "nl_NL": "Typografie samenvatting",
        "de_DE": "Auszugstypografie",
        "fr_FR": "Typographie de l’extrait",
        "es_ES": "Tipografía del extracto",
        "it_IT": "Tipografia dell’estratto",
        "pt_PT": "Tipografia do excerto"
    }
}


def fail(message: str) -> None:
    raise SystemExit(f"ERROR: {message}")


def load_config(path: Path) -> dict:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError) as exc:
        fail(f"could not read i18n config {path}: {exc}")

    required = {
        "product",
        "domain",
        "main_file",
        "version_constant",
        "exclude",
        "skip_js",
        "commit_mo",
        "source_paths",
    }
    missing = sorted(required - set(data))
    if missing:
        fail("missing i18n config keys: " + ", ".join(missing))

    data.setdefault("commit_l10n_php", False)
    return data


def command(name: str) -> str:
    resolved = shutil.which(name)
    if not resolved:
        fail(f"required i18n command is unavailable: {name}")
    return resolved


def run(args: list[str], *, capture: bool = False) -> str:
    result = subprocess.run(args, cwd=ROOT, text=True, capture_output=capture)
    if result.returncode:
        detail = (result.stderr or result.stdout).strip() if capture else ""
        suffix = f": {detail}" if detail else ""
        fail(f"command failed ({result.returncode}): {' '.join(args)}{suffix}")
    return result.stdout if capture else ""


def source_version(cfg: dict) -> str:
    source = (ROOT / cfg["main_file"]).read_text(encoding="utf-8")
    header = re.search(r"^\s*\*\s*Version:\s*([^\s]+)", source, re.M)
    constant = re.search(
        r"define\(\s*['\"]"
        + re.escape(cfg["version_constant"])
        + r"['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)",
        source,
    )
    if not header or not constant or header.group(1) != constant.group(1):
        fail("plugin Version header and version constant must exist and match")
    return header.group(1)


def source_stamp(cfg: dict, git_bin: str) -> str:
    paths = [str(path) for path in cfg["source_paths"]]
    raw = run([git_bin, "log", "-1", "--format=%ct", "--", *paths], capture=True).strip()
    if not raw.isdigit():
        fail("could not resolve deterministic source timestamp from git")
    stamp = dt.datetime.fromtimestamp(int(raw), dt.timezone.utc)
    return stamp.strftime("%Y-%m-%d %H:%M+0000")


def generate_pot(cfg: dict, output: Path, wp_bin: str, version: str) -> None:
    args = [
        wp_bin,
        "i18n",
        "make-pot",
        ".",
        str(output),
        f"--domain={cfg['domain']}",
        "--exclude=" + ",".join(cfg["exclude"]),
        "--headers="
        + json.dumps(
            {"Project-Id-Version": f"{cfg['product']} {version}"},
            separators=(",", ":"),
        ),
        "--quiet",
    ]
    if cfg["skip_js"]:
        args.append("--skip-js")
    run(args)


def rewrite_header(path: Path, values: dict[str, str], *, insert_missing: bool = False) -> None:
    lines = path.read_text(encoding="utf-8").splitlines()
    found: set[str] = set()

    for index, line in enumerate(lines):
        for key, value in values.items():
            if line.startswith(f'"{key}:'):
                lines[index] = f'"{key}: {value}\\n"'
                found.add(key)

    missing = [key for key in values if key not in found]
    if missing and insert_missing:
        header_end = next(
            (i for i, line in enumerate(lines[1:], start=1) if line == ""),
            None,
        )
        if header_end is None:
            fail(f"could not locate PO metadata header in {path.relative_to(ROOT)}")
        for key in reversed(missing):
            lines.insert(header_end, f'"{key}: {values[key]}\\n"')
        missing = []

    if missing:
        fail(f"catalog header missing {missing} in {path.relative_to(ROOT)}")

    path.write_text("\n".join(lines).rstrip() + "\n", encoding="utf-8")


def header_value(path: Path, key: str) -> str | None:
    prefix = f'"{key}: '
    for line in path.read_text(encoding="utf-8").splitlines():
        if line.startswith(prefix) and line.endswith('\\n"'):
            return line[len(prefix) : -3]
    return None


def po_literal(fragment: str) -> str:
    try:
        value = ast.literal_eval(fragment.strip())
    except (SyntaxError, ValueError) as exc:
        fail(f"invalid PO string literal: {fragment}: {exc}")
    if not isinstance(value, str):
        fail(f"invalid PO string value: {fragment}")
    return value


def directive(lines: list[str], keyword: str) -> str | None:
    prefix = keyword + " "
    for index, line in enumerate(lines):
        if not line.startswith(prefix):
            continue
        value = po_literal(line[len(prefix) :])
        cursor = index + 1
        while cursor < len(lines) and lines[cursor].startswith('"'):
            value += po_literal(lines[cursor])
            cursor += 1
        return value
    return None


def plural_values(lines: list[str]) -> list[str]:
    values: dict[int, str] = {}
    index = 0
    while index < len(lines):
        match = re.match(r'^msgstr\[(\d+)\]\s+(".*")$', lines[index])
        if not match:
            index += 1
            continue
        plural_index = int(match.group(1))
        value = po_literal(match.group(2))
        index += 1
        while index < len(lines) and lines[index].startswith('"'):
            value += po_literal(lines[index])
            index += 1
        values[plural_index] = value
    return [values[key] for key in sorted(values)]


def blocks(path: Path) -> list[str]:
    return re.split(r"\n\s*\n", path.read_text(encoding="utf-8").strip())


def entry_key(block: str) -> str | None:
    lines = block.splitlines()
    msgid = directive(lines, "msgid")
    if msgid is None or msgid == "":
        return None
    context = directive(lines, "msgctxt")
    plural = directive(lines, "msgid_plural")
    key = msgid if plural is None else msgid + "\x00" + plural
    return key if context is None else context + "\x04" + key


def key_set(path: Path) -> set[str]:
    return {key for block in blocks(path) if (key := entry_key(block)) is not None}


def has_fuzzy(block: str) -> bool:
    for line in block.splitlines():
        if not line.startswith("#,"):
            continue
        flags = {flag.strip() for flag in line[2:].split(",")}
        if "fuzzy" in flags:
            return True
    return False


def translation_values(block: str) -> list[str]:
    lines = block.splitlines()
    if directive(lines, "msgid_plural") is not None:
        return plural_values(lines)
    value = directive(lines, "msgstr")
    return [] if value is None else [value]


def replace_singular_translation(block: str, translation: str) -> str:
    lines = block.splitlines()
    if directive(lines, "msgid_plural") is not None:
        fail("shared translation target unexpectedly became plural")

    cleaned: list[str] = []
    for line in lines:
        if line.startswith("#,"):
            flags = [
                flag.strip()
                for flag in line[2:].split(",")
                if flag.strip() and flag.strip() != "fuzzy"
            ]
            if flags:
                cleaned.append("#, " + ", ".join(flags))
        else:
            cleaned.append(line)

    for index, line in enumerate(cleaned):
        if not line.startswith("msgstr "):
            continue
        end = index + 1
        while end < len(cleaned) and cleaned[end].startswith('"'):
            end += 1
        replacement = "msgstr " + json.dumps(translation, ensure_ascii=False)
        return "\n".join(cleaned[:index] + [replacement] + cleaned[end:])

    fail("shared translation target has no msgstr")


def apply_shared_translations(po_path: Path, locale: str, pot_keys: set[str]) -> None:
    current = blocks(po_path)
    by_msgid: dict[str, int] = {}

    for index, block in enumerate(current):
        lines = block.splitlines()
        msgid = directive(lines, "msgid")
        if not msgid:
            continue
        if directive(lines, "msgctxt") is None and directive(lines, "msgid_plural") is None:
            by_msgid[msgid] = index

    for msgid, translations in SHARED_TRANSLATIONS.items():
        if msgid not in pot_keys:
            continue
        if msgid not in by_msgid:
            fail(f"{locale} PO missing shared translation target: {msgid}")
        current[by_msgid[msgid]] = replace_singular_translation(
            current[by_msgid[msgid]], translations[locale]
        )

    for msgid in sorted(pot_keys):
        if not re.fullmatch(r"https?://\S+", msgid):
            continue
        if msgid not in by_msgid:
            fail(f"{locale} PO missing URL identity translation target: {msgid}")
        current[by_msgid[msgid]] = replace_singular_translation(
            current[by_msgid[msgid]], msgid
        )

    po_path.write_text("\n\n".join(current).rstrip() + "\n", encoding="utf-8")


def validate_po(
    po_path: Path,
    pot_keys: set[str],
    msgfmt_bin: str,
    mo_output: Path | None = None,
) -> None:
    po_blocks = blocks(po_path)
    po_keys = {key for block in po_blocks if (key := entry_key(block)) is not None}
    missing = sorted(pot_keys - po_keys)
    stale = sorted(po_keys - pot_keys)

    if missing:
        fail(f"{po_path.name} missing {len(missing)} current POT entries; first: {missing[0]}")
    if stale:
        fail(f"{po_path.name} contains {len(stale)} stale non-obsolete entries; first: {stale[0]}")
    if re.search(r"^#~", po_path.read_text(encoding="utf-8"), re.M):
        fail(f"obsolete entries remain in {po_path.name}")

    for block in po_blocks:
        key = entry_key(block)
        if key is None:
            continue
        if has_fuzzy(block):
            fail(f"fuzzy translation in {po_path.name}: {key}")
        values = translation_values(block)
        if not values or any(not value.strip() for value in values):
            fail(f"untranslated entry in {po_path.name}: {key}")

    if mo_output is None:
        with tempfile.NamedTemporaryFile(prefix="cb-i18n-", suffix=".mo") as handle:
            run(
                [
                    msgfmt_bin,
                    "--check-format",
                    "--check-header",
                    "-o",
                    handle.name,
                    str(po_path),
                ]
            )
        return

    run(
        [
            msgfmt_bin,
            "--check-format",
            "--check-header",
            "-o",
            str(mo_output),
            str(po_path),
        ]
    )


def normalize_po_headers(
    po: Path, cfg: dict, version: str, stamp: str, locale: str
) -> None:
    rewrite_header(
        po,
        {
            "Project-Id-Version": f"{cfg['product']} {version}",
            "POT-Creation-Date": stamp,
            "PO-Revision-Date": stamp,
            "Last-Translator": "Core Blueprint",
            "Language-Team": locale,
            "Language": locale,
            "Generated-By": f"Core Blueprint i18n tooling {I18N_TOOLING_VERSION}",
        },
        insert_missing=True,
    )


def generate_l10n_php(wp_bin: str, po: Path, output_dir: Path, expected_name: str) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    run([wp_bin, "i18n", "make-php", str(po), str(output_dir), "--quiet"])
    candidates = list(output_dir.glob("*.l10n.php"))
    if len(candidates) != 1:
        fail(f"wp i18n make-php produced {len(candidates)} PHP catalogs for {po.name}; expected 1")
    generated = candidates[0]
    if generated.name != expected_name:
        fail(
            f"wp i18n make-php produced unexpected filename {generated.name}; expected {expected_name}"
        )
    return generated


def do_update(args: argparse.Namespace, cfg: dict) -> None:
    wp = command(args.wp_bin)
    msgmerge = command(args.msgmerge_bin)
    msgattrib = command(args.msgattrib_bin)
    msgfmt = command(args.msgfmt_bin)
    git_bin = command(args.git_bin)

    version = source_version(cfg)
    stamp = source_stamp(cfg, git_bin)
    lang = ROOT / "languages"
    lang.mkdir(parents=True, exist_ok=True)
    pot = lang / f"{cfg['domain']}.pot"

    generate_pot(cfg, pot, wp, version)
    rewrite_header(
        pot,
        {
            "Project-Id-Version": f"{cfg['product']} {version}",
            "POT-Creation-Date": stamp,
        },
    )
    pot_keys = key_set(pot)
    if not pot_keys:
        fail("generated POT contains no translatable messages")

    for locale in LOCALES:
        po = lang / f"{cfg['domain']}-{locale}.po"
        if not po.is_file():
            fail(f"missing reviewed translation source: {po.relative_to(ROOT)}")

        run(
            [
                msgmerge,
                "--update",
                "--backup=none",
                "--no-fuzzy-matching",
                str(po),
                str(pot),
            ]
        )
        tmp = po.with_suffix(".po.tmp")
        run([msgattrib, "--no-obsolete", "-o", str(tmp), str(po)])
        tmp.replace(po)

        apply_shared_translations(po, locale, pot_keys)
        normalize_po_headers(po, cfg, version, stamp, locale)

        mo_output = lang / f"{cfg['domain']}-{locale}.mo" if cfg["commit_mo"] else None
        validate_po(po, pot_keys, msgfmt, mo_output)

        if cfg["commit_l10n_php"]:
            expected_name = f"{cfg['domain']}-{locale}.l10n.php"
            with tempfile.TemporaryDirectory(prefix="cb-i18n-php-") as tmpdir:
                generated = generate_l10n_php(wp, po, Path(tmpdir), expected_name)
                shutil.copyfile(generated, lang / expected_name)

    print(
        f"PASS: {cfg['product']} catalogs updated from source; "
        f"locales={len(LOCALES)} tooling={I18N_TOOLING_VERSION}"
    )


def do_check(args: argparse.Namespace, cfg: dict) -> None:
    wp = command(args.wp_bin)
    msgfmt = command(args.msgfmt_bin)
    version = source_version(cfg)
    lang = ROOT / "languages"
    pot = lang / f"{cfg['domain']}.pot"

    if not pot.is_file():
        fail(f"missing committed POT: {pot.relative_to(ROOT)}")

    committed_keys = key_set(pot)
    expected_project = f"{cfg['product']} {version}"
    if header_value(pot, "Project-Id-Version") != expected_project:
        fail(f"POT Project-Id-Version does not match current release: {expected_project}")

    with tempfile.TemporaryDirectory(prefix="cb-i18n-check-") as tmpdir:
        temp_root = Path(tmpdir)
        fresh = temp_root / pot.name
        generate_pot(cfg, fresh, wp, version)
        fresh_keys = key_set(fresh)
        if committed_keys != fresh_keys:
            fail(
                f"POT/source drift: missing={len(fresh_keys - committed_keys)} "
                f"stale={len(committed_keys - fresh_keys)}"
            )

        for locale in LOCALES:
            po = lang / f"{cfg['domain']}-{locale}.po"
            if not po.is_file():
                fail(f"missing locale PO: {po.relative_to(ROOT)}")
            if header_value(po, "Project-Id-Version") != expected_project:
                fail(f"{po.name} Project-Id-Version does not match current release: {expected_project}")
            if header_value(po, "Language") != locale:
                fail(f"{po.name} Language header must be {locale}")

            validate_po(po, committed_keys, msgfmt)

            by_msgid: dict[str, str] = {}
            for block in blocks(po):
                lines = block.splitlines()
                msgid = directive(lines, "msgid")
                if not msgid:
                    continue
                if directive(lines, "msgctxt") is None and directive(lines, "msgid_plural") is None:
                    values = translation_values(block)
                    by_msgid[msgid] = values[0] if values else ""

            for msgid in {key for key in SHARED_TRANSLATIONS if key in committed_keys}:
                if by_msgid.get(msgid) != SHARED_TRANSLATIONS[msgid][locale]:
                    fail(f"shared translation drift in {po.name}: {msgid}")

            if cfg["commit_mo"]:
                committed_mo = lang / f"{cfg['domain']}-{locale}.mo"
                if not committed_mo.is_file():
                    fail(f"missing committed MO: {committed_mo.relative_to(ROOT)}")
                generated_mo = temp_root / committed_mo.name
                validate_po(po, committed_keys, msgfmt, generated_mo)
                if generated_mo.read_bytes() != committed_mo.read_bytes():
                    fail(f"committed MO is not reproducible from {po.name}")

            if cfg["commit_l10n_php"]:
                committed_php = lang / f"{cfg['domain']}-{locale}.l10n.php"
                if not committed_php.is_file():
                    fail(f"missing committed PHP catalog: {committed_php.relative_to(ROOT)}")
                php_dir = temp_root / f"php-{locale}"
                generated_php = generate_l10n_php(wp, po, php_dir, committed_php.name)
                if generated_php.read_bytes() != committed_php.read_bytes():
                    fail(f"committed PHP catalog is not reproducible from {po.name}")

    print(
        f"PASS: {cfg['product']} i18n check source=POT=PO "
        f"locales={len(LOCALES)} tooling={I18N_TOOLING_VERSION}"
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("mode", choices=("update", "check"))
    parser.add_argument("--config", default=str(DEFAULT_CONFIG_PATH))
    parser.add_argument("--wp-bin", default="wp")
    parser.add_argument("--msgmerge-bin", default="msgmerge")
    parser.add_argument("--msgattrib-bin", default="msgattrib")
    parser.add_argument("--msgfmt-bin", default="msgfmt")
    parser.add_argument("--git-bin", default="git")
    args = parser.parse_args()

    cfg = load_config(Path(args.config))
    if args.mode == "update":
        do_update(args, cfg)
    else:
        do_check(args, cfg)


if __name__ == "__main__":
    main()
