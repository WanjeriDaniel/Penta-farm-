<?php

require_once "../config/database.php";

$totalProducts = 0;
$totalStockValue = 0;
$lowStock = 0;
$outOfStock = 0;
$stockReceivedToday = 0;
$recentReceipts = [];
$lowStockProducts = [];

try {

    /*
    |--------------------------------------------------------------------------
    | Total Active Products
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM products
        WHERE is_active = 1
    ");

    $totalProducts = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Total Inventory Value
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

    $totalStockValue = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Low Stock
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE is_active = 1
        AND stock_quantity > 0
        AND stock_quantity <= low_stock_level
    ");

    $lowStock = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Out Of Stock
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

    $outOfStock = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Stock Received Today
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

    $stockReceivedToday = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Recent Receipts
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
        ORDER BY receipt_date DESC, id DESC
        LIMIT 10
    ");

    $recentReceipts = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Low Stock Products
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
        AND stock_quantity <= low_stock_level
        ORDER BY stock_quantity ASC, product_name ASC
        LIMIT 10
    ");

    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.4);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
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

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card h2 {
            margin-top: 0;
            font-size: 20px;
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
            background: #f5f6f8;
            font-size: 14px;
        }

        .status {
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .received {
            background: #e3f7e8;
            color: #16702b;
        }

        .cancelled {
            background: #ffe5e5;
            color: #a00000;
        }

        .warning {
            color: #b36b00;
            font-weight: bold;
        }

        .danger {
            color: #b00000;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            color: #777;
            padding: 25px;
        }

        .error {
            background: #ffe5e5;
            color: #a00000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 1050px) {

            .cards {
                grid-template-columns: repeat(3, 1fr);
            }

        }

        @media (max-width: 750px) {

            .cards {
                grid-template-columns: 1fr 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 500px) {

            .cards {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>📦 Stock Dashboard</h1>

        <p>
            Monitor inventory, stock levels and incoming stock.
        </p>

        <div class="actions">

            <a
                href="add.php"
                class="button btn-primary"
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


    <?php if (!empty($error)): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- STATISTICS -->

    <div class="cards">

        <div class="card">

            <div class="stat-title">
                Active Products
            </div>

            <div class="stat-number">
                <?= number_format($totalProducts) ?>
            </div>

        </div>


        <div class="card">

            <div class="stat-title">
                Inventory Value
            </div>

            <div class="stat-number">
                KSh <?= number_format($totalStockValue, 2) ?>
            </div>

        </div>


        <div class="card">

            <div class="stat-title">
                Low Stock
            </div>

            <div class="stat-number warning">
                <?= number_format($lowStock) ?>
            </div>

        </div>


        <div class="card">

            <div class="stat-title">
                Out of Stock
            </div>

            <div class="stat-number danger">
                <?= number_format($outOfStock) ?>
            </div>

        </div>


        <div class="card">

            <div class="stat-title">
                Received Today
            </div>

            <div class="stat-number">
                KSh <?= number_format($stockReceivedToday, 2) ?>
            </div>

        </div>

    </div>


    <!-- RECENT RECEIPTS + LOW STOCK -->

    <div class="dashboard-grid">


        <!-- RECENT RECEIPTS -->

        <div class="card">

            <h2>Recent Stock Receipts</h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>Receipt</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($recentReceipts)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="empty"
                            >
                                No stock receipts yet.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentReceipts as $receipt): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $receipt["receipt_number"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $receipt["supplier_name"]
                                        ?: "—"
                                    ) ?>
                                </td>

                                <td>
                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $receipt["receipt_date"]
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    KSh
                                    <?= number_format(
                                        $receipt["total_cost"],
                                        2
                                    ) ?>
                                </td>

                                <td>

                                    <span
                                        class="status
                                        <?= htmlspecialchars(
                                            $receipt["status"]
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            $receipt["status"]
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


        <!-- LOW STOCK -->

        <div class="card">

            <h2>⚠️ Low Stock</h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>Product</th>
                            <th>Stock</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($lowStockProducts)): ?>

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
                            $lowStockProducts as $product
                        ): ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        $product["product_name"]
                                    ) ?>

                                </td>

                                <td
                                    class="<?=
                                        $product["stock_quantity"] <= 0
                                        ? "danger"
                                        : "warning"
                                    ?>"
                                >

                                    <?= number_format(
                                        (float)$product[
                                            "stock_quantity"
                                        ],
                                        3
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $product["unit"] ?? ""
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


    <!-- INVENTORY SUMMARY -->

    <div class="card">

        <h2>Inventory Overview</h2>

        <p>
            Inventory value is calculated using the current
            buying price multiplied by the current stock quantity.
        </p>

        <p>
            Stock received through the Stock In module is
            automatically reflected in inventory.
        </p>

    </div>

</div>

</body>

</html>