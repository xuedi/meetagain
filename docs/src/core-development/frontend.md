# Frontend

Templates, CSS, admin UI conventions, translations, and HTML sanitization.

---

## Template layout

Templates live in `templates/` and mirror the controller structure:

```
templates/
├── base.html.twig              ← Public site base layout
├── admin/
│   └── base.html.twig          ← Admin section base layout
├── events/
│   ├── index.html.twig         ← Full page
│   ├── details.html.twig       ← Full page
│   └── _event_card.html.twig   ← Fragment (underscore prefix = reusable partial)
├── _components/                ← Reusable snippets across multiple pages
│   └── warning_box.html.twig
└── cms/
    └── blocks/                 ← CMS content-block renderers
```

**Naming conventions:**
- `snake_case` for all template files
- Prefix fragments (partials) with `_` — e.g. `_event_card.html.twig`
- `templates/_components/` for snippets included in multiple different pages
- `templates/{page}/_partials/` for sub-sections specific to one page

**Key blocks** in `base.html.twig` to override in child templates:

| Block | Purpose |
|---|---|
| `title` | `<title>` content |
| `content` | Main page content |
| `stylesheets` | Extra `<link>` tags |
| `javascripts` | Extra `<script>` tags |

---

## Twig namespaces

| Namespace | Resolves to |
|---|---|
| (no prefix) | `templates/` |
| `@PluginName/` | `plugins/plugin-name/templates/` |

Plugin templates always use their own namespace:

```twig
{# In a plugin template: #}
{% extends '@MyPlugin/base.html.twig' %}

{# Core template including a plugin partial: #}
{{ plugin.getEventTile(event.id)|raw }}
```

---

## Bulma CSS

The entire UI uses [Bulma](https://bulma.io/) — no other CSS framework. Keep styling minimal.

**Use these elements:**

```twig
{# Layout #}
<div class="container">
<div class="columns">
<div class="column is-6">

{# Tables #}
<table class="table is-fullwidth">

{# Forms #}
<div class="field">
<div class="control">
<input class="input" type="text">
<div class="select">
<textarea class="textarea">

{# Buttons — only for form submits #}
<button class="button is-primary">Save</button>
<a href="..." class="button is-light">Cancel</a>
```

**Avoid:** `box`, `card`, `notification`, `message`, `hero`, `section`, `tag` (for status),
`buttons` group wrapper. These add visual weight without benefit.

**Icons:** Font Awesome via `<span class="icon"><i class="fa fa-edit"></i></span>`.

---

## Admin UI conventions

The admin interface follows a minimal design philosophy. See the full spec in
[design guidelines](.claude/docs/core/design.md).

### Table lists

The standard pattern for list pages:

```twig
<div class="container">
    <table id="filteredTable" class="table is-fullwidth">
        <thead>
        <tr>
            <th>Name</th>
            <th>Status</th>
            {# Create action in last header column #}
            <th>
                <a href="{{ path('app_admin_entity_create') }}">
                    <span class="icon"><i class="fa fa-plus"></i></span>
                </a>
            </th>
        </tr>
        </thead>
        <tbody>
        {% for item in items %}
            <tr class="{{ item.canceled ? 'has-background-danger-light' : '' }}">
                <td>{{ item.name }}</td>
                <td>{{ item.active ? 'Active' : 'Inactive' }}</td>  {# plain text, not tags #}
                <td>
                    <a href="{{ path('app_admin_entity_edit', {'id': item.id}) }}">
                        <span class="icon"><i class="fa fa-edit"></i></span>
                    </a>
                    <a href="{{ path('app_admin_entity_delete', {'id': item.id}) }}">
                        <span class="icon"><i class="fa fa-trash"></i></span>
                    </a>
                </td>
            </tr>
        {% endfor %}
        </tbody>
    </table>
</div>
```

**Standard action icons:**

| Icon | FA class | Use for |
|---|---|---|
| Edit | `fa-edit` | Navigate to edit form |
| Delete | `fa-trash` | Navigate to delete confirmation |
| Create | `fa-plus` | In table header only |
| Cancel | `fa-ban has-text-danger` | Destructive state change |
| Approve | `fa-check has-text-success` | Positive state change |

### Row colour coding

Use light background variants to indicate row state:

| Class | When to use |
|---|---|
| `has-background-danger-light` | Canceled / inactive |
| `has-background-info-light` | Recurring / special |
| `has-background-warning-light` | Featured / next |

### Delete warning boxes

Prefer the reusable warning box component over `onclick="confirm()"` dialogs:

```twig
{% include '_components/warning_box.html.twig' with {
    'id': 'entity-delete-warning',
    'title': 'Delete Entity',
    'content': '<p>This will permanently delete the entity and all related data.</p>',
    'buttonLabel': 'Yes, Delete',
    'buttonUrl': path('app_admin_entity_delete', {'id': entity.id}),
    'type': 'danger'
} %}
```

---

## Translations

Translation keys use `snake_case` and are scoped by concept:

```twig
{# In templates #}
{{ 'event.title.label'|trans }}
{{ 'action.save'|trans }}
{{ 'user.email.placeholder'|trans }}
```

Translation files live in `translations/`:

```
translations/
├── messages.en.yaml
├── messages.de.yaml
└── messages.cn.yaml
```

YAML format — quote values that contain special characters:

```yaml
event.title.label: 'Event title'
event.rsvp.confirm: 'Are you sure you want to RSVP?'
action.save: 'Save'
```

**Workflow for adding new keys:**

1. Add the key and English value to `translations/messages.en.yaml`
2. Run `just translationExtract` to scan all templates for new keys
3. Run `/fill-translations` in Claude Code to fill missing DE/CN keys

---

## HTML sanitization

CMS page content can contain HTML entered by admins. Never use
`|raw` in CMS templates — use `|sanitize_html` instead:

```twig
{# ✅ Safe — strips disallowed tags #}
{{ block.content|sanitize_html('cms.content') }}

{# ❌ Dangerous — XSS risk #}
{{ block.content|raw }}
```

The allowlist is configured in `config/packages/html_sanitizer.yaml`. The `cms.content`
profile allows common formatting tags (`<p>`, `<a>`, `<strong>`, `<ul>`, `<img>`, etc.)
while stripping scripts and event attributes.

For non-CMS content (descriptions entered through validated Symfony forms), standard Twig
auto-escaping is sufficient — no `|raw` or `|sanitize_html` needed.

---

## JavaScript

JavaScript is **progressive enhancement only**. The site must be fully functional without JS.

```twig
{# ✅ Works with and without JS #}
<a href="{{ path('app_event_toggle_rsvp', {event: event.id}) }}"
   class="button is-primary"
   data-action="ajax-toggle-rsvp">
    RSVP
</a>
{# Without JS: full page navigation to controller and back
   With JS: AJAX intercepts, updates UI in place #}

{# ❌ Broken without JS #}
<button onclick="submitRsvp({{ event.id }})">RSVP</button>
```

No inline `<script>` blocks in templates. Use `data-action` attributes and external JS files.
