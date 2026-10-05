<?php

require_once "../config/database.php";

$message = "";
$message_type = "";

try {

    /*
    |--------------------------------------------------------------------------
    | Fetch Active Products
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            product_name,
            unit,
            purchase_unit,
            purchase_conversion,
            buying_price,
            selling_price,
            stock_quantity
        FROM products
        WHERE is_active = 1
        ORDER BY product_name ASC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $products = [];

    $message = "Database Error: " . $e->getMessage();
    $message_type = "error";
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

    <title>Receive Stock - Penta Farm</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #eef3f9,
                    #f7f9fc
                );

            color: #1f2937;

        }


        .container {

            width: 94%;

            max-width: 1200px;

            margin: 28px auto 50px;

        }


        /* =========================================================
           HEADER
           ========================================================= */

        .header {

            position: relative;

            background:
                linear-gradient(
                    135deg,
                    #173b72 0%,
                    #2456a4 55%,
                    #2e6bc4 100%
                );

            color: white;

            padding: 25px 28px;

            border-radius: 15px;

            margin-bottom: 20px;

            box-shadow:
                0 8px 25px
                rgba(30, 60, 114, 0.20);

            overflow: hidden;

        }


        .header::after {

            content: "";

            position: absolute;

            width: 180px;
            height: 180px;

            right: -55px;
            top: -80px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.08);

        }


        .header h1 {

            margin: 0 0 7px;

            font-size: 28px;

            position: relative;

            z-index: 1;

        }


        .header p {

            margin: 0;

            opacity: 0.9;

            position: relative;

            z-index: 1;

            font-size: 14px;

        }


        /* =========================================================
           CARDS
           ========================================================= */

        .card {

            background: white;

            padding: 24px;

            border-radius: 14px;

            box-shadow:
                0 4px 18px
                rgba(15, 23, 42, 0.07);

            margin-bottom: 20px;

            border: 1px solid #edf0f4;

        }


        .card h2 {

            margin: 0 0 20px;

            font-size: 19px;

            color: #173b72;

        }


        /* =========================================================
           FORM
           ========================================================= */

        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

        }


        .form-group {

            display: flex;

            flex-direction: column;

            min-width: 0;

        }


        label {

            font-size: 13px;

            font-weight: 700;

            color: #374151;

            margin-bottom: 7px;

        }


        input,
        select {

            width: 100%;

            padding: 11px 12px;

            border:
                1px solid #d7dce3;

            border-radius: 8px;

            font-size: 14px;

            color: #1f2937;

            background: white;

            transition:
                border-color .2s,
                box-shadow .2s;

        }


        input:focus,
        select:focus {

            outline: none;

            border-color: #2a64b7;

            box-shadow:
                0 0 0 3px
                rgba(42, 100, 183, 0.10);

        }


        /* =========================================================
           SEARCHABLE PRODUCT SELECTOR
           ========================================================= */

        .product-search-wrapper {

            position: relative;

            width: 100%;

        }


        .product-search {

            position: relative;

            width: 100%;

        }


        #product_search {

            width: 100%;

            padding:
                12px 40px 12px 13px;

            border:
                1px solid #cfd6df;

            border-radius: 9px;

            background: #fff;

            font-size: 14px;

            transition:
                border-color .2s,
                box-shadow .2s;

        }


        #product_search:focus {

            outline: none;

            border-color: #2a64b7;

            box-shadow:
                0 0 0 3px
                rgba(42,100,183,0.10);

        }


        .search-icon {

            position: absolute;

            right: 13px;

            top: 50%;

            transform: translateY(-50%);

            color: #7a8695;

            font-size: 16px;

            pointer-events: none;

        }


        .product-suggestions {

            position: absolute;

            left: 0;

            right: 0;

            top: calc(100% + 6px);

            background: white;

            border:
                1px solid #d8dee7;

            border-radius: 10px;

            box-shadow:
                0 12px 30px
                rgba(15, 23, 42, 0.16);

            max-height: 290px;

            overflow-y: auto;

            display: none;

            z-index: 2000;

        }


        .product-suggestion {

            padding: 12px 14px;

            border-bottom:
                1px solid #f0f2f5;

            cursor: pointer;

            transition:
                background .15s;

        }


        .product-suggestion:last-child {

            border-bottom: none;

        }


        .product-suggestion:hover,
        .product-suggestion.active {

            background: #f1f6ff;

        }


        .suggestion-main {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

        }


        .suggestion-name {

            font-weight: 700;

            color: #1f2937;

            font-size: 14px;

        }


        .suggestion-stock {

            font-size: 11px;

            color: #6b7280;

            white-space: nowrap;

        }


        .suggestion-details {

            margin-top: 5px;

            font-size: 11px;

            color: #7a8491;

        }


        .no-products {

            padding: 18px;

            text-align: center;

            color: #777;

            font-size: 13px;

        }


        .selected-product {

            display: none;

            margin-top: 7px;

            padding: 6px 9px;

            background: #edf6ff;

            color: #1d5fa7;

            border-radius: 6px;

            font-size: 12px;

            font-weight: 700;

        }


        /* Hidden compatibility select */

        #product_id {

            display: none;

        }


        /* =========================================================
           PRODUCT INFORMATION
           ========================================================= */

        .product-info {

            margin-top: 18px;

            padding: 16px;

            background:
                linear-gradient(
                    135deg,
                    #f2f6ff,
                    #f7faff
                );

            border:
                1px solid #dce6f6;

            border-radius: 10px;

            display: none;

        }


        .product-info-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

        }


        .info-box {

            background: white;

            padding: 12px;

            border:
                1px solid #e1e6ed;

            border-radius: 8px;

        }


        .info-box small {

            display: block;

            color: #718096;

            margin-bottom: 5px;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .3px;

        }


        .info-box strong {

            font-size: 16px;

            color: #1f2937;

        }


        /* =========================================================
           BUTTONS
           ========================================================= */

        .button {

            border: none;

            padding: 11px 18px;

            border-radius: 8px;

            cursor: pointer;

            font-size: 14px;

            font-weight: 700;

            transition:
                transform .15s,
                box-shadow .15s,
                background .15s;

        }


        .button:hover {

            transform: translateY(-1px);

        }


        .btn-add {

            background: #18864b;

            color: white;

            margin-top: 18px;

            box-shadow:
                0 4px 10px
                rgba(24,134,75,0.18);

        }


        .btn-add:hover {

            background: #126c3c;

        }


        .btn-save {

            background:
                linear-gradient(
                    135deg,
                    #173b72,
                    #285aa5
                );

            color: white;

            padding:
                13px 25px;

            font-size: 15px;

            box-shadow:
                0 5px 14px
                rgba(23,59,114,0.20);

        }


        .btn-save:hover {

            background:
                linear-gradient(
                    135deg,
                    #142f5a,
                    #214b8b
                );

        }


        .btn-remove {

            background: #fff1f1;

            color: #c62828;

            border:
                1px solid #ffd3d3;

            padding: 7px 10px;

            font-size: 12px;

        }


        .btn-remove:hover {

            background: #ffe2e2;

        }


        /* =========================================================
           TABLE
           ========================================================= */

        .table-wrapper {

            overflow-x: auto;

            border:
                1px solid #edf0f4;

            border-radius: 9px;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 850px;

        }


        th,
        td {

            padding: 12px;

            border-bottom:
                1px solid #edf0f3;

            text-align: left;

            font-size: 13px;

        }


        th {

            background: #f7f9fc;

            color: #596579;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .3px;

        }


        tbody tr:hover {

            background: #fafcff;

        }


        tbody tr:last-child td {

            border-bottom: none;

        }


        /* =========================================================
           TOTAL
           ========================================================= */

        .receipt-footer {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-top: 20px;

        }


        .total-box {

            text-align: right;

            font-size: 14px;

            color: #6b7280;

        }


        .total-box strong {

            display: block;

            color: #173b72;

            font-size: 25px;

            margin-top: 3px;

        }


        /* =========================================================
           MESSAGE
           ========================================================= */

        .message {

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

        }


        .error {

            background: #ffe8e8;

            color: #a00000;

            border:
                1px solid #ffcaca;

        }


        /* =========================================================
           EMPTY STATE
           ========================================================= */

        .empty-row {

            text-align: center !important;

            color: #8a94a3;

            padding: 30px !important;

        }


        /* =========================================================
           RESPONSIVE
           ========================================================= */

        @media (max-width: 900px) {

            .form-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .product-info-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 600px) {

            .container {

                width: 94%;

                margin-top: 15px;

            }


            .header {

                padding: 20px;

            }


            .header h1 {

                font-size: 23px;

            }


            .card {

                padding: 18px;

            }


            .form-grid {

                grid-template-columns: 1fr;

                gap: 15px;

            }


            .product-info-grid {

                grid-template-columns: 1fr 1fr;

            }


            .receipt-footer {

                flex-direction: column;

                align-items: stretch;

            }


            .total-box {

                text-align: left;

            }


            .btn-save {

                width: 100%;

            }

        }

    </style>

</head>

<body>

<div class="container">


    <!-- =========================================================
         HEADER
         ========================================================= -->

    <div class="header">

        <h1>📦 Receive Stock</h1>

        <p>
            Add new inventory to Penta Farm
        </p>

    </div>


    <?php if ($message): ?>

        <div class="message <?= htmlspecialchars($message_type) ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         STOCK RECEIPT INFORMATION
         ========================================================= -->

    <div class="card">

        <h2>Stock Receipt Information</h2>

        <div class="form-grid">

            <div class="form-group">

                <label for="supplier_name">
                    Supplier Name
                </label>

                <input
                    type="text"
                    id="supplier_name"
                    placeholder="Optional"
                >

            </div>


            <div class="form-group">

                <label for="receipt_date">
                    Receipt Date
                </label>

                <input
                    type="date"
                    id="receipt_date"
                    value="<?= date('Y-m-d') ?>"
                >

            </div>


            <div class="form-group">

                <label for="notes">
                    Notes
                </label>

                <input
                    type="text"
                    id="notes"
                    placeholder="Optional"
                >

            </div>

        </div>

    </div>


    <!-- =========================================================
         ADD PRODUCT
         ========================================================= -->

    <div class="card">

        <h2>Add Product</h2>

        <div class="form-grid">


            <!-- PRODUCT SEARCH -->

            <div class="form-group">

                <label for="product_search">
                    Product
                </label>

                <div class="product-search-wrapper">

                    <div class="product-search">

                        <input
                            type="text"
                            id="product_search"
                            placeholder="Search product..."
                            autocomplete="off"
                        >

                        <span class="search-icon">
                            🔍
                        </span>

                    </div>


                    <div
                        id="product_suggestions"
                        class="product-suggestions"
                    ></div>

                </div>


                <div
                    id="selected_product"
                    class="selected-product"
                ></div>


                <!--
                    Original selector retained invisibly
                    so existing product logic continues
                    working.
                -->

                <select id="product_id">

                    <option value="">
                        -- Select Product --
                    </option>

                    <?php foreach ($products as $product): ?>

                        <option
                            value="<?= $product['id'] ?>"
                            data-unit="<?= htmlspecialchars($product['unit'] ?? '') ?>"
                            data-purchase-unit="<?= htmlspecialchars($product['purchase_unit'] ?? '') ?>"
                            data-conversion="<?= $product['purchase_conversion'] ?>"
                            data-buying-price="<?= $product['buying_price'] ?>"
                            data-stock="<?= $product['stock_quantity'] ?>"
                        >

                            <?= htmlspecialchars($product['product_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- PURCHASE QUANTITY -->

            <div class="form-group">

                <label for="purchase_quantity">
                    Purchase Quantity
                </label>

                <input
                    type="number"
                    id="purchase_quantity"
                    min="0.001"
                    step="0.001"
                    placeholder="e.g. 4"
                >

            </div>


            <!-- BUYING PRICE -->

            <div class="form-group">

                <label for="buying_price">
                    Buying Price / Purchase Unit
                </label>

                <input
                    type="number"
                    id="buying_price"
                    min="0"
                    step="0.01"
                    placeholder="e.g. 3500"
                >

            </div>

        </div>


        <!-- =====================================================
             PRODUCT INFORMATION
             ===================================================== -->

        <div
            class="product-info"
            id="product_info"
        >

            <div class="product-info-grid">


                <div class="info-box">

                    <small>
                        Purchase Unit
                    </small>

                    <strong id="display_purchase_unit">
                        -
                    </strong>

                </div>


                <div class="info-box">

                    <small>
                        Selling Unit
                    </small>

                    <strong id="display_selling_unit">
                        -
                    </strong>

                </div>


                <div class="info-box">

                    <small>
                        Conversion
                    </small>

                    <strong id="display_conversion">
                        -
                    </strong>

                </div>


                <div class="info-box">

                    <small>
                        Current Stock
                    </small>

                    <strong id="display_stock">
                        -
                    </strong>

                </div>

            </div>

        </div>


        <button
            type="button"
            class="button btn-add"
            onclick="addProduct()"
        >

            + Add Product

        </button>

    </div>


    <!-- =========================================================
         RECEIPT ITEMS
         ========================================================= -->

    <div class="card">

        <h2>Stock Receipt Items</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Product</th>
                        <th>Purchase Qty</th>
                        <th>Purchase Unit</th>
                        <th>Conversion</th>
                        <th>Stock Added</th>
                        <th>Buying Price</th>
                        <th>Subtotal</th>
                        <th>Action</th>

                    </tr>

                </thead>


                <tbody id="receipt_items">

                    <tr id="empty_row">

                        <td
                            colspan="8"
                            class="empty-row"
                        >

                            No products added yet.

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <div class="receipt-footer">

            <div></div>

            <div class="total-box">

                Total Cost

                <strong>

                    KSh
                    <span id="total_cost">
                        0.00
                    </span>

                </strong>

            </div>

        </div>


        <button
            type="button"
            class="button btn-save"
            onclick="saveReceipt()"
        >

            💾 Receive Stock

        </button>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Product Data
|--------------------------------------------------------------------------
*/

const products =
    <?= json_encode(
        $products,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>;


let receiptItems = [];

let activeSuggestionIndex = -1;


/*
|--------------------------------------------------------------------------
| DOM Elements
|--------------------------------------------------------------------------
*/

const productSelect =
    document.getElementById("product_id");

const productSearch =
    document.getElementById("product_search");

const productSuggestions =
    document.getElementById("product_suggestions");

const selectedProduct =
    document.getElementById("selected_product");


/*
|--------------------------------------------------------------------------
| Show Product Suggestions
|--------------------------------------------------------------------------
*/

function showProductSuggestions(searchText = "") {

    const search =
        searchText
            .trim()
            .toLowerCase();


    productSuggestions.innerHTML = "";


    let matches = products;


    /*
    |--------------------------------------------------------------------------
    | Search product names
    |--------------------------------------------------------------------------
    */

    if (search !== "") {

        matches =
            products.filter(product => {

                const name =
                    String(
                        product.product_name || ""
                    )
                    .trim()
                    .toLowerCase();


                return name.includes(search);

            });

    }


    /*
    |--------------------------------------------------------------------------
    | No Results
    |--------------------------------------------------------------------------
    */

    if (matches.length === 0) {

        productSuggestions.innerHTML = `

            <div class="no-products">

                No products found for
                "<strong>${escapeHtml(searchText)}</strong>"

            </div>

        `;

        productSuggestions.style.display =
            "block";

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Build Suggestions
    |--------------------------------------------------------------------------
    */

    matches.forEach(
        (product, index) => {

            const item =
                document.createElement("div");


            item.className =
                "product-suggestion";


            item.dataset.index =
                index;


            const stock =
                parseFloat(
                    product.stock_quantity || 0
                );


            const unit =
                product.unit || "";


            const purchaseUnit =
                product.purchase_unit ||
                product.unit ||
                "";


            item.innerHTML = `

                <div class="suggestion-main">

                    <span class="suggestion-name">

                        ${escapeHtml(
                            product.product_name
                        )}

                    </span>


                    <span class="suggestion-stock">

                        Stock:
                        ${stock.toFixed(3)}
                        ${escapeHtml(unit)}

                    </span>

                </div>


                <div class="suggestion-details">

                    Purchase:
                    ${escapeHtml(purchaseUnit)}

                    &nbsp; • &nbsp;

                    Conversion:
                    ${parseFloat(
                        product.purchase_conversion || 1
                    ).toFixed(3)}

                </div>

            `;


            /*
            |--------------------------------------------------------------------------
            | Mouse Selection
            |--------------------------------------------------------------------------
            */

            item.addEventListener(
                "mousedown",
                function(event) {

                    event.preventDefault();

                    selectProduct(product);

                }
            );


            productSuggestions.appendChild(
                item
            );

        }
    );


    productSuggestions.style.display =
        "block";


    activeSuggestionIndex = -1;

}


/*
|--------------------------------------------------------------------------
| Select Product
|--------------------------------------------------------------------------
*/

function selectProduct(product) {

    /*
    |--------------------------------------------------------------------------
    | Set hidden original select
    |--------------------------------------------------------------------------
    */

    productSelect.value =
        product.id;


    /*
    |--------------------------------------------------------------------------
    | Display selected product
    |--------------------------------------------------------------------------
    */

    productSearch.value =
        product.product_name;


    selectedProduct.textContent =
        "✓ Selected: " +
        product.product_name;


    selectedProduct.style.display =
        "block";


    /*
    |--------------------------------------------------------------------------
    | Hide dropdown
    |--------------------------------------------------------------------------
    */

    productSuggestions.style.display =
        "none";


    activeSuggestionIndex = -1;


    /*
    |--------------------------------------------------------------------------
    | Trigger original product logic
    |--------------------------------------------------------------------------
    */

    productSelect.dispatchEvent(
        new Event("change")
    );

}


/*
|--------------------------------------------------------------------------
| Search Input
|--------------------------------------------------------------------------
*/

productSearch.addEventListener(
    "input",
    function() {

        const value =
            this.value;


        /*
        |--------------------------------------------------------------------------
        | Clear selection if user changes the
        | selected product manually.
        |--------------------------------------------------------------------------
        */

        const selectedId =
            productSelect.value;


        if (selectedId) {

            const selected =
                products.find(
                    product =>
                        String(product.id) ===
                        String(selectedId)
                );


            if (
                selected &&
                value !== selected.product_name
            ) {

                productSelect.value = "";

                selectedProduct.textContent = "";

                selectedProduct.style.display =
                    "none";

                document.getElementById(
                    "product_info"
                ).style.display = "none";

            }

        }


        showProductSuggestions(value);

    }
);


/*
|--------------------------------------------------------------------------
| Focus
|--------------------------------------------------------------------------
*/

productSearch.addEventListener(
    "focus",
    function() {

        showProductSuggestions(
            this.value
        );

    }
);


/*
|--------------------------------------------------------------------------
| Keyboard Navigation
|--------------------------------------------------------------------------
*/

productSearch.addEventListener(
    "keydown",
    function(event) {

        const items =
            productSuggestions.querySelectorAll(
                ".product-suggestion"
            );


        /*
        |--------------------------------------------------------------------------
        | Arrow Down
        |--------------------------------------------------------------------------
        */

        if (event.key === "ArrowDown") {

            if (!items.length) {
                return;
            }


            event.preventDefault();


            activeSuggestionIndex++;


            if (
                activeSuggestionIndex >=
                items.length
            ) {

                activeSuggestionIndex = 0;

            }


            items.forEach(
                item =>
                    item.classList.remove("active")
            );


            items[
                activeSuggestionIndex
            ].classList.add("active");


            items[
                activeSuggestionIndex
            ].scrollIntoView({
                block: "nearest"
            });

        }


        /*
        |--------------------------------------------------------------------------
        | Arrow Up
        |--------------------------------------------------------------------------
        */

        else if (event.key === "ArrowUp") {

            if (!items.length) {
                return;
            }


            event.preventDefault();


            activeSuggestionIndex--;


            if (
                activeSuggestionIndex < 0
            ) {

                activeSuggestionIndex =
                    items.length - 1;

            }


            items.forEach(
                item =>
                    item.classList.remove("active")
            );


            items[
                activeSuggestionIndex
            ].classList.add("active");


            items[
                activeSuggestionIndex
            ].scrollIntoView({
                block: "nearest"
            });

        }


        /*
        |--------------------------------------------------------------------------
        | Enter
        |--------------------------------------------------------------------------
        */

        else if (event.key === "Enter") {

            if (
                activeSuggestionIndex >= 0 &&
                items[
                    activeSuggestionIndex
                ]
            ) {

                event.preventDefault();


                const search =
                    productSearch.value
                        .trim()
                        .toLowerCase();


                const matches =
                    products.filter(
                        product => {

                            const name =
                                String(
                                    product.product_name || ""
                                )
                                .trim()
                                .toLowerCase();


                            return name.includes(
                                search
                            );

                        }
                    );


                if (
                    matches[
                        activeSuggestionIndex
                    ]
                ) {

                    selectProduct(
                        matches[
                            activeSuggestionIndex
                        ]
                    );

                }

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Escape
        |--------------------------------------------------------------------------
        */

        else if (event.key === "Escape") {

            productSuggestions.style.display =
                "none";

            activeSuggestionIndex = -1;

        }

    }
);


/*
|--------------------------------------------------------------------------
| Close dropdown when clicking outside
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "click",
    function(event) {

        if (
            !productSearch.contains(event.target) &&
            !productSuggestions.contains(event.target)
        ) {

            productSuggestions.style.display =
                "none";

            activeSuggestionIndex = -1;

        }

    }
);


/*
|--------------------------------------------------------------------------
| Existing Product Selection Logic
|--------------------------------------------------------------------------
*/

productSelect.addEventListener(
    "change",
    function() {

        const option =
            this.options[
                this.selectedIndex
            ];


        if (!this.value) {

            document.getElementById(
                "product_info"
            ).style.display =
                "none";

            return;

        }


        const unit =
            option.dataset.unit || "";


        const purchaseUnit =
            option.dataset.purchaseUnit || "";


        const conversion =
            parseFloat(
                option.dataset.conversion || 1
            );


        const buyingPrice =
            parseFloat(
                option.dataset.buyingPrice || 0
            );


        const stock =
            parseFloat(
                option.dataset.stock || 0
            );


        document.getElementById(
            "display_purchase_unit"
        ).textContent =
            purchaseUnit || "-";


        document.getElementById(
            "display_selling_unit"
        ).textContent =
            unit || "-";


        document.getElementById(
            "display_conversion"
        ).textContent =
            conversion +
            " " +
            unit +
            " / " +
            (purchaseUnit || unit);


        document.getElementById(
            "display_stock"
        ).textContent =
            stock.toFixed(3) +
            " " +
            unit;


        document.getElementById(
            "buying_price"
        ).value =
            buyingPrice > 0
                ? buyingPrice
                : "";


        document.getElementById(
            "product_info"
        ).style.display =
            "block";

    }
);


/*
|--------------------------------------------------------------------------
| Add Product
|--------------------------------------------------------------------------
*/

function addProduct() {

    const productId =
        parseInt(
            productSelect.value
        );


    const quantity =
        parseFloat(
            document.getElementById(
                "purchase_quantity"
            ).value
        );


    const buyingPrice =
        parseFloat(
            document.getElementById(
                "buying_price"
            ).value
        );


    /*
    |--------------------------------------------------------------------------
    | Validate Product
    |--------------------------------------------------------------------------
    */

    if (!productId) {

        alert(
            "Please select a product."
        );

        productSearch.focus();

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Quantity
    |--------------------------------------------------------------------------
    */

    if (
        !quantity ||
        quantity <= 0
    ) {

        alert(
            "Please enter a valid purchase quantity."
        );

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Buying Price
    |--------------------------------------------------------------------------
    */

    if (
        isNaN(buyingPrice) ||
        buyingPrice < 0
    ) {

        alert(
            "Please enter a valid buying price."
        );

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Find Product
    |--------------------------------------------------------------------------
    */

    const product =
        products.find(
            p =>
                parseInt(p.id) ===
                productId
        );


    if (!product) {

        alert(
            "Product not found."
        );

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | Conversion
    |--------------------------------------------------------------------------
    */

    const conversion =
        parseFloat(
            product.purchase_conversion || 1
        );


    /*
    |--------------------------------------------------------------------------
    | Stock Quantity
    |--------------------------------------------------------------------------
    */

    const stockQuantity =
        quantity * conversion;


    /*
    |--------------------------------------------------------------------------
    | Subtotal
    |--------------------------------------------------------------------------
    */

    const subtotal =
        quantity * buyingPrice;


    /*
    |--------------------------------------------------------------------------
    | Add Item
    |--------------------------------------------------------------------------
    */

    receiptItems.push({

        product_id:
            productId,

        product_name:
            product.product_name,

        purchase_quantity:
            quantity,

        purchase_unit:
            product.purchase_unit ||
            product.unit,

        selling_unit:
            product.unit,

        conversion_factor:
            conversion,

        stock_quantity:
            stockQuantity,

        buying_price:
            buyingPrice,

        subtotal:
            subtotal

    });


    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    renderItems();


    /*
    |--------------------------------------------------------------------------
    | Reset Product Selection
    |--------------------------------------------------------------------------
    */

    productSelect.value = "";

    productSearch.value = "";

    selectedProduct.textContent = "";

    selectedProduct.style.display =
        "none";


    document.getElementById(
        "purchase_quantity"
    ).value = "";


    document.getElementById(
        "buying_price"
    ).value = "";


    document.getElementById(
        "product_info"
    ).style.display =
        "none";


    productSearch.focus();

}


/*
|--------------------------------------------------------------------------
| Render Items
|--------------------------------------------------------------------------
*/

function renderItems() {

    const tbody =
        document.getElementById(
            "receipt_items"
        );


    tbody.innerHTML = "";


    /*
    |--------------------------------------------------------------------------
    | Empty
    |--------------------------------------------------------------------------
    */

    if (
        receiptItems.length === 0
    ) {

        tbody.innerHTML = `

            <tr>

                <td
                    colspan="8"
                    class="empty-row"
                >

                    No products added yet.

                </td>

            </tr>

        `;


        document.getElementById(
            "total_cost"
        ).textContent =
            "0.00";


        return;

    }


    let total = 0;


    receiptItems.forEach(
        (item, index) => {

            total +=
                item.subtotal;


            const row =
                document.createElement(
                    "tr"
                );


            row.innerHTML = `

                <td>

                    <strong>
                        ${escapeHtml(
                            item.product_name
                        )}
                    </strong>

                </td>


                <td>

                    ${item.purchase_quantity.toFixed(3)}

                </td>


                <td>

                    ${escapeHtml(
                        item.purchase_unit
                    )}

                </td>


                <td>

                    ${item.conversion_factor.toFixed(3)}
                    ${escapeHtml(
                        item.selling_unit
                    )}

                </td>


                <td>

                    ${item.stock_quantity.toFixed(3)}
                    ${escapeHtml(
                        item.selling_unit
                    )}

                </td>


                <td>

                    KSh
                    ${item.buying_price.toFixed(2)}

                </td>


                <td>

                    <strong>

                        KSh
                        ${item.subtotal.toFixed(2)}

                    </strong>

                </td>


                <td>

                    <button
                        type="button"
                        class="button btn-remove"
                        onclick="removeItem(${index})"
                    >

                        Remove

                    </button>

                </td>

            `;


            tbody.appendChild(row);

        }
    );


    document.getElementById(
        "total_cost"
    ).textContent =
        total.toFixed(2);

}


/*
|--------------------------------------------------------------------------
| Remove Item
|--------------------------------------------------------------------------
*/

function removeItem(index) {

    receiptItems.splice(
        index,
        1
    );


    renderItems();

}


/*
|--------------------------------------------------------------------------
| Save Receipt
|--------------------------------------------------------------------------
*/

function saveReceipt() {

    if (
        receiptItems.length === 0
    ) {

        alert(
            "Please add at least one product."
        );

        return;

    }


    const supplier =
        document.getElementById(
            "supplier_name"
        ).value.trim();


    const receiptDate =
        document.getElementById(
            "receipt_date"
        ).value;


    const notes =
        document.getElementById(
            "notes"
        ).value.trim();


    const data = {

        supplier_name:
            supplier,

        receipt_date:
            receiptDate,

        notes:
            notes,

        items:
            receiptItems

    };


    fetch(
        "process_stock.php",
        {

            method: "POST",

            headers: {

                "Content-Type":
                    "application/json"

            },

            body:
                JSON.stringify(data)

        }
    )

    .then(
        response =>
            response.json()
    )

    .then(
        result => {

            if (result.success) {

                alert(

                    "Stock received successfully.\n\n" +

                    "Receipt: " +
                    result.receipt_number

                );


                window.location.reload();

            }

            else {

                alert(

                    "Error: " +

                    (
                        result.message ||
                        "Unable to save stock."
                    )

                );

            }

        }
    )

    .catch(
        error => {

            console.error(
                error
            );


            alert(
                "A connection error occurred while saving stock."
            );

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
        text;


    return div.innerHTML;

}

</script>

</body>

</html>