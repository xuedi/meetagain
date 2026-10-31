/**
 * city-picker — Search-as-you-type for the city field.
 *
 * Every `[data-city-picker]` block holds a country select, a search box and a hidden city id.
 * Typing asks the geo module's city search for matches in the chosen country (or anywhere) and
 * lists them under the box; picking one fills the box and the id. Any later keystroke or a new
 * country clears the id, so the form only ever submits a city the directory returned.
 *
 * Loaded in:  templates/base.html.twig (all pages), App\AssetMapper\AppBundle::SOURCES
 * Used by:    templates/_form/city.html.twig ([data-city-picker], [data-city-picker-search],
 *             [data-city-picker-country], [data-city-picker-id], [data-city-picker-results])
 * Depends on: ma-fetch.js (maFetch)
 */

document.addEventListener('DOMContentLoaded', function () {
    const MIN_LENGTH = 2;
    const DELAY_MS = 250;

    document.querySelectorAll('[data-city-picker]').forEach((picker) => {
        const search = picker.querySelector('[data-city-picker-search]');
        const country = picker.querySelector('[data-city-picker-country]');
        const id = picker.querySelector('[data-city-picker-id]');
        const dropdown = picker.querySelector('[data-city-picker-dropdown]');
        const results = picker.querySelector('[data-city-picker-results]');
        if (!search || !id || !dropdown || !results) return;

        let timer = null;
        let latest = 0;

        const close = () => dropdown.classList.remove('is-active');

        const pick = (city) => {
            id.value = city.id;
            search.value = city.label;
            if (country && !country.value) country.value = city.country;
            close();
        };

        const render = (cities) => {
            results.replaceChildren();
            if (cities.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'dropdown-item has-text-grey';
                empty.textContent = picker.dataset.cityPickerEmpty || '';
                results.append(empty);
            }
            cities.forEach((city) => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'dropdown-item';
                item.textContent = city.label;
                item.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    pick(city);
                });
                results.append(item);
            });
            dropdown.classList.add('is-active');
        };

        const lookup = () => {
            const query = search.value.trim();
            if (query.length < MIN_LENGTH) {
                close();
                return;
            }
            const url = new URL(picker.dataset.cityPickerUrl, window.location.origin);
            url.searchParams.set('q', query);
            if (country && country.value) url.searchParams.set('country', country.value);
            const request = ++latest;
            maFetch(url.toString(), true).then((cities) => {
                if (request === latest && Array.isArray(cities)) render(cities);
            }).catch(close);
        };

        search.addEventListener('input', () => {
            id.value = '';
            clearTimeout(timer);
            timer = setTimeout(lookup, DELAY_MS);
        });
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
            if (event.key === 'Enter' && dropdown.classList.contains('is-active')) {
                const first = results.querySelector('a.dropdown-item');
                if (first) {
                    event.preventDefault();
                    first.dispatchEvent(new MouseEvent('mousedown'));
                }
            }
        });
        search.addEventListener('blur', close);
        if (country) {
            country.addEventListener('change', () => {
                id.value = '';
                search.value = '';
                close();
            });
        }
    });
});
