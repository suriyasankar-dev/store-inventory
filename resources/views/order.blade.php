<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1c2b40">
    <title>New Order | Stockroom</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-low-stock-threshold="{{ config('inventory.low_stock_threshold', 5) }}">
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="/" aria-label="Stockroom counter home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 8h16l-1.4 11H5.4L4 8Zm3 0 2-4h6l2 4m-8 4v3m6-3v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="brand-name">Stockroom<small>Store counter</small></span>
        </a>
        <p class="nav-label">Workspace</p>
        <nav class="side-nav" aria-label="Main navigation">
            <button class="nav-link active" type="button" data-view-target="new-order" data-title="New order" data-subtitle="Build a customer bill and keep stock up to date." aria-current="page">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 3.5h12v13H4zM7 7h6M7 10h6m-6 3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>New order</span>
            </button>
            <button class="nav-link" type="button" data-view-target="order-history" data-title="Order history" data-subtitle="Find a customer's previous bills by email.">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.5 6.5h13v10h-13zM6 3.5h8M6.5 10h7m-7 3h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>Order history</span>
            </button>
            <button class="nav-link" type="button" data-view-target="inventory" data-title="Inventory" data-subtitle="Review prices, tax rates, and stock on hand.">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2.8 6.5 3.5v7.4L10 17.2l-6.5-3.5V6.3L10 2.8Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="m3.8 6.6 6.2 3.5 6.2-3.5M10 10.1v6.8" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                <span>Inventory</span>
            </button>
        </nav>
        @include('partials.footer')
    </aside>

    <main class="main-area">
        <div class="workspace">
            @include('partials.header')

            <section class="view" data-view="new-order">
                <form id="order-form" novalidate>
                    <div class="order-layout">
                        <div class="order-main">
                            <section class="form-section" aria-labelledby="customer-heading">
                                <div class="section-heading"><h2 id="customer-heading">Customer</h2><span class="section-caption">Existing customers are recognized by email</span></div>
                                <div class="customer-fields">
                                    <div class="field">
                                        <label for="customer-email">Email</label>
                                        <input id="customer-email" name="email" type="email" autocomplete="email" placeholder="e.g. taylor@example.com" required>
                                        <span class="field-hint" id="customer-hint" aria-live="polite"></span>
                                    </div>
                                    <div class="field">
                                        <label for="customer-name">Name</label>
                                        <input id="customer-name" name="name" type="text" autocomplete="name" maxlength="255" placeholder="Auto-filled for existing customers" required>
                                    </div>
                                </div>
                            </section>

                            <section class="form-section" aria-labelledby="products-heading">
                                <div class="section-heading">
                                    <div class="section-title-group"><h2 id="products-heading">Products</h2><span class="section-caption" id="product-count">Select products and quantities</span></div>
                                    <button class="text-button" id="add-product" type="button">
                                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12m6-6H4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        Add product
                                    </button>
                                </div>
                                <div class="product-table" role="table" aria-label="Products in this order">
                                    <div class="product-head" role="row"><span role="columnheader">Product</span><span role="columnheader">Qty</span><span role="columnheader">Price</span><span role="columnheader">Line total</span><span aria-hidden="true"></span></div>
                                    <div id="product-rows"></div>
                                </div>
                            </section>

                            <div class="form-alert" id="order-alert" role="alert" aria-live="assertive" hidden>
                                <svg class="alert-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 3 17 16H3L10 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 7.5v3m0 2v.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                <span id="order-alert-message"></span>
                                <button class="alert-dismiss" id="dismiss-order-alert" type="button" aria-label="Dismiss alert">
                                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                </button>
                            </div>
                        </div>

                        <aside class="stock-panel" aria-labelledby="stock-heading">
                            <div class="stock-title" id="stock-heading">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 3 7 12H3L10 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 7v3.5m0 2.2v.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                Low stock alert
                            </div>
                            <p class="stock-loading" id="low-stock-loading">Loading stock…</p>
                            <p class="stock-empty" id="low-stock-empty" hidden>All items are above the low-stock threshold.</p>
                            <ul class="stock-list" id="low-stock-list" hidden></ul>
                        </aside>
                        <div class="lower-spacer" aria-hidden="true"></div>
                        <div class="checkout-column">
                            <section class="form-section payment-section" aria-labelledby="payment-heading">
                                <div class="section-heading"><h2 id="payment-heading">Payment</h2><span class="section-caption">Amounts include product tax rates</span></div>
                                <div class="payment-layout">
                                    <div class="payment-summary">
                                        <div class="summary-line"><span>Subtotal</span><strong id="subtotal-value">₹0.00</strong></div>
                                        <div class="summary-line"><span>Tax</span><strong id="tax-value">₹0.00</strong></div>
                                        <div class="summary-line grand"><span>Grand total</span><strong id="grand-total-value">₹0.00</strong></div>
                                        <div class="field cash-field">
                                            <label for="amount-received">Amount given by customer</label>
                                            <input id="amount-received" type="number" min="0" step="0.01" inputmode="decimal" placeholder="₹0.00" required>
                                        </div>
                                        <div class="change-row"><span>Balance to return</span><strong id="change-value">₹0.00</strong></div>
                                        <div class="denominations" id="denomination-value">Exact amount</div>
                                    </div>
                                    <div class="submit-column">
                                        <button class="primary-button" id="generate-bill" type="submit" disabled>Generate bill</button>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </form>
            </section>

            <section class="view data-panel" data-view="order-history" hidden>
                <h2>Customer order history</h2>
                <form class="history-search" id="history-search">
                    <input id="history-email" type="email" placeholder="Filter by customer email (optional)" aria-label="Filter by customer email (optional)">
                    <button type="submit">Search orders</button>
                </form>
                <div class="table-wrap">
                    <table class="data-table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th></tr></thead><tbody id="history-rows"><tr><td colspan="5" class="empty-state">Loading recent orders…</td></tr></tbody></table>
                </div>
                <div class="table-pagination" id="history-pagination" aria-label="Order history pagination" hidden>
                    <span class="pagination-summary" id="history-pagination-summary"></span>
                    <div class="pagination-controls">
                        <button class="pagination-button" id="history-previous" type="button" disabled>Previous</button>
                        <span class="pagination-page" id="history-page-number">Page 1 of 1</span>
                        <button class="pagination-button" id="history-next" type="button" disabled>Next</button>
                    </div>
                </div>
            </section>

            <section class="view data-panel" data-view="inventory" hidden>
                <div class="section-heading"><div><h2>Product catalog</h2><span class="section-caption">Current selling price and stock on hand</span></div></div>
                <div class="table-wrap">
                    <table class="data-table"><thead><tr><th>Product</th><th>Code</th><th>Unit price</th><th>Tax</th><th>Stock</th></tr></thead><tbody id="inventory-rows"><tr><td colspan="5" class="empty-state">Loading product catalog…</td></tr></tbody></table>
                </div>
                <div class="table-pagination" id="inventory-pagination" aria-label="Inventory pagination" hidden>
                    <span class="pagination-summary" id="inventory-pagination-summary"></span>
                    <div class="pagination-controls">
                        <button class="pagination-button" id="inventory-previous" type="button" disabled>Previous</button>
                        <span class="pagination-page" id="inventory-page-number">Page 1 of 1</span>
                        <button class="pagination-button" id="inventory-next" type="button" disabled>Next</button>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>
<dialog class="receipt-dialog" id="receipt-dialog" aria-labelledby="receipt-title">
    <div class="receipt-inner">
        <div class="receipt-head">
            <div><h2 id="receipt-title">Bill generated</h2><p id="receipt-number"></p><p class="receipt-meta" id="receipt-customer"></p><p class="receipt-meta" id="receipt-date"></p></div>
            <button class="icon-button receipt-close" id="close-receipt" type="button" aria-label="Close bill">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
        </div>
        <div class="receipt-items" id="receipt-lines"></div>
        <div class="receipt-line"><span>Subtotal</span><strong id="receipt-subtotal"></strong></div>
        <div class="receipt-line receipt-gap"><span>Tax</span><strong id="receipt-tax"></strong></div>
        <div class="receipt-line receipt-total"><span>Grand total</span><strong id="receipt-total"></strong></div>
        <div class="receipt-actions">
            <button class="secondary-button" id="close-receipt-secondary" type="button">Close</button>
            <button class="print-button" id="print-receipt" type="button">Print bill</button>
        </div>
    </div>
</dialog>
</body>
</html>
