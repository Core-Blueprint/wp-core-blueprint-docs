(() => {
	'use strict';

	const roots = document.querySelectorAll('[data-cb-docs-search]');
	if (!roots.length) {
		return;
	}

	roots.forEach((root) => {
		const form = root.querySelector('.cb-docs-search__form');
		const input = root.querySelector('.cb-docs-search__input');
		const results = root.querySelector('.cb-docs-search__results');
		const list = root.querySelector('.cb-docs-search__list');
		const status = root.querySelector('[data-cb-docs-search-status]');
		if (!form || !input || !results || !list || !status) {
			return;
		}

		const endpoint = root.dataset.endpoint || '';
		const limit = Math.max(1, Math.min(50, Number.parseInt(root.dataset.limit || '20', 10) || 20));
		const minChars = Math.max(1, Math.min(10, Number.parseInt(root.dataset.minChars || '2', 10) || 2));
		const showExcerpt = root.dataset.showExcerpt === '1';
		const showCategory = root.dataset.showCategory === '1';
		const category = root.dataset.category || '';
		const tag = root.dataset.tag || '';
		const loadingLabel = root.dataset.loadingLabel || 'Searching…';
		const noResultsLabel = root.dataset.noResultsLabel || 'No matching documentation found.';
		const errorLabel = root.dataset.errorLabel || 'Live search is temporarily unavailable. Submit the form to search.';
		let debounceTimer = 0;
		let request = null;
		let activeIndex = -1;

		const options = () => Array.from(list.querySelectorAll('.cb-docs-search__option'));

		const setExpanded = (expanded) => {
			input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			results.hidden = !expanded;
		};

		const clearActive = () => {
			options().forEach((option) => option.setAttribute('aria-selected', 'false'));
			activeIndex = -1;
			input.removeAttribute('aria-activedescendant');
		};

		const setActive = (index) => {
			const items = options();
			if (!items.length) {
				clearActive();
				return;
			}

			activeIndex = Math.max(0, Math.min(items.length - 1, index));
			items.forEach((option, optionIndex) => {
				option.setAttribute('aria-selected', optionIndex === activeIndex ? 'true' : 'false');
			});

			const active = items[activeIndex];
			if (active.id) {
				input.setAttribute('aria-activedescendant', active.id);
			}
			active.scrollIntoView({ block: 'nearest' });
		};

		const resetResults = () => {
			if (request) {
				request.abort();
				request = null;
			}
			window.clearTimeout(debounceTimer);
			list.replaceChildren();
			status.textContent = '';
			clearActive();
			setExpanded(false);
		};

		const createText = (className, text) => {
			const node = document.createElement('span');
			node.className = className;
			node.textContent = text;
			return node;
		};

		const renderItems = (items) => {
			list.replaceChildren();
			clearActive();

			if (!Array.isArray(items) || !items.length) {
				status.textContent = noResultsLabel;
				setExpanded(true);
				return;
			}

			status.textContent = '';
			items.forEach((item, index) => {
				if (!item || !item.title || !item.permalink) {
					return;
				}

				const option = document.createElement('li');
				option.className = 'cb-docs-search__option';
				option.id = `${input.id}-option-${index + 1}`;
				option.setAttribute('role', 'option');
				option.setAttribute('aria-selected', 'false');

				const link = document.createElement('a');
				link.className = 'cb-docs-search__result';
				link.href = item.permalink;
				link.append(createText('cb-docs-search__title', item.title));

				if (showCategory && Array.isArray(item.categories) && item.categories[0] && item.categories[0].name) {
					link.append(createText('cb-docs-search__category', item.categories[0].name));
				}

				if (showExcerpt && item.excerpt) {
					link.append(createText('cb-docs-search__excerpt', item.excerpt));
				}

				option.append(link);
				list.append(option);
			});

			if (!options().length) {
				status.textContent = noResultsLabel;
			}
			setExpanded(true);
		};

		const search = async (term) => {
			if (!endpoint) {
				return;
			}

			if (request) {
				request.abort();
			}
			request = new AbortController();
			status.textContent = loadingLabel;
			list.replaceChildren();
			clearActive();
			setExpanded(true);

			const url = new URL(endpoint, window.location.origin);
			url.searchParams.set('q', term);
			url.searchParams.set('limit', String(limit));
			if (category) {
				url.searchParams.set('category', category);
			}
			if (tag) {
				url.searchParams.set('tag', tag);
			}

			try {
				const response = await fetch(url.toString(), {
					method: 'GET',
					headers: { Accept: 'application/json' },
					signal: request.signal,
					credentials: 'same-origin',
				});
				if (!response.ok) {
					throw new Error(`Docs search request failed with ${response.status}`);
				}

				const payload = await response.json();
				if (input.value.trim() !== term) {
					return;
				}
				renderItems(payload.items || []);
			} catch (error) {
				if (error && error.name === 'AbortError') {
					return;
				}
				status.textContent = errorLabel;
				list.replaceChildren();
				clearActive();
				setExpanded(true);
			} finally {
				request = null;
			}
		};

		input.addEventListener('input', () => {
			const term = input.value.trim();
			window.clearTimeout(debounceTimer);
			if (term.length < minChars) {
				resetResults();
				return;
			}
			debounceTimer = window.setTimeout(() => search(term), 250);
		});

		input.addEventListener('keydown', (event) => {
			const items = options();
			if (event.key === 'ArrowDown' && items.length) {
				event.preventDefault();
				setActive(activeIndex < items.length - 1 ? activeIndex + 1 : 0);
				return;
			}
			if (event.key === 'ArrowUp' && items.length) {
				event.preventDefault();
				setActive(activeIndex > 0 ? activeIndex - 1 : items.length - 1);
				return;
			}
			if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
				const link = items[activeIndex].querySelector('a[href]');
				if (link) {
					event.preventDefault();
					window.location.assign(link.href);
				}
				return;
			}
			if (event.key === 'Escape') {
				clearActive();
				setExpanded(false);
			}
		});

		input.addEventListener('focus', () => {
			if (options().length || status.textContent) {
				setExpanded(true);
			}
		});

		document.addEventListener('pointerdown', (event) => {
			if (!root.contains(event.target)) {
				clearActive();
				setExpanded(false);
			}
		});
	});
})();
