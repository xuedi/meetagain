/**
 * CMS FactsRow Editor -- add/remove tiles for the FactsRow block edit form
 *
 * Clones a hidden <template> tile into the visible list when "+" is clicked, removes a tile
 * when its "x" button is clicked, hides the "+" once the cap of 6 is reached, and re-numbers
 * all input `name` attributes to `facts[i][icon]` / `facts[i][label]` after every mutation.
 * Also live-renders an icon preview next to the icon input by mirroring its value into the
 * sibling `<i class="...">`.
 *
 * The script self-scopes via getElementById early-return: on any page that does not include
 * the FactsRow editor template, all DOM lookups return null and the module is a no-op. This
 * makes it safe to load on both the per-block edit page and the CMS page-edit page (which
 * embeds the inline new-block form). Without JavaScript, already-saved tiles are still
 * editable and savable; only the "+" button is inert.
 *
 * Loaded in:  templates/admin/cms/cms_block_edit.html.twig (per-block edit page)
 *             templates/admin/cms/cms_edit.html.twig       (page-edit with inline new-block form)
 * Used by:    #facts-row-tiles, #facts-row-tile-template, #facts-row-add, #facts-row-cap-msg,
 *             .facts-row-tile, .facts-row-remove, .facts-row-icon-input, .facts-row-icon-preview
 */

(function () {
    const list = document.getElementById('facts-row-tiles');
    const tpl = document.getElementById('facts-row-tile-template');
    const addBtn = document.getElementById('facts-row-add');
    const capMsg = document.getElementById('facts-row-cap-msg');
    if (!list || !tpl || !addBtn) {
        return;
    }

    const MAX = 6;

    function tiles() {
        return list.querySelectorAll('.facts-row-tile');
    }

    function renumber() {
        const all = tiles();
        all.forEach((tile, i) => {
            tile.querySelectorAll('input[data-name-suffix], input[name^="facts["]').forEach((input) => {
                const suffix = input.dataset.nameSuffix
                    || (input.getAttribute('name') || '').replace(/^facts\[\d+\]/, '');
                if (suffix) {
                    input.setAttribute('name', 'facts[' + i + ']' + suffix);
                }
            });
            const heading = tile.querySelector('p.has-text-weight-bold');
            if (heading) {
                heading.textContent = heading.textContent.replace(/\d+|__N__/, String(i + 1));
            }
        });
        const atCap = all.length >= MAX;
        addBtn.classList.toggle('is-hidden', atCap);
        if (capMsg) {
            capMsg.classList.toggle('is-hidden', !atCap);
        }
    }

    function bindIconPreview(tile) {
        const input = tile.querySelector('.facts-row-icon-input');
        const previewIcon = tile.querySelector('.facts-row-icon-preview > i');
        if (!input || !previewIcon) {
            return;
        }
        const update = () => {
            const raw = input.value.trim();
            // Auto-prepend the FA family class when the admin types only the icon name
            // (e.g. "fa-server" -> "fa fa-server"). FA 6 needs a family class for the font.
            previewIcon.className = raw.startsWith('fa-') ? 'fa ' + raw : raw;
        };
        input.addEventListener('input', update);
        update();
    }

    list.addEventListener('click', (e) => {
        const btn = e.target.closest('.facts-row-remove');
        if (!btn) {
            return;
        }
        const tile = btn.closest('.facts-row-tile');
        if (tile) {
            tile.remove();
        }
        renumber();
    });

    addBtn.addEventListener('click', () => {
        if (tiles().length >= MAX) {
            return;
        }
        const node = tpl.content.firstElementChild.cloneNode(true);
        list.appendChild(node);
        bindIconPreview(node);
        renumber();
    });

    tiles().forEach(bindIconPreview);
    renumber();
}());
