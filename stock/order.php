<?php
/*
|--------------------------------------------------------------------------
| PENTA FARM — STOCK ORDER
|--------------------------------------------------------------------------
| Stage 2:
| Products are now loaded from the Penta Farm products table.
|
| Confirmed orders are still temporarily stored in browser localStorage.
| The next stage will save approved orders to stock_orders.
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";



/*
|--------------------------------------------------------------------------
| CREATE STOCK ORDER
|--------------------------------------------------------------------------
| The interface remains unchanged.
| This endpoint replaces the temporary localStorage saving.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json; charset=utf-8');

    try {

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (
            !is_array($input) ||
            !isset($input['items']) ||
            !is_array($input['items']) ||
            count($input['items']) === 0
        ) {

            throw new Exception(
                'There are no products in this order.'
            );

        }


        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Generate unique order number
        |--------------------------------------------------------------------------
        */

        do {

            $orderNumber =
                'PO-' .
                date('Ymd-His') .
                '-' .
                strtoupper(
                    substr(
                        bin2hex(random_bytes(3)),
                        0,
                        6
                    )
                );


            $check =
                $pdo->prepare("
                    SELECT COUNT(*)
                    FROM stock_orders
                    WHERE order_number = ?
                ");


            $check->execute([
                $orderNumber
            ]);


            $exists =
                (int) $check->fetchColumn() > 0;

        } while ($exists);


        /*
        |--------------------------------------------------------------------------
        | Create the stock order
        |--------------------------------------------------------------------------
        */

        $insertOrder =
            $pdo->prepare("
                INSERT INTO stock_orders
                (
                    order_number,
                    supplier_name,
                    status,
                    notes
                )
                VALUES
                (
                    ?,
                    NULL,
                    'approved',
                    NULL
                )
            ");


        $insertOrder->execute([
            $orderNumber
        ]);


        $orderId =
            (int) $pdo->lastInsertId();


        if ($orderId <= 0) {

            throw new Exception(
                'The stock order could not be created.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Prepare product lookup
        |--------------------------------------------------------------------------
        */

        $getProduct =
            $pdo->prepare("
                SELECT
                    id,
                    product_name,
                    unit,
                    is_active
                FROM products
                WHERE id = ?
                LIMIT 1
            ");


        /*
        |--------------------------------------------------------------------------
        | Prepare order item insert
        |--------------------------------------------------------------------------
        */

        $insertItem =
            $pdo->prepare("
                INSERT INTO stock_order_items
                (
                    stock_order_id,
                    product_id,
                    product_name,
                    unit,
                    quantity
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


        /*
        |--------------------------------------------------------------------------
        | Save each product
        |--------------------------------------------------------------------------
        */

        foreach ($input['items'] as $item) {

            $productId =
                isset($item['id'])
                    ? (int) $item['id']
                    : 0;


            $quantity =
                isset($item['quantity'])
                    ? (int) $item['quantity']
                    : 0;


            if ($productId <= 0) {

                throw new Exception(
                    'Invalid product selected.'
                );

            }


            if ($quantity <= 0) {

                throw new Exception(
                    'Product quantity must be greater than zero.'
                );

            }


            $getProduct->execute([
                $productId
            ]);


            $product =
                $getProduct->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$product) {

                throw new Exception(
                    'One of the selected products could not be found.'
                );

            }


            if ((int) $product['is_active'] !== 1) {

                throw new Exception(
                    'One of the selected products is no longer active.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Store database values, not browser values
            |--------------------------------------------------------------------------
            */

            $insertItem->execute([

                $orderId,

                $productId,

                $product['product_name'],

                $product['unit'],

                $quantity

            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | Commit everything
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        echo json_encode([

            'success' =>
                true,

            'message' =>
                'Stock order created successfully.',

            'order_id' =>
                $orderId,

            'order_number' =>
                $orderNumber

        ]);

        exit;


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }


        http_response_code(400);


        echo json_encode([

            'success' =>
                false,

            'message' =>
                $e->getMessage()

        ]);

        exit;

    }
}



/*
|--------------------------------------------------------------------------
| LOAD ACTIVE PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];
$databaseError = null;

try {

    $stmt = $pdo->query("
        SELECT
            id,
            product_name,
            unit,
            stock_quantity,
            low_stock_level,
            is_active
        FROM products
        WHERE is_active = 1
        ORDER BY product_name ASC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $databaseError =
        "Unable to load products from the database.";

}


/*
|--------------------------------------------------------------------------
| PREPARE PRODUCT DATA FOR THE INTERFACE
|--------------------------------------------------------------------------
|
| Your products table does not currently have a category field.
| Therefore category is intentionally left as "—".
|
| Stock status is calculated from the actual database values.
|--------------------------------------------------------------------------
*/

foreach ($products as &$product) {

    $stock =
        (float) $product['stock_quantity'];

    $lowStockLevel =
        (float) $product['low_stock_level'];


    if ($stock <= 0) {

        $product['status'] =
            'Out of Stock';

    } elseif ($stock <= $lowStockLevel) {

        $product['status'] =
            'Low Stock';

    } else {

        $product['status'] =
            'In Stock';

    }


    $product['name'] =
        $product['product_name'];

    $product['category'] =
        '—';

}

unset($product);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Create Stock Order | Penta Farm</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        button,
        input {
            font-family: inherit;
        }


        /* -------------------------------------------------------------
           HEADER
        ------------------------------------------------------------- */

        .page-header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 22px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .header-title h1 {
            margin: 0 0 5px;
            font-size: 25px;
            color: #111827;
        }

        .header-title p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            border: 0;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .btn-secondary {
            background: #eef2f7;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-primary {
            background: #0f766e;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0d625c;
        }

        .btn-danger {
            background: #fee2e2;
            color: #b91c1c;
        }


        /* -------------------------------------------------------------
           DATABASE MESSAGE
        ------------------------------------------------------------- */

        .database-error {
            margin: 20px 28px 0;
            padding: 14px 16px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 9px;
            font-size: 13px;
        }


        /* -------------------------------------------------------------
           MAIN LAYOUT
        ------------------------------------------------------------- */

        .page-content {
            padding: 24px 28px 40px;
        }

        .order-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 390px;
            gap: 22px;
            align-items: start;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 7px rgba(0, 0, 0, 0.04);
        }

        .card-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f2;
        }

        .card-header h2 {
            margin: 0 0 4px;
            font-size: 17px;
        }

        .card-header p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }


        /* -------------------------------------------------------------
           SEARCH
        ------------------------------------------------------------- */

        .search-area {
            padding: 16px 20px;
            border-bottom: 1px solid #edf0f2;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            height: 42px;
            padding: 0 14px 0 40px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }

        .search-box input:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.08);
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 12px;
            color: #9ca3af;
        }


        /* -------------------------------------------------------------
           PRODUCT LIST
        ------------------------------------------------------------- */

        .product-list {
            padding: 0;
        }

        .product-row {
            display: grid;
            grid-template-columns: 42px minmax(180px, 1.5fr) 1fr 90px 100px;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f2f4;
            transition: background 0.15s ease;
        }

        .product-row:last-child {
            border-bottom: 0;
        }

        .product-row:hover {
            background: #f9fafb;
        }

        .product-row.selected {
            background: #f0fdfa;
        }

        .product-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0f766e;
        }

        .product-name {
            font-weight: 600;
            font-size: 14px;
            color: #111827;
        }

        .product-category,
        .product-unit,
        .product-stock {
            font-size: 13px;
            color: #6b7280;
        }

        .status {
            display: inline-flex;
            width: fit-content;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-in {
            background: #dcfce7;
            color: #166534;
        }

        .status-low {
            background: #fef3c7;
            color: #92400e;
        }

        .status-out {
            background: #fee2e2;
            color: #991b1b;
        }

        .no-products {
            padding: 50px 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }


        /* -------------------------------------------------------------
           ORDER PANEL
        ------------------------------------------------------------- */

        .order-card {
            position: sticky;
            top: 20px;
        }

        .order-summary-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-count {
            background: #e6fffb;
            color: #0f766e;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 11px;
            font-weight: 700;
        }

        .selected-items {
            min-height: 180px;
            max-height: 420px;
            overflow-y: auto;
        }

        .empty-order {
            padding: 45px 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }

        .empty-order-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .selected-item {
            padding: 15px 18px;
            border-bottom: 1px solid #edf0f2;
        }

        .selected-item-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .selected-item-name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .selected-item-unit {
            font-size: 12px;
            color: #6b7280;
            margin-top: 3px;
        }

        .remove-item {
            border: 0;
            background: transparent;
            color: #9ca3af;
            cursor: pointer;
            font-size: 16px;
        }

        .remove-item:hover {
            color: #dc2626;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #d1d5db;
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 17px;
        }

        .quantity-btn:hover {
            background: #f3f4f6;
        }

        .quantity-input {
            width: 65px;
            height: 30px;
            text-align: center;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            outline: none;
            font-size: 13px;
            font-weight: 600;
        }

        .quantity-input:focus {
            border-color: #0f766e;
        }

        .order-footer {
            border-top: 1px solid #e5e7eb;
            padding: 16px 18px;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
            color: #6b7280;
        }

        .summary-line strong {
            color: #111827;
        }

        .order-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px;
            margin-top: 15px;
        }


        /* -------------------------------------------------------------
           REVIEW MODAL
        ------------------------------------------------------------- */

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.58);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
        }

        .modal-overlay.show {
            display: flex;
        }

        .review-modal {
            background: #ffffff;
            width: min(900px, 100%);
            max-height: 90vh;
            overflow: hidden;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
        }

        .review-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .review-header h2 {
            margin: 0 0 4px;
            font-size: 20px;
        }

        .review-header p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }

        .close-review {
            border: 0;
            background: #f3f4f6;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            color: #4b5563;
        }

        .close-review:hover {
            background: #e5e7eb;
        }

        .review-body {
            padding: 20px 24px;
            overflow-y: auto;
        }

        .review-table {
            width: 100%;
            border-collapse: collapse;
        }

        .review-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 11px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .review-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf0f2;
            font-size: 13px;
        }

        .review-product {
            font-weight: 600;
            color: #111827;
        }

        .review-category {
            color: #6b7280;
            font-size: 12px;
            margin-top: 3px;
        }

        .review-quantity {
            font-weight: 700;
        }

        .review-remove {
            border: 0;
            background: transparent;
            color: #dc2626;
            cursor: pointer;
            font-size: 13px;
        }

        .review-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .review-summary-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 14px;
        }

        .review-summary-box span {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .review-summary-box strong {
            font-size: 20px;
            color: #111827;
        }

        .review-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }


        /* -------------------------------------------------------------
           SUCCESS MESSAGE
        ------------------------------------------------------------- */

        .prototype-message {
            display: none;
            position: fixed;
            right: 25px;
            bottom: 25px;
            background: #111827;
            color: white;
            padding: 14px 18px;
            border-radius: 9px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            font-size: 13px;
            z-index: 2000;
        }

        .prototype-message.show {
            display: block;
        }


        /* -------------------------------------------------------------
           RESPONSIVE
        ------------------------------------------------------------- */

        @media (max-width: 950px) {

            .order-layout {
                grid-template-columns: 1fr;
            }

            .order-card {
                position: static;
            }

        }


        @media (max-width: 700px) {

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            .page-content {
                padding: 15px;
            }

            .database-error {
                margin: 15px 15px 0;
            }

            .product-row {
                grid-template-columns: 38px 1fr auto;
            }

            .product-category,
            .product-unit {
                display: none;
            }

            .product-stock {
                text-align: right;
            }

            .review-table th:nth-child(2),
            .review-table td:nth-child(2) {
                display: none;
            }

            .review-summary {
                grid-template-columns: 1fr 1fr;
            }

        }

    </style>

</head>


<body>


<!-- ================================================================
     HEADER
================================================================ -->

<header class="page-header">

    <div class="header-title">

        <h1>
            🛒 Create Stock Order
        </h1>

        <p>
            Select the products you need and prepare an order.
        </p>

    </div>


    <div class="header-actions">

        <a
            href="index.php"
            class="btn btn-secondary"
        >
            ← Stock Dashboard
        </a>


        <a
            href="history.php"
            class="btn btn-secondary"
        >
            Stock History
        </a>

    </div>

</header>


<?php if ($databaseError): ?>

    <div class="database-error">
        <?= htmlspecialchars($databaseError) ?>
    </div>

<?php endif; ?>


<!-- ================================================================
     MAIN CONTENT
================================================================ -->

<main class="page-content">

    <div class="order-layout">


        <!-- =========================================================
             PRODUCTS
        ========================================================== -->

        <section class="card">

            <div class="card-header">

                <h2>
                    Select Products
                </h2>

                <p>
                    Tick the products you want to include in this order.
                </p>

            </div>


            <div class="search-area">

                <div class="search-box">

                    <span class="search-icon">
                        🔎
                    </span>

                    <input
                        type="text"
                        id="productSearch"
                        placeholder="Search products..."
                    >

                </div>

            </div>


            <div
                class="product-list"
                id="productList"
            >

                <?php if (empty($products)): ?>

                    <div class="no-products">

                        <?php if ($databaseError): ?>

                            Products could not be loaded.

                        <?php else: ?>

                            No active products are currently available.

                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <?php foreach ($products as $product): ?>

                        <?php

                            $statusClass = 'status-in';

                            if (
                                $product['status']
                                === 'Low Stock'
                            ) {

                                $statusClass =
                                    'status-low';

                            }

                            if (
                                $product['status']
                                === 'Out of Stock'
                            ) {

                                $statusClass =
                                    'status-out';

                            }

                        ?>


                        <div
                            class="product-row"
                            data-id="<?= (int) $product['id'] ?>"
                            data-name="<?= htmlspecialchars(
                                $product['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            data-category="<?= htmlspecialchars(
                                $product['category'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            data-unit="<?= htmlspecialchars(
                                $product['unit'] ?? '—',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <div>

                                <input
                                    type="checkbox"
                                    class="product-checkbox"
                                    data-id="<?= (int) $product['id'] ?>"
                                >

                            </div>


                            <div>

                                <div class="product-name">
                                    <?= htmlspecialchars(
                                        $product['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                            </div>


                            <div class="product-category">
                                <?= htmlspecialchars(
                                    $product['category'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>


                            <div class="product-unit">
                                <?= htmlspecialchars(
                                    $product['unit'] ?? '—',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>


                            <div class="product-stock">

                                <span class="status <?= $statusClass ?>">
                                    <?= htmlspecialchars(
                                        $product['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>


        <!-- =========================================================
             ORDER PANEL
        ========================================================== -->

        <aside class="card order-card">

            <div class="card-header">

                <div class="order-summary-top">

                    <div>

                        <h2>
                            Your Order
                        </h2>

                        <p>
                            Adjust quantities before reviewing.
                        </p>

                    </div>


                    <span
                        class="selected-count"
                        id="selectedCount"
                    >
                        0 items
                    </span>

                </div>

            </div>


            <div
                class="selected-items"
                id="selectedItems"
            >

                <div
                    class="empty-order"
                    id="emptyOrder"
                >

                    <div class="empty-order-icon">
                        🛒
                    </div>

                    <div>
                        No products selected yet.
                    </div>

                </div>

            </div>


            <div class="order-footer">

                <div class="summary-line">

                    <span>
                        Products
                    </span>

                    <strong id="summaryProducts">
                        0
                    </strong>

                </div>


                <div class="summary-line">

                    <span>
                        Total Quantity
                    </span>

                    <strong id="summaryQuantity">
                        0
                    </strong>

                </div>


                <div class="order-actions">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="clearOrder"
                    >
                        Clear
                    </button>


                    <button
                        type="button"
                        class="btn btn-primary"
                        id="reviewOrder"
                    >
                        Review Order
                    </button>

                </div>

            </div>

        </aside>

    </div>

</main>


<!-- ================================================================
     REVIEW MODAL
================================================================ -->

<div
    class="modal-overlay"
    id="reviewModal"
>

    <div class="review-modal">


        <div class="review-header">

            <div>

                <h2>
                    Review Stock Order
                </h2>

                <p>
                    Check the products and quantities before confirming.
                </p>

            </div>


            <button
                type="button"
                class="close-review"
                id="closeReview"
            >
                ×
            </button>

        </div>


        <div class="review-body">

            <table class="review-table">

                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Unit
                        </th>

                        <th>
                            Quantity
                        </th>

                        <th>
                        </th>

                    </tr>

                </thead>


                <tbody id="reviewItems">
                </tbody>

            </table>


            <div class="review-summary">

                <div class="review-summary-box">

                    <span>
                        Total Products
                    </span>

                    <strong id="reviewProductCount">
                        0
                    </strong>

                </div>


                <div class="review-summary-box">

                    <span>
                        Total Quantity
                    </span>

                    <strong id="reviewQuantityCount">
                        0
                    </strong>

                </div>

            </div>

        </div>


        <div class="review-footer">

            <button
                type="button"
                class="btn btn-secondary"
                id="backToEdit"
            >
                ← Back to Edit
            </button>


            <button
                type="button"
                class="btn btn-primary"
                id="confirmOrder"
            >
                ✓ Confirm Order
            </button>

        </div>

    </div>

</div>


<!-- ================================================================
     MESSAGE
================================================================ -->

<div
    class="prototype-message"
    id="prototypeMessage"
>
</div>


<script>

/* =================================================================
   ORDER STATE
================================================================= */

let selectedProducts = {};


/* =================================================================
   PRODUCT CHECKBOXES
================================================================= */

document
    .querySelectorAll('.product-checkbox')
    .forEach(function (checkbox) {

        checkbox.addEventListener(
            'change',
            function () {

                const row =
                    this.closest('.product-row');

                const id =
                    row.dataset.id;


                if (this.checked) {

                    selectedProducts[id] = {

                        id: id,

                        name:
                            row.dataset.name,

                        category:
                            row.dataset.category,

                        unit:
                            row.dataset.unit,

                        quantity: 1

                    };

                    row.classList.add('selected');

                } else {

                    delete selectedProducts[id];

                    row.classList.remove('selected');

                }


                renderSelectedItems();

            }
        );

    });


/* =================================================================
   RENDER SELECTED ITEMS
================================================================= */

function renderSelectedItems() {

    const container =
        document.getElementById(
            'selectedItems'
        );

    const ids =
        Object.keys(selectedProducts);


    container.innerHTML = '';


    if (ids.length === 0) {

        container.innerHTML = `

            <div class="empty-order">

                <div class="empty-order-icon">
                    🛒
                </div>

                <div>
                    No products selected yet.
                </div>

            </div>

        `;

        updateSummary();

        return;
    }


    ids.forEach(function (id) {

        const item =
            selectedProducts[id];


        const wrapper =
            document.createElement('div');


        wrapper.className =
            'selected-item';


        wrapper.innerHTML = `

            <div class="selected-item-top">

                <div>

                    <div class="selected-item-name">
                        ${escapeHtml(item.name)}
                    </div>

                    <div class="selected-item-unit">
                        ${escapeHtml(item.unit)}
                    </div>

                </div>


                <button
                    type="button"
                    class="remove-item"
                    onclick="removeProduct('${id}')"
                >
                    ✕
                </button>

            </div>


            <div class="quantity-control">

                <button
                    type="button"
                    class="quantity-btn"
                    onclick="changeQuantity('${id}', -1)"
                >
                    −
                </button>


                <input
                    type="number"
                    min="1"
                    step="1"
                    class="quantity-input"
                    value="${item.quantity}"
                    onchange="setQuantity('${id}', this.value)"
                >


                <button
                    type="button"
                    class="quantity-btn"
                    onclick="changeQuantity('${id}', 1)"
                >
                    +
                </button>

            </div>

        `;


        container.appendChild(wrapper);

    });


    updateSummary();

}


/* =================================================================
   CHANGE QUANTITY
================================================================= */

function changeQuantity(id, amount) {

    if (!selectedProducts[id]) {
        return;
    }


    let quantity =
        parseInt(
            selectedProducts[id].quantity,
            10
        ) || 1;


    quantity += amount;


    if (quantity < 1) {
        quantity = 1;
    }


    selectedProducts[id].quantity =
        quantity;


    renderSelectedItems();

}


/* =================================================================
   SET QUANTITY
================================================================= */

function setQuantity(id, value) {

    if (!selectedProducts[id]) {
        return;
    }


    let quantity =
        parseInt(value, 10);


    if (
        isNaN(quantity) ||
        quantity < 1
    ) {

        quantity = 1;

    }


    selectedProducts[id].quantity =
        quantity;


    renderSelectedItems();

}


/* =================================================================
   REMOVE PRODUCT
================================================================= */

function removeProduct(id) {

    delete selectedProducts[id];


    const checkbox =
        document.querySelector(
            `.product-checkbox[data-id="${id}"]`
        );


    if (checkbox) {

        checkbox.checked = false;


        const row =
            checkbox.closest('.product-row');


        if (row) {

            row.classList.remove('selected');

        }

    }


    renderSelectedItems();

}


/* =================================================================
   UPDATE SUMMARY
================================================================= */

function updateSummary() {

    const ids =
        Object.keys(selectedProducts);


    let totalQuantity = 0;


    ids.forEach(function (id) {

        totalQuantity +=
            parseInt(
                selectedProducts[id].quantity,
                10
            ) || 0;

    });


    document.getElementById(
        'selectedCount'
    ).textContent =
        ids.length +
        (
            ids.length === 1
                ? ' item'
                : ' items'
        );


    document.getElementById(
        'summaryProducts'
    ).textContent =
        ids.length;


    document.getElementById(
        'summaryQuantity'
    ).textContent =
        totalQuantity;

}


/* =================================================================
   CLEAR ORDER
================================================================= */

document
    .getElementById('clearOrder')
    .addEventListener(
        'click',
        function () {

            selectedProducts = {};


            document
                .querySelectorAll('.product-checkbox')
                .forEach(function (checkbox) {

                    checkbox.checked = false;


                    const row =
                        checkbox.closest(
                            '.product-row'
                        );


                    if (row) {

                        row.classList.remove(
                            'selected'
                        );

                    }

                });


            renderSelectedItems();

        }
    );


/* =================================================================
   SEARCH PRODUCTS
================================================================= */

document
    .getElementById('productSearch')
    .addEventListener(
        'input',
        function () {

            const search =
                this.value
                    .toLowerCase()
                    .trim();


            document
                .querySelectorAll('.product-row')
                .forEach(function (row) {

                    const text =
                        row.textContent
                            .toLowerCase();


                    row.style.display =
                        text.includes(search)
                            ? 'grid'
                            : 'none';

                });

        }
    );


/* =================================================================
   REVIEW ORDER
================================================================= */

document
    .getElementById('reviewOrder')
    .addEventListener(
        'click',
        function () {

            const ids =
                Object.keys(
                    selectedProducts
                );


            if (ids.length === 0) {

                showMessage(
                    'Please select at least one product.'
                );

                return;

            }


            renderReview();


            document
                .getElementById('reviewModal')
                .classList.add('show');

        }
    );


/* =================================================================
   RENDER REVIEW
================================================================= */

function renderReview() {

    const container =
        document.getElementById(
            'reviewItems'
        );


    container.innerHTML = '';


    const ids =
        Object.keys(selectedProducts);


    let totalQuantity = 0;


    ids.forEach(function (id) {

        const item =
            selectedProducts[id];


        totalQuantity +=
            parseInt(
                item.quantity,
                10
            ) || 0;


        const row =
            document.createElement('tr');


        row.innerHTML = `

            <td>

                <div class="review-product">
                    ${escapeHtml(item.name)}
                </div>

                <div class="review-category">
                    ${escapeHtml(item.category)}
                </div>

            </td>


            <td>
                ${escapeHtml(item.category)}
            </td>


            <td>
                ${escapeHtml(item.unit)}
            </td>


            <td>

                <span class="review-quantity">
                    ${escapeHtml(item.quantity)}
                </span>

            </td>


            <td>

                <button
                    type="button"
                    class="review-remove"
                    onclick="removeFromReview('${id}')"
                >
                    Remove
                </button>

            </td>

        `;


        container.appendChild(row);

    });


    document.getElementById(
        'reviewProductCount'
    ).textContent =
        ids.length;


    document.getElementById(
        'reviewQuantityCount'
    ).textContent =
        totalQuantity;

}


/* =================================================================
   REMOVE FROM REVIEW
================================================================= */

function removeFromReview(id) {

    removeProduct(id);


    const ids =
        Object.keys(
            selectedProducts
        );


    if (ids.length === 0) {

        closeReviewModal();

        return;

    }


    renderReview();

}


/* =================================================================
   CLOSE REVIEW
================================================================= */
document
    .getElementById('confirmOrder')
    .addEventListener(
        'click',
        async function () {

            const ids =
                Object.keys(
                    selectedProducts
                );


            if (ids.length === 0) {

                showMessage(
                    'There are no products in this order.'
                );

                closeReviewModal();

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | Send only the product ID and quantity.
            | The server will obtain the product name and unit
            | directly from the database.
            |--------------------------------------------------------------------------
            */

            const orderItems =
                ids.map(function (id) {

                    const item =
                        selectedProducts[id];


                    return {

                        id:
                            item.id,

                        quantity:
                            parseInt(
                                item.quantity,
                                10
                            ) || 1

                    };

                });


            const confirmButton =
                document.getElementById(
                    'confirmOrder'
                );


            confirmButton.disabled =
                true;


            confirmButton.textContent =
                'Saving Order...';


            try {

                const response =
                    await fetch(
                        'order.php',
                        {

                            method:
                                'POST',

                            headers: {

                                'Content-Type':
                                    'application/json'

                            },

                            body:
                                JSON.stringify({

                                    items:
                                        orderItems

                                })

                        }
                    );


                const result =
                    await response.json();


                if (
                    !response.ok ||
                    !result.success
                ) {

                    throw new Error(
                        result.message ||
                        'The stock order could not be created.'
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | The database has now created the order.
                |
                | IMPORTANT:
                | Use the actual database order ID.
                |--------------------------------------------------------------------------
                */

                if (!result.order_id) {

                    throw new Error(
                        'The order was saved, but no order ID was returned.'
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | Open the approved order using its database ID.
                |--------------------------------------------------------------------------
                */

                window.location.href =
                    'approved_order.php?id=' +
                    encodeURIComponent(
                        result.order_id
                    );

            } catch (error) {

                console.error(
                    'Stock order error:',
                    error
                );


                showMessage(
                    error.message ||
                    'The order could not be saved.'
                );


                confirmButton.disabled =
                    false;


                confirmButton.textContent =
                    '✓ Confirm Order';

            }

        }
    );



/* =================================================================
   ESC KEY
================================================================= */

document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {

            closeReviewModal();

        }

    }
);


/* =================================================================
   MESSAGE
================================================================= */

function showMessage(message) {

    const box =
        document.getElementById(
            'prototypeMessage'
        );


    box.textContent =
        message;


    box.classList.add('show');


    setTimeout(
        function () {

            box.classList.remove('show');

        },
        3500
    );

}


/* =================================================================
   HTML ESCAPE
================================================================= */

function escapeHtml(value) {

    return String(value)

        .replace(
            /&/g,
            '&amp;'
        )

        .replace(
            /</g,
            '&lt;'
        )

        .replace(
            />/g,
            '&gt;'
        )

        .replace(
            /"/g,
            '&quot;'
        )

        .replace(
            /'/g,
            '&#039;'
        );

}

</script>

</body>

</html>
