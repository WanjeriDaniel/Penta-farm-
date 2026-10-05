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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Receive Stock - Penta Farm</title>

    <style>

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
            width: 95%;
            max-width: 1200px;
            margin: 30px auto;
        }

        .header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        select {
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #2a5298;
        }

        .product-info {
            margin-top: 15px;
            padding: 15px;
            background: #f1f5ff;
            border-radius: 8px;
            display: none;
        }

        .product-info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .info-box {
            background: white;
            padding: 12px;
            border-radius: 7px;
            border: 1px solid #ddd;
        }

        .info-box small {
            display: block;
            color: #666;
            margin-bottom: 4px;
        }

        .info-box strong {
            font-size: 17px;
        }

        .button {
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
        }

        .btn-add {
            background: #1e7e34;
            color: white;
            margin-top: 20px;
        }

        .btn-add:hover {
            background: #155d27;
        }

        .btn-save {
            background: #1e3c72;
            color: white;
            padding: 14px 25px;
            font-size: 16px;
        }

        .btn-save:hover {
            background: #162e59;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f4f6f9;
        }

        .total-box {
            text-align: right;
            font-size: 22px;
            font-weight: bold;
            margin: 20px 0;
        }

        .message {
            padding: 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .error {
            background: #ffe5e5;
            color: #a00000;
        }

        @media (max-width: 800px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .product-info-grid {
                grid-template-columns: 1fr 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

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


    <!-- STOCK RECEIPT INFORMATION -->

    <div class="card">

        <h2>Stock Receipt Information</h2>

        <div class="form-grid">

            <div class="form-group">

                <label>Supplier Name</label>

                <input
                    type="text"
                    id="supplier_name"
                    placeholder="Optional"
                >

            </div>


            <div class="form-group">

                <label>Receipt Date</label>

                <input
                    type="date"
                    id="receipt_date"
                    value="<?= date('Y-m-d') ?>"
                >

            </div>


            <div class="form-group">

                <label>Notes</label>

                <input
                    type="text"
                    id="notes"
                    placeholder="Optional"
                >

            </div>

        </div>

    </div>


    <!-- ADD PRODUCT -->

    <div class="card">

        <h2>Add Product</h2>

        <div class="form-grid">

            <div class="form-group">

                <label>Product</label>

                <select id="product_id">

                    <option value="">-- Select Product --</option>

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


            <div class="form-group">

                <label>Purchase Quantity</label>

                <input
                    type="number"
                    id="purchase_quantity"
                    min="0.001"
                    step="0.001"
                    placeholder="e.g. 4"
                >

            </div>


            <div class="form-group">

                <label>Buying Price / Purchase Unit</label>

                <input
                    type="number"
                    id="buying_price"
                    min="0"
                    step="0.01"
                    placeholder="e.g. 3500"
                >

            </div>

        </div>


        <div class="product-info" id="product_info">

            <div class="product-info-grid">

                <div class="info-box">

                    <small>Purchase Unit</small>

                    <strong id="display_purchase_unit">-</strong>

                </div>


                <div class="info-box">

                    <small>Selling Unit</small>

                    <strong id="display_selling_unit">-</strong>

                </div>


                <div class="info-box">

                    <small>Conversion</small>

                    <strong id="display_conversion">-</strong>

                </div>


                <div class="info-box">

                    <small>Current Stock</small>

                    <strong id="display_stock">-</strong>

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


    <!-- RECEIPT ITEMS -->

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

                        <td colspan="8" style="text-align:center;">

                            No products added yet.

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <div class="total-box">

            Total Cost:
            KSh <span id="total_cost">0.00</span>

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

const products = <?= json_encode($products) ?>;

let receiptItems = [];


/*
|--------------------------------------------------------------------------
| Product Selection
|--------------------------------------------------------------------------
*/

document
    .getElementById("product_id")
    .addEventListener("change", function () {

        const option = this.options[this.selectedIndex];

        if (!this.value) {

            document.getElementById("product_info").style.display = "none";

            return;

        }

        const unit = option.dataset.unit || "";
        const purchaseUnit = option.dataset.purchaseUnit || "";
        const conversion = parseFloat(option.dataset.conversion || 1);
        const buyingPrice = parseFloat(option.dataset.buyingPrice || 0);
        const stock = parseFloat(option.dataset.stock || 0);

        document.getElementById("display_purchase_unit").textContent =
            purchaseUnit || "-";

        document.getElementById("display_selling_unit").textContent =
            unit || "-";

        document.getElementById("display_conversion").textContent =
            conversion + " " + unit + " / " + (purchaseUnit || unit);

        document.getElementById("display_stock").textContent =
            stock.toFixed(3) + " " + unit;

        document.getElementById("buying_price").value =
            buyingPrice > 0 ? buyingPrice : "";

        document.getElementById("product_info").style.display = "block";

    });


/*
|--------------------------------------------------------------------------
| Add Product
|--------------------------------------------------------------------------
*/

function addProduct() {

    const productSelect = document.getElementById("product_id");

    const productId = parseInt(productSelect.value);

    const quantity = parseFloat(
        document.getElementById("purchase_quantity").value
    );

    const buyingPrice = parseFloat(
        document.getElementById("buying_price").value
    );


    if (!productId) {

        alert("Please select a product.");

        return;

    }


    if (!quantity || quantity <= 0) {

        alert("Please enter a valid purchase quantity.");

        return;

    }


    if (isNaN(buyingPrice) || buyingPrice < 0) {

        alert("Please enter a valid buying price.");

        return;

    }


    const product = products.find(
        p => parseInt(p.id) === productId
    );


    if (!product) {

        alert("Product not found.");

        return;

    }


    const conversion =
        parseFloat(product.purchase_conversion || 1);


    const stockQuantity =
        quantity * conversion;


    const subtotal =
        quantity * buyingPrice;


    receiptItems.push({

        product_id: productId,

        product_name: product.product_name,

        purchase_quantity: quantity,

        purchase_unit: product.purchase_unit || product.unit,

        selling_unit: product.unit,

        conversion_factor: conversion,

        stock_quantity: stockQuantity,

        buying_price: buyingPrice,

        subtotal: subtotal

    });


    renderItems();


    productSelect.value = "";

    document.getElementById("purchase_quantity").value = "";

    document.getElementById("buying_price").value = "";

    document.getElementById("product_info").style.display = "none";

}


/*
|--------------------------------------------------------------------------
| Render Items
|--------------------------------------------------------------------------
*/

function renderItems() {

    const tbody =
        document.getElementById("receipt_items");


    tbody.innerHTML = "";


    if (receiptItems.length === 0) {

        tbody.innerHTML = `

            <tr>

                <td colspan="8" style="text-align:center;">

                    No products added yet.

                </td>

            </tr>

        `;

        document.getElementById("total_cost").textContent =
            "0.00";

        return;

    }


    let total = 0;


    receiptItems.forEach((item, index) => {

        total += item.subtotal;


        const row = document.createElement("tr");


        row.innerHTML = `

            <td>${escapeHtml(item.product_name)}</td>

            <td>${item.purchase_quantity.toFixed(3)}</td>

            <td>${escapeHtml(item.purchase_unit)}</td>

            <td>
                ${item.conversion_factor.toFixed(3)}
                ${escapeHtml(item.selling_unit)}
            </td>

            <td>
                ${item.stock_quantity.toFixed(3)}
                ${escapeHtml(item.selling_unit)}
            </td>

            <td>
                KSh ${item.buying_price.toFixed(2)}
            </td>

            <td>
                KSh ${item.subtotal.toFixed(2)}
            </td>

            <td>

                <button
                    type="button"
                    onclick="removeItem(${index})"
                >

                    Remove

                </button>

            </td>

        `;


        tbody.appendChild(row);

    });


    document.getElementById("total_cost").textContent =
        total.toFixed(2);

}


/*
|--------------------------------------------------------------------------
| Remove Item
|--------------------------------------------------------------------------
*/

function removeItem(index) {

    receiptItems.splice(index, 1);

    renderItems();

}


/*
|--------------------------------------------------------------------------
| Save Receipt
|--------------------------------------------------------------------------
*/

function saveReceipt() {

    if (receiptItems.length === 0) {

        alert("Please add at least one product.");

        return;

    }


    const supplier =
        document.getElementById("supplier_name").value.trim();


    const receiptDate =
        document.getElementById("receipt_date").value;


    const notes =
        document.getElementById("notes").value.trim();


    const data = {

        supplier_name: supplier,

        receipt_date: receiptDate,

        notes: notes,

        items: receiptItems

    };


    fetch("process_stock.php", {

        method: "POST",

        headers: {

            "Content-Type": "application/json"

        },

        body: JSON.stringify(data)

    })

    .then(response => response.json())

    .then(result => {

        if (result.success) {

            alert(
                "Stock received successfully.\n\n" +
                "Receipt: " + result.receipt_number
            );

            window.location.reload();

        } else {

            alert(
                "Error: " +
                (result.message || "Unable to save stock.")
            );

        }

    })

    .catch(error => {

        console.error(error);

        alert(
            "A connection error occurred while saving stock."
        );

    });

}


/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(text) {

    const div = document.createElement("div");

    div.textContent = text;

    return div.innerHTML;

}

</script>

</body>

</html>