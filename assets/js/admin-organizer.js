const dataEl = document.getElementById('wp-script-module-data-@cb-docs/organizer');
let data = {};
try {
	data = dataEl ? JSON.parse(dataEl.textContent) : {};
} catch {
	data = {};
}

const root = document.querySelector('[data-cb-docs-organizer]');
const reorder = window.cbCore?.reorder;

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

if (root && reorder?.enhance) {
	const controller = reorder.enhance(root, {
		crossList: true,

		canMove(move) {
			const kind = itemKind(move.itemId);
			if (kind === 'term') {
				return move.from.listId === move.to.listId && move.to.listId.startsWith('terms:');
			}
			if (kind === 'doc') {
				return /^docs:\d+$/.test(move.to.listId);
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
				if (row) row.dataset.currentTerm = String(targetTermId);
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
} else if (root) {
	root.classList.add('is-reorder-unavailable');
}
