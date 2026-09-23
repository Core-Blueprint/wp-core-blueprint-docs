const dataEl = document.getElementById('wp-script-module-data-@cb-docs/organizer');
let data = {};
try {
	data = dataEl ? JSON.parse(dataEl.textContent) : {};
} catch {
	data = {};
}

const root = document.querySelector('[data-cb-docs-organizer]');
const reorder = window.cbCore?.reorder;
const storageKey = String(data.stateKey || 'cb-docs-organizer:v1');

const request = async (endpoint, payload) => {
	const response = await fetch(endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': data.nonce || '',
		},
		body: JSON.stringify(payload),
	});

	let body = {};
	try {
		body = await response.json();
	} catch {
		body = {};
	}

	if (!response.ok) {
		const error = new Error(body?.message || data.i18n?.saveFailed || 'The documentation structure could not be saved.');
		error.status = response.status;
		error.code = body?.code || '';
		error.data = body?.data || {};
		throw error;
	}

	return body;
};

const itemKind = (itemId) => String(itemId || '').split(':', 1)[0];

const readDisclosureState = () => {
	try {
		const parsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
		if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {};

		return Object.fromEntries(
			Object.entries(parsed).filter(([, value]) => typeof value === 'boolean')
		);
	} catch {
		return {};
	}
};

let disclosureState = readDisclosureState();

const writeDisclosureState = () => {
	try {
		window.localStorage.setItem(storageKey, JSON.stringify(disclosureState));
	} catch {
		// Browser storage is an optional convenience, never a functional dependency.
	}
};

const setTermExpanded = (section, expanded, persist = true) => {
	if (!section) return;

	const termId = String(section.dataset.cbDocsTermId || '');
	const toggle = section.querySelector(':scope > .cb-docs-organizer__term-header [data-cb-docs-toggle-term]');
	const content = section.querySelector(':scope > [data-cb-docs-term-content]');
	if (!toggle || !content) return;

	toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
	content.hidden = !expanded;
	section.dataset.cbDocsExpanded = expanded ? '1' : '0';

	if (persist && termId) {
		disclosureState[termId] = expanded;
		writeDisclosureState();
	}
};

const initializeDisclosure = () => {
	if (!root) return;

	const liveTermIds = new Set();
	root.querySelectorAll('[data-cb-docs-term-id]').forEach((section) => {
		const termId = String(section.dataset.cbDocsTermId || '');
		if (!termId) return;
		liveTermIds.add(termId);

		const defaultExpanded = section.dataset.cbDocsDefaultExpanded === '1';
		const expanded = Object.prototype.hasOwnProperty.call(disclosureState, termId)
			? disclosureState[termId]
			: defaultExpanded;
		setTermExpanded(section, expanded, false);
	});

	disclosureState = Object.fromEntries(
		Object.entries(disclosureState).filter(([termId]) => liveTermIds.has(termId))
	);
	writeDisclosureState();
};

const setAllTermsExpanded = (expanded) => {
	if (!root) return;

	root.querySelectorAll('[data-cb-docs-term-id]').forEach((section) => {
		const termId = String(section.dataset.cbDocsTermId || '');
		setTermExpanded(section, expanded, false);
		if (termId) disclosureState[termId] = expanded;
	});
	writeDisclosureState();
};

const syncMoveToControl = (row, targetTermId) => {
	const select = row?.querySelector?.('[data-cb-docs-move-to]');
	if (!select) return;

	Array.from(select.options).forEach((option) => {
		const optionTermId = Number.parseInt(option.value, 10);
		option.disabled = Number.isInteger(optionTermId) && optionTermId === targetTermId;
	});
};

const countLabel = (count) => {
	const template = count === 1
		? (data.i18n?.documentOne || '%d document')
		: (data.i18n?.documentMany || '%d documents');
	return template.replace('%d', String(count));
};

const syncDocumentCounts = () => {
	if (!root) return;

	root.querySelectorAll('[data-cb-docs-term-id]').forEach((section) => {
		const countNode = section.querySelector(':scope > .cb-docs-organizer__term-header [data-cb-docs-document-count]');
		if (!countNode) return;

		const count = section.querySelectorAll('[data-cb-docs-kind="doc"]').length;
		countNode.textContent = countLabel(count);
	});
};

const syncOrganizerState = () => {
	if (!root) return;

	root.querySelectorAll('[data-cb-core-reorder-list]').forEach((list) => {
		const hasItems = Array.from(list.children).some((child) => child.matches?.('[data-cb-core-reorder-item]'));
		list.dataset.cbDocsEmpty = hasItems ? '0' : '1';
	});
	syncDocumentCounts();
};

if (root) {
	initializeDisclosure();

	root.addEventListener('click', (event) => {
		const toggle = event.target.closest?.('[data-cb-docs-toggle-term]');
		if (toggle && root.contains(toggle)) {
			const section = toggle.closest('[data-cb-docs-term-id]');
			const expanded = toggle.getAttribute('aria-expanded') === 'true';
			setTermExpanded(section, !expanded);
			return;
		}

		if (event.target.closest?.('[data-cb-docs-expand-all]')) {
			setAllTermsExpanded(true);
			return;
		}

		if (event.target.closest?.('[data-cb-docs-collapse-all]')) {
			setAllTermsExpanded(false);
		}
	});
}

if (root && reorder?.enhance) {
	const controller = reorder.enhance(root, {
		crossList: true,

		canMove(move) {
			const kind = itemKind(move.itemId);
			if (kind === 'term') {
				return move.from.listId === move.to.listId && move.to.listId.startsWith('terms:');
			}
			if (kind === 'doc') {
				if (!/^docs:\d+$/.test(move.to.listId)) return false;
				if (move.from.listId === move.to.listId) return true;

				const item = root.querySelector(`[data-cb-core-reorder-item="${move.itemId}"]`);
				return item?.dataset?.canAssign === '1';
			}
			return false;
		},

		async onMove(move) {
			const revision = root.dataset.revision || '';
			const kind = itemKind(move.itemId);

			if (kind === 'term') {
				const termId = Number.parseInt(move.itemId.slice(5), 10);
				const result = await request(data.termEndpoint, {
					term_id: termId,
					target_index: move.to.index,
					revision,
				});
				root.dataset.revision = result.revision || revision;
				return true;
			}

			if (kind === 'doc') {
				const documentId = Number.parseInt(move.itemId.slice(4), 10);
				const targetTermId = Number.parseInt(move.to.listId.slice(5), 10);
				const result = await request(data.documentEndpoint, {
					document_id: documentId,
					target_term_id: targetTermId,
					target_index: move.to.index,
					revision,
				});
				root.dataset.revision = result.revision || revision;
				const row = root.querySelector(`[data-cb-core-reorder-item="doc:${documentId}"]`);
				if (row) {
					row.dataset.currentTerm = String(targetTermId);
					syncMoveToControl(row, targetTermId);
				}
				return true;
			}

			return false;
		},
	});

	root.addEventListener('click', (event) => {
		const button = event.target.closest?.('[data-cb-docs-move-up], [data-cb-docs-move-down]');
		if (!button) return;

		const item = button.closest('[data-cb-core-reorder-item]');
		const itemId = item?.dataset?.cbCoreReorderItem || '';
		if (!itemId) return;

		if (button.matches('[data-cb-docs-move-up]')) {
			void controller.moveUp(itemId);
		} else {
			void controller.moveDown(itemId);
		}
	});

	root.addEventListener('change', (event) => {
		const select = event.target.closest?.('[data-cb-docs-move-to]');
		if (!select) return;

		const item = select.closest('[data-cb-core-reorder-item]');
		const itemId = item?.dataset?.cbCoreReorderItem || '';
		const targetTermId = Number.parseInt(select.value, 10);
		if (!itemId || !Number.isInteger(targetTermId) || targetTermId <= 0) {
			select.value = '';
			return;
		}

		const targetListId = `docs:${targetTermId}`;
		const target = controller.snapshot().find((list) => list.listId === targetListId);
		if (!target) {
			select.value = '';
			return;
		}

		void controller.move(itemId, targetListId, target.itemIds.length).then(() => {
			select.value = '';
		});
	});

	root.addEventListener('cb:reorder:change', syncOrganizerState);
	root.addEventListener('cb:reorder:error', syncOrganizerState);
	syncOrganizerState();
} else if (root) {
	root.classList.add('is-reorder-unavailable');
}
