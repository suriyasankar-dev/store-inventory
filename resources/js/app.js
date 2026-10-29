import './bootstrap';

const productRows = document.querySelector('#product-rows');
const orderForm = document.querySelector('#order-form');
const orderAlert = document.querySelector('#order-alert');
const orderAlertMessage = document.querySelector('#order-alert-message');
const moneyFormatter = new Intl.NumberFormat('en-IN', {
	style: 'currency',
	currency: 'INR',
	minimumFractionDigits: 2,
});

if (productRows && orderForm) {
	const products = new Map();
	const lowStockThreshold = Number(document.body.dataset.lowStockThreshold || 5);
	const customerEmail = document.querySelector('#customer-email');
	const customerName = document.querySelector('#customer-name');
	const customerHint = document.querySelector('#customer-hint');
	const amountReceived = document.querySelector('#amount-received');
	let customerNameEdited = false;
	let historyEmail = '';
	let historyCurrentPage = 1;
	let historyLastPage = 1;
	let inventoryCurrentPage = 1;
	let inventoryLastPage = 1;

	const money = (cents) => moneyFormatter.format(cents / 100);
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);

	async function request(url, options = {}, includeMetadata = false) {
		const response = await fetch(url, {
			...options,
			headers: {
				Accept: 'application/json',
				...(options.body ? { 'Content-Type': 'application/json' } : {}),
				...options.headers,
			},
		});
		const payload = await response.json().catch(() => ({}));

		if (!response.ok) {
			const validationMessage = payload.errors ? Object.values(payload.errors).flat()[0] : null;
			throw new Error(validationMessage || payload.message || 'The request could not be completed.');
		}

		return includeMetadata ? payload : payload.data;
	}

	function showAlert(message) {
		orderAlertMessage.textContent = message;
		orderAlert.hidden = false;
	}

	function hideAlert() {
		orderAlert.hidden = true;
		orderAlertMessage.textContent = '';
	}

	function addProductRow() {
		const row = document.createElement('div');
		row.className = 'product-row';
		const rowId = `product-row-${crypto.randomUUID()}`;
		row.innerHTML = `
			<div class="product-picker">
				<input class="product-search" type="search" role="combobox" aria-label="Search products" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false" aria-controls="${rowId}-options" autocomplete="off" placeholder="Search or choose a product">
				<button class="product-picker-toggle" type="button" aria-label="Show products" tabindex="-1">
					<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<div class="product-options" id="${rowId}-options" role="listbox" hidden></div>
				<input class="product-id" type="hidden" value="">
			</div>
			<input class="qty-input" type="number" min="1" step="1" value="1" aria-label="Quantity">
			<span class="unit-price">-</span>
			<span class="line-total">-</span>
			<button class="remove-line" type="button" title="Remove product" aria-label="Remove product">
				<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 6h12M8 6V4h4v2m-6 0 1 10h6l1-10M8.5 9v4m3-4v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<span class="stock-inline" hidden></span>
		`;

		const search = row.querySelector('.product-search');
		const selectedProductId = row.querySelector('.product-id');
		const optionsList = row.querySelector('.product-options');
		let activeOption = -1;

		function closeOptions() {
			optionsList.hidden = true;
			search.setAttribute('aria-expanded', 'false');
			search.removeAttribute('aria-activedescendant');
			activeOption = -1;
		}

		function renderOptions(query = '') {
			const searchTerm = query.trim().toLocaleLowerCase();
			const matches = Array.from(products.values()).filter((product) =>
				`${product.name} ${product.code}`.toLocaleLowerCase().includes(searchTerm),
			);

			optionsList.innerHTML = matches.length ? matches.map((product, index) => {
				const stock = Number(product.stock_on_hand);
				const optionId = `${rowId}-option-${index}`;
				return `<button class="product-option" id="${optionId}" type="button" role="option" aria-selected="false" data-product-id="${product.id}" ${stock < 1 ? 'disabled' : ''}>
					<span class="product-option-details"><strong>${escapeHtml(product.name)}</strong><small>${escapeHtml(product.code)} · ${money(Math.round(Number(product.price) * 100))}</small></span>
					<span class="product-option-stock">${stock > 0 ? `${stock} in stock` : 'Out of stock'}</span>
				</button>`;
			}).join('') : '<div class="product-no-results" role="status">No matching products.</div>';
			optionsList.hidden = false;
			search.setAttribute('aria-expanded', 'true');
			search.removeAttribute('aria-activedescendant');
			activeOption = -1;
		}

		function selectProduct(productId) {
			const product = products.get(Number(productId));
			if (!product || Number(product.stock_on_hand) < 1) return;

			selectedProductId.value = product.id;
			search.value = product.name;
			search.dataset.selectedName = product.name;
			closeOptions();
			updateTotals();
		}

		function setActiveOption(index) {
			const options = Array.from(optionsList.querySelectorAll('.product-option:not(:disabled)'));
			if (!options.length) return;

			activeOption = (index + options.length) % options.length;
			for (const option of options) option.setAttribute('aria-selected', 'false');
			const active = options[activeOption];
			active.setAttribute('aria-selected', 'true');
			search.setAttribute('aria-activedescendant', active.id);
			active.scrollIntoView({ block: 'nearest' });
		}

		search.addEventListener('focus', () => {
			const selectedName = search.dataset.selectedName;
			if (selectedName && search.value === selectedName) search.select();
			renderOptions(selectedName && search.value === selectedName ? '' : search.value);
		});
		search.addEventListener('input', () => {
			selectedProductId.value = '';
			delete search.dataset.selectedName;
			updateTotals();
			renderOptions(search.value);
		});
		search.addEventListener('keydown', (event) => {
			if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
				event.preventDefault();
				if (optionsList.hidden) renderOptions(search.value);
				const direction = event.key === 'ArrowDown' ? 1 : -1;
				const nextOption = activeOption < 0
					? (direction > 0 ? 0 : optionsList.querySelectorAll('.product-option:not(:disabled)').length - 1)
					: activeOption + direction;
				setActiveOption(nextOption);
			} else if (event.key === 'Enter' && !optionsList.hidden) {
				event.preventDefault();
				const active = optionsList.querySelector('[aria-selected="true"]');
				const firstAvailable = optionsList.querySelector('.product-option:not(:disabled)');
				(active || firstAvailable)?.click();
			} else if (event.key === 'Escape') {
				closeOptions();
			}
		});
		optionsList.addEventListener('click', (event) => {
			const option = event.target.closest('.product-option');
			if (option) selectProduct(option.dataset.productId);
		});
		row.querySelector('.product-picker-toggle').addEventListener('click', () => {
			if (optionsList.hidden) {
				search.focus();
				renderOptions('');
			} else {
				closeOptions();
			}
		});
		search.addEventListener('blur', () => {
			window.setTimeout(() => {
				if (!row.contains(document.activeElement)) closeOptions();
			}, 120);
		});

		row.querySelector('.qty-input').addEventListener('input', updateTotals);
		row.querySelector('.remove-line').addEventListener('click', () => {
			row.remove();
			if (!productRows.children.length) addProductRow();
			updateTotals();
		});
		productRows.append(row);
		updateTotals();
	}

	function selectedLines() {
		return Array.from(productRows.querySelectorAll('.product-row')).flatMap((row) => {
			const id = row.querySelector('.product-id').value;
			const quantity = Number(row.querySelector('.qty-input').value);
			const product = products.get(Number(id));
			return product && Number.isInteger(quantity) && quantity > 0 ? [{ product, quantity }] : [];
		});
	}

	function changeBreakdown(changeCents) {
		const denominations = [50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10];
		let remaining = changeCents;
		const parts = [];

		for (const denomination of denominations) {
			const count = Math.floor(remaining / denomination);
			if (count) {
				parts.push(`${count} × ${money(denomination)}`);
				remaining -= count * denomination;
			}
		}

		return parts.length ? parts.join(' + ') : 'Exact amount';
	}

	function updateTotals() {
		let subtotalCents = 0;
		let taxCents = 0;
		let hasStockIssue = false;

		for (const row of productRows.querySelectorAll('.product-row')) {
			const product = products.get(Number(row.querySelector('.product-id').value));
			const quantity = Number(row.querySelector('.qty-input').value);
			const priceDisplay = row.querySelector('.unit-price');
			const totalDisplay = row.querySelector('.line-total');
			const stockMessage = row.querySelector('.stock-inline');

			if (!product || !Number.isInteger(quantity) || quantity < 1) {
				priceDisplay.textContent = '-';
				totalDisplay.textContent = '-';
				stockMessage.hidden = true;
				continue;
			}

			const lineSubtotal = Math.round(Number(product.price) * 100) * quantity;
			const taxBasisPoints = Math.round(Number(product.tax_percentage) * 100);
			const lineTax = Math.round(lineSubtotal * taxBasisPoints / 10000);
			subtotalCents += lineSubtotal;
			taxCents += lineTax;
			priceDisplay.textContent = money(Math.round(Number(product.price) * 100));
			totalDisplay.textContent = money(lineSubtotal + lineTax);

			const insufficient = quantity > Number(product.stock_on_hand);
			stockMessage.hidden = !insufficient;
			stockMessage.textContent = insufficient ? `Only ${product.stock_on_hand} left in stock.` : '';
			hasStockIssue ||= insufficient;
		}

		const totalCents = subtotalCents + taxCents;
		document.querySelector('#subtotal-value').textContent = money(subtotalCents);
		document.querySelector('#tax-value').textContent = money(taxCents);
		document.querySelector('#grand-total-value').textContent = money(totalCents);
		const receivedCents = Math.round(Number(amountReceived.value || 0) * 100);
		const changeCents = Math.max(0, receivedCents - totalCents);
		document.querySelector('#change-value').textContent = money(changeCents);
		document.querySelector('#denomination-value').textContent = changeBreakdown(changeCents);
		document.querySelector('#generate-bill').disabled = products.size === 0 || hasStockIssue;
	}

	function renderLowStock(items) {
		const list = document.querySelector('#low-stock-list');
		const empty = document.querySelector('#low-stock-empty');
		list.replaceChildren();
		empty.hidden = items.length > 0;
		list.hidden = items.length === 0;

		for (const product of items) {
			const item = document.createElement('li');
			item.className = 'stock-item';
			item.innerHTML = `<span>${escapeHtml(product.name)}</span><strong>${Number(product.stock_on_hand)} left</strong>`;
			list.append(item);
		}
	}

	function renderInventory(items) {
		const body = document.querySelector('#inventory-rows');
		body.innerHTML = items.length ? items.map((product) => {
			const low = Number(product.stock_on_hand) < lowStockThreshold;
			return `<tr>
				<td>${escapeHtml(product.name)}</td>
				<td>${escapeHtml(product.code)}</td>
				<td>${money(Math.round(Number(product.price) * 100))}</td>
				<td>${Number(product.tax_percentage)}%</td>
				<td><span class="status-pill ${low ? '' : 'ok'}">${Number(product.stock_on_hand)} ${low ? 'Low stock' : 'In stock'}</span></td>
			</tr>`;
		}).join('') : '<tr><td colspan="5" class="empty-state">No products in the catalog.</td></tr>';
	}

	function renderPagination(prefix, pagination) {
		const currentPage = Number(pagination.current_page);
		const lastPage = Number(pagination.last_page);
		const total = Number(pagination.total);
		document.querySelector(`#${prefix}-pagination`).hidden = false;
		document.querySelector(`#${prefix}-pagination-summary`).textContent = total
			? `Showing ${pagination.from}–${pagination.to} of ${total}`
			: 'No records';
		document.querySelector(`#${prefix}-page-number`).textContent = `Page ${currentPage} of ${lastPage}`;
		document.querySelector(`#${prefix}-previous`).disabled = currentPage <= 1;
		document.querySelector(`#${prefix}-next`).disabled = currentPage >= lastPage;

		if (prefix === 'history') {
			historyCurrentPage = currentPage;
			historyLastPage = lastPage;
		} else {
			inventoryCurrentPage = currentPage;
			inventoryLastPage = lastPage;
		}
	}

	async function loadInventory(page = 1) {
		const body = document.querySelector('#inventory-rows');
		body.innerHTML = '<tr><td colspan="5" class="empty-state">Loading inventory…</td></tr>';

		try {
			const response = await request(`/api/products?page=${page}`, {}, true);
			renderInventory(response.data);
			renderPagination('inventory', response.pagination);
		} catch (error) {
			body.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(error.message)}</td></tr>`;
			document.querySelector('#inventory-pagination').hidden = true;
		}
	}

	async function loadHistory(page = 1) {
		const body = document.querySelector('#history-rows');
		body.innerHTML = '<tr><td colspan="5" class="empty-state">Loading order history…</td></tr>';
		document.querySelector('#history-pagination').hidden = true;

		try {
			const query = new URLSearchParams({ page: String(page) });
			if (historyEmail) query.set('email', historyEmail);
			const response = await request(`/api/customers/orders?${query}`, {}, true);
			body.innerHTML = response.data.length ? response.data.map((order) => `
				<tr>
					<td>#${Number(order.id)}</td>
					<td><div class="history-customer"><strong>${escapeHtml(order.customer.name)}</strong><small>${escapeHtml(order.customer.email)}</small></div></td>
					<td>${new Date(order.created_at).toLocaleString('en-IN')}</td>
					<td>${order.items.reduce((sum, item) => sum + Number(item.quantity), 0)}</td>
					<td><strong>${money(Math.round(Number(order.total) * 100))}</strong></td>
				</tr>
			`).join('') : `<tr><td colspan="5" class="empty-state">${historyEmail ? 'No orders found for this email.' : 'No orders have been placed yet.'}</td></tr>`;
			renderPagination('history', response.pagination);
		} catch (error) {
			body.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(error.message)}</td></tr>`;
		}
	}

	async function loadCatalog() {
		const loading = document.querySelector('#low-stock-loading');
		loading.hidden = false;

		try {
			const [catalog, lowStock, inventoryResponse] = await Promise.all([
				request('/api/products/catalog'),
				request('/api/products/low-stock'),
				request('/api/products?page=1', {}, true),
			]);
			products.clear();
			for (const product of catalog) products.set(Number(product.id), product);
			productRows.replaceChildren();
			addProductRow();
			renderLowStock(lowStock);
			renderInventory(inventoryResponse.data);
			renderPagination('inventory', inventoryResponse.pagination);
		} catch (error) {
			showAlert(`Could not load the product catalog. ${error.message}`);
			document.querySelector('#low-stock-empty').textContent = 'Stock data is unavailable until the catalog API is ready.';
			document.querySelector('#low-stock-empty').hidden = false;
			document.querySelector('#low-stock-list').hidden = true;
			document.querySelector('#inventory-rows').innerHTML = '<tr><td colspan="5" class="empty-state">Unable to load products. Check the database and retry.</td></tr>';
			document.querySelector('#inventory-pagination').hidden = true;
		} finally {
			loading.hidden = true;
			updateTotals();
		}
	}

	function fillReceipt(order) {
		document.querySelector('#receipt-number').textContent = `Order #${order.id}`;
		document.querySelector('#receipt-customer').textContent = `${order.customer.name} · ${order.customer.email}`;
		document.querySelector('#receipt-date').textContent = new Date(order.created_at).toLocaleString('en-IN');
		document.querySelector('#receipt-lines').innerHTML = order.items.map((item) => `
			<div class="receipt-line">
				<span>${escapeHtml(item.product.name)} × ${Number(item.quantity)}</span>
				<strong>${money(Math.round(Number(item.total) * 100))}</strong>
			</div>
		`).join('');
		document.querySelector('#receipt-subtotal').textContent = money(Math.round(Number(order.subtotal) * 100));
		document.querySelector('#receipt-tax').textContent = money(Math.round(Number(order.tax) * 100));
		document.querySelector('#receipt-total').textContent = money(Math.round(Number(order.total) * 100));
		document.querySelector('#receipt-dialog').showModal();
	}

	orderForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		hideAlert();

		const lines = selectedLines();
		if (!lines.length) {
			showAlert('Add at least one product to this bill.');
			return;
		}

		const totalCents = Math.round(Number(document.querySelector('#grand-total-value').textContent.replace(/[^\d.-]/g, '')) * 100);
		const receivedCents = Math.round(Number(amountReceived.value || 0) * 100);
		if (receivedCents < totalCents) {
			showAlert('Amount given must cover the grand total.');
			amountReceived.focus();
			return;
		}

		const button = document.querySelector('#generate-bill');
		button.disabled = true;
		button.textContent = 'Creating bill…';

		try {
			const order = await request('/api/orders', {
				method: 'POST',
				body: JSON.stringify({
					customer: { name: customerName.value.trim(), email: customerEmail.value.trim() },
					items: lines.map(({ product, quantity }) => ({ product_id: product.id, quantity })),
				}),
			});
			fillReceipt(order);
			customerEmail.value = '';
			customerName.value = '';
			customerNameEdited = false;
			customerHint.textContent = '';
			customerHint.classList.remove('success');
			amountReceived.value = '';
			productRows.replaceChildren();
			addProductRow();
			button.disabled = true;
			await loadCatalog();
		} catch (error) {
			showAlert(error.message);
		} finally {
			button.textContent = 'Generate bill';
			updateTotals();
		}
	});

	amountReceived.addEventListener('input', updateTotals);
	customerName.addEventListener('input', () => {
		customerNameEdited = true;
		customerHint.textContent = '';
		customerHint.classList.remove('success');
	});

	customerEmail.addEventListener('blur', async () => {
		if (!customerEmail.validity.valid || !customerEmail.value.trim()) return;
		try {
			const customer = await request(`/api/customers/lookup?email=${encodeURIComponent(customerEmail.value.trim())}`);
			if (customer && !customerNameEdited) {
				customerName.value = customer.name;
				customerHint.textContent = 'Customer found. Name filled from previous order.';
				customerHint.classList.add('success');
			} else if (!customer) {
				customerHint.textContent = 'New customer. Enter their name to continue.';
				customerHint.classList.remove('success');
			}
		} catch {
			customerHint.textContent = 'Customer lookup unavailable. Enter their name to continue.';
			customerHint.classList.remove('success');
		}
	});

	document.querySelector('#add-product').addEventListener('click', addProductRow);
	document.querySelector('#dismiss-order-alert').addEventListener('click', hideAlert);
	document.querySelector('#print-receipt').addEventListener('click', () => window.print());
	document.querySelector('#close-receipt').addEventListener('click', () => document.querySelector('#receipt-dialog').close());
	document.querySelector('#close-receipt-secondary').addEventListener('click', () => document.querySelector('#receipt-dialog').close());
	document.querySelector('#receipt-dialog').addEventListener('click', (event) => {
		if (event.target === event.currentTarget) event.currentTarget.close();
	});

	document.querySelectorAll('[data-view-target]').forEach((button) => {
		button.addEventListener('click', () => {
			const target = button.dataset.viewTarget;
			document.querySelectorAll('[data-view]').forEach((view) => {
				view.hidden = view.dataset.view !== target;
			});
			document.querySelectorAll('[data-view-target]').forEach((navButton) => {
				navButton.classList.toggle('active', navButton === button);
				navButton.setAttribute('aria-current', navButton === button ? 'page' : 'false');
			});
			document.querySelector('#page-title').textContent = button.dataset.title;
			document.querySelector('#page-subtitle').textContent = button.dataset.subtitle;
			document.querySelector('#top-section').textContent = button.dataset.title === 'New order' ? 'Billing' : button.dataset.title;
			if (target === 'order-history') {
				historyEmail = document.querySelector('#history-email').value.trim();
				loadHistory(1);
			}
		});
	});

	document.querySelector('#history-search').addEventListener('submit', async (event) => {
		event.preventDefault();
		historyEmail = document.querySelector('#history-email').value.trim();
		await loadHistory(1);
	});

	document.querySelector('#history-previous').addEventListener('click', () => {
		if (historyCurrentPage > 1) loadHistory(historyCurrentPage - 1);
	});
	document.querySelector('#history-next').addEventListener('click', () => {
		if (historyCurrentPage < historyLastPage) loadHistory(historyCurrentPage + 1);
	});
	document.querySelector('#inventory-previous').addEventListener('click', () => {
		if (inventoryCurrentPage > 1) loadInventory(inventoryCurrentPage - 1);
	});
	document.querySelector('#inventory-next').addEventListener('click', () => {
		if (inventoryCurrentPage < inventoryLastPage) loadInventory(inventoryCurrentPage + 1);
	});

	loadCatalog();
}
