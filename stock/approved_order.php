<?php
/*
|--------------------------------------------------------------------------
| PENTA FARM — APPROVED STOCK ORDER
|--------------------------------------------------------------------------
| Database-backed version
|
| Loads the selected stock order from:
|   stock_orders
|   stock_order_items
|
| Supplier information is saved directly to stock_orders.
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| GET ORDER ID
|--------------------------------------------------------------------------
*/

$orderId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


$order = null;
$orderItems = [];
$errorMessage = null;


/*
|--------------------------------------------------------------------------
| LOAD ORDER FROM DATABASE
|--------------------------------------------------------------------------
*/

if (!$orderId || $orderId <= 0) {

    $errorMessage =
        "No valid stock order was selected.";

} else {

    try {

        /*
        |--------------------------------------------------------------------------
        | LOAD ORDER HEADER
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                order_number,
                supplier_name,
                order_date,
                status,
                notes
            FROM stock_orders
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $orderId
        ]);

        $order = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$order) {

            $errorMessage =
                "The selected stock order could not be found.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | LOAD ORDER ITEMS
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    product_id,
                    product_name,
                    unit,
                    quantity
                FROM stock_order_items
                WHERE stock_order_id = ?
                ORDER BY id ASC
            ");

            $stmt->execute([
                $orderId
            ]);

            $orderItems =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

        }

    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load the stock order from the database.";

    }

}


/*
|--------------------------------------------------------------------------
| SAVE SUPPLIER
|--------------------------------------------------------------------------
|
| This endpoint is called by JavaScript.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    header(
        "Content-Type: application/json; charset=utf-8"
    );


    $postedOrderId =
        filter_input(
            INPUT_POST,
            "order_id",
            FILTER_VALIDATE_INT
        );


    $supplierName =
        trim(
            $_POST["supplier_name"] ?? ""
        );


    if (
        !$postedOrderId ||
        $postedOrderId <= 0
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid stock order."
        ]);

        exit;

    }


    if ($supplierName === "") {

        echo json_encode([
            "success" => false,
            "message" => "Please enter the supplier name."
        ]);

        exit;

    }


    if (
        mb_strlen(
            $supplierName
        ) > 255
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Supplier name is too long."
        ]);

        exit;

    }


    try {

        /*
        |--------------------------------------------------------------------------
        | CONFIRM ORDER EXISTS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM stock_orders
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $postedOrderId
        ]);


        if (!$stmt->fetch()) {

            echo json_encode([
                "success" => false,
                "message" => "Stock order not found."
            ]);

            exit;

        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE SUPPLIER
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE stock_orders
            SET supplier_name = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $supplierName,
            $postedOrderId
        ]);


        echo json_encode([
            "success" => true,
            "message" => "Supplier saved successfully.",
            "supplier_name" => $supplierName
        ]);

        exit;


    } catch (PDOException $e) {

        echo json_encode([
            "success" => false,
            "message" => "Supplier could not be saved."
        ]);

        exit;

    }

}


/*
|--------------------------------------------------------------------------
| PREPARE DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$orderNumber =
    $order["order_number"] ?? "—";


$supplierName =
    $order["supplier_name"] ?? "";


$orderDate =
    $order["order_date"] ?? null;


$orderStatus =
    $order["status"] ?? "approved";


$totalProducts =
    count($orderItems);


$totalQuantity = 0;


foreach (
    $orderItems
    as $item
) {

    $totalQuantity +=
        (float)(
            $item["quantity"] ?? 0
        );

}


$displayStatus =
    ucfirst(
        strtolower(
            $orderStatus
        )
    );


?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Approved Stock Order — Penta Farm
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 24px;
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
        }


        .page {
            max-width: 1050px;
            margin: 0 auto;
        }


        /* ----------------------------------------------------------
           ACTION BAR
        ---------------------------------------------------------- */

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }


        .action-left,
        .action-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }


        .btn {
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }


        .btn-back {
            background: #e2e8f0;
            color: #0f172a;
        }


        .btn-print {
            background: #0f172a;
            color: #ffffff;
        }


        .btn-copy {
            background: #2563eb;
            color: #ffffff;
        }


        .btn-whatsapp {
            background: #16a34a;
            color: #ffffff;
        }


        .btn-email {
            background: #ea580c;
            color: #ffffff;
        }


        .btn:hover {
            opacity: 0.9;
        }


        /* ----------------------------------------------------------
           DOCUMENT
        ---------------------------------------------------------- */

        .document {
            background: #ffffff;
            border-radius: 14px;
            box-shadow:
                0 10px 30px
                rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }


        /* ----------------------------------------------------------
           HEADER
        ---------------------------------------------------------- */

        .document-header {
            padding: 32px 36px 28px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
        }


        .company-area {
            display: flex;
            align-items: center;
            min-width: 0;
        }


        .company-logo {
            display: block;
            width: 220px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }


        .order-heading {
            text-align: right;
            flex-shrink: 0;
        }


        .order-heading h1 {
            margin: 0 0 8px;
            font-size: 25px;
            letter-spacing: 0.5px;
        }


        .approved-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 8px;
        }


        .order-number {
            font-size: 13px;
            color: #64748b;
            font-weight: 700;
        }


        /* ----------------------------------------------------------
           CONTENT
        ---------------------------------------------------------- */

        .document-body {
            padding: 30px 36px 36px;
        }


        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }


        .info-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            background: #f8fafc;
        }


        .info-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 800;
            margin-bottom: 6px;
        }


        .info-value {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }


        /* ----------------------------------------------------------
           SUPPLIER
        ---------------------------------------------------------- */

        .supplier-editor {
            display: flex;
            gap: 8px;
            width: 100%;
        }


        .supplier-input {
            flex: 1;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 8px 10px;
            font-size: 14px;
            outline: none;
        }


        .supplier-input:focus {
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }


        .supplier-save {
            border: none;
            border-radius: 7px;
            padding: 8px 12px;
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }


        .supplier-save:disabled {
            opacity: 0.6;
            cursor: wait;
        }


        .supplier-display {
            display: flex;
            align-items: center;
            gap: 8px;
        }


        .supplier-name {
            word-break: break-word;
        }


        .supplier-edit {
            border: none;
            background: transparent;
            color: #2563eb;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            padding: 2px 0;
            white-space: nowrap;
        }


        /* ----------------------------------------------------------
           TABLE
        ---------------------------------------------------------- */

        .section-title {
            font-size: 16px;
            font-weight: 800;
            margin: 0 0 12px;
        }


        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }


        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }


        th {
            background: #f8fafc;
            color: #475569;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 13px 14px;
            border-bottom: 1px solid #e2e8f0;
        }


        td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }


        tbody tr:last-child td {
            border-bottom: none;
        }


        .product-name {
            font-weight: 700;
        }


        .category {
            color: #64748b;
        }


        .quantity {
            font-weight: 800;
        }


        /* ----------------------------------------------------------
           SUMMARY
        ---------------------------------------------------------- */

        .summary {
            display: flex;
            justify-content: flex-end;
            gap: 14px;
            margin-top: 22px;
        }


        .summary-card {
            min-width: 170px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px 18px;
            background: #f8fafc;
        }


        .summary-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 800;
            margin-bottom: 6px;
        }


        .summary-value {
            font-size: 22px;
            font-weight: 900;
        }


        /* ----------------------------------------------------------
           NOTES
        ---------------------------------------------------------- */

        .notes {
            margin-top: 28px;
            padding: 16px;
            border-left: 4px solid #2563eb;
            background: #eff6ff;
            border-radius: 7px;
            color: #334155;
            font-size: 13px;
            line-height: 1.6;
        }


        .notes strong {
            color: #0f172a;
        }


        /* ----------------------------------------------------------
           EMPTY / ERROR STATE
        ---------------------------------------------------------- */

        .empty-state {
            text-align: center;
            padding: 70px 30px;
        }


        .empty-state h2 {
            margin: 0 0 8px;
        }


        .empty-state p {
            color: #64748b;
            margin-bottom: 20px;
        }


        /* ----------------------------------------------------------
           TOAST
        ---------------------------------------------------------- */

        .toast {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: #0f172a;
            color: #ffffff;
            padding: 12px 17px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px);
            transition: 0.25s ease;
            z-index: 9999;
        }


        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }


        /* ----------------------------------------------------------
           RESPONSIVE
        ---------------------------------------------------------- */

        @media (max-width: 750px) {

            body {
                padding: 12px;
            }


            .document-header {
                padding: 24px 20px;
                flex-direction: column;
                align-items: flex-start;
            }


            .company-logo {
                width: 190px;
            }


            .order-heading {
                text-align: left;
            }


            .document-body {
                padding: 22px 20px 28px;
            }


            .info-grid {
                grid-template-columns: 1fr;
            }


            .summary {
                justify-content: stretch;
                flex-direction: column;
            }


            .summary-card {
                width: 100%;
            }


            .action-bar {
                align-items: stretch;
            }


            .action-left,
            .action-right {
                width: 100%;
            }


            .btn {
                justify-content: center;
            }

        }


        /* ----------------------------------------------------------
           PRINT
        ---------------------------------------------------------- */

        @media print {

            body {
                padding: 0;
                background: #ffffff;
            }


            .action-bar {
                display: none !important;
            }


            .document {
                box-shadow: none;
                border-radius: 0;
            }


            .supplier-edit,
            .supplier-editor {
                display: none !important;
            }


            .document-header {
                padding: 20px 0;
            }


            .document-body {
                padding: 20px 0;
            }

        }

    </style>

</head>


<body>

<div class="page">


    <!-- ==========================================================
         ACTION BAR
    =========================================================== -->

    <div class="action-bar">

        <div class="action-left">

            <a
                href="order.php"
                class="btn btn-back"
            >
                ← Back to Stock Order
            </a>

        </div>


        <div class="action-right">

            <button
                type="button"
                class="btn btn-print"
                onclick="printOrder()"
            >
                🖨 Print
            </button>


            <button
                type="button"
                class="btn btn-copy"
                onclick="copyOrder()"
            >
                📋 Copy Order
            </button>


            <button
                type="button"
                class="btn btn-whatsapp"
                onclick="sendWhatsApp()"
            >
                WhatsApp
            </button>


            <button
                type="button"
                class="btn btn-email"
                onclick="sendEmail()"
            >
                ✉ Email
            </button>

        </div>

    </div>


    <!-- ==========================================================
         DOCUMENT
    =========================================================== -->

    <div class="document">


        <!-- ======================================================
             DOCUMENT HEADER
        ======================================================= -->

        <div class="document-header">


            <div class="company-area">

                <img
                    src="/penta_farm/assets/penta-farm-logo.png"
                    alt="Penta Farm Trading Co. Ltd."
                    class="company-logo"
                >

            </div>


            <div class="order-heading">

                <h1>
                    STOCK ORDER
                </h1>


                <div class="approved-badge">

                    ✓
                    <?= htmlspecialchars(
                        strtoupper(
                            $displayStatus
                        )
                    ) ?>

                </div>


                <div class="order-number">

                    <?= htmlspecialchars(
                        $orderNumber
                    ) ?>

                </div>

            </div>

        </div>


        <!-- ======================================================
             DOCUMENT BODY
        ======================================================= -->

        <div class="document-body">


        <?php if ($errorMessage): ?>


            <div class="empty-state">

                <h2>
                    Stock Order Not Available
                </h2>


                <p>
                    <?= htmlspecialchars(
                        $errorMessage
                    ) ?>
                </p>


                <a
                    href="order.php"
                    class="btn btn-copy"
                >
                    Create Stock Order
                </a>

            </div>


        <?php elseif (
            empty($orderItems)
        ): ?>


            <div class="empty-state">

                <h2>
                    Empty Stock Order
                </h2>


                <p>
                    This order exists in the database,
                    but it contains no products.
                </p>


                <a
                    href="order.php"
                    class="btn btn-copy"
                >
                    Create Stock Order
                </a>

            </div>


        <?php else: ?>


            <!-- ==================================================
                 ORDER INFORMATION
            =================================================== -->

            <div class="info-grid">


                <div class="info-card">

                    <div class="info-label">
                        Order Date
                    </div>


                    <div class="info-value">

                        <?= $orderDate
                            ? htmlspecialchars(
                                date(
                                    "d M Y",
                                    strtotime(
                                        $orderDate
                                    )
                                )
                            )
                            : "—"
                        ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-label">
                        Status
                    </div>


                    <div
                        class="info-value"
                        id="statusValue"
                    >

                        <?= htmlspecialchars(
                            $displayStatus
                        ) ?>

                    </div>

                </div>


                <div class="info-card">

                    <div class="info-label">
                        Supplier
                    </div>


                    <div
                        class="info-value"
                        id="supplierContainer"
                    >


                    <?php if ($supplierName): ?>


                        <div class="supplier-display">

                            <span class="supplier-name">

                                <?= htmlspecialchars(
                                    $supplierName
                                ) ?>

                            </span>


                            <button
                                type="button"
                                class="supplier-edit"
                                onclick="editSupplier()"
                            >
                                ✎ Edit
                            </button>

                        </div>


                    <?php else: ?>


                        <div class="supplier-editor">

                            <input
                                type="text"
                                id="supplierInput"
                                class="supplier-input"
                                placeholder="Enter supplier name"
                                maxlength="255"
                            >


                            <button
                                type="button"
                                class="supplier-save"
                                onclick="saveSupplier()"
                            >
                                Save
                            </button>

                        </div>


                    <?php endif; ?>


                    </div>

                </div>

            </div>


            <!-- ==================================================
                 PRODUCTS
            =================================================== -->

            <h2 class="section-title">
                Ordered Products
            </h2>


            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Product
                            </th>

                            <th>
                                Unit
                            </th>

                            <th>
                                Quantity
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $orderItems
                        as $index => $item
                    ): ?>


                        <tr>


                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <td>

                                <div class="product-name">

                                    <?= htmlspecialchars(
                                        $item[
                                            "product_name"
                                        ]
                                    ) ?>

                                </div>


                                <div class="category">
                                    —
                                </div>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $item[
                                        "unit"
                                    ] ?? "—"
                                ) ?>

                            </td>


                            <td class="quantity">

                                <?= number_format(
                                    (float)(
                                        $item[
                                            "quantity"
                                        ] ?? 0
                                    ),
                                    2
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


            <!-- ==================================================
                 SUMMARY
            =================================================== -->

            <div class="summary">


                <div class="summary-card">

                    <div class="summary-label">
                        Total Products
                    </div>


                    <div class="summary-value">

                        <?= number_format(
                            $totalProducts
                        ) ?>

                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-label">
                        Total Quantity
                    </div>


                    <div class="summary-value">

                        <?= number_format(
                            $totalQuantity,
                            2
                        ) ?>

                    </div>

                </div>


            </div>


            <!-- ==================================================
                 NOTES
            =================================================== -->

            <div class="notes">

                <strong>Note:</strong>

                This stock order has been approved
                and is ready to be processed with the supplier.

                Inventory will only increase when the
                ordered goods are physically received.

            </div>


        <?php endif; ?>


        </div>

    </div>

</div>


<div
    id="toast"
    class="toast"
></div>


<script>

/*
|--------------------------------------------------------------------------
| ORDER INFORMATION
|--------------------------------------------------------------------------
*/

const orderId =
    <?= (int)$orderId ?>;


const orderNumber =
    <?= json_encode(
        $orderNumber,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;


const orderDate =
    <?= json_encode(
        $orderDate ?? "",
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;


let currentSupplier =
    <?= json_encode(
        $supplierName,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;


const orderItems =
    <?= json_encode(
        $orderItems,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value ?? "")
        .replace(
            /&/g,
            "&amp;"
        )
        .replace(
            /</g,
            "&lt;"
        )
        .replace(
            />/g,
            "&gt;"
        )
        .replace(
            /"/g,
            "&quot;"
        )
        .replace(
            /'/g,
            "&#039;"
        );

}


/*
|--------------------------------------------------------------------------
| SAVE SUPPLIER
|--------------------------------------------------------------------------
*/

async function saveSupplier() {

    const input =
        document.getElementById(
            "supplierInput"
        );


    if (!input) {
        return;
    }


    const supplierName =
        input.value.trim();


    if (!supplierName) {

        showMessage(
            "Please enter the supplier name."
        );

        input.focus();

        return;

    }


    if (supplierName.length > 255) {

        showMessage(
            "Supplier name is too long."
        );

        return;

    }


    const saveButton =
        document.querySelector(
            ".supplier-save"
        );


    if (saveButton) {

        saveButton.disabled = true;

        saveButton.textContent =
            "Saving...";

    }


    try {

        const formData =
            new FormData();


        formData.append(
            "order_id",
            orderId
        );


        formData.append(
            "supplier_name",
            supplierName
        );


        const response =
            await fetch(
                "approved_order.php?id=" +
                encodeURIComponent(orderId),
                {
                    method: "POST",
                    body: formData
                }
            );


        const result =
            await response.json();


        if (!result.success) {

            throw new Error(
                result.message ||
                "Supplier could not be saved."
            );

        }


        currentSupplier =
            result.supplier_name;


        renderSupplier();


        showMessage(
            "Supplier saved successfully."
        );


    } catch (error) {

        console.error(error);

        showMessage(
            error.message ||
            "Supplier could not be saved."
        );


        if (saveButton) {

            saveButton.disabled = false;

            saveButton.textContent =
                "Save";

        }

    }

}


/*
|--------------------------------------------------------------------------
| RENDER SUPPLIER
|--------------------------------------------------------------------------
*/

function renderSupplier() {

    const container =
        document.getElementById(
            "supplierContainer"
        );


    if (!container) {
        return;
    }


    if (currentSupplier) {

        container.innerHTML = `

            <div class="supplier-display">

                <span class="supplier-name">
                    ${escapeHtml(currentSupplier)}
                </span>

                <button
                    type="button"
                    class="supplier-edit"
                    onclick="editSupplier()"
                >
                    ✎ Edit
                </button>

            </div>

        `;

    } else {

        container.innerHTML = `

            <div class="supplier-editor">

                <input
                    type="text"
                    id="supplierInput"
                    class="supplier-input"
                    placeholder="Enter supplier name"
                    maxlength="255"
                >

                <button
                    type="button"
                    class="supplier-save"
                    onclick="saveSupplier()"
                >
                    Save
                </button>

            </div>

        `;

    }

}


/*
|--------------------------------------------------------------------------
| EDIT SUPPLIER
|--------------------------------------------------------------------------
*/

function editSupplier() {

    const container =
        document.getElementById(
            "supplierContainer"
        );


    if (!container) {
        return;
    }


    container.innerHTML = `

        <div class="supplier-editor">

            <input
                type="text"
                id="supplierInput"
                class="supplier-input"
                value="${escapeHtml(currentSupplier)}"
                placeholder="Enter supplier name"
                maxlength="255"
            >

            <button
                type="button"
                class="supplier-save"
                onclick="saveSupplier()"
            >
                Save
            </button>

        </div>

    `;


    const input =
        document.getElementById(
            "supplierInput"
        );


    if (input) {

        input.focus();

        input.select();

    }

}


/*
|--------------------------------------------------------------------------
| BUILD ORDER MESSAGE
|--------------------------------------------------------------------------
*/

function buildOrderMessage() {

    if (
        !orderId ||
        !orderItems.length
    ) {

        return "";

    }


    let message = "";


    message +=
        "PENTA FARM TRADING CO. LTD.\n";

    message +=
        "STOCK ORDER\n\n";


    message +=
        "Order Number: " +
        (orderNumber || "—") +
        "\n";


    message +=
        "Order Date: " +
        (orderDate || "—") +
        "\n";


    message +=
        "Status: Approved\n";


    message +=
        "Supplier: " +
        (currentSupplier || "Not Selected") +
        "\n\n";


    message +=
        "ORDERED PRODUCTS\n";


    orderItems.forEach(
        function(item, index) {

            message +=
                (index + 1) +
                ". " +
                (item.product_name || "Product") +
                " — " +
                (item.quantity || 0) +
                " " +
                (item.unit || "") +
                "\n";

        }
    );


    message += "\n";


    message +=
        "Total Products: " +
        orderItems.length +
        "\n";


    const totalQuantity =
        orderItems.reduce(
            function(total, item) {

                return total +
                    (
                        parseFloat(
                            item.quantity
                        ) || 0
                    );

            },
            0
        );


    message +=
        "Total Quantity: " +
        totalQuantity +
        "\n";


    return message;

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

function printOrder() {

    window.print();

}


/*
|--------------------------------------------------------------------------
| COPY
|--------------------------------------------------------------------------
*/

async function copyOrder() {

    const message =
        buildOrderMessage();


    if (!message) {

        showMessage(
            "No order available to copy."
        );

        return;

    }


    try {

        await navigator.clipboard.writeText(
            message
        );


        showMessage(
            "Order copied to clipboard."
        );


    } catch (error) {

        console.error(error);

        showMessage(
            "Could not copy the order."
        );

    }

}


/*
|--------------------------------------------------------------------------
| WHATSAPP
|--------------------------------------------------------------------------
*/

function sendWhatsApp() {

    const message =
        buildOrderMessage();


    if (!message) {
        return;
    }


    const url =
        "https://wa.me/?text=" +
        encodeURIComponent(
            message
        );


    window.open(
        url,
        "_blank"
    );

}


/*
|--------------------------------------------------------------------------
| EMAIL
|--------------------------------------------------------------------------
*/

function sendEmail() {

    const message =
        buildOrderMessage();


    if (!message) {
        return;
    }


    const subject =
        "Penta Farm Stock Order " +
        (orderNumber || "");


    const mailto =
        "mailto:?subject=" +
        encodeURIComponent(
            subject
        ) +
        "&body=" +
        encodeURIComponent(
            message
        );


    window.location.href =
        mailto;

}


/*
|--------------------------------------------------------------------------
| TOAST
|--------------------------------------------------------------------------
*/

let toastTimer = null;


function showMessage(message) {

    const toast =
        document.getElementById(
            "toast"
        );


    toast.textContent =
        message;


    toast.classList.add(
        "show"
    );


    clearTimeout(
        toastTimer
    );


    toastTimer =
        setTimeout(
            function() {

                toast.classList.remove(
                    "show"
                );

            },
            2500
        );

}

</script>

</body>

</html>

