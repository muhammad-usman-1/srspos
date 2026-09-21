import React, { Component } from "react";
import { createRoot } from "react-dom/client";
import axios from "axios";
import Swal from "sweetalert2";

/* -------------------------------------------------------------------------
 * Offline-capable POS.
 * The cart, held bills and sales live in the browser (localStorage). Products
 * and customers are copied from the server and refreshed while online. Every
 * sale is saved to a local queue first and uploaded whenever a connection is
 * available (each sale carries a uuid so it is only ever saved once).
 * ---------------------------------------------------------------------- */

window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    window.__installPrompt = e;
});

const key = (name) => `pos:${window.APP.user_id}:${name}`;
const load = (name, fallback) => {
    try {
        const v = localStorage.getItem(key(name));
        return v ? JSON.parse(v) : fallback;
    } catch (e) {
        return fallback;
    }
};
const save = (name, value) => {
    try {
        localStorage.setItem(key(name), JSON.stringify(value));
    } catch (e) {}
};
const uuid = () =>
    window.crypto && crypto.randomUUID
        ? crypto.randomUUID()
        : "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
              const r = (Math.random() * 16) | 0;
              return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
          });
const esc = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const round2 = (v) => Math.round(v * 100) / 100;

const METHODS = {
    cash: "Cash",
    card: "Card",
    easypaisa: "EasyPaisa",
    jazzcash: "JazzCash",
    bank_transfer: "Bank Transfer",
};

// Same maths as App\Services\OrderService: subtotal -> discount -> tax
function calcTotals(items, s, discountType, discountValue, taxRate) {
    const subtotal = round2(items.reduce((t, i) => t + i.price * i.qty, 0));
    let discount = 0;
    if (s.enable_discount) {
        const v = parseFloat(discountValue) || 0;
        discount = discountType === "percent" ? (subtotal * Math.min(v, 100)) / 100 : v;
        discount = round2(Math.min(Math.max(discount, 0), subtotal));
    }
    const rate = s.enable_tax ? parseFloat(taxRate) || 0 : 0;
    const tax = round2(((subtotal - discount) * rate) / 100);
    return { subtotal, discount, tax, rate, total: round2(subtotal - discount + tax) };
}

// Browser copy of resources/views/orders/receipt.blade.php, used when offline
function buildReceiptHtml(sale, billNo, s, cashier) {
    const n = (v) => Number(v || 0).toFixed(2);
    const t = sale.totals;
    const d = new Date(sale.created_at);
    const date = d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "2-digit" }).replace(/ /g, "-");
    const time = d.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
    const tendered = Number(sale.amount) || 0;
    const due = Math.max(t.total - tendered, 0);
    const rows = sale.items
        .map(
            (i, idx) => `
        <tr><td class="l name" colspan="3">${idx + 1}. ${esc(i.name)}</td><td class="b">${n(i.price * i.qty)}</td></tr>
        <tr class="sep"><td class="l">${esc(i.barcode)}</td><td>${n(i.qty)}</td><td>${n(i.price)}</td><td></td></tr>`
        )
        .join("");

    return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bill ${esc(billNo)}</title><style>
*{margin:0;padding:0;box-sizing:border-box}@page{size:80mm auto;margin:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.35;width:80mm;padding:3mm 4mm;color:#000;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.center{text-align:center}.r{text-align:right}.b{font-weight:bold}
.logo{max-width:60mm;max-height:22mm;margin-bottom:3px}
.address{font-weight:bold;font-size:12px;margin:2px 0 4px;white-space:pre-line}
.phone{border:2px solid #000;font-weight:bold;padding:3px 4px;margin:4px 0;font-size:12px}
.title{font-weight:bold;font-size:13px;margin:6px 0 3px}
.meta{display:flex;justify-content:space-between;font-size:11.5px}
table{width:100%;border-collapse:collapse}
.items{border-top:2px solid #000;border-bottom:1px solid #000;margin-top:4px;table-layout:fixed}
.items th,.items td{white-space:nowrap;overflow:hidden}
.items th{font-size:10.5px;text-align:right;padding:2px 1px;font-weight:bold}.items th.l{text-align:left}
.items thead{border-bottom:1px solid #000}
.items td{padding:1px 1px;font-size:11.5px;text-align:right;vertical-align:top}.items td.l{text-align:left}
.items .name{font-weight:bold;white-space:normal}.items tr.sep td{border-bottom:1px dashed #000;padding-bottom:3px}
.totals td{font-size:14px;font-weight:bold;padding:2px 0}.totals td.s{font-size:12px}
.box{border:1px dashed #000;margin:5px 0}.box td{padding:3px 4px;font-weight:bold;font-size:13px}
.box td+td{border-left:1px solid #000;text-align:right}.box .dark{background:#000;color:#fff}
.policy{border:2px solid #000;padding:4px;font-size:10px;text-align:center;margin:6px 0;white-space:pre-line}
.thanks{font-family:'Times New Roman',serif;font-weight:bold;font-size:15px;text-align:center;border:2px solid #000;padding:4px;margin:5px 0}
.credit{font-size:9px;text-align:center}
</style></head><body>
<div class="center">
  ${s.logo_url ? `<img class="logo" src="${esc(s.logo_url)}" alt="">` : ""}
  ${s.store_address ? `<div class="address">${esc(s.store_address)}</div>` : `<div class="b" style="font-size:15px">${esc(s.app_name)}</div>`}
  ${s.store_phone ? `<div class="phone">Ph: ${esc(s.store_phone)}</div>` : ""}
  <div class="title">${esc((s.receipt_title || "Original Sales Invoice").toUpperCase())}</div>
</div>
<div class="meta"><span>Date &amp; Time: ${date}</span><span>${time}</span></div>
<div class="meta"><span>Cashier: ${esc(cashier)}</span><span>Bill No: <b>${esc(billNo)}</b></span></div>
${sale.customer_name ? `<div class="meta"><span>Customer: ${esc(sale.customer_name)}</span></div>` : ""}
<table class="items"><colgroup><col style="width:46%"><col style="width:17%"><col style="width:17%"><col style="width:20%"></colgroup>
<thead><tr><th class="l" colspan="3">No. &nbsp;Item Name / Barcode</th><th>Amount</th></tr>
<tr><th class="l"></th><th>Qty</th><th>Price</th><th></th></tr></thead><tbody>${rows}</tbody></table>
<table class="totals" style="margin-top:4px">
  ${t.discount > 0 ? `<tr><td class="s">Discount</td><td class="r s">- ${n(t.discount)}</td></tr>` : ""}
  <tr><td>Grand Total</td><td class="r">${n(t.total)}</td></tr>
  <tr><td>${esc(METHODS[sale.method] || "Cash")} Paid</td><td class="r">${n(tendered)}</td></tr>
  ${due > 0 ? `<tr><td>Amount Due</td><td class="r">${n(due)}</td></tr>` : `<tr><td>Balance</td><td class="r">${n(Math.max(tendered - t.total, 0))}</td></tr>`}
</table>
${s.receipt_policy ? `<div class="policy">${esc(s.receipt_policy)}</div>` : ""}
<div class="thanks">${esc((s.receipt_footer || "Thanks for your visit").toUpperCase())}</div>
${s.receipt_credit ? `<div class="credit">${esc(s.receipt_credit)}</div>` : ""}
</body></html>`;
}

const defaultSettings = () => ({
    app_name: "POS",
    currency_symbol: window.APP.currency_symbol,
    warning_quantity: Number(window.APP.warning_quantity) || 10,
    enable_discount: !!window.APP.enable_discount,
    enable_tax: !!window.APP.enable_tax,
    tax_name: window.APP.tax_name || "Tax",
    tax_rate: Number(window.APP.tax_rate) || 0,
});

class Cart extends Component {
    constructor(props) {
        super(props);
        const settings = { ...defaultSettings(), ...load("settings", {}) };
        this.state = {
            products: load("products", []),
            customers: load("customers", []),
            settings,
            cart: load("cart", []),
            held: load("held", []),
            queue: load("queue", []),
            barcode: "",
            search: "",
            customer_id: "",
            online: navigator.onLine,
            reachable: true,
            syncing: false,
            sessionExpired: false,
            installPrompt: window.__installPrompt || null,
            method: "cash",
            discountType: "fixed",
            discountValue: "",
            taxRate: settings.tax_rate || 0,
            amountReceived: null,
            autoPrint: (() => {
                try {
                    return localStorage.getItem("pos_auto_print") !== "0";
                } catch (e) {
                    return true;
                }
            })(),
            submitting: false,
            activeProduct: -1,
            activeCart: -1,
        };

        this.queueRef = this.state.queue;
        this.syncChain = Promise.resolve();
        this.barcodeRef = React.createRef();
        this.searchRef = React.createRef();
        this.receivedRef = React.createRef();
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleScanBarcode = this.handleScanBarcode.bind(this);
        this.handleClickSubmit = this.handleClickSubmit.bind(this);
        this.handleHoldBill = this.handleHoldBill.bind(this);
        this.handleShowHeldBills = this.handleShowHeldBills.bind(this);
        this.handleEmptyCart = this.handleEmptyCart.bind(this);
        this.syncQueue = this.syncQueue.bind(this);
        this.refreshFromServer = this.refreshFromServer.bind(this);
        this.setOnline = this.setOnline.bind(this);
        this.onInstallPrompt = this.onInstallPrompt.bind(this);
        this.onInstalled = () => this.setState({ installed: true });
    }

    componentDidMount() {
        document.addEventListener("keydown", this.handleKeyDown);
        window.addEventListener("online", this.setOnline);
        window.addEventListener("offline", this.setOnline);
        window.addEventListener("beforeinstallprompt", this.onInstallPrompt);
        window.addEventListener("appinstalled", this.onInstalled);
        if (this.barcodeRef.current) this.barcodeRef.current.focus();

        this.refreshFromServer();
        // retry uploads every 30s, refresh products every 5 min
        this.tick = 0;
        this.timer = setInterval(() => {
            this.tick++;
            if (this.tick % 10 === 0) this.refreshFromServer();
            else this.syncQueue();
        }, 30000);
    }

    componentWillUnmount() {
        document.removeEventListener("keydown", this.handleKeyDown);
        window.removeEventListener("online", this.setOnline);
        window.removeEventListener("offline", this.setOnline);
        window.removeEventListener("beforeinstallprompt", this.onInstallPrompt);
        window.removeEventListener("appinstalled", this.onInstalled);
        clearInterval(this.timer);
    }

    setOnline() {
        this.setState({ online: navigator.onLine, reachable: true });
        if (navigator.onLine) this.refreshFromServer();
    }

    onInstallPrompt(e) {
        e.preventDefault();
        this.setState({ installPrompt: e });
    }

    // ---- local persistence ---------------------------------------------------
    setCart(cart, extra = {}) {
        save("cart", cart);
        this.setState({ cart, ...extra });
    }
    setProducts(products) {
        save("products", products);
        this.setState({ products });
    }
    setQueue(queue) {
        this.queueRef = queue;
        save("queue", queue);
        this.setState({ queue });
    }
    setHeld(held) {
        save("held", held);
        this.setState({ held });
    }

    stockOf(id) {
        const p = this.state.products.find((x) => x.id === id);
        return p ? p.quantity : 0;
    }

    // ---- server sync ---------------------------------------------------------
    isNetworkError(err) {
        return !err.response;
    }

    handleSyncError(err) {
        if (this.isNetworkError(err)) {
            this.setState({ reachable: false });
        } else if (err.response.status === 401 || err.response.status === 419) {
            this.setState({ sessionExpired: true });
        }
    }

    /** Upload queued sales (one run at a time). Resolves to a map of uuid -> order id for the sales that were saved. */
    syncQueue() {
        const run = this.syncChain.then(() => this.syncOnce());
        this.syncChain = run.catch(() => {});
        return run;
    }

    async syncOnce() {
        const pending = this.queueRef.filter((q) => !q.failed);
        if (!pending.length || !navigator.onLine) return {};
        this.setState({ syncing: true });
        const saved = {};
        try {
            const res = await axios.post("/admin/pos/sales", { sales: pending });
            let queue = this.queueRef;
            (res.data.results || []).forEach((r) => {
                if (r.ok) {
                    saved[r.uuid] = r.order_id;
                    queue = queue.filter((q) => q.uuid !== r.uuid);
                } else {
                    queue = queue.map((q) => (q.uuid === r.uuid ? { ...q, failed: true, error: r.message } : q));
                }
            });
            this.setQueue(queue);
            this.setState({ reachable: true, sessionExpired: false });
        } catch (err) {
            this.handleSyncError(err);
        } finally {
            this.setState({ syncing: false });
        }
        return saved;
    }
    async refreshFromServer() {
        if (!navigator.onLine) return;
        await this.syncQueue();
        // only replace local stock with the server's once nothing is waiting to upload
        if (this.queueRef.some((q) => !q.failed)) return;
        try {
            const res = await axios.get("/admin/pos/bootstrap");
            const d = res.data;
            const settings = { ...defaultSettings(), ...d.settings };
            save("settings", settings);
            save("customers", d.customers);
            this.setState({ customers: d.customers, settings, reachable: true, sessionExpired: false });
            this.setProducts(d.products);
            // make sure the logo is stored by the service worker for offline bills
            if (settings.logo_url) fetch(settings.logo_url).catch(() => {});
        } catch (err) {
            this.handleSyncError(err);
        }
    }

    handleManualSync() {
        this.refreshFromServer();
    }

    showFailedSales() {
        const failed = this.queueRef.filter((q) => q.failed);
        const cur = this.state.settings.currency_symbol;
        const rows = failed
            .map(
                (q) => `<tr><td class="text-left">${esc(q.offline_ref || q.uuid.slice(0, 8))}<br><small>${new Date(q.created_at).toLocaleString()}</small></td>
                <td>${cur} ${Number(q.totals.total).toFixed(2)}</td>
                <td class="text-left text-danger"><small>${esc(q.error)}</small></td>
                <td><button class="btn btn-sm btn-primary" data-retry="${q.uuid}">Retry</button>
                    <button class="btn btn-sm btn-danger" data-discard="${q.uuid}">Discard</button></td></tr>`
            )
            .join("");
        Swal.fire({
            title: "Sales that could not be saved",
            width: 750,
            showConfirmButton: false,
            showCloseButton: true,
            html: `<table class="table table-sm"><thead><tr><th class="text-left">Sale</th><th>Total</th><th class="text-left">Reason</th><th></th></tr></thead><tbody>${rows}</tbody></table>`,
            didOpen: (popup) => {
                popup.addEventListener("click", (e) => {
                    const retry = e.target.closest("[data-retry]");
                    const discard = e.target.closest("[data-discard]");
                    if (retry) {
                        this.setQueue(this.queueRef.map((q) => (q.uuid === retry.dataset.retry ? { ...q, failed: false, error: null } : q)));
                        Swal.close();
                        this.syncQueue();
                    } else if (discard) {
                        this.setQueue(this.queueRef.filter((q) => q.uuid !== discard.dataset.discard));
                        Swal.close();
                    }
                });
            },
        });
    }

    isInstalled() {
        return (
            this.state.installed ||
            (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) ||
            window.navigator.standalone === true
        );
    }

    async handleInstall() {
        const p = this.state.installPrompt || window.__installPrompt;
        if (p) {
            p.prompt();
            const choice = await p.userChoice;
            window.__installPrompt = null;
            this.setState({ installPrompt: null });
            if (choice && choice.outcome === "accepted") this.setState({ installed: true });
            return;
        }
        // The browser only offers its install prompt once per visit (and never on plain http),
        // so when it is not available show the manual steps instead.
        const secure = window.isSecureContext;
        Swal.fire({
            title: "Install this POS as an app",
            width: 620,
            confirmButtonText: "OK",
            html: `<div class="text-left" style="font-size:15px">
                ${secure ? "" : `<div class="alert alert-warning">This page is not on <b>https</b>, so the browser will not install it. Open the site with https:// first.</div>`}
                <p class="mb-1"><b>Chrome / Edge on a PC</b></p>
                <ol class="pl-3"><li>Click the <b>install icon</b> at the right end of the address bar (a small screen with a down arrow),<br>or open the <b>⋮ menu → Cast, save and share → Install page as app</b> (Edge: <b>… → Apps → Install this site as an app</b>).</li><li>Click <b>Install</b>. A shortcut appears on the desktop.</li></ol>
                <p class="mb-1"><b>Android</b></p>
                <ol class="pl-3"><li>Open the browser menu <b>⋮</b> → <b>Install app</b> / <b>Add to Home screen</b>.</li></ol>
                <p class="mb-1"><b>iPhone / iPad (Safari)</b></p>
                <ol class="pl-3"><li>Tap <b>Share</b> → <b>Add to Home Screen</b>.</li></ol>
                <p class="text-muted mb-0"><small>After installing, open the POS once while online so products are saved for offline use.</small></p>
            </div>`,
        });
    }

    // ---- cart ----------------------------------------------------------------
    addProduct(product) {
        if (!product) return;
        const cart = this.state.cart;
        const inCart = cart.find((c) => c.id === product.id);
        const have = inCart ? inCart.qty : 0;
        if (product.quantity <= 0) {
            Swal.fire("Error!", "Out of stock", "error");
            return;
        }
        if (have + 1 > product.quantity) {
            Swal.fire("Error!", `Only ${product.quantity} available`, "error");
            return;
        }
        if (inCart) {
            this.setCart(cart.map((c) => (c.id === product.id ? { ...c, qty: c.qty + 1 } : c)));
        } else {
            this.setCart([
                ...cart,
                { id: product.id, name: product.name, barcode: product.barcode, price: product.price, mkt_price: product.mkt_price, qty: 1 },
            ]);
        }
    }

    addProductToCart(barcode) {
        this.addProduct(this.state.products.find((p) => p.barcode === barcode));
    }

    handleScanBarcode(event) {
        event.preventDefault();
        const code = this.state.barcode.trim();
        if (!code) return;
        const product = this.state.products.find((p) => p.barcode === code);
        if (!product) {
            Swal.fire("Error!", "Product not found for this barcode", "error");
        } else {
            this.addProduct(product);
        }
        this.setState({ barcode: "" });
    }

    handleChangeQty(id, value) {
        const stock = this.stockOf(id);
        let qty = parseInt(value, 10);
        if (isNaN(qty)) {
            // allow clearing the box while typing
            this.setCart(this.state.cart.map((c) => (c.id === id ? { ...c, qty: "" } : c)));
            return;
        }
        if (qty > stock) {
            Swal.fire("Error!", `Only ${stock} available`, "error");
            qty = stock;
        }
        if (qty < 1) qty = 1;
        this.setCart(this.state.cart.map((c) => (c.id === id ? { ...c, qty } : c)));
    }

    handleClickDelete(id) {
        this.setCart(this.state.cart.filter((c) => c.id !== id));
    }

    handleEmptyCart() {
        this.setCart([], { customer_id: "", discountValue: "", amountReceived: null });
    }

    computeTotals() {
        const items = this.state.cart.map((c) => ({ ...c, qty: Number(c.qty) || 0 }));
        return calcTotals(items, this.state.settings, this.state.discountType, this.state.discountValue, this.state.taxRate);
    }

    // ---- held bills (stored in this browser) -----------------------------------
    handleHoldBill() {
        if (!this.state.cart.length) return;
        const bill = {
            id: uuid(),
            created_at: new Date().toISOString(),
            customer_id: this.state.customer_id || null,
            items: this.state.cart,
        };
        this.setHeld([bill, ...this.state.held]);
        this.setCart([], { customer_id: "", discountValue: "", amountReceived: null });
        Swal.fire({ toast: true, position: "top-end", icon: "success", title: "Bill put on hold", showConfirmButton: false, timer: 1500 });
    }

    handleShowHeldBills() {
        const cur = this.state.settings.currency_symbol;
        const customers = this.state.customers;
        const rows = this.state.held.length
            ? this.state.held
                  .map((b) => {
                      const cust = customers.find((c) => c.id === b.customer_id);
                      const total = b.items.reduce((t, i) => t + i.price * i.qty, 0);
                      const count = b.items.reduce((t, i) => t + Number(i.qty), 0);
                      return `<tr>
                        <td class="text-left">${new Date(b.created_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}<br><small>${esc(cust ? cust.first_name + " " + cust.last_name : "General Customer")}</small></td>
                        <td>${count}</td><td>${cur} ${total.toFixed(2)}</td>
                        <td><button class="btn btn-sm btn-primary" data-resume="${b.id}">Resume</button>
                            <button class="btn btn-sm btn-danger" data-delete="${b.id}"><i class="fas fa-trash"></i></button></td></tr>`;
                  })
                  .join("")
            : `<tr><td colspan="4" class="text-muted">No held bills</td></tr>`;

        Swal.fire({
            title: "Held Bills",
            width: 650,
            showConfirmButton: false,
            showCloseButton: true,
            html: `<table class="table table-sm"><thead><tr><th class="text-left">Held</th><th>Items</th><th>Total</th><th></th></tr></thead><tbody>${rows}</tbody></table>`,
            didOpen: (popup) => {
                popup.addEventListener("click", (e) => {
                    const resume = e.target.closest("[data-resume]");
                    const del = e.target.closest("[data-delete]");
                    if (resume) {
                        if (this.state.cart.length) {
                            Swal.showValidationMessage("Hold or cancel the current bill before resuming another one.");
                            return;
                        }
                        const bill = this.state.held.find((b) => b.id === resume.dataset.resume);
                        const skipped = [];
                        const items = [];
                        bill.items.forEach((i) => {
                            const stock = this.stockOf(i.id);
                            if (stock < 1) skipped.push(i.name);
                            else {
                                const qty = Math.min(Number(i.qty), stock);
                                if (qty < Number(i.qty)) skipped.push(`${i.name} (${qty} of ${i.qty})`);
                                items.push({ ...i, qty });
                            }
                        });
                        this.setHeld(this.state.held.filter((b) => b.id !== bill.id));
                        this.setCart(items, { customer_id: bill.customer_id || "" });
                        Swal.close();
                        if (skipped.length) Swal.fire("Some items adjusted", "Out of stock / reduced: " + skipped.join(", "), "warning");
                    } else if (del) {
                        this.setHeld(this.state.held.filter((b) => b.id !== del.dataset.delete));
                        Swal.close();
                    }
                });
            },
        });
    }

    // ---- printing --------------------------------------------------------------
    printFrame(setup) {
        const old = document.getElementById("receipt-frame");
        if (old) old.remove();
        const frame = document.createElement("iframe");
        frame.id = "receipt-frame";
        frame.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;";
        setup(frame);
        document.body.appendChild(frame);
    }

    printServerReceipt(orderId) {
        this.printFrame((f) => {
            f.src = `/admin/orders/${orderId}/receipt?print=1`;
        });
    }

    printOfflineReceipt(sale, billNo) {
        const html = buildReceiptHtml(sale, billNo, this.state.settings, window.APP.cashier);
        this.printFrame((f) => {
            f.onload = () => {
                try {
                    f.contentWindow.focus();
                    f.contentWindow.print();
                } catch (e) {}
            };
            f.srcdoc = html;
        });
    }

    // ---- checkout --------------------------------------------------------------
    async handleClickSubmit() {
        if (this.state.submitting || !this.state.cart.length) return;
        const items = this.state.cart.map((c) => ({ ...c, qty: Number(c.qty) || 0 })).filter((c) => c.qty > 0);
        if (!items.length) return;

        const t = this.computeTotals();
        const { amountReceived, method, autoPrint, customer_id } = this.state;
        const amount = amountReceived === null || amountReceived === "" ? t.total : parseFloat(amountReceived) || 0;
        const customer = this.state.customers.find((c) => String(c.id) === String(customer_id));

        const sale = {
            uuid: uuid(),
            offline_ref: null,
            created_at: new Date().toISOString(),
            customer_id: customer_id || null,
            customer_name: customer ? `${customer.first_name} ${customer.last_name}` : "",
            method,
            amount: round2(amount),
            discount_type: this.state.discountType,
            discount_value: this.state.discountValue === "" ? null : this.state.discountValue,
            tax_rate: this.state.settings.enable_tax ? this.state.taxRate : null,
            totals: t,
            items: items.map((i) => ({
                product_id: i.id,
                name: i.name,
                barcode: i.barcode,
                quantity: i.qty,
                qty: i.qty,
                price: i.price,
                mkt_price: i.mkt_price,
            })),
        };

        this.setState({ submitting: true });
        // 1. the sale is written to the local queue first, so it can never be lost
        this.setQueue([...this.queueRef, sale]);
        // 2. local stock goes down and the cart is cleared
        this.setProducts(
            this.state.products.map((p) => {
                const line = items.find((i) => i.id === p.id);
                return line ? { ...p, quantity: p.quantity - line.qty } : p;
            })
        );
        this.setCart([], { customer_id: "", discountValue: "", amountReceived: null, submitting: false });
        this.focusInput(this.barcodeRef);

        // 3. try to upload right away
        const saved = await this.syncQueue();
        if (saved[sale.uuid]) {
            if (autoPrint) this.printServerReceipt(saved[sale.uuid]);
            Swal.fire({ toast: true, position: "top-end", icon: "success", title: `Payment recorded - Order #${saved[sale.uuid]}`, showConfirmButton: false, timer: 2500 });
            return;
        }

        const stillQueued = this.queueRef.find((q) => q.uuid === sale.uuid);
        if (stillQueued && stillQueued.failed) {
            Swal.fire({ toast: true, position: "top-end", icon: "error", title: "Sale not saved on the server", text: stillQueued.error + " — it stays in the list of unsent sales.", showConfirmButton: false, timer: 5000 });
            return;
        }

        // 4. offline: give the bill a temporary number and print it from the browser
        const seq = (load("offline_seq", 0) || 0) + 1;
        save("offline_seq", seq);
        const ref = "OFF-" + String(seq).padStart(4, "0");
        const withRef = { ...sale, offline_ref: ref };
        this.setQueue(this.queueRef.map((q) => (q.uuid === sale.uuid ? withRef : q)));
        if (autoPrint) this.printOfflineReceipt(withRef, ref);
        Swal.fire({ toast: true, position: "top-end", icon: "info", title: `Saved offline (${ref})`, text: "It will upload automatically when the internet returns.", showConfirmButton: false, timer: 3500 });
    }

    // ---- keyboard control ------------------------------------------------------
    // F1 barcode | F2 or / search | F4 received | F8 hold | F9 or Ctrl+Enter checkout
    // Arrows move over products, Enter adds | PgUp/PgDn pick cart row, + - qty, Del remove
    handleKeyDown(e) {
        // let dialogs (SweetAlert) handle their own keys
        if (document.querySelector(".swal2-container")) return;

        const tag = e.target.tagName;
        const typing = tag === "TEXTAREA" || tag === "SELECT" || (tag === "INPUT" && e.target.type !== "checkbox");
        const cart = this.state.cart;
        const stop = () => e.preventDefault();

        if (e.key === "F1") { stop(); this.focusInput(this.barcodeRef); return; }
        if (e.key === "F2") { stop(); this.focusInput(this.searchRef); return; }
        if (e.key === "F4") { stop(); this.focusInput(this.receivedRef); return; }
        if (e.key === "F8") { stop(); if (cart.length) this.handleHoldBill(); return; }
        if (e.key === "F9" || (e.ctrlKey && e.key === "Enter")) {
            stop();
            if (cart.length && !this.state.submitting) this.handleClickSubmit();
            return;
        }
        if (e.key === "Escape") {
            if (document.activeElement) document.activeElement.blur();
            this.setState({ activeProduct: -1, activeCart: -1 });
            this.focusInput(this.barcodeRef);
            return;
        }

        if (typing) {
            // from the search box, Arrow Down drops into the product grid
            if (e.target === this.searchRef.current && e.key === "ArrowDown") {
                stop();
                e.target.blur();
                this.moveProduct(0, true);
            }
            return;
        }

        if (e.key === "/") { stop(); this.focusInput(this.searchRef); return; }

        const arrows = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: "row", ArrowUp: "-row" };
        if (e.key in arrows) { stop(); this.moveProduct(arrows[e.key]); return; }

        if ((e.key === "Enter" || e.key === " ") && this.state.activeProduct >= 0) {
            stop();
            const p = this.visibleProducts()[this.state.activeProduct];
            if (p && p.quantity > 0) this.addProduct(p);
            return;
        }

        if (e.key === "PageDown" || e.key === "PageUp") {
            stop();
            if (!cart.length) return;
            const cur = this.state.activeCart;
            const next = e.key === "PageDown" ? Math.min(cur + 1, cart.length - 1) : Math.max(cur - 1, 0);
            this.setState({ activeCart: next });
            return;
        }
        const row = cart[this.state.activeCart];
        if (!row) return;
        if (e.key === "+" || e.key === "=") {
            stop();
            this.handleChangeQty(row.id, Number(row.qty) + 1);
        } else if (e.key === "-" || e.key === "_") {
            stop();
            if (Number(row.qty) > 1) this.handleChangeQty(row.id, Number(row.qty) - 1);
        } else if (e.key === "Delete" || e.key === "Backspace") {
            stop();
            this.handleClickDelete(row.id);
            this.setState({ activeCart: Math.max(this.state.activeCart - 1, cart.length > 1 ? 0 : -1) });
        }
    }

    focusInput(ref) {
        if (ref.current) {
            ref.current.focus();
            if (ref.current.select) ref.current.select();
        }
    }

    // move the highlighted product tile; step is a number, "row" or "-row"
    moveProduct(step, first = false) {
        const tiles = document.querySelectorAll(".order-product .item");
        if (!tiles.length) return;
        let cols = 1;
        while (cols < tiles.length && tiles[cols].offsetTop === tiles[0].offsetTop) cols++;

        let idx = this.state.activeProduct;
        if (first) {
            idx = 0;
        } else {
            const delta = step === "row" ? cols : step === "-row" ? -cols : step;
            if (idx < 0) {
                idx = 0;
            } else if (delta < 0 && idx + delta < 0 && idx < cols) {
                // up from the first row goes back to the search box
                this.setState({ activeProduct: -1 });
                this.focusInput(this.searchRef);
                return;
            } else {
                idx = Math.min(Math.max(idx + delta, 0), tiles.length - 1);
            }
        }
        this.setState({ activeProduct: idx });
        tiles[idx].scrollIntoView({ block: "nearest" });
    }

    visibleProducts() {
        const q = this.state.search.trim().toLowerCase();
        if (!q) return this.state.products;
        return this.state.products.filter((p) => p.name.toLowerCase().includes(q) || String(p.barcode).toLowerCase().includes(q));
    }

    // ---- render ----------------------------------------------------------------
    render() {
        const { cart, customers, barcode, settings, queue, held } = this.state;
        const cur = settings.currency_symbol;
        const products = this.visibleProducts();
        const offline = !this.state.online || !this.state.reachable;
        const waiting = queue.filter((q) => !q.failed).length;
        const failed = queue.filter((q) => q.failed).length;
        const t = this.computeTotals();
        const received = this.state.amountReceived === null ? t.total.toFixed(2) : this.state.amountReceived;
        const diff = (parseFloat(received) || 0) - t.total;
        const set = (patch) => this.setState(patch);

        return (
            <div>
                <div className="pos-status d-flex flex-wrap align-items-center mb-2">
                    <span className={"badge mr-2 " + (offline ? "badge-danger" : "badge-success")}>
                        <i className={"fas " + (offline ? "fa-wifi" : "fa-check-circle")}></i> {offline ? "Offline" : "Online"}
                    </span>
                    {waiting > 0 && (
                        <span className="badge badge-warning mr-2">
                            {waiting} sale{waiting > 1 ? "s" : ""} waiting to sync
                        </span>
                    )}
                    {failed > 0 && (
                        <button className="btn btn-xs btn-danger mr-2" onClick={() => this.showFailedSales()}>
                            {failed} sale{failed > 1 ? "s" : ""} not saved – review
                        </button>
                    )}
                    {this.state.sessionExpired && (
                        <a className="badge badge-danger mr-2" href="/login" target="_blank" rel="noreferrer">
                            Session expired – log in again to sync
                        </a>
                    )}
                    <button className="btn btn-xs btn-outline-secondary mr-2" onClick={() => this.handleManualSync()} disabled={this.state.syncing || !this.state.online}>
                        <i className={"fas fa-sync" + (this.state.syncing ? " fa-spin" : "")}></i> Sync now
                    </button>
                    {!this.isInstalled() && (
                        <button className="btn btn-xs btn-outline-primary" onClick={() => this.handleInstall()}>
                            <i className="fas fa-download"></i> Install app
                        </button>
                    )}
                </div>

                <div className="row">
                    <div className="col-md-6 col-lg-4">
                        <div className="row mb-2">
                            <div className="col">
                                <form onSubmit={this.handleScanBarcode}>
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="Scan Barcode"
                                        ref={this.barcodeRef}
                                        value={barcode}
                                        onChange={(e) => set({ barcode: e.target.value })}
                                    />
                                </form>
                            </div>
                            <div className="col">
                                <select className="form-control" value={this.state.customer_id} onChange={(e) => set({ customer_id: e.target.value })}>
                                    <option value="">General Customer</option>
                                    {customers.map((cus) => (
                                        <option key={cus.id} value={cus.id}>{`${cus.first_name} ${cus.last_name}`}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="user-cart">
                            <div className="card">
                                <table className="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Quantity</th>
                                            <th className="text-right">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cart.map((c, ci) => (
                                            <tr key={c.id} className={ci === this.state.activeCart ? "table-primary" : ""}>
                                                <td>{c.name}</td>
                                                <td>
                                                    <input
                                                        type="text"
                                                        className="form-control form-control-sm qty"
                                                        value={c.qty}
                                                        onChange={(e) => this.handleChangeQty(c.id, e.target.value)}
                                                    />
                                                    <button className="btn btn-danger btn-sm" onClick={() => this.handleClickDelete(c.id)}>
                                                        <i className="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                                <td className="text-right">
                                                    {cur} {(c.price * (Number(c.qty) || 0)).toFixed(2)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="checkout-panel border rounded p-2 mb-2">
                            <div className="d-flex justify-content-between">
                                <span>Subtotal</span>
                                <span>{cur} {t.subtotal.toFixed(2)}</span>
                            </div>
                            {settings.enable_discount && (
                                <div className="d-flex justify-content-between align-items-center my-1">
                                    <span>Discount</span>
                                    <div className="input-group input-group-sm" style={{ maxWidth: 170 }}>
                                        <input
                                            type="number" min="0" step="0.01" className="form-control text-right" placeholder="0"
                                            value={this.state.discountValue}
                                            onChange={(e) => set({ discountValue: e.target.value, amountReceived: null })}
                                        />
                                        <select
                                            className="form-control" style={{ maxWidth: 80 }} value={this.state.discountType}
                                            onChange={(e) => set({ discountType: e.target.value, amountReceived: null })}
                                        >
                                            <option value="fixed">{cur}</option>
                                            <option value="percent">%</option>
                                        </select>
                                    </div>
                                </div>
                            )}
                            {settings.enable_tax && (
                                <div className="d-flex justify-content-between align-items-center my-1">
                                    <span>{settings.tax_name} (%)</span>
                                    <div className="d-flex align-items-center">
                                        <small className="mr-2 text-muted">{cur} {t.tax.toFixed(2)}</small>
                                        <input
                                            type="number" min="0" max="100" step="0.01" className="form-control form-control-sm text-right" style={{ width: 80 }}
                                            value={this.state.taxRate}
                                            onChange={(e) => set({ taxRate: e.target.value, amountReceived: null })}
                                        />
                                    </div>
                                </div>
                            )}
                            <div className="d-flex justify-content-between font-weight-bold border-top pt-1 mt-1" style={{ fontSize: "1.15rem" }}>
                                <span>Total</span>
                                <span>{cur} {t.total.toFixed(2)}</span>
                            </div>
                            <div className="d-flex justify-content-between align-items-center mt-2">
                                <span>Payment</span>
                                <select
                                    className="form-control form-control-sm" style={{ maxWidth: 170 }} value={this.state.method}
                                    onChange={(e) => set(e.target.value === "cash" ? { method: "cash" } : { method: e.target.value, amountReceived: null })}
                                >
                                    {Object.entries(METHODS).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="d-flex justify-content-between align-items-center mt-1">
                                <span>Received</span>
                                <input
                                    type="number" min="0" step="0.01" className="form-control form-control-sm text-right" style={{ maxWidth: 170 }}
                                    ref={this.receivedRef} value={received}
                                    onChange={(e) => set({ amountReceived: e.target.value })}
                                />
                            </div>
                            {cart.length > 0 && (
                                <div className={"text-right font-weight-bold " + (diff >= 0 ? "text-success" : "text-danger")}>
                                    {diff >= 0 ? `Change: ${cur} ${diff.toFixed(2)}` : `Balance due: ${cur} ${(-diff).toFixed(2)}`}
                                </div>
                            )}
                            <div className="custom-control custom-checkbox mt-1">
                                <input
                                    type="checkbox" className="custom-control-input" id="auto-print" checked={this.state.autoPrint}
                                    onChange={(e) => {
                                        try {
                                            localStorage.setItem("pos_auto_print", e.target.checked ? "1" : "0");
                                        } catch (err) {}
                                        set({ autoPrint: e.target.checked });
                                    }}
                                />
                                <label className="custom-control-label" htmlFor="auto-print">Print receipt automatically</label>
                            </div>
                        </div>

                        <div className="row mb-2 mt-2">
                            <div className="col">
                                <button type="button" className="btn btn-warning btn-block" disabled={!cart.length} onClick={this.handleHoldBill}>
                                    <i className="fas fa-pause"></i> Hold Bill
                                </button>
                            </div>
                            <div className="col">
                                <button type="button" className="btn btn-info btn-block" onClick={this.handleShowHeldBills}>
                                    <i className="fas fa-list"></i> Held Bills ({held.length})
                                </button>
                            </div>
                        </div>
                        <div className="row">
                            <div className="col">
                                <button type="button" className="btn btn-danger btn-block" onClick={this.handleEmptyCart} disabled={!cart.length}>
                                    Cancel
                                </button>
                            </div>
                            <div className="col">
                                <button type="button" className="btn btn-primary btn-block" disabled={!cart.length || this.state.submitting} onClick={this.handleClickSubmit}>
                                    Checkout
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="col-md-6 col-lg-8">
                        <div className="mb-2">
                            <input
                                type="text" className="form-control" placeholder="Search Product..."
                                ref={this.searchRef} value={this.state.search}
                                onChange={(e) => set({ search: e.target.value, activeProduct: -1 })}
                            />
                            <small className="text-muted d-block mt-1 kbd-hints">
                                <b>F1</b> Barcode · <b>F2</b> Search · <b>↑↓←→</b> Select · <b>Enter</b> Add · <b>PgUp/PgDn</b> Cart row · <b>+ / −</b> Qty · <b>Del</b> Remove · <b>F4</b> Received · <b>F8</b> Hold · <b>F9</b> Checkout · <b>Esc</b> Reset
                            </small>
                        </div>
                        <div className="order-product">
                            {products.map((p, pi) => {
                                const out = p.quantity <= 0;
                                const low = !out && Number(settings.warning_quantity) >= p.quantity;
                                const state = out ? "out" : low ? "low" : "ok";
                                return (
                                    <div
                                        onClick={() => !out && this.addProduct(p)}
                                        key={p.id}
                                        className={`item item-${state}${pi === this.state.activeProduct ? " item-active" : ""}`}
                                        title={p.name}
                                    >
                                        <div className="item-name">{p.name}</div>
                                        <div className="item-meta">
                                            <span className="item-price">{cur} {Number(p.price).toFixed(2)}</span>
                                            <span className="item-stock">{out ? "Out of stock" : `${p.quantity} left`}</span>
                                        </div>
                                    </div>
                                );
                            })}
                            {!products.length && <div className="text-muted p-3">No products found.</div>}
                        </div>
                    </div>
                </div>
            </div>
        );
    }
}

export default Cart;

const root = document.getElementById("cart");
if (root) {
    const rootInstance = createRoot(root);
    rootInstance.render(<Cart />);
}
