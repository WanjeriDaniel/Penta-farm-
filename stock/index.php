<?php

require_once "../config/database.php";


$totalProducts = 0;
$totalStockValue = 0;
$lowStock = 0;
$outOfStock = 0;
$stockReceivedToday = 0;

$approvedOrders = 0;
$receivedOrders = 0;
$cancelledOrders = 0;

$recentReceipts = [];
$recentOrders = [];
$lowStockProducts = [];
$filteredProducts = [];

$stockFilter = $_GET["filter"] ?? "";

$error = null;


try {

    /*
    |--------------------------------------------------------------------------
    | TOTAL ACTIVE PRODUCTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE is_active = 1
    ");

    $totalProducts =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL INVENTORY VALUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COALESCE(
            SUM(
                COALESCE(stock_quantity, 0) *
                COALESCE(buying_price, 0)
            ),
            0
        )
        FROM products
        WHERE is_active = 1
    ");

    $totalStockValue =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | LOW STOCK
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE is_active = 1
        AND stock_quantity > 0
        AND stock_quantity <= low_stock_level
    ");

    $lowStock =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | OUT OF STOCK
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE is_active = 1
        AND (
            stock_quantity IS NULL
            OR stock_quantity <= 0
        )
    ");

    $outOfStock =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | STOCK RECEIVED TODAY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COALESCE(
            SUM(total_cost),
            0
        )
        FROM stock_receipts
        WHERE status = 'received'
        AND DATE(receipt_date) = CURDATE()
    ");

    $stockReceivedToday =
        (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | APPROVED STOCK ORDERS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM stock_orders
        WHERE status = 'approved'
    ");

    $approvedOrders =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | RECEIVED STOCK ORDERS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM stock_orders
        WHERE status = 'received'
    ");

    $receivedOrders =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | CANCELLED STOCK ORDERS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM stock_orders
        WHERE status = 'cancelled'
    ");

    $cancelledOrders =
        (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | RECENT STOCK ORDERS
    |--------------------------------------------------------------------------
    |
    | The item count and total quantity are calculated from
    | stock_order_items.
    |
    */

    $stmt = $pdo->query("
        SELECT
            so.id,
            so.order_number,
            so.supplier_name,
            so.order_date,
            so.status,

            COUNT(soi.id) AS total_products,

            COALESCE(
                SUM(soi.quantity),
                0
            ) AS total_quantity

        FROM stock_orders so

        LEFT JOIN stock_order_items soi
            ON soi.stock_order_id = so.id

        GROUP BY
            so.id,
            so.order_number,
            so.supplier_name,
            so.order_date,
            so.status

        ORDER BY
            so.order_date DESC,
            so.id DESC

        LIMIT 10
    ");

    $recentOrders =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | RECENT STOCK RECEIPTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            receipt_number,
            supplier_name,
            receipt_date,
            total_cost,
            status
        FROM stock_receipts
        ORDER BY
            receipt_date DESC,
            id DESC
        LIMIT 10
    ");

    $recentReceipts =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | LOW STOCK PRODUCTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            product_name,
            unit,
            stock_quantity,
            low_stock_level,
            buying_price
        FROM products
        WHERE is_active = 1
        AND stock_quantity > 0
        AND stock_quantity <= low_stock_level
        ORDER BY
            stock_quantity ASC,
            product_name ASC
        LIMIT 10
    ");

    $lowStockProducts =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | FILTERED STOCK PRODUCTS
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $stockFilter,
            ["low", "out"],
            true
        )
    ) {

        if ($stockFilter === "low") {

            $stmt = $pdo->query("
                SELECT
                    id,
                    product_name,
                    unit,
                    stock_quantity,
                    low_stock_level,
                    buying_price,
                    selling_price
                FROM products
                WHERE is_active = 1
                AND stock_quantity > 0
                AND stock_quantity <= low_stock_level
                ORDER BY
                    stock_quantity ASC,
                    product_name ASC
            ");

        } else {

            $stmt = $pdo->query("
                SELECT
                    id,
                    product_name,
                    unit,
                    stock_quantity,
                    low_stock_level,
                    buying_price,
                    selling_price
                FROM products
                WHERE is_active = 1
                AND (
                    stock_quantity IS NULL
                    OR stock_quantity <= 0
                )
                ORDER BY
                    product_name ASC
            ");

        }


        $filteredProducts =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    }


} catch (PDOException $e) {

    $error =
        "Unable to load Stock Dashboard data.";

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

    <title>Stock Dashboard - Penta Farm</title>


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
            max-width: 1250px;
            margin: 30px auto;
        }


        /* -------------------------------------------------------------
           HEADER
        ------------------------------------------------------------- */

        .header {
            background: linear-gradient(
                135deg,
                #1e3c72,
                #2a5298
            );

            color: white;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 25px;
        }


        .header h1 {
            margin: 0 0 8px;
        }


        .header p {
            margin: 0;
            opacity: 0.9;
        }


        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }


        .button {
            display: inline-block;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 8px;
            font-weight: bold;
        }


        .btn-primary {
            background: white;
            color: #1e3c72;
        }


        .btn-secondary {
            background: rgba(
                255,
                255,
                255,
                0.15
            );

            color: white;

            border: 1px solid
                rgba(
                    255,
                    255,
                    255,
                    0.4
                );
        }


        /* -------------------------------------------------------------
           ERROR
        ------------------------------------------------------------- */

        .error {
            background: #ffe5e5;
            color: #a00000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }


        /* -------------------------------------------------------------
           STAT CARDS
        ------------------------------------------------------------- */

        .cards {
            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 15px;

            margin-bottom: 25px;
        }


        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);
        }


        .stat-card {
            display: block;

            color: inherit;

            text-decoration: none;

            transition:
                transform 0.18s ease,
                box-shadow 0.18s ease;

            cursor: pointer;
        }


        .stat-card:hover {
            transform: translateY(-3px);

            box-shadow:
                0 7px 20px
                rgba(0, 0, 0, 0.12);
        }


        .stat-card:focus {
            outline:
                3px solid
                rgba(42, 82, 152, 0.25);

            outline-offset: 2px;
        }


        .click-hint {
            margin-top: 8px;

            font-size: 12px;

            color: #777;
        }


        .stat-title {
            color: #666;

            font-size: 14px;

            margin-bottom: 8px;
        }


        .stat-number {
            font-size: 25px;

            font-weight: bold;
        }


        .warning {
            color: #b36b00;
            font-weight: bold;
        }


        .danger {
            color: #b00000;
            font-weight: bold;
        }


        .order-blue {
            color: #1e3c72;
        }


        .order-green {
            color: #16702b;
        }


        .order-red {
            color: #a00000;
        }


        /* -------------------------------------------------------------
           FILTER
        ------------------------------------------------------------- */

        .filter-header {
            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 15px;

            flex-wrap: wrap;

            margin-bottom: 15px;
        }


        .filter-header h2 {
            margin: 0;
        }


        .clear-filter {
            display: inline-block;

            text-decoration: none;

            background: #eef2f7;

            color: #1e3c72;

            padding: 8px 12px;

            border-radius: 7px;

            font-size: 13px;

            font-weight: bold;
        }


        .clear-filter:hover {
            background: #e2e8f0;
        }


        .filter-badge {
            display: inline-block;

            margin-left: 6px;

            padding: 4px 9px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;
        }


        .filter-low {
            background: #fff1d6;
            color: #a45d00;
        }


        .filter-out {
            background: #ffe5e5;
            color: #a00000;
        }


        /* -------------------------------------------------------------
           GRID
        ------------------------------------------------------------- */

        .dashboard-grid {
            display: grid;

            grid-template-columns:
                2fr 1fr;

            gap: 20px;

            margin-bottom: 20px;
        }


        .card h2 {
            margin-top: 0;

            font-size: 20px;
        }


        /* -------------------------------------------------------------
           TABLE
        ------------------------------------------------------------- */

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

            border-bottom:
                1px solid #eee;

            text-align: left;
        }


        th {
            background: #f5f6f8;

            font-size: 14px;
        }


        .status {
            padding: 5px 9px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            display: inline-block;
        }


        .status-approved {
            background: #e8f0ff;
            color: #1e3c72;
        }


        .status-received {
            background: #e3f7e8;
            color: #16702b;
        }


        .status-cancelled {
            background: #ffe5e5;
            color: #a00000;
        }


        .received {
            background: #e3f7e8;
            color: #16702b;
        }


        .cancelled {
            background: #ffe5e5;
            color: #a00000;
        }


        .order-action {
            color: #1e3c72;

            font-weight: bold;

            text-decoration: none;
        }


        .order-action:hover {
            text-decoration: underline;
        }


        .empty {
            text-align: center;

            color: #777;

            padding: 25px;
        }


        /* -------------------------------------------------------------
           ORDER INFORMATION
        ------------------------------------------------------------- */

        .order-number {
            font-weight: bold;

            color: #1e3c72;
        }


        .supplier {
            color: #555;

            font-size: 13px;
        }


        .muted {
            color: #777;

            font-size: 13px;
        }


        /* -------------------------------------------------------------
           RESPONSIVE
        ------------------------------------------------------------- */

        @media (max-width: 1100px) {

            .cards {
                grid-template-columns:
                    repeat(3, 1fr);
            }

        }


        @media (max-width: 750px) {

            .cards {
                grid-template-columns:
                    1fr 1fr;
            }


            .dashboard-grid {
                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 500px) {

            .cards {
                grid-template-columns:
                    1fr;
            }


            .container {
                width: 94%;
                margin-top: 15px;
            }


            .header {
                padding: 20px;
            }

        }

    </style>

</head>


<body>

<div class="container">


    <!-- =============================================================
         HEADER
    ============================================================== -->

    <div class="header">

        <h1>
            📦 Stock Dashboard
        </h1>


        <p>
            Monitor inventory, stock levels,
            incoming stock and stock orders.
        </p>


        <div class="actions">

            <a
                href="order.php"
                class="button btn-primary"
            >
                + Create Stock Order
            </a>


            <a
                href="add.php"
                class="button btn-secondary"
            >
                + Receive Stock
            </a>


            <a
                href="history.php"
                class="button btn-secondary"
            >
                📋 Stock History
            </a>

        </div>

    </div>


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =============================================================
         INVENTORY STATISTICS
    ============================================================== -->

    <div class="cards">


        <div class="card">

            <div class="stat-title">
                Active Products
            </div>


            <div class="stat-number">

                <?= number_format(
                    $totalProducts
                ) ?>

            </div>

        </div>


        <div class="card">

            <div class="stat-title">
                Inventory Value
            </div>


            <div class="stat-number">

                KSh
                <?= number_format(
                    $totalStockValue,
                    2
                ) ?>

            </div>

        </div>


        <a
            href="?filter=low"
            class="card stat-card"
            aria-label="Show low stock products"
        >

            <div class="stat-title">
                Low Stock
            </div>


            <div class="stat-number warning">

                <?= number_format(
                    $lowStock
                ) ?>

            </div>


            <div class="click-hint">
                Click to view products →
            </div>

        </a>


        <a
            href="?filter=out"
            class="card stat-card"
            aria-label="Show out of stock products"
        >

            <div class="stat-title">
                Out of Stock
            </div>


            <div class="stat-number danger">

                <?= number_format(
                    $outOfStock
                ) ?>

            </div>


            <div class="click-hint">
                Click to view products →
            </div>

        </a>


        <div class="card">

            <div class="stat-title">
                Received Today
            </div>


            <div class="stat-number">

                KSh
                <?= number_format(
                    $stockReceivedToday,
                    2
                ) ?>

            </div>

        </div>

    </div>


    <!-- =============================================================
         STOCK ORDER STATISTICS
    ============================================================== -->

    <div class="cards">


        <a
            href="#stock-orders"
            class="card stat-card"
        >

            <div class="stat-title">
                Approved Orders
            </div>


            <div class="stat-number order-blue">

                <?= number_format(
                    $approvedOrders
                ) ?>

            </div>


            <div class="click-hint">
                Incoming stock orders
            </div>

        </a>


        <a
            href="#stock-orders"
            class="card stat-card"
        >

            <div class="stat-title">
                Received Orders
            </div>


            <div class="stat-number order-green">

                <?= number_format(
                    $receivedOrders
                ) ?>

            </div>


            <div class="click-hint">
                Completed stock orders
            </div>

        </a>


        <a
            href="#stock-orders"
            class="card stat-card"
        >

            <div class="stat-title">
                Cancelled Orders
            </div>


            <div class="stat-number order-red">

                <?= number_format(
                    $cancelledOrders
                ) ?>

            </div>


            <div class="click-hint">
                Cancelled stock orders
            </div>

        </a>


        <div class="card">

            <div class="stat-title">
                Total Orders
            </div>


            <div class="stat-number">

                <?= number_format(
                    $approvedOrders +
                    $receivedOrders +
                    $cancelledOrders
                ) ?>

            </div>


            <div class="click-hint">
                All stock orders
            </div>

        </div>


        <a
            href="order.php"
            class="card stat-card"
        >

            <div class="stat-title">
                New Stock Order
            </div>


            <div class="stat-number order-blue">
                +
            </div>


            <div class="click-hint">
                Create another order →
            </div>

        </a>

    </div>


    <!-- =============================================================
         FILTERED PRODUCTS
    ============================================================== -->

    <?php if (
        in_array(
            $stockFilter,
            ["low", "out"],
            true
        )
    ): ?>


        <div
            class="card"
            style="margin-bottom: 20px;"
        >


            <div class="filter-header">


                <div>

                    <h2>

                        <?= $stockFilter === "low"
                            ? "⚠️ Low Stock Products"
                            : "🔴 Out of Stock Products"
                        ?>


                        <span
                            class="filter-badge
                            <?= $stockFilter === "low"
                                ? "filter-low"
                                : "filter-out"
                            ?>"
                        >

                            <?= number_format(
                                count(
                                    $filteredProducts
                                )
                            ) ?>

                            products

                        </span>

                    </h2>

                </div>


                <a
                    href="?"
                    class="clear-filter"
                >
                    Show All Products
                </a>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Unit
                            </th>

                            <th>
                                Current Stock
                            </th>

                            <th>
                                Minimum Level
                            </th>

                            <th>
                                Buying Price
                            </th>

                            <th>
                                Selling Price
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        empty(
                            $filteredProducts
                        )
                    ): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty"
                            >
                                No products found
                                in this category.
                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach (
                            $filteredProducts
                            as $product
                        ): ?>


                            <tr>


                                <td>

                                    <?= htmlspecialchars(
                                        $product[
                                            "product_name"
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $product[
                                            "unit"
                                        ] ?? ""
                                    ) ?>

                                </td>


                                <td
                                    class="<?=
                                        $stockFilter === "out"
                                            ? "danger"
                                            : "warning"
                                    ?>"
                                >

                                    <?= number_format(
                                        (float)(
                                            $product[
                                                "stock_quantity"
                                            ] ?? 0
                                        ),
                                        3
                                    ) ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        (float)(
                                            $product[
                                                "low_stock_level"
                                            ] ?? 0
                                        ),
                                        3
                                    ) ?>

                                </td>


                                <td>

                                    KSh
                                    <?= number_format(
                                        (float)(
                                            $product[
                                                "buying_price"
                                            ] ?? 0
                                        ),
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    KSh
                                    <?= number_format(
                                        (float)(
                                            $product[
                                                "selling_price"
                                            ] ?? 0
                                        ),
                                        2
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


    <?php endif; ?>


    <!-- =============================================================
         RECENT STOCK ORDERS
    ============================================================== -->

    <div
        class="card"
        id="stock-orders"
        style="margin-bottom: 20px;"
    >


        <div class="filter-header">

            <div>

                <h2>
                    📋 Recent Stock Orders
                </h2>

                <div class="muted">
                    Orders created through the
                    Stock Order system.
                </div>

            </div>


            <a
                href="order.php"
                class="clear-filter"
            >
                + Create Stock Order
            </a>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Order
                        </th>

                        <th>
                            Supplier
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Products
                        </th>

                        <th>
                            Quantity
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    empty($recentOrders)
                ): ?>


                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >
                            No stock orders have
                            been created yet.
                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $recentOrders
                        as $order
                    ): ?>


                        <?php

                            $status =
                                strtolower(
                                    $order[
                                        "status"
                                    ]
                                );


                            $statusClass =
                                "status-approved";


                            if (
                                $status ===
                                "received"
                            ) {

                                $statusClass =
                                    "status-received";

                            }


                            if (
                                $status ===
                                "cancelled"
                            ) {

                                $statusClass =
                                    "status-cancelled";

                            }

                        ?>


                        <tr>


                            <td>

                                <div
                                    class="order-number"
                                >

                                    <?= htmlspecialchars(
                                        $order[
                                            "order_number"
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <div
                                    class="supplier"
                                >

                                    <?= htmlspecialchars(
                                        $order[
                                            "supplier_name"
                                        ]
                                        ?: "Not Selected"
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <?= date(
                                    "d M Y",
                                    strtotime(
                                        $order[
                                            "order_date"
                                        ]
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= number_format(
                                    (int)(
                                        $order[
                                            "total_products"
                                        ] ?? 0
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= number_format(
                                    (float)(
                                        $order[
                                            "total_quantity"
                                        ] ?? 0
                                    ),
                                    2
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="status
                                    <?= $statusClass ?>"
                                >

                                    <?= ucfirst(
                                        htmlspecialchars(
                                            $status
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="approved_order.php?id=<?= (int)$order["id"] ?>"
                                    class="order-action"
                                >
                                    View
                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


    <!-- =============================================================
         RECENT RECEIPTS + LOW STOCK
    ============================================================== -->

    <div class="dashboard-grid">


        <!-- =========================================================
             RECENT RECEIPTS
        ========================================================== -->

        <div class="card">

            <h2>
                Recent Stock Receipts
            </h2>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Receipt
                            </th>

                            <th>
                                Supplier
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        empty(
                            $recentReceipts
                        )
                    ): ?>


                        <tr>

                            <td
                                colspan="5"
                                class="empty"
                            >
                                No stock receipts yet.
                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach (
                            $recentReceipts
                            as $receipt
                        ): ?>


                            <tr>


                                <td>

                                    <?= htmlspecialchars(
                                        $receipt[
                                            "receipt_number"
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $receipt[
                                            "supplier_name"
                                        ]
                                        ?: "—"
                                    ) ?>

                                </td>


                                <td>

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $receipt[
                                                "receipt_date"
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    KSh
                                    <?= number_format(
                                        (float)(
                                            $receipt[
                                                "total_cost"
                                            ] ?? 0
                                        ),
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="status
                                        <?=
                                            $receipt[
                                                "status"
                                            ] ===
                                            "received"
                                                ? "received"
                                                : "cancelled"
                                        ?>"
                                    >

                                        <?= ucfirst(
                                            htmlspecialchars(
                                                $receipt[
                                                    "status"
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


        <!-- =========================================================
             LOW STOCK
        ========================================================== -->

        <div class="card">

            <h2>
                ⚠️ Low Stock
            </h2>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Stock
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        empty(
                            $lowStockProducts
                        )
                    ): ?>


                        <tr>

                            <td
                                colspan="2"
                                class="empty"
                            >
                                No low-stock products.
                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach (
                            $lowStockProducts
                            as $product
                        ): ?>


                            <tr>


                                <td>

                                    <?= htmlspecialchars(
                                        $product[
                                            "product_name"
                                        ]
                                    ) ?>

                                </td>


                                <td
                                    class="<?=
                                        $product[
                                            "stock_quantity"
                                        ] <= 0
                                            ? "danger"
                                            : "warning"
                                    ?>"
                                >

                                    <?= number_format(
                                        (float)(
                                            $product[
                                                "stock_quantity"
                                            ]
                                        ),
                                        3
                                    ) ?>


                                    <?= htmlspecialchars(
                                        $product[
                                            "unit"
                                        ] ?? ""
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =============================================================
         INVENTORY OVERVIEW
    ============================================================== -->

    <div class="card">

        <h2>
            Inventory Overview
        </h2>


        <p>
            Inventory value is calculated using
            the current buying price multiplied
            by the current stock quantity.
        </p>


        <p>
            Stock received through the
            Receive Stock module is automatically
            reflected in inventory.
        </p>


        <p>
            Approved stock orders are displayed
            as incoming orders and do not increase
            inventory until the goods are physically
            received.
        </p>

    </div>


</div>

</body>

</html>