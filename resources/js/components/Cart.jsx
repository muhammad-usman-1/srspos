import React, { Component } from "react";
import { createRoot } from "react-dom/client";
import axios from "axios";
import Swal from "sweetalert2";
import { sum } from "lodash";

class Cart extends Component {
    constructor(props) {
        super(props);
        this.state = {
            cart: [],
            products: [],
            customers: [],
            barcode: "",
            search: "",
            customer_id: "",
            translations: {},
            heldBills: [],
            method: "cash",
            discountType: "fixed",
            discountValue: "",
            taxRate: window.APP.tax_rate || 0,
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

        this.barcodeRef = React.createRef();
        this.searchRef = React.createRef();
        this.receivedRef = React.createRef();
        this.handleKeyDown = this.handleKeyDown.bind(this);

        this.loadCart = this.loadCart.bind(this);
        this.handleOnChangeBarcode = this.handleOnChangeBarcode.bind(this);
        this.handleScanBarcode = this.handleScanBarcode.bind(this);
        this.handleChangeQty = this.handleChangeQty.bind(this);
        this.handleEmptyCart = this.handleEmptyCart.bind(this);

        this.loadProducts = this.loadProducts.bind(this);
        this.handleChangeSearch = this.handleChangeSearch.bind(this);
        this.handleSeach = this.handleSeach.bind(this);
        this.setCustomerId = this.setCustomerId.bind(this);
        this.handleClickSubmit = this.handleClickSubmit.bind(this);
        this.loadTranslations = this.loadTranslations.bind(this);
        this.loadHeldBills = this.loadHeldBills.bind(this);
        this.handleHoldBill = this.handleHoldBill.bind(this);
        this.handleShowHeldBills = this.handleShowHeldBills.bind(this);
    }

    componentDidMount() {
        // load user cart
        this.loadTranslations();
        this.loadCustomers();
        this.loadProducts();
        this.loadCart();
        this.loadHeldBills();
        document.addEventListener("keydown", this.handleKeyDown);
        if (this.barcodeRef.current) this.barcodeRef.current.focus();
    }

    componentWillUnmount() {
        document.removeEventListener("keydown", this.handleKeyDown);
    }

    // ---- keyboard control -------------------------------------------------
    // F1 barcode | F2 or / search | F4 received | F8 hold | F9 or Ctrl+Enter checkout
    // Arrows move over products, Enter adds | PgUp/PgDn pick cart row, + - qty, Del remove
    handleKeyDown(e) {
        // let dialogs (SweetAlert) handle their own keys
        if (document.querySelector(".swal2-container")) return;

        const tag = e.target.tagName;
        const typing =
            tag === "TEXTAREA" ||
            tag === "SELECT" ||
            (tag === "INPUT" && e.target.type !== "checkbox");
        const cart = this.state.cart;
        const stop = () => e.preventDefault();

        // shortcuts that work everywhere
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
            const p = this.state.products[this.state.activeProduct];
            if (p && p.quantity > 0) this.addProductToCart(p.barcode);
            return;
        }

        // cart row selection and editing
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
            const stock = (this.state.products.find((p) => p.id === row.id) || {}).quantity;
            const qty = Number(row.pivot.quantity) + 1;
            if (stock === undefined || qty <= stock) this.handleChangeQty(row.id, qty);
        } else if (e.key === "-" || e.key === "_") {
            stop();
            const qty = Number(row.pivot.quantity) - 1;
            if (qty >= 1) this.handleChangeQty(row.id, qty);
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
    // load the transaltions for the react component
    loadTranslations() {
        axios
            .get("/admin/locale/cart")
            .then((res) => {
                const translations = res.data;
                this.setState({ translations });
            })
            .catch((error) => {
                console.error("Error loading translations:", error);
                this.setState({ translations: {} });
            });
    }

    loadCustomers() {
        axios.get(`/admin/customers`, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }).then((res) => {
            const customers = res.data;
            this.setState({ customers });
        }).catch((error) => {
            console.error("Error loading customers:", error);
            this.setState({ customers: [] });
        });
    }

    loadProducts(search = "") {
        const query = !!search ? `?search=${search}` : "";
        axios.get(`/admin/products${query}`, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }).then((res) => {
            const products = res.data.data || [];
            this.setState({ products });
        }).catch((error) => {
            console.error("Error loading products:", error);
            this.setState({ products: [] });
        });
    }

    handleOnChangeBarcode(event) {
        const barcode = event.target.value;
        this.setState({ barcode });
    }

    loadCart() {
        axios.get("/admin/cart", {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }).then((res) => {
            const cart = Array.isArray(res.data) ? res.data : [];
            this.setState({ cart });
        }).catch((error) => {
            console.error("Error loading cart:", error);
            this.setState({ cart: [] });
        });
    }
    handleScanBarcode(event) {
        event.preventDefault();
        const { barcode } = this.state;
        if (!!barcode) {
            axios
                .post("/admin/cart", { barcode })
                .then((res) => {
                    this.loadCart();
                    this.setState({ barcode: "" });
                })
                .catch((err) => {
                    Swal.fire("Error!", err.response.data.message, "error");
                });
        }
    }
    handleChangeQty(product_id, qty) {
        const cart = this.state.cart.map((c) => {
            if (c.id === product_id) {
                c.pivot.quantity = qty;
            }
            return c;
        });

        this.setState({ cart });
        if (!qty) return;

        axios
            .post("/admin/cart/change-qty", { product_id, quantity: qty })
            .then((res) => {})
            .catch((err) => {
                Swal.fire("Error!", err.response.data.message, "error");
            });
    }

    getTotal(cart) {
        const total = cart.map((c) => c.pivot.quantity * c.price);
        return sum(total).toFixed(2);
    }
    handleClickDelete(product_id) {
        axios
            .post("/admin/cart/delete", { product_id, _method: "DELETE" })
            .then((res) => {
                const cart = this.state.cart.filter((c) => c.id !== product_id);
                this.setState({ cart });
            });
    }
    handleEmptyCart() {
        axios.post("/admin/cart/empty", { _method: "DELETE" }).then((res) => {
            this.setState({ cart: [] });
        });
    }
    handleChangeSearch(event) {
        const search = event.target.value;
        this.setState({ search });
    }
    handleSeach(event) {
        if (event.keyCode === 13) {
            this.loadProducts(event.target.value);
        }
    }

    addProductToCart(barcode) {
        let product = this.state.products.find((p) => p.barcode === barcode);
        if (!!product) {
            // if product is already in cart
            let cart = this.state.cart.find((c) => c.id === product.id);
            if (!!cart) {
                // update quantity
                this.setState({
                    cart: this.state.cart.map((c) => {
                        if (
                            c.id === product.id &&
                            product.quantity > c.pivot.quantity
                        ) {
                            c.pivot.quantity = c.pivot.quantity + 1;
                        }
                        return c;
                    }),
                });
            } else {
                if (product.quantity > 0) {
                    product = {
                        ...product,
                        pivot: {
                            quantity: 1,
                            product_id: product.id,
                            user_id: 1,
                        },
                    };

                    this.setState({ cart: [...this.state.cart, product] });
                }
            }

            axios
                .post("/admin/cart", { barcode })
                .then((res) => {
                    // this.loadCart();
                })
                .catch((err) => {
                    Swal.fire("Error!", err.response.data.message, "error");
                });
        }
    }

    setCustomerId(event) {
        this.setState({ customer_id: event.target.value });
    }
    loadHeldBills() {
        axios
            .get("/admin/held-bills")
            .then((res) => this.setState({ heldBills: res.data }))
            .catch(() => this.setState({ heldBills: [] }));
    }

    handleHoldBill() {
        axios
            .post("/admin/held-bills", {
                customer_id: this.state.customer_id || null,
            })
            .then(() => {
                this.setState({ cart: [], customer_id: "", discountValue: "", amountReceived: null });
                this.loadHeldBills();
                this.loadProducts();
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    icon: "success",
                    title: "Bill put on hold",
                    showConfirmButton: false,
                    timer: 1500,
                });
            })
            .catch((err) => {
                Swal.fire("Error!", err.response?.data?.message || "Could not hold bill", "error");
            });
    }

    handleShowHeldBills() {
        const money = (n) => `${window.APP.currency_symbol} ${Number(n).toFixed(2)}`;
        const esc = (s) =>
            String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
        const bills = this.state.heldBills;
        const rows = bills.length
            ? bills
                  .map(
                      (b) => `<tr>
                        <td>#${b.id}</td>
                        <td class="text-left">${esc(b.note) || "-"}<br><small>${esc(b.customer) || "General Customer"} · ${b.created_at}</small></td>
                        <td>${b.items_count}</td>
                        <td>${money(b.total)}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-resume="${b.id}">Resume</button>
                            <button class="btn btn-sm btn-danger" data-delete="${b.id}"><i class="fas fa-trash"></i></button>
                        </td></tr>`
                  )
                  .join("")
            : `<tr><td colspan="5" class="text-muted">No held bills</td></tr>`;

        Swal.fire({
            title: "Held Bills",
            width: 700,
            showConfirmButton: false,
            showCloseButton: true,
            html: `<table class="table table-sm"><thead><tr><th>#</th><th class="text-left">Note</th><th>Items</th><th>Total</th><th></th></tr></thead><tbody>${rows}</tbody></table>`,
            didOpen: (popup) => {
                popup.addEventListener("click", (e) => {
                    const resume = e.target.closest("[data-resume]");
                    const del = e.target.closest("[data-delete]");
                    if (resume) {
                        axios
                            .post(`/admin/held-bills/${resume.dataset.resume}/resume`)
                            .then((res) => {
                                Swal.close();
                                this.setState({ customer_id: res.data.customer_id || "" });
                                this.loadCart();
                                this.loadHeldBills();
                                if (res.data.skipped.length) {
                                    Swal.fire("Some items adjusted", "Out of stock / reduced: " + res.data.skipped.join(", "), "warning");
                                }
                            })
                            .catch((err) => Swal.showValidationMessage(err.response?.data?.message || "Could not resume bill"));
                    } else if (del) {
                        axios.delete(`/admin/held-bills/${del.dataset.delete}`).then(() => {
                            this.loadHeldBills();
                            Swal.close();
                        });
                    }
                });
            },
        });
    }

    printReceipt(orderId, tendered) {
        const old = document.getElementById("receipt-frame");
        if (old) old.remove();
        const frame = document.createElement("iframe");
        frame.id = "receipt-frame";
        frame.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;";
        frame.src = `/admin/orders/${orderId}/receipt?print=1&tendered=${encodeURIComponent(tendered)}`;
        document.body.appendChild(frame);
    }

    // subtotal -> discount -> tax, mirrors OrderController::store
    computeTotals() {
        const subtotal = parseFloat(this.getTotal(this.state.cart)) || 0;
        const { discountType, discountValue, taxRate } = this.state;
        let discount = 0;
        if (window.APP.enable_discount) {
            const v = parseFloat(discountValue) || 0;
            discount = discountType === "percent" ? (subtotal * Math.min(v, 100)) / 100 : v;
            discount = Math.round(Math.min(Math.max(discount, 0), subtotal) * 100) / 100;
        }
        const rate = window.APP.enable_tax ? parseFloat(taxRate) || 0 : 0;
        const tax = Math.round((subtotal - discount) * rate) / 100;
        const total = Math.round((subtotal - discount + tax) * 100) / 100;
        return { subtotal, discount, tax, total };
    }

    handleClickSubmit() {
        const { total } = this.computeTotals();
        const { amountReceived, method, autoPrint } = this.state;
        const amount = amountReceived === null ? total.toFixed(2) : amountReceived;
        this.setState({ submitting: true });

        axios
            .post("/admin/orders", {
                customer_id: this.state.customer_id,
                amount,
                method,
                discount_type: this.state.discountType,
                discount_value: this.state.discountValue,
                tax_rate: window.APP.enable_tax ? this.state.taxRate : undefined,
            })
            .then((res) => {
                this.setState({
                    submitting: false,
                    customer_id: "",
                    discountValue: "",
                    amountReceived: null,
                });
                this.loadCart();
                this.loadProducts();
                if (autoPrint) this.printReceipt(res.data.order_id, amount);
                Swal.fire({
                    icon: "success",
                    title: "Payment recorded",
                    text: `Order #${res.data.order_id}`,
                    timer: 1500,
                    showConfirmButton: false,
                });
            })
            .catch((err) => {
                this.setState({ submitting: false });
                const errors = err.response?.data?.errors;
                const msg = errors
                    ? Object.values(errors).flat().join("\n")
                    : err.response?.data?.message || "Checkout failed";
                Swal.fire("Error!", msg, "error");
            });
    }

    render() {
        const { cart = [], products = [], customers = [], barcode = "", translations = {} } = this.state;
        return (
            <div className="row">
                <div className="col-md-6 col-lg-4">
                    <div className="row mb-2">
                        <div className="col">
                            <form onSubmit={this.handleScanBarcode}>
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder={translations["scan_barcode"] || "Scan Barcode"}
                                    ref={this.barcodeRef}
                                    value={barcode}
                                    onChange={this.handleOnChangeBarcode}
                                />
                            </form>
                        </div>
                        <div className="col">
                            <select
                                className="form-control"
                                value={this.state.customer_id}
                                onChange={this.setCustomerId}
                            >
                                <option value="">
                                    {translations["general_customer"] || "General Customer"}
                                </option>
                                {customers.map((cus) => (
                                    <option
                                        key={cus.id}
                                        value={cus.id}
                                    >{`${cus.first_name} ${cus.last_name}`}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="user-cart">
                        <div className="card">
                            <table className="table table-striped">
                                <thead>
                                <tr>
                                    <th>{translations["product_name"] || "Product"}</th>
                                    <th>{translations["quantity"] || "Qty"}</th>
                                    <th className="text-right">
                                        {translations["price"] || "Price"}
                                    </th>
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
                                                value={c.pivot.quantity}
                                                onChange={(event) =>
                                                    this.handleChangeQty(
                                                        c.id,
                                                        event.target.value
                                                    )
                                                }
                                            />
                                            <button
                                                className="btn btn-danger btn-sm"
                                                onClick={() =>
                                                    this.handleClickDelete(
                                                        c.id
                                                    )
                                                }
                                            >
                                                <i className="fas fa-trash"></i>
                                            </button>
                                        </td>
                                        <td className="text-right">
                                            {window.APP.currency_symbol}{" "}
                                            {(
                                                c.price * c.pivot.quantity
                                            ).toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {(() => {
                        const cur = window.APP.currency_symbol;
                        const t = this.computeTotals();
                        const received = this.state.amountReceived === null ? t.total.toFixed(2) : this.state.amountReceived;
                        const diff = (parseFloat(received) || 0) - t.total;
                        const set = (patch) => this.setState(patch);
                        return (
                            <div className="checkout-panel border rounded p-2 mb-2">
                                <div className="d-flex justify-content-between">
                                    <span>Subtotal</span>
                                    <span>{cur} {t.subtotal.toFixed(2)}</span>
                                </div>
                                {window.APP.enable_discount && (
                                    <div className="d-flex justify-content-between align-items-center my-1">
                                        <span>Discount</span>
                                        <div className="input-group input-group-sm" style={{ maxWidth: 170 }}>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className="form-control text-right"
                                                placeholder="0"
                                                value={this.state.discountValue}
                                                onChange={(e) => set({ discountValue: e.target.value, amountReceived: null })}
                                            />
                                            <select
                                                className="form-control"
                                                style={{ maxWidth: 80 }}
                                                value={this.state.discountType}
                                                onChange={(e) => set({ discountType: e.target.value, amountReceived: null })}
                                            >
                                                <option value="fixed">{cur}</option>
                                                <option value="percent">%</option>
                                            </select>
                                        </div>
                                    </div>
                                )}
                                {window.APP.enable_tax && (
                                    <div className="d-flex justify-content-between align-items-center my-1">
                                        <span>{window.APP.tax_name} (%)</span>
                                        <div className="d-flex align-items-center">
                                            <small className="mr-2 text-muted">{cur} {t.tax.toFixed(2)}</small>
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                className="form-control form-control-sm text-right"
                                                style={{ width: 80 }}
                                                value={this.state.taxRate}
                                                onChange={(e) => set({ taxRate: e.target.value, amountReceived: null })}
                                            />
                                        </div>
                                    </div>
                                )}
                                <div className="d-flex justify-content-between font-weight-bold border-top pt-1 mt-1" style={{ fontSize: "1.15rem" }}>
                                    <span>{translations["total"] || "Total"}</span>
                                    <span>{cur} {t.total.toFixed(2)}</span>
                                </div>
                                <div className="d-flex justify-content-between align-items-center mt-2">
                                    <span>Payment</span>
                                    <select
                                        className="form-control form-control-sm"
                                        style={{ maxWidth: 170 }}
                                        value={this.state.method}
                                        onChange={(e) => {
                                            // non-cash methods are normally paid in full
                                            set(e.target.value === "cash" ? { method: "cash" } : { method: e.target.value, amountReceived: null });
                                        }}
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="easypaisa">EasyPaisa</option>
                                        <option value="jazzcash">JazzCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                <div className="d-flex justify-content-between align-items-center mt-1">
                                    <span>Received</span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        className="form-control form-control-sm text-right"
                                        style={{ maxWidth: 170 }}
                                        ref={this.receivedRef}
                                        value={received}
                                        onChange={(e) => set({ amountReceived: e.target.value })}
                                    />
                                </div>
                                {this.state.cart.length > 0 && (
                                    <div className={"text-right font-weight-bold " + (diff >= 0 ? "text-success" : "text-danger")}>
                                        {diff >= 0
                                            ? `Change: ${cur} ${diff.toFixed(2)}`
                                            : `Balance due: ${cur} ${(-diff).toFixed(2)}`}
                                    </div>
                                )}
                                <div className="custom-control custom-checkbox mt-1">
                                    <input
                                        type="checkbox"
                                        className="custom-control-input"
                                        id="auto-print"
                                        checked={this.state.autoPrint}
                                        onChange={(e) => {
                                            try {
                                                localStorage.setItem("pos_auto_print", e.target.checked ? "1" : "0");
                                            } catch (err) {}
                                            set({ autoPrint: e.target.checked });
                                        }}
                                    />
                                    <label className="custom-control-label" htmlFor="auto-print">
                                        Print receipt automatically
                                    </label>
                                </div>
                            </div>
                        );
                    })()}
                    <div className="row mb-2 mt-2">
                        <div className="col">
                            <button
                                type="button"
                                className="btn btn-warning btn-block"
                                disabled={!cart.length}
                                onClick={this.handleHoldBill}
                            >
                                <i className="fas fa-pause"></i> Hold Bill
                            </button>
                        </div>
                        <div className="col">
                            <button
                                type="button"
                                className="btn btn-info btn-block"
                                onClick={this.handleShowHeldBills}
                            >
                                <i className="fas fa-list"></i> Held Bills ({this.state.heldBills.length})
                            </button>
                        </div>
                    </div>
                    <div className="row">
                        <div className="col">
                            <button
                                type="button"
                                className="btn btn-danger btn-block"
                                onClick={this.handleEmptyCart}
                                disabled={!cart.length}
                            >
                                {translations["cancel"] || "Cancel"}
                            </button>
                        </div>
                        <div className="col">
                            <button
                                type="button"
                                className="btn btn-primary btn-block"
                                disabled={!cart.length || this.state.submitting}
                                onClick={this.handleClickSubmit}
                            >
                                {translations["checkout"] || "Checkout"}
                            </button>
                        </div>
                    </div>
                </div>
                <div className="col-md-6 col-lg-8">
                    <div className="mb-2">
                        <input
                            type="text"
                            className="form-control"
                            placeholder={(translations["search_product"] || "Search Product") + "..."}
                            ref={this.searchRef}
                            onChange={this.handleChangeSearch}
                            onKeyDown={this.handleSeach}
                        />
                        <small className="text-muted d-block mt-1 kbd-hints">
                            <b>F1</b> Barcode · <b>F2</b> Search · <b>↑↓←→</b> Select · <b>Enter</b> Add · <b>PgUp/PgDn</b> Cart row · <b>+ / −</b> Qty · <b>Del</b> Remove · <b>F4</b> Received · <b>F8</b> Hold · <b>F9</b> Checkout · <b>Esc</b> Reset
                        </small>
                    </div>
                    <div className="order-product">
                        {products.map((p, pi) => {
                            const out = p.quantity <= 0;
                            const low = !out && Number(window.APP.warning_quantity) >= p.quantity;
                            const state = out ? "out" : low ? "low" : "ok";
                            return (
                                <div
                                    onClick={() => !out && this.addProductToCart(p.barcode)}
                                    key={p.id}
                                    className={`item item-${state}${pi === this.state.activeProduct ? " item-active" : ""}`}
                                    title={p.name}
                                >
                                    <div className="item-name">{p.name}</div>
                                    <div className="item-meta">
                                        <span className="item-price">
                                            {window.APP.currency_symbol} {Number(p.price).toFixed(2)}
                                        </span>
                                        <span className="item-stock">
                                            {out ? "Out of stock" : `${p.quantity} left`}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
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
