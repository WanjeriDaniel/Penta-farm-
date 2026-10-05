<?php

require_once "../config/database.php";

$error = "";
$products = [];

try {

    /*
    |--------------------------------------------------------------------------
    | Load Active Products
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            product_name,
            unit,
            selling_price,
            buying_price,
            stock_quantity,
            minimum_sale_quantity,
            quantity_step
        FROM products
        WHERE is_active = 1
        ORDER BY product_name ASC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = $e->getMessage();

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Penta Farm POS</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #222;
}

.container {
    width: 96%;
    max-width: 1400px;
    margin: 20px auto;
}


/* =========================================================
   HEADER
========================================================= */

.header {
    background: linear-gradient(
        135deg,
        #1e3c72,
        #2a5298
    );

    color: white;
    padding: 22px;
    border-radius: 14px;
    margin-bottom: 20px;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.header h1 {
    margin: 0 0 5px;
}

.header p {
    margin: 0;
    opacity: .9;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.button {
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: bold;
}

.btn-light {
    background: white;
    color: #1e3c72;
}

.btn-dark {
    background: rgba(255,255,255,.15);
    color: white;
    border: 1px solid rgba(255,255,255,.35);
}


/* =========================================================
   POS LAYOUT
========================================================= */

.pos-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.panel {
    background: white;
    padding: 20px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,.06);
}

.panel h2 {
    margin-top: 0;
}


/* =========================================================
   SEARCH
========================================================= */

.search-box {
    margin-bottom: 15px;
}

.search-box input {
    width: 100%;
    padding: 14px 16px;

    border: 1px solid #ccc;
    border-radius: 9px;

    font-size: 16px;
    outline: none;
}

.search-box input:focus {
    border-color: #2a5298;
}

.results {
    border: 1px solid #ddd;
    border-radius: 9px;
    max-height: 380px;
    overflow-y: auto;
}

.product-result {
    padding: 13px;
    border-bottom: 1px solid #eee;
    cursor: pointer;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.product-result:last-child {
    border-bottom: none;
}

.product-result:hover {
    background: #f3f6fb;
}

.product-name {
    font-weight: bold;
}

.product-info {
    color: #666;
    font-size: 13px;
    margin-top: 4px;
}

.product-price {
    font-weight: bold;
    white-space: nowrap;
}

.out {
    color: #b00000;
}


/* =========================================================
   CART
========================================================= */

.cart-table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 11px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th {
    background: #f5f6f8;
    font-size: 13px;
}

.qty-input {
    width: 90px;
    padding: 7px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.remove-btn {
    border: none;
    background: #ffe5e5;
    color: #a00000;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
}

.empty {
    text-align: center;
    color: #777;
    padding: 35px;
}


/* =========================================================
   CHECKOUT
========================================================= */

.checkout {
    position: sticky;
    top: 20px;
}

.form-group {
    margin-top: 15px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 11px;

    border: 1px solid #ccc;
    border-radius: 7px;

    font-size: 14px;
    font-family: inherit;
}

.form-group textarea {
    min-height: 75px;
    resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #2a5298;
}


/* =========================================================
   SALE TYPE
========================================================= */

.sale-type-box {
    background: #f5f7fb;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 5px;
}

.sale-type-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.sale-type-btn {
    border: 2px solid #d7dce5;
    background: white;
    padding: 12px;
    border-radius: 8px;

    font-weight: bold;
    cursor: pointer;
    color: #555;
}

.sale-type-btn.active {
    border-color: #2a5298;
    background: #eef3ff;
    color: #1e3c72;
}


/* =========================================================
   INVOICE
========================================================= */

.invoice-section {
    display: none;

    margin-top: 15px;
    padding: 15px;

    border: 1px solid #dfe4ec;
    border-radius: 10px;

    background: #fafbfd;
}

.invoice-section.show {
    display: block;
}

.invoice-heading {
    color: #1e3c72;
    font-size: 15px;
    font-weight: bold;
    margin-bottom: 5px;
}


/* =========================================================
   DELIVERY
========================================================= */

.delivery-box {
    margin-top: 15px;

    padding: 15px;

    border: 1px solid #dfe4ec;

    border-radius: 10px;

    background: #fafbfd;
}

.delivery-title {
    color: #1e3c72;

    font-size: 15px;

    font-weight: bold;

    margin-bottom: 10px;
}

.delivery-buttons {
    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 10px;
}

.delivery-btn {
    border: 2px solid #d7dce5;

    background: white;

    padding: 11px;

    border-radius: 8px;

    font-weight: bold;

    cursor: pointer;

    color: #555;
}

.delivery-btn.active {
    border-color: #2a5298;

    background: #eef3ff;

    color: #1e3c72;
}


/*
|--------------------------------------------------------------------------
| Fuel Charge Section
|--------------------------------------------------------------------------
*/

.fuel-charge-section {
    display: none;

    margin-top: 12px;

    padding: 12px;

    border-radius: 8px;

    background: #fff8e8;

    border: 1px solid #efd58d;
}

.fuel-charge-section.show {
    display: block;
}

.fuel-charge-label {
    display: block;

    font-weight: bold;

    margin-bottom: 6px;

    color: #725800;
}

.fuel-charge-section input {
    width: 100%;

    padding: 11px;

    border: 1px solid #d9c27c;

    border-radius: 7px;

    font-size: 14px;
}

.fuel-charge-note {
    font-size: 12px;

    color: #806f3b;

    margin-top: 5px;
}

/* =========================================================
   DELIVERY
========================================================= */

.delivery-box {
    margin-top: 15px;
    padding: 15px;
    border: 1px solid #dfe4ec;
    border-radius: 10px;
    background: #fafbfd;
}

.delivery-title {
    color: #1e3c72;
    font-size: 15px;
    font-weight: bold;
    margin-bottom: 10px;
}

.delivery-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.delivery-btn {
    border: 2px solid #d7dce5;
    background: white;
    padding: 11px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    color: #555;
}

.delivery-btn.active {
    border-color: #2a5298;
    background: #eef3ff;
    color: #1e3c72;
}

.fuel-charge-section {
    display: none;
    margin-top: 12px;
    padding: 12px;
    border-radius: 8px;
    background: #fff8e8;
    border: 1px solid #efd58d;
}

.fuel-charge-section.show {
    display: block;
}

.fuel-charge-label {
    display: block;
    font-weight: bold;
    margin-bottom: 6px;
    color: #725800;
}

.fuel-charge-section input {
    width: 100%;
    padding: 11px;
    border: 1px solid #d9c27c;
    border-radius: 7px;
    font-size: 14px;
}

.fuel-charge-note {
    font-size: 12px;
    color: #806f3b;
    margin-top: 5px;
}

/* =========================================================
   SUMMARY
========================================================= */

.summary-row {
    display: flex;
    justify-content: space-between;

    margin: 13px 0;

    gap: 15px;
}

.summary-total {
    font-size: 25px;

    font-weight: bold;

    border-top: 2px solid #eee;

    padding-top: 15px;
}


/* =========================================================
   BALANCE
========================================================= */

.balance-box {
    margin-top: 15px;

    background: #fff7e5;

    padding: 14px;

    border-radius: 8px;

    border: 1px solid #f0d99a;
}

.balance-label {
    color: #7a6200;

    font-size: 13px;
}

.balance-value {
    color: #805f00;

    font-size: 21px;

    font-weight: bold;
}


/* =========================================================
   CHANGE
========================================================= */

.change-box {
    background: #eef8ee;

    padding: 13px;

    border-radius: 8px;

    margin-top: 15px;
}

.change-label {
    font-size: 13px;
}

.change-value {
    font-size: 21px;

    font-weight: bold;

    color: #16702b;
}


/* =========================================================
   COMPLETE BUTTON
========================================================= */

.complete-btn {
    width: 100%;

    margin-top: 20px;

    padding: 15px;

    border: none;

    border-radius: 9px;

    background: #1e3c72;

    color: white;

    font-size: 17px;

    font-weight: bold;

    cursor: pointer;
}

.complete-btn:hover {
    background: #16315f;
}

.complete-btn:disabled {
    opacity: .65;

    cursor: not-allowed;
}


/* =========================================================
   ERROR
========================================================= */

.error {
    background: #ffe5e5;

    color: #a00000;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 950px) {

    .pos-grid {
        grid-template-columns: 1fr;
    }

    .checkout {
        position: static;
    }

}

@media (max-width: 600px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .sale-type-buttons,
    .delivery-buttons {
        grid-template-columns: 1fr;
    }

    th,
    td {
        padding: 8px;
        font-size: 13px;
    }

}

</style>

</head>


<body>

<div class="container">


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <div class="header">

        <div>

            <h1>
                🛒 Penta Farm POS
            </h1>

            <p>
                Point of Sale & Hardware Inventory
            </p>

        </div>


        <div class="header-actions">

            <a
                href="../stock/index.php"
                class="button btn-dark"
            >
                📦 Stock
            </a>


            <a
                href="history.php"
                class="button btn-dark"
            >
                🧾 Sales
            </a>


            <a
                href="../index.php"
                class="button btn-light"
            >
                🏠 Dashboard
            </a>

        </div>

    </div>


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <div class="pos-grid">


        <!-- =====================================================
             LEFT SIDE
        ====================================================== -->

        <div class="panel">


            <h2>
                🔎 Find Product
            </h2>


            <div class="search-box">

                <input
                    type="text"
                    id="productSearch"
                    placeholder="Type product name..."
                    autocomplete="off"
                    spellcheck="false"
                >

            </div>


            <div
                id="productResults"
                class="results"
            >

                <div class="empty">

                    Start typing to search for a product.

                </div>

            </div>


            <br>


            <h2>
                🛒 Current Sale
            </h2>


            <div class="cart-table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Qty
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                            </th>

                        </tr>

                    </thead>


                    <tbody id="cartBody">

                        <tr>

                            <td
                                colspan="5"
                                class="empty"
                            >

                                Cart is empty.

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =====================================================
             RIGHT SIDE / CHECKOUT
        ====================================================== -->

        <div class="panel checkout">


            <h2>
                💳 Checkout
            </h2>


            <!-- =================================================
                 SALE TYPE
            ================================================== -->

            <div class="sale-type-box">

                <div
                    style="
                        font-weight:bold;
                        margin-bottom:10px;
                    "
                >

                    Sale Type

                </div>


                <div class="sale-type-buttons">


                    <button
                        type="button"
                        id="cashSaleBtn"
                        class="sale-type-btn active"
                        onclick="setSaleType('cash')"
                    >

                        💵 Cash Sale

                    </button>


                    <button
                        type="button"
                        id="invoiceSaleBtn"
                        class="sale-type-btn"
                        onclick="setSaleType('invoice')"
                    >

                        🧾 Invoice

                    </button>


 <button
        type="button"
        id="payLaterBtn"
        class="sale-type-btn"
        onclick="setSaleType('paylater')"
    >

        🕐 Pay Later

    </button>
                </div>

            </div>


            <!-- =================================================
                 INVOICE DETAILS
            ================================================== -->

            <div
                id="invoiceSection"
                class="invoice-section"
            >

                <div class="invoice-heading">

                    🧾 Invoice Customer

                </div>

        
                <div class="form-group">

                    <label>
                        Customer Name *
                    </label>

                    <input
                        type="text"
                        id="customerName"
                        placeholder="Customer / Company name"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Phone
                    </label>

                    <input
                        type="text"
                        id="customerPhone"
                        placeholder="Optional"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Delivery Address
                    </label>

                    <textarea
                        id="customerAddress"
                        placeholder="Where should the goods be delivered?"
                    ></textarea>

                </div>


                <div class="form-group">

                    <label>
                        Invoice Due Date *
                    </label>

                    <input
                        type="date"
                        id="dueDate"
                    >

                </div>

            </div>

                        <!-- =================================================
                 PAY LATER DETAILS
            ================================================== -->

            <div
                id="payLaterSection"
                class="invoice-section"
                style="display:none;"
            >

                <div class="invoice-heading">

                    🕐 Pay Later

                </div>


                <div class="form-group">

                    <label>
                        Customer Name *
                    </label>

                    <input
                        type="text"
                        id="payLaterCustomerName"
                        placeholder="Customer name"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Phone
                    </label>

                    <input
                        type="text"
                        id="payLaterCustomerPhone"
                        placeholder="Customer phone"
                    >

                </div>


                <div
                    style="
                        padding:10px;
                        margin-top:10px;
                        background:#f8f9fa;
                        border-radius:6px;
                    "
                >

                    <strong>
                        Payment Status:
                    </strong>

                    <span
                        style="
                            margin-left:8px;
                            font-weight:bold;
                        "
                    >

                        ❌ NOT PAID

                    </span>

                </div>


                <div class="form-group">

                    <label>
                        Amount Due
                    </label>

                    <input
                        type="text"
                        id="payLaterAmountDue"
                        readonly
                    >

                </div>

            </div>


            <!-- =================================================
                 DELIVERY
            ================================================== -->

            <div class="delivery-box">

                <div class="delivery-title">

                    🚚 Customer Wants Delivery?

                </div>


                <div class="delivery-buttons">


                    <button
                        type="button"
                        id="noDeliveryBtn"
                        class="delivery-btn active"
                        onclick="setDelivery(false)"
                    >

                        ❌ No Delivery

                    </button>


                    <button
                        type="button"
                        id="yesDeliveryBtn"
                        class="delivery-btn"
                        onclick="setDelivery(true)"
                    >

                        🚚 Yes, Deliver

                    </button>


                </div>


                <!-- FUEL CHARGE -->

                <div
                    id="fuelChargeSection"
                    class="fuel-charge-section"
                >

                    <label
                        for="fuelCharge"
                        class="fuel-charge-label"
                    >

                        Fuel / Delivery Charge

                    </label>


                    <input
                        type="number"
                        id="fuelCharge"
                        value="0"
                        min="0"
                        step="0.01"
                        placeholder="Enter delivery charge"
                    >


                    <div class="fuel-charge-note">

                        This amount will be added to the sale total.

                    </div>

                </div>

            </div>
         


            <!-- =================================================
                 SUBTOTAL
            ================================================== -->

            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <strong id="subtotal">
                    KSh 0.00
                </strong>

            </div>


            <!-- =================================================
                 DISCOUNT
            ================================================== -->

            <div class="form-group">

                <label>
                    Discount
                </label>

                <input
                    type="number"
                    id="discount"
                    value="0"
                    min="0"
                    step="0.01"
                >

            </div>


            <!-- =================================================
                 TOTAL
            ================================================== -->

            <div class="summary-row summary-total">

                <span>
                    Total
                </span>

                <span id="total">
                    KSh 0.00
                </span>

            </div>


            <!-- =================================================
                 CASH PAYMENT
            ================================================== -->

            <div id="cashPaymentSection">


                <div class="form-group">

                    <label>
                        Payment Method
                    </label>

                    <select id="paymentMethod">

                        <option value="Cash">
                            Cash
                        </option>

                        <option value="M-Pesa">
                            M-Pesa
                        </option>

                        <option value="Bank">
                            Bank
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Amount Paid
                    </label>

                    <input
                        type="number"
                        id="amountPaid"
                        min="0"
                        step="0.01"
                        placeholder="Enter amount paid"
                    >

                </div>


                <div class="change-box">

                    <div class="change-label">
                        Change
                    </div>

                    <div
                        class="change-value"
                        id="changeAmount"
                    >
                        KSh 0.00
                    </div>

                </div>

            </div>


            <!-- =================================================
                 INVOICE PAYMENT
            ================================================== -->

            <div
                id="invoicePaymentSection"
                style="display:none;"
            >

                <div class="form-group">

                    <label>
                        Initial Payment
                    </label>

                    <input
                        type="number"
                        id="invoiceAmountPaid"
                        min="0"
                        step="0.01"
                        value="0"
                        placeholder="Enter amount paid now"
                    >

                </div>


                <div
                    style="
                        font-size:12px;
                        color:#777;
                        margin-top:5px;
                    "
                >

                    Leave at 0 for a completely unpaid invoice.

                </div>


                <div class="balance-box">

                    <div class="balance-label">
                        Outstanding Balance
                    </div>

                    <div
                        class="balance-value"
                        id="invoiceBalance"
                    >
                        KSh 0.00
                    </div>

                </div>

            </div>


            <!-- =================================================
                 COMPLETE
            ================================================== -->

            <button
                type="button"
                id="completeSaleButton"
                class="complete-btn"
                onclick="completeSale()"
            >

                ✅ Complete Sale

            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

const products =
    <?= json_encode(
        $products,
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;


/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/

let cart = [];


/*
|--------------------------------------------------------------------------
| Sale Type
|--------------------------------------------------------------------------
*/

let saleType = "cash";
let deliveryRequired = false;





/*
|--------------------------------------------------------------------------
| Search Elements
|--------------------------------------------------------------------------
*/

const searchInput =
    document.getElementById(
        "productSearch"
    );

const results =
    document.getElementById(
        "productResults"
    );


/*
|--------------------------------------------------------------------------
| Normalize Search Text
|--------------------------------------------------------------------------
*/

function normalizeSearch(text) {

    return String(text || "")
        .toLowerCase()
        .trim()
        .replace(/\s+/g, " ");

}


/*
|--------------------------------------------------------------------------
| Product Search
|--------------------------------------------------------------------------
*/

function performProductSearch() {

    const search =
        normalizeSearch(
            searchInput.value
        );


    if (!search) {

        results.innerHTML = `
            <div class="empty">
                Start typing to search for a product.
            </div>
        `;

        return;

    }


    let matches =
        products.filter(
            product => {

                const name =
                    normalizeSearch(
                        product.product_name
                    );

                return name.includes(search);

            }
        );


    matches.sort(
        (a, b) => {

            const nameA =
                normalizeSearch(
                    a.product_name
                );

            const nameB =
                normalizeSearch(
                    b.product_name
                );


            const startsA =
                nameA.startsWith(search);


            const startsB =
                nameB.startsWith(search);


            if (
                startsA &&
                !startsB
            ) {

                return -1;

            }


            if (
                !startsA &&
                startsB
            ) {

                return 1;

            }


            return nameA.localeCompare(
                nameB
            );

        }
    );


    if (!matches.length) {

        results.innerHTML = `
            <div class="empty">
                No products found for
                "<strong>${escapeHtml(search)}</strong>".
            </div>
        `;

        return;

    }


    results.innerHTML =
        matches
        .map(
            product => {

                const stock =
                    Number(
                        product.stock_quantity || 0
                    );


                const price =
                    Number(
                        product.selling_price || 0
                    );


                const unit =
                    product.unit || "";


                const minimum =
                    Number(
                        product.minimum_sale_quantity || 1
                    );


                const step =
                    Number(
                        product.quantity_step || 1
                    );


                return `

                    <div
                        class="product-result"
                        onclick="addToCart(${Number(product.id)})"
                    >

                        <div>

                            <div class="product-name">

                                ${escapeHtml(
                                    product.product_name
                                )}

                            </div>


                            <div class="product-info">

                                Stock:
                                ${formatQuantity(stock)}
                                ${escapeHtml(unit)}

                                &nbsp; | &nbsp;

                                Min:
                                ${formatQuantity(minimum)}

                                &nbsp; | &nbsp;

                                Step:
                                ${formatQuantity(step)}

                            </div>

                        </div>


                        <div
                            class="
                                product-price
                                ${stock <= 0 ? "out" : ""}
                            "
                        >

                            KSh
                            ${price.toFixed(2)}

                        </div>

                    </div>

                `;

            }
        )
        .join("");

}


searchInput.addEventListener(
    "input",
    performProductSearch
);

searchInput.addEventListener(
    "keyup",
    performProductSearch
);


/*
|--------------------------------------------------------------------------
| Add Product
|--------------------------------------------------------------------------
*/

function addToCart(productId) {

    const product =
        products.find(
            product =>
                Number(product.id) ===
                Number(productId)
        );


    if (!product) {
        return;
    }


    const stock =
        Number(
            product.stock_quantity || 0
        );


    if (stock <= 0) {

        alert(
            product.product_name +
            " is out of stock."
        );

        return;

    }


    let minimum =
        Number(
            product.minimum_sale_quantity
        );


    let step =
        Number(
            product.quantity_step
        );


    if (
        !Number.isFinite(minimum) ||
        minimum <= 0
    ) {

        minimum = 1;

    }


    if (
        !Number.isFinite(step) ||
        step <= 0
    ) {

        step = 1;

    }


    minimum =
        roundQuantity(minimum);

    step =
        roundQuantity(step);


    const existing =
        cart.find(
            item =>
                Number(item.id) ===
                Number(product.id)
        );


    if (existing) {

        const newQuantity =
            roundQuantity(
                existing.quantity +
                existing.step
            );


        if (
            newQuantity >
            existing.stock +
            0.000001
        ) {

            alert(
                "Not enough stock available."
            );

            return;

        }


        existing.quantity =
            newQuantity;

    }

    else {

        if (
            minimum >
            stock +
            0.000001
        ) {

            alert(
                "Available stock is below the minimum sale quantity."
            );

            return;

        }


        cart.push({

            id:
                Number(product.id),

            name:
                product.product_name,

            unit:
                product.unit || "",

            quantity:
                minimum,

            unit_price:
                Number(
                    product.selling_price || 0
                ),

            buying_price:
                Number(
                    product.buying_price || 0
                ),

            stock:
                stock,

            minimum:
                minimum,

            step:
                step

        });

    }


    renderCart();


    searchInput.value = "";


    results.innerHTML = `
        <div class="empty">
            Start typing to search for a product.
        </div>
    `;


    searchInput.focus();

}


/*
|--------------------------------------------------------------------------
| Parse Quantity
|--------------------------------------------------------------------------
*/

function parseQuantity(value) {

    if (
        typeof value === "number"
    ) {

        if (
            Number.isFinite(value)
        ) {

            return roundQuantity(value);

        }

        return NaN;

    }


    let input =
        String(value || "")
        .trim()
        .replace(/\s+/g, " ");


    if (!input) {
        return NaN;
    }


    const mixed =
        input.match(
            /^(\d+(?:\.\d+)?)\s+(\d+)\s*\/\s*(\d+)$/
        );


    if (mixed) {

        const whole =
            Number(mixed[1]);

        const numerator =
            Number(mixed[2]);

        const denominator =
            Number(mixed[3]);


        if (
            denominator === 0 ||
            numerator >= denominator
        ) {

            return NaN;

        }


        return roundQuantity(
            whole +
            numerator / denominator
        );

    }


    const fraction =
        input.match(
            /^(\d+)\s*\/\s*(\d+)$/
        );


    if (fraction) {

        const numerator =
            Number(fraction[1]);

        const denominator =
            Number(fraction[2]);


        if (
            denominator === 0 ||
            numerator >= denominator
        ) {

            return NaN;

        }


        return roundQuantity(
            numerator / denominator
        );

    }


    if (
        /^\d+(?:\.\d+)?$/.test(input)
    ) {

        return roundQuantity(
            Number(input)
        );

    }


    return NaN;

}


/*
|--------------------------------------------------------------------------
| Validate Quantity
|--------------------------------------------------------------------------
*/

function isValidQuantity(
    quantity,
    minimum,
    step
) {

    quantity =
        roundQuantity(quantity);

    minimum =
        roundQuantity(minimum);

    step =
        roundQuantity(step);


    if (
        !Number.isFinite(quantity) ||
        !Number.isFinite(minimum) ||
        !Number.isFinite(step) ||
        step <= 0
    ) {

        return false;

    }


    if (
        quantity <
        minimum -
        0.000001
    ) {

        return false;

    }


    const difference =
        quantity -
        minimum;


    const steps =
        difference /
        step;


    return Math.abs(
        steps -
        Math.round(steps)
    ) < 0.000001;

}


/*
|--------------------------------------------------------------------------
| Change Quantity
|--------------------------------------------------------------------------
*/

function changeQuantity(
    index,
    value
) {

    const item =
        cart[index];


    if (!item) {
        return;
    }


    const quantity =
        parseQuantity(value);


    if (
        !Number.isFinite(quantity) ||
        quantity <= 0
    ) {

        alert(
            "Please enter a valid quantity."
        );

        renderCart();

        return;

    }


    if (
        quantity <
        item.minimum -
        0.000001
    ) {

        alert(
            "Minimum sale quantity is " +
            formatQuantity(item.minimum) +
            " " +
            item.unit +
            "."
        );

        renderCart();

        return;

    }


    if (
        !isValidQuantity(
            quantity,
            item.minimum,
            item.step
        )
    ) {

        alert(
            "Quantity must increase by " +
            formatQuantity(item.step) +
            " " +
            item.unit +
            " from the minimum of " +
            formatQuantity(item.minimum) +
            " " +
            item.unit +
            "."
        );

        renderCart();

        return;

    }


    if (
        quantity >
        item.stock +
        0.000001
    ) {

        alert(
            "Only " +
            formatQuantity(item.stock) +
            " " +
            item.unit +
            " available."
        );

        renderCart();

        return;

    }


    item.quantity =
        roundQuantity(quantity);


    renderCart();

}


/*
|--------------------------------------------------------------------------
| Remove Product
|--------------------------------------------------------------------------
*/

function removeItem(index) {

    cart.splice(index, 1);

    renderCart();

}


/*
|--------------------------------------------------------------------------
| Render Cart
|--------------------------------------------------------------------------
*/

function renderCart() {

    const body =
        document.getElementById(
            "cartBody"
        );


    if (!cart.length) {

        body.innerHTML = `
            <tr>

                <td
                    colspan="5"
                    class="empty"
                >

                    Cart is empty.

                </td>

            </tr>
        `;

        calculateTotals();

        return;

    }


    body.innerHTML =
        cart
        .map(
            (item, index) => {

                const lineTotal =
                    Number(item.quantity) *
                    Number(item.unit_price);


                return `

                    <tr>

                        <td>

                            <strong>
                                ${escapeHtml(item.name)}
                            </strong>

                            <br>

                            <small>
                                ${escapeHtml(item.unit)}
                            </small>

                        </td>


                        <td>

                            <input
                                class="qty-input"
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                value="${formatQuantity(item.quantity)}"
                                onchange="changeQuantity(${index}, this.value)"
                                onkeydown="
                                    if(event.key === 'Enter'){
                                        this.blur();
                                    }
                                "
                            >

                        </td>


                        <td>

                            KSh
                            ${Number(item.unit_price).toFixed(2)}

                        </td>


                        <td>

                            KSh
                            ${lineTotal.toFixed(2)}

                        </td>


                        <td>

                            <button
                                type="button"
                                class="remove-btn"
                                onclick="removeItem(${index})"
                            >

                                ✕

                            </button>

                        </td>

                    </tr>

                `;

            }
        )
        .join("");


    calculateTotals();

}


/*
|--------------------------------------------------------------------------
| Sale Type
|--------------------------------------------------------------------------
*/
function setSaleType(type) {

    saleType = type;

    const cashButton =
        document.getElementById("cashSaleBtn");

    const invoiceButton =
        document.getElementById("invoiceSaleBtn");

    const invoiceSection =
        document.getElementById("invoiceSection");

    const cashPaymentSection =
        document.getElementById("cashPaymentSection");

    const invoicePaymentSection =
        document.getElementById("invoicePaymentSection");

    const payLaterSection =
        document.getElementById("payLaterSection");


    /* --------------------------------
       CASH SALE
    -------------------------------- */

    if (type === "cash") {

        cashButton.classList.add("active");

        invoiceButton.classList.remove("active");

        invoiceSection.classList.remove("show");

        cashPaymentSection.style.display = "block";

        invoicePaymentSection.style.display = "none";

        if (payLaterSection) {
            payLaterSection.style.display = "none";
        }

    }


    /* --------------------------------
       INVOICE
    -------------------------------- */

    else if (type === "invoice") {

        cashButton.classList.remove("active");

        invoiceButton.classList.add("active");

        invoiceSection.classList.add("show");

        cashPaymentSection.style.display = "none";

        invoicePaymentSection.style.display = "block";

        if (payLaterSection) {
            payLaterSection.style.display = "none";
        }

        setDefaultDueDate();

    }


    /* --------------------------------
       PAY LATER
    -------------------------------- */

    else if (type === "paylater") {

        cashButton.classList.remove("active");

        invoiceButton.classList.remove("active");

        invoiceSection.classList.remove("show");

        cashPaymentSection.style.display = "none";

        invoicePaymentSection.style.display = "none";

        if (payLaterSection) {
            payLaterSection.style.display = "block";
        }

    }


    calculateTotals();

}

/*
|--------------------------------------------------------------------------
| Delivery Selection
|--------------------------------------------------------------------------
*/

function setDelivery(required) {

    deliveryRequired =
        required;


    const noDeliveryBtn =
        document.getElementById(
            "noDeliveryBtn"
        );

    const yesDeliveryBtn =
        document.getElementById(
            "yesDeliveryBtn"
        );

    const fuelSection =
        document.getElementById(
            "fuelChargeSection"
        );

    const fuelInput =
        document.getElementById(
            "fuelCharge"
        );


    if (required) {

        noDeliveryBtn.classList.remove(
            "active"
        );

        yesDeliveryBtn.classList.add(
            "active"
        );

        fuelSection.classList.add(
            "show"
        );

        fuelInput.focus();

    }

    else {

        yesDeliveryBtn.classList.remove(
            "active"
        );

        noDeliveryBtn.classList.add(
            "active"
        );

        fuelSection.classList.remove(
            "show"
        );

        /*
        |----------------------------------------------------------------------
        | Important:
        | If delivery is switched off, remove the charge completely.
        |----------------------------------------------------------------------
        */

        fuelInput.value = "0";

    }


    calculateTotals();

}

/*
|--------------------------------------------------------------------------
| Delivery Selection
|--------------------------------------------------------------------------
*/

function setDelivery(required) {

    deliveryRequired = required;

    const noDeliveryBtn =
        document.getElementById("noDeliveryBtn");

    const yesDeliveryBtn =
        document.getElementById("yesDeliveryBtn");

    const fuelSection =
        document.getElementById("fuelChargeSection");

    const fuelInput =
        document.getElementById("fuelCharge");


    if (required) {

        noDeliveryBtn.classList.remove("active");

        yesDeliveryBtn.classList.add("active");

        fuelSection.classList.add("show");

        fuelInput.focus();

    } else {

        yesDeliveryBtn.classList.remove("active");

        noDeliveryBtn.classList.add("active");

        fuelSection.classList.remove("show");

        fuelInput.value = "0";
    }


    calculateTotals();
}

/*
|--------------------------------------------------------------------------
| Default Due Date
|--------------------------------------------------------------------------
*/

function setDefaultDueDate() {

    const dueDate =
        document.getElementById(
            "dueDate"
        );


    if (
        dueDate.value
    ) {

        return;

    }


    const date =
        new Date();


    date.setDate(
        date.getDate() + 30
    );


    const year =
        date.getFullYear();


    const month =
        String(
            date.getMonth() + 1
        ).padStart(2, "0");


    const day =
        String(
            date.getDate()
        ).padStart(2, "0");


    dueDate.value =
        year +
        "-" +
        month +
        "-" +
        day;

}


/*
|--------------------------------------------------------------------------
| Calculate Totals
|--------------------------------------------------------------------------
*/

function calculateTotals() {

    let subtotal = 0;


    cart.forEach(
        item => {

            subtotal +=
                Number(item.quantity) *
                Number(item.unit_price);

        }
    );


    subtotal =
        Number(
            subtotal.toFixed(2)
        );


    let discount =
        Number(
            document.getElementById(
                "discount"
            ).value
        ) || 0;

    if (discount < 0) {

        discount = 0;

    }


    /*
    |--------------------------------------------------------------------------
    | Fuel / Delivery Charge
    |--------------------------------------------------------------------------
    */

    let fuelCharge = 0;


    if (deliveryRequired) {

        fuelCharge =
            Number(
                document.getElementById(
                    "fuelCharge"
                ).value
            ) || 0;


        if (fuelCharge < 0) {

            fuelCharge = 0;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Total
    |--------------------------------------------------------------------------
    */

    let total =
        subtotal -
        discount +
        fuelCharge;


    if (
        total < 0
    ) {

        total = 0;

    }


    total =
        Number(
            total.toFixed(2)
        );


    document.getElementById(
        "subtotal"
    ).textContent =
        "KSh " +
        subtotal.toFixed(2);


    document.getElementById(
        "total"
    ).textContent =
        "KSh " +
        total.toFixed(2);


    if (
        saleType === "cash"
    ) {

        calculateCashChange(
            total
        );

    }

    else {

        calculateInvoiceBalance(
            total
        );

    }


    

}


/*
|--------------------------------------------------------------------------
| Cash Change
|--------------------------------------------------------------------------
*/

function calculateCashChange(
    total
) {

    const paid =
        Number(
            document.getElementById(
                "amountPaid"
            ).value
        ) || 0;


    const change =
        Math.max(
            0,
            paid -
            total
        );


    document.getElementById(
        "changeAmount"
    ).textContent =
        "KSh " +
        change.toFixed(2);

}


/*
|--------------------------------------------------------------------------
| Invoice Balance
|--------------------------------------------------------------------------
*/

function calculateInvoiceBalance(
    total
) {

    const paid =
        Number(
            document.getElementById(
                "invoiceAmountPaid"
            ).value
        ) || 0;


    const balance =
        Math.max(
            0,
            total -
            paid
        );


    document.getElementById(
        "invoiceBalance"
    ).textContent =
        "KSh " +
        balance.toFixed(2);

}


/*
|--------------------------------------------------------------------------
| Input Listeners
|--------------------------------------------------------------------------
*/

document.getElementById(
    "discount"
).addEventListener(
    "input",
    calculateTotals
);


document.getElementById(
    "amountPaid"
).addEventListener(
    "input",
    calculateTotals
);


document.getElementById(
    "invoiceAmountPaid"
).addEventListener(
    "input",
    calculateTotals
);


document.getElementById(
    "fuelCharge"
).addEventListener(
    "input",
    calculateTotals
);


/*
|--------------------------------------------------------------------------
| Complete Sale
|--------------------------------------------------------------------------
*/

function completeSale() {

    if (
        !cart.length
    ) {

        alert(
            "Please add at least one product."
        );

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Final Quantity Validation
    |--------------------------------------------------------------------------
    */

    for (
        let i = 0;
        i < cart.length;
        i++
    ) {

        const item =
            cart[i];


        const quantity =
            roundQuantity(
                item.quantity
            );


        if (
            !isValidQuantity(
                quantity,
                item.minimum,
                item.step
            )
        ) {

            alert(
                "Invalid quantity for " +
                item.name +
                "."
            );

            return;

        }


        if (
            quantity >
            item.stock +
            0.000001
        ) {

            alert(
                "Not enough stock for " +
                item.name +
                "."
            );

            return;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Discount
    |--------------------------------------------------------------------------
    */

    let discount =
        Number(
            document.getElementById(
                "discount"
            ).value
        ) || 0;


    if (discount < 0) {

        discount = 0;

    }


    /*
    |--------------------------------------------------------------------------
    | Subtotal
    |--------------------------------------------------------------------------
    */

    const subtotal =
        cart.reduce(
            (
                sum,
                item
            ) =>
                sum +
                Number(item.quantity) *
                Number(item.unit_price),
            0
        );

            /*
    |--------------------------------------------------------------------------
    | Fuel / Delivery Charge
    |--------------------------------------------------------------------------
    */

    let fuelCharge = 0;

    if (deliveryRequired) {

        fuelCharge =
            Number(
                document.getElementById(
                    "fuelCharge"
                ).value
            ) || 0;

        if (fuelCharge < 0) {
            fuelCharge = 0;
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Final Total
    |--------------------------------------------------------------------------
    */

    const total =
        Math.max(
            0,
            Number(
                (
                    subtotal -
                    discount +
                    fuelCharge
                ).toFixed(2)
            )
        );



    /*
    |--------------------------------------------------------------------------
    | CASH SALE
    |--------------------------------------------------------------------------
    */

    if (
        saleType === "cash"
    ) {

        const paymentMethod =
            document.getElementById(
                "paymentMethod"
            ).value;


        const amountPaid =
            Number(
                document.getElementById(
                    "amountPaid"
                ).value
            ) || 0;


        if (
            amountPaid <
            total
        ) {

            alert(
                "Amount paid is less than the total."
            );

            return;

        }


        sendSale({

            sale_type:
                "cash",

            discount:
                discount,

            delivery_required:
                deliveryRequired ? 1 : 0,

            fuel_charge:
                fuelCharge,

            payment_method:
                paymentMethod,

            amount_paid:
                amountPaid,

            customer_name:
                "",

            customer_phone:
                "",

            customer_address:
                "",

            due_date:
                "",

            items:
                cart.map(
                    item => ({

                        product_id:
                            item.id,

                        quantity:
                            roundQuantity(
                                item.quantity
                            )

                    })
                )

        });


        return;

    }


    /*
    |--------------------------------------------------------------------------
    | INVOICE SALE
    |--------------------------------------------------------------------------
    */

    const customerName =
        document.getElementById(
            "customerName"
        )
        .value
        .trim();


    const customerPhone =
        document.getElementById(
            "customerPhone"
        )
        .value
        .trim();


    const customerAddress =
        document.getElementById(
            "customerAddress"
        )
        .value
        .trim();


    const dueDate =
        document.getElementById(
            "dueDate"
        ).value;


    const initialPayment =
        Number(
            document.getElementById(
                "invoiceAmountPaid"
            ).value
        ) || 0;


    if (
        !customerName
    ) {

        alert(
            "Please enter the customer name."
        );

        return;

    }


    if (
        !dueDate
    ) {

        alert(
            "Please select the invoice due date."
        );

        return;

    }


    if (
        initialPayment < 0
    ) {

        alert(
            "Initial payment cannot be negative."
        );

        return;

    }


    if (
        initialPayment >
        total
    ) {

        alert(
            "Initial payment cannot be greater than the invoice total."
        );

        return;

    }


    sendSale({

        sale_type:
            "invoice",

        discount:
            discount,

        delivery_required:
            deliveryRequired ? 1 : 0,

        fuel_charge:
            fuelCharge,

        payment_method:
            initialPayment > 0
            ? "Cash"
            : "",

        amount_paid:
            initialPayment,

        customer_name:
            customerName,

        customer_phone:
            customerPhone,

        customer_address:
            customerAddress,

        due_date:
            dueDate,

        items:
            cart.map(
                item => ({

                    product_id:
                        item.id,

                    quantity:
                        roundQuantity(
                            item.quantity
                        )

                })
            )

    });

}


/*
|--------------------------------------------------------------------------
| Send Sale
|--------------------------------------------------------------------------
*/

function sendSale(data) {

    const button =
        document.getElementById(
            "completeSaleButton"
        );


    /*
    |--------------------------------------------------------------------------
    | Prevent Double Submission
    |--------------------------------------------------------------------------
    */

    button.disabled = true;

    button.textContent =
        "⏳ Processing...";


    fetch(
        "process_sale.php",
        {

            method:
                "POST",

            headers: {

                "Content-Type":
                    "application/json"

            },

            body:
                JSON.stringify(data)

        }
    )
    .then(
        async response => {

            const text =
                await response.text();


            console.log(
                "HTTP Status:",
                response.status
            );


            console.log(
                "Server Response:",
                text
            );


            let result;


            try {

                result =
                    JSON.parse(text);

            }

            catch (error) {

                throw new Error(

                    "The server did not return valid JSON.\n\n" +

                    "HTTP Status: " +
                    response.status +

                    "\n\nServer Response:\n" +
                    text

                );

            }


            return result;

        }
    )
    .then(
        result => {

            if (
                result.success
            ) {

                alert(

                    data.sale_type ===
                    "invoice"

                    ?

                    "Invoice created successfully!\n\n" +
                    "Invoice: " +
                    result.receipt_number

                    :

                    "Sale completed successfully!\n\n" +
                    "Receipt: " +
                    result.receipt_number

                );


                window.location.href =
                    "receipt.php?id=" +
                    result.sale_id;


                return;

            }


            alert(

                "Transaction failed:\n\n" +

                (
                    result.message ||
                    "Unknown server error."
                )

            );


            button.disabled =
                false;


            button.textContent =
                "✅ Complete Sale";

        }
    )
    .catch(
        error => {

            console.error(
                "Transaction error:",
                error
            );


            alert(

                "SERVER ERROR:\n\n" +
                error.message

            );


            button.disabled =
                false;


            button.textContent =
                "✅ Complete Sale";

        }
    );

}


/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(text) {

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        text ?? "";


    return div.innerHTML;

}


/*
|--------------------------------------------------------------------------
| Round Quantity
|--------------------------------------------------------------------------
*/

function roundQuantity(value) {

    return Math.round(
        (
            Number(value) +
            Number.EPSILON
        ) *
        1000
    ) / 1000;

}


/*
|--------------------------------------------------------------------------
| Format Quantity
|--------------------------------------------------------------------------
*/

function formatQuantity(value) {

    return roundQuantity(
        value || 0
    )
    .toFixed(3)
    .replace(
        /\.?0+$/,
        ""
    );

}


/*
|--------------------------------------------------------------------------
| Initial Render
|--------------------------------------------------------------------------
*/

renderCart();

</script>

</body>

</html>