# Design Guidelines

**Quick Links:** [Architecture](architecture.md) | [Conventions](conventions.md) | [Testing](testing.md)

---

## Design Philosophy

**Principle:** Light, simple, minimal design. Less is more.

- Prefer simple text over styled components
- Avoid unnecessary visual noise (colors, buttons, boxes)
- Use icons for actions instead of buttons
- Keep layouts clean and spacious
- Only use color for meaningful distinctions (row backgrounds, state indicators)
- **Use very simple Bulma elements** - stick to basics (table, input, select, field)
- **Don't over-explain with info boxes** - users can see what's on the page
- Trust users to understand the interface without hand-holding

---

## Admin Interface Design

### Table Lists (Primary Pattern)

**Reference Implementation:** `plugins/adminTables/templates/tables/event_list.html.twig`

#### Basic Structure

```twig
<div class="container">
    <table id="filteredTable" class="table is-fullwidth">
        <thead>
        <tr>
            <th>Column 1</th>
            <th>Column 2</th>
            <th><a href="{{ path('app_admin_entity_create') }}"><span class="icon"><i class="fa fa-plus"></i></span></a></th>
        </tr>
        </thead>
        <tbody>
        {% for item in items %}
            <tr>
                <td>{{ item.name }}</td>
                <td>{{ item.value }}</td>
                <td>
                    <a href="{{ path('app_admin_entity_edit', {'id': item.id}) }}">
                        <span class="icon"><i class="fa fa-edit"></i></span>
                    </a>
                </td>
            </tr>
        {% endfor %}
        </tbody>
    </table>
</div>
```

#### Key Design Patterns

**✅ DO:**
- Use simple `<a>` tags with icon spans for actions
- Put "create" icon in table header (last column)
- Use warning boxes for destructive actions (see "Delete Warning Boxes" section)
- Use row background colors for state (canceled, recurring, featured)
- Keep action icons inline, no button wrapping
- Use plain text for status instead of tags

**❌ DON'T:**
- Don't wrap icons in `<button>` elements
- Don't use `<form>` for simple GET actions
- Don't use colored button classes (`is-primary`, `is-success`, etc.)
- Don't use notification boxes for obvious information ("Showing X items")
- Don't add unnecessary headers/titles above tables
- Don't use tags for simple status (use plain text instead)

### Action Icons

**Standard Icon Set:**

```twig
{# Edit #}
<a href="{{ path('...edit', {'id': item.id}) }}">
    <span class="icon"><i class="fa fa-edit"></i></span>
</a>

{# Delete #}
<a href="{{ path('...delete', {'id': item.id}) }}">
    <span class="icon"><i class="fa fa-trash"></i></span>
</a>

{# Create (in header) #}
<a href="{{ path('...create') }}">
    <span class="icon"><i class="fa fa-plus"></i></span>
</a>

{# Cancel #}
<a href="{{ path('...cancel', {'id': item.id}) }}" onclick="return confirm('...')">
    <span class="icon has-text-danger"><i class="fa fa-ban"></i></span>
</a>

{# Uncancel #}
<a href="{{ path('...uncancel', {'id': item.id}) }}" onclick="return confirm('...')">
    <span class="icon has-text-success"><i class="fa fa-check"></i></span>
</a>

{# Featured indicator (non-clickable) #}
<span class="icon has-text-warning"><i class="fa fa-star"></i></span>
```

**Icon Colors:**
- **Default** (no color): Edit, delete, view, general actions
- **`has-text-danger`**: Destructive/negative actions (cancel, ban)
- **`has-text-success`**: Positive actions (uncancel, approve)
- **`has-text-warning`**: Featured/important indicators

### Delete Warning Boxes

**Reusable Block:** `templates/_block/warning_box.html.twig`
**Reference Implementation:** `templates/admin/cms/cms_edit.html.twig`

**Prefer warning boxes over JavaScript `onclick="confirm()"` dialogs** for better UX and consistency.

```twig
{# Use the reusable warning box block #}
{% include '_block/warning_box.html.twig' with {
    'id': 'entity-delete-warning',
    'title': 'Delete [Entity Name]',
    'content': '<p>Warning text explaining consequences of deletion. Be specific about what will be deleted.</p>',
    'buttonLabel': 'Yes, Delete',
    'buttonUrl': path('app_admin_entity_delete', {'id': entity.id}),
    'type': 'danger'
} %}

{# Danger Zone - trigger button in right sidebar #}
<fieldset class="mt-5 p-4" style="border: 2px solid #f14668; border-radius: 4px;">
    <legend class="px-2 has-text-danger has-text-weight-semibold is-size-7">Danger Zone</legend>

    <a class="button is-light is-fullwidth toggleTrigger" data-id="entity-delete-warning"
       href="{{ path('app_admin_entity_delete', {'id': entity.id}) }}">
        <span class="icon"><i class="fa fa-trash"></i></span>
        <span>Delete [Entity]</span>
    </a>
</fieldset>
```

**Parameters:**
- `id` - Unique ID for the warning box (required)
- `title` - Header title (required)
- `content` - Warning message HTML (required)
- `buttonLabel` - Confirmation button label (optional)
- `buttonUrl` - Delete action URL (optional)
- `type` - 'danger' (red, default) or 'warning' (orange)
- `showButton` - Whether to show confirmation button (default: true if buttonUrl set)

**Styling:**
- Red (`danger`) or orange (`warning`) header, white body with black text
- Grey borders applied automatically via global CSS (`custom.css`)
- Confirmation button uses plain `button` class (no color - context already clear)
- Trigger button uses `is-light` for subtle appearance

### Row Color Coding

Use subtle background colors on table rows to indicate state:

```twig
{% set textColor = '' %}
{% if item.canceled %}
    {% set textColor = 'has-background-danger-light' %}
{% elseif item.isSpecial %}
    {% set textColor = 'has-background-info-light' %}
{% endif %}
<tr class="{{ textColor }}">
```

**Standard Row Colors:**
- **`has-background-danger-light`**: Canceled/inactive items
- **`has-background-info-light`**: Recurring/special items
- **`has-background-warning-light`**: Next/featured items
- **`has-text-grey-light`**: Auto-generated/secondary items
- **White (default)**: Normal items

### Status Display

**Simple text, not tags:**

```twig
{# ✅ DO #}
<td>
    {% if item.published %}
        {{ 'published'|trans }}
    {% else %}
        {{ 'draft'|trans }}
    {% endif %}
    {% if item.canceled %}
        , {{ 'Canceled'|trans }}
    {% endif %}
</td>

{# ❌ DON'T #}
<td>
    <span class="tag is-success">Published</span>
    <span class="tag is-danger">Canceled</span>
</td>
```

### Search and Filters

**Keep minimal and compact:**

```twig
<form method="get" action="{{ path('...') }}" class="mb-4">
    <div class="columns is-multiline">
        <div class="column is-6">
            <input type="text" name="search" value="{{ filters.search }}" class="input" placeholder="Search...">
        </div>
        <div class="column is-3">
            <div class="select is-fullwidth">
                <select name="filter">...</select>
            </div>
        </div>
        <div class="column is-3">
            <button type="submit" class="button">Filter</button>
            <a href="{{ path('...') }}" class="button is-light">Clear</a>
        </div>
    </div>
</form>
```

**Filter Guidelines:**
- No box/card wrapper (just `class="mb-4"`)
- Compact layout (use columns)
- Simple labels, minimal help text
- Only 2-3 rows of filters maximum
- Clear button to reset

---

## Form Pages

### Edit Forms

**Reference Implementation:** `plugins/adminTables/templates/tables/event_edit.html.twig`

**Minimal structure:**

```twig
{% extends 'admin/base.html.twig' %}

{% block content %}
<div class="container">
    {{ form_start(form) }}
        <div class="columns is-multiline">
            <div class="column is-6">
                <div class="field">
                    {{ form_label(form.fieldName) }}
                    <div class="control">
                        {{ form_widget(form.fieldName, {'attr': {'class': 'input'}}) }}
                    </div>
                    {{ form_errors(form.fieldName) }}
                </div>
            </div>
        </div>

        <div class="field">
            <button type="submit" class="button is-primary">Save</button>
            <a href="{{ path('...list') }}" class="button is-light">Cancel</a>
        </div>
    {{ form_end(form) }}
</div>
{% endblock %}
```

**Form Guidelines:**
- No unnecessary headers/titles above forms
- Use Bulma columns for layout (2-3 columns typical)
- Group related fields together
- Use `class="mb-4"` for section spacing instead of `<hr>` or boxes
- Submit button: `is-primary`, cancel button: `is-light`

---

## Navigation

### Admin Menu

Uses `AdminNavigationInterface` pattern - navigation is auto-generated from controllers.

**Simple navigation config:**

```php
public function getAdminNavigation(): ?AdminNavigationConfig
{
    return AdminNavigationConfig::single(
        section: 'System',
        label: 'menu_admin_entity',
        route: 'app_admin_entity',
        active: 'entity',
        linkRole: 'ROLE_ORGANIZER',
    );
}
```

**No custom navigation styling needed** - the base admin template handles it.

---

## Component Usage

### When to Use Bulma Components

**✅ Use (Simple Elements Only):**
- `table` - For all list views
- `input`, `select`, `textarea` - Form fields
- `button` (minimal usage) - Only for form submits
- `columns` - Layout grid
- `field`, `control` - Form structure

**❌ Avoid (Complex/Decorative Elements):**
- `box`, `card` - Don't wrap content unnecessarily
- `tag` - Don't use for status (use plain text)
- `buttons` group - Don't wrap action icons
- `hero`, `section` - Don't add visual weight
- `level` - Don't use for simple headers
- `notification`, `message` - See "Info Boxes" section below

### Info Boxes - When NOT to Use Them

**Don't use for:** row counts ("Showing 10 events"), obvious instructions ("click edit to edit"), self-explanatory page descriptions, or success messages for expected behavior.

**Only use for:**
- ✅ Critical errors (database failures, save errors)
- ✅ Unexpected states requiring action (no permissions, misconfiguration)
- ✅ Flash messages confirming user-initiated actions

**Guideline:** If the user can see it on the page, don't explain it with a box.

### Color Usage

**Minimize color usage:**

- **Primary (`is-primary`)**: Only form submit buttons
- **Success (`has-text-success`)**: Positive action icons only
- **Danger (`has-text-danger`)**: Destructive action icons only
- **Warning (`has-text-warning`)**: Featured indicators only
- **Light (`is-light`)**: Cancel/secondary buttons

**Background colors (row states):**
- Use `-light` variants for subtle distinction
- Only on table rows, not on wrappers/boxes

---

## Pagination

**Simple pagination:**

```twig
{% if totalPages > 1 %}
<nav class="pagination is-centered" role="navigation">
    {% if page > 1 %}
        <a href="{{ path('...', filters|merge({'page': page - 1})) }}" class="pagination-previous">Previous</a>
    {% else %}
        <a class="pagination-previous" disabled>Previous</a>
    {% endif %}

    {% if page < totalPages %}
        <a href="{{ path('...', filters|merge({'page': page + 1})) }}" class="pagination-next">Next</a>
    {% else %}
        <a class="pagination-next" disabled>Next</a>
    {% endif %}

    <ul class="pagination-list">
        {% for p in 1..totalPages %}
            {% if p == page %}
                <li><a class="pagination-link is-current">{{ p }}</a></li>
            {% else %}
                <li><a href="{{ path('...', filters|merge({'page': p})) }}" class="pagination-link">{{ p }}</a></li>
            {% endif %}
        {% endfor %}
    </ul>
</nav>
{% endif %}
```

**Guidelines:**
- Only show if more than 1 page
- Center alignment
- Show all page numbers (for reasonable counts)
- Disable prev/next at boundaries

---

## Legend/Help Text

**Minimal legends:**

Only include legend if row colors aren't obvious:

```twig
<div class="mt-4 has-text-centered">
    <span class="tag has-background-info-light">Recurring events</span>
    <span class="tag has-background-danger-light">Canceled events</span>
</div>
```

**Guidelines:**
- Use tags for legend items (one of few acceptable tag uses)
- Center aligned
- Bottom of page only
- Maximum 3-5 legend items

---

## Examples

### Good vs Bad

```twig
{# ✅ GOOD - adminTables style #}
<table id="filteredTable" class="table is-fullwidth">
    <thead><tr>
        <th>Name</th><th>Status</th>
        <th><a href="{{ path('app_admin_user_add') }}"><span class="icon"><i class="fa fa-plus"></i></span></a></th>
    </tr></thead>
    <tbody>{% for user in users %}
        <tr>
            <td>{{ user.name }}</td>
            <td>{{ user.active ? 'Active' : 'Inactive' }}</td>  {# plain text, not tags #}
            <td>
                <a href="{{ path('app_admin_user_edit', {'id': user.id}) }}"><span class="icon"><i class="fa fa-edit"></i></span></a>
                <a href="{{ path('app_admin_user_delete', {'id': user.id}) }}"><span class="icon"><i class="fa fa-trash"></i></span></a>
            </td>
        </tr>
    {% endfor %}</tbody>
</table>

{# ❌ BAD - over-styled: box wrapper, notification, level header, tag status, button elements #}
<div class="box">
    <div class="notification is-info">Showing 10 users</div>
    <table class="table"><tbody>{% for user in users %}<tr>
        <td>{{ user.name }}</td>
        <td><span class="tag is-{{ user.active ? 'success' : 'danger' }}">{{ user.active ? 'Active' : 'Inactive' }}</span></td>
        <td><div class="buttons"><button class="button is-info is-small">Edit</button></div></td>
    </tr>{% endfor %}</tbody></table>
</div>
```

---

## Summary Checklist

When creating admin interfaces:

- [ ] Use simple icon links for actions (no buttons)
- [ ] Put create icon in table header
- [ ] Use plain text for status (not tags)
- [ ] Only use row background colors for state
- [ ] Keep forms minimal (no unnecessary wrappers)
- [ ] No boxes/cards unless absolutely necessary
- [ ] Minimize color usage (only for meaningful distinctions)
- [ ] Use warning boxes for destructive actions (prefer over `onclick="confirm()"`)
- [ ] Keep pagination simple and centered
- [ ] Only show legends if colors aren't obvious
- [ ] **Use very simple Bulma elements** (table, input, select, field, control)
- [ ] **Don't add info boxes for obvious things** (row counts, instructions, help text)
- [ ] **Trust users** - if they can see it, don't explain it

**When in doubt, reference adminTables templates for the preferred style.**
