<?php
/*
|--------------------------------------------------------------------------
| PENTA FARM — PRODUCT HISTORY DETAILS
|--------------------------------------------------------------------------
| PART 2 — Transaction / Movement Investigation
|
| This page is opened from:
|     product_history.php
|
| URL:
|     product_history_details.php?movement_id=123
|
| It follows:
|
| stock_movements
|       ↓
| reference_id
|       ↓
| sales
|       ↓
| sale_items
|
| The page is READ ONLY.
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value)
{
    return number_format((float)$value, 2);
}

function qty($value)
{
    $value = (float)$value;

    if (floor($value) == $value) {
        return number_format($value, 0);
    }

    return number_format($value, 2);
}

function movementLabel($type)
{
    switch ($type) {

        case 'stock_in':
            return 'Stock Received';

        case 'sale':
            return 'Sale';

        case 'return':
            return 'Return';

        case 'adjustment':
            return 'Adjustment';

        default:
            return ucfirst(
                str_replace('_', ' ', $type)
            );
    }
}

function saleTypeLabel($type)
{
    switch ($type) {

        case 'cash':
            return 'Cash Sale';

        case 'invoice':
            return 'Invoice';

        case 'paylater':
            return 'Pay Later';

        default:
            return ucfirst(
                (string)$type
            );
    }
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$movementId = isset($_GET['movement_id'])
    ? (int)$_GET['movement_id']
    : 0;

$error = '';

$movement = null;
$product = null;
$sale = null;
$saleItem = null;

/*
|--------------------------------------------------------------------------
| VALIDATE MOVEMENT ID
|--------------------------------------------------------------------------
*/

if ($movementId <= 0) {

    $error = 'No valid movement was selected.';
}

/*
|--------------------------------------------------------------------------
| LOAD MOVEMENT
|--------------------------------------------------------------------------
*/

if ($movementId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                sm.id,
                sm.product_id,
                sm.movement_type,
                sm.quantity,
                sm.buying_price,
                sm.reference_id,
                sm.notes,
                sm.created_at,

                p.product_name,
                p.unit,
                p.stock_quantity,
                p.buying_price AS current_buying_price,
                p.selling_price

            FROM stock_movements sm

            INNER JOIN products p
                ON p.id = sm.product_id

            WHERE sm.id = :movement_id

            LIMIT 1
        ");

        $stmt->execute([
            ':movement_id' => $movementId
        ]);

        $movement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$movement) {

            $error = 'The selected stock movement could not be found.';
        }

    } catch (Throwable $e) {

        $error = 'Unable to load the selected movement.';
    }
}

/*
|--------------------------------------------------------------------------
| LOAD RELATED SALE
|--------------------------------------------------------------------------
|
| For sales:
|
| stock_movements.reference_id = sales.id
|--------------------------------------------------------------------------
*/

if (
    $movement &&
    $movement['movement_type'] === 'sale' &&
    !empty($movement['reference_id'])
) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                receipt_number,
                sale_type,
                customer_name,
                customer_phone,
                customer_address,
                due_date,
                sale_date,
                subtotal,
                discount,
                fuel_charge,
                total_amount,
                payment_method,
                amount_paid,
                payment_status,
                change_amount,
                status

            FROM sales

            WHERE id = :sale_id

            LIMIT 1
        ");

        $stmt->execute([
            ':sale_id' => (int)$movement['reference_id']
        ]);

        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $sale = null;
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD EXACT PRODUCT SALE ITEM
    |--------------------------------------------------------------------------
    */

    if ($sale) {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    sale_id,
                    product_id,
                    quantity,
                    unit_price,
                    buying_price,
                    subtotal

                FROM sale_items

                WHERE sale_id = :sale_id
                  AND product_id = :product_id

                ORDER BY id ASC

                LIMIT 1
            ");

            $stmt->execute([
                ':sale_id' => (int)$sale['id'],
                ':product_id' => (int)$movement['product_id']
            ]);

            $saleItem = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Throwable $e) {

            $saleItem = null;
        }
    }
}

/*
|--------------------------------------------------------------------------
| STOCK POSITION
|--------------------------------------------------------------------------
|
| The movement itself already tells us how much changed.
|
| For a sale:
|     movement quantity = negative
|
| We calculate a simple before/after position using movements before
| and including the selected movement.
|--------------------------------------------------------------------------
*/

$stockBefore = null;
$stockAfter = null;

if ($movement) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(quantity), 0) AS stock_before

            FROM stock_movements

            WHERE product_id = :product_id

              AND (
                    created_at < :created_at

                    OR (
                        created_at = :created_at
                        AND id < :movement_id
                    )
              )
        ");

        $stmt->execute([
            ':product_id' => (int)$movement['product_id'],
            ':created_at' => $movement['created_at'],
            ':movement_id' => (int)$movement['id']
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $stockBefore = (float)$row['stock_before'];

        $stockAfter =
            $stockBefore +
            (float)$movement['quantity'];

    } catch (Throwable $e) {

        $stockBefore = null;
        $stockAfter = null;
    }
}

/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Movement Details';

if ($movement) {

    $pageTitle =
        movementLabel($movement['movement_type'])
        . ' — '
        . $movement['product_name'];
}

/*
|--------------------------------------------------------------------------
| BALANCE
|--------------------------------------------------------------------------
*/

$balance = 0;

if ($sale) {

    $balance =
        (float)$sale['total_amount']
        -
        (float)$sale['amount_paid'];
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

    <title>
        <?php echo e($pageTitle); ?> | Penta Farm
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #172033;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;
        }

        .page {
            width: min(1250px, 95%);
            margin: 0 auto;
            padding: 28px 0 50px;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }

        .header p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 13px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            padding: 0 16px;
            border-radius: 9px;
            border: 1px solid #d8dee8;
            background: #ffffff;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .back-button:hover {
            background: #f8fafc;
        }

        /*
        |--------------------------------------------------------------------------
        | ALERT
        |--------------------------------------------------------------------------
        */

        .alert {
            background: #fff0f0;
            border: 1px solid #f3c5c5;
            color: #a61b1b;
            padding: 15px 17px;
            border-radius: 11px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        /*
        |--------------------------------------------------------------------------
        | CARDS
        |--------------------------------------------------------------------------
        */

        .card {
            background: #ffffff;
            border: 1px solid #e4e9f0;
            border-radius: 17px;
            padding: 21px;
            margin-bottom: 18px;
            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.035);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .card-header span {
            color: #7b8493;
            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | PRODUCT HERO
        |--------------------------------------------------------------------------
        */

        .product-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .product-name {
            margin: 0;
            font-size: 23px;
            font-weight: 800;
        }

        .product-meta {
            margin-top: 7px;
            color: #6b7280;
            font-size: 13px;
        }

        .movement-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eef4ff;
            color: #2563eb;
            font-size: 12px;
            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | INFORMATION GRID
        |--------------------------------------------------------------------------
        */

        .info-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 12px;
        }

        .info-box {
            border: 1px solid #e7ebf1;
            background: #fafbfd;
            border-radius: 11px;
            padding: 13px;
        }

        .info-label {
            color: #7b8493;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .info-value {
            margin-top: 6px;
            color: #172033;
            font-size: 14px;
            font-weight: 800;
            word-break: break-word;
        }

        /*
        |--------------------------------------------------------------------------
        | STOCK IMPACT
        |--------------------------------------------------------------------------
        */

        .stock-impact {
            display: grid;
            grid-template-columns:
                1fr 80px 1fr;

            align-items: center;
            gap: 14px;
            max-width: 650px;
        }

        .stock-box {
            border: 1px solid #e3e8ef;
            border-radius: 13px;
            padding: 18px;
            background: #fafbfd;
            text-align: center;
        }

        .stock-box small {
            display: block;
            color: #7b8493;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .stock-box strong {
            display: block;
            margin-top: 7px;
            font-size: 28px;
        }

        .stock-arrow {
            text-align: center;
            color: #7b8493;
            font-size: 25px;
            font-weight: 800;
        }

        .movement-quantity {
            margin-top: 8px;
            font-size: 13px;
            color: #dc2626;
            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION HEADER
        |--------------------------------------------------------------------------
        */

        .transaction-banner {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding: 17px;
            border-radius: 12px;
            background: #f7f9fc;
            border: 1px solid #e6ebf1;
            margin-bottom: 18px;
        }

        .receipt-number {
            font-size: 21px;
            font-weight: 900;
            letter-spacing: .3px;
        }

        .sale-type {
            margin-top: 5px;
            color: #687385;
            font-size: 13px;
        }

        .payment-status {
            display: inline-flex;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .payment-paid {
            background: #e9f8ef;
            color: #15803d;
        }

        .payment-partial {
            background: #fff6df;
            color: #a16207;
        }

        .payment-unpaid {
            background: #feecec;
            color: #b91c1c;
        }

        /*
        |--------------------------------------------------------------------------
        | CUSTOMER
        |--------------------------------------------------------------------------
        */

        .customer-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 12px;
        }

        .customer-box {
            padding: 14px;
            border-radius: 11px;
            border: 1px solid #e6ebf1;
            background: #fff;
        }

        .customer-label {
            color: #7b8493;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .customer-value {
            margin-top: 6px;
            font-size: 14px;
            font-weight: 700;
            color: #263044;
            word-break: break-word;
        }

        /*
        |--------------------------------------------------------------------------
        | SALE ITEM TABLE
        |--------------------------------------------------------------------------
        */

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e9ef;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th {
            text-align: left;
            padding: 12px 14px;
            background: #f7f9fc;
            color: #697386;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        td {
            padding: 14px;
            border-top: 1px solid #edf0f4;
            color: #273247;
            font-size: 13px;
        }

        td strong {
            font-weight: 800;
        }

        .number {
            text-align: right;
        }

        /*
        |--------------------------------------------------------------------------
        | FINANCIAL SUMMARY
        |--------------------------------------------------------------------------
        */

        .financial-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 10px;
            max-width: 700px;
        }

        .financial-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
        }

        .financial-row span:first-child {
            color: #6b7280;
        }

        .financial-row strong {
            color: #172033;
        }

        .financial-total {
            padding-top: 14px;
            margin-top: 3px;
            border-top: 2px solid #dfe5ed;
            border-bottom: 0;
            font-size: 16px;
        }

        .balance-due {
            color: #dc2626 !important;
        }

        .balance-clear {
            color: #15803d !important;
        }

        /*
        |--------------------------------------------------------------------------
        | MOVEMENT META
        |--------------------------------------------------------------------------
        */

        .reference-box {
            background: #f7f9fc;
            border: 1px dashed #d7dee8;
            border-radius: 12px;
            padding: 15px;
        }

        .reference-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 7px 0;
            font-size: 13px;
        }

        .reference-row span:first-child {
            color: #737d8d;
        }

        .reference-row strong {
            text-align: right;
            word-break: break-word;
        }

        /*
        |--------------------------------------------------------------------------
        | NO SALE
        |--------------------------------------------------------------------------
        */

        .no-sale {
            padding: 28px;
            border-radius: 12px;
            background: #fff8eb;
            border: 1px solid #f1dfb8;
            color: #8a5b08;
        }

        .no-sale strong {
            display: block;
            margin-bottom: 5px;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 950px) {

            .info-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .customer-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .product-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-grid,
            .customer-grid,
            .financial-grid {
                grid-template-columns: 1fr;
            }

            .transaction-banner {
                flex-direction: column;
            }

            .stock-impact {
                grid-template-columns: 1fr;
            }

            .stock-arrow {
                transform: rotate(90deg);
            }
        }

    </style>

</head>

<body>

<div class="page">

    <!-- ================================================================
         HEADER
    ================================================================= -->

    <div class="header">

        <div>

            <h1>
                Transaction Details
            </h1>

            <p>
                Detailed investigation of a stock movement
            </p>

        </div>

        <a
            href="product_history.php?product_id=<?php
                echo $movement
                    ? (int)$movement['product_id']
                    : 0;
            ?>"
            class="back-button"
        >
            ← Back to Product History
        </a>

    </div>


    <?php if ($error): ?>

        <!-- ============================================================
             ERROR
        ============================================================= -->

        <div class="alert">

            <?php echo e($error); ?>

        </div>

    <?php elseif ($movement): ?>


        <!-- ============================================================
             PRODUCT
        ============================================================= -->

        <div class="card">

            <div class="product-hero">

                <div>

                    <h2 class="product-name">

                        <?php
                        echo e(
                            $movement['product_name']
                        );
                        ?>

                    </h2>

                    <div class="product-meta">

                        Product ID:
                        <?php echo (int)$movement['product_id']; ?>

                        <?php if (!empty($movement['unit'])): ?>

                            &nbsp; • &nbsp;

                            Unit:
                            <?php echo e($movement['unit']); ?>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="movement-badge">

                    <?php
                    echo e(
                        movementLabel(
                            $movement['movement_type']
                        )
                    );
                    ?>

                </div>

            </div>

        </div>


        <!-- ============================================================
             STOCK IMPACT
        ============================================================= -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Stock Impact
                </h2>

                <span>
                    Movement #<?php echo (int)$movement['id']; ?>
                </span>

            </div>


            <div class="stock-impact">

                <div class="stock-box">

                    <small>
                        Stock Before
                    </small>

                    <strong>

                        <?php
                        echo $stockBefore !== null
                            ? qty($stockBefore)
                            : '—';
                        ?>

                    </strong>

                </div>


                <div class="stock-arrow">

                    →

                    <div class="movement-quantity">

                        <?php
                        $movementQty =
                            (float)$movement['quantity'];

                        echo $movementQty > 0
                            ? '+' . qty($movementQty)
                            : qty($movementQty);
                        ?>

                    </div>

                </div>


                <div class="stock-box">

                    <small>
                        Stock After
                    </small>

                    <strong>

                        <?php
                        echo $stockAfter !== null
                            ? qty($stockAfter)
                            : '—';
                        ?>

                    </strong>

                </div>

            </div>

        </div>


        <!-- ============================================================
             MOVEMENT INFORMATION
        ============================================================= -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Movement Information
                </h2>

            </div>


            <div class="info-grid">

                <div class="info-box">

                    <div class="info-label">
                        Movement Type
                    </div>

                    <div class="info-value">

                        <?php
                        echo e(
                            movementLabel(
                                $movement['movement_type']
                            )
                        );
                        ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Quantity
                    </div>

                    <div class="info-value">

                        <?php
                        echo qty(
                            $movement['quantity']
                        );
                        ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Buying Price
                    </div>

                    <div class="info-value">

                        KSh
                        <?php
                        echo money(
                            $movement['buying_price']
                        );
                        ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Movement Date
                    </div>

                    <div class="info-value">

                        <?php
                        echo date(
                            'd M Y, H:i:s',
                            strtotime(
                                $movement['created_at']
                            )
                        );
                        ?>

                    </div>

                </div>

            </div>

        </div>


        <?php if ($sale): ?>

            <!-- ========================================================
                 SALE TRANSACTION
            ========================================================= -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        POS Transaction
                    </h2>

                    <span>
                        Sale ID:
                        <?php echo (int)$sale['id']; ?>
                    </span>

                </div>


                <!-- TRANSACTION BANNER -->

                <div class="transaction-banner">

                    <div>

                        <div class="receipt-number">

                            <?php
                            echo e(
                                $sale['receipt_number']
                                ?: 'No Receipt Number'
                            );
                            ?>

                        </div>

                        <div class="sale-type">

                            <?php
                            echo e(
                                saleTypeLabel(
                                    $sale['sale_type']
                                )
                            );
                            ?>

                            &nbsp; • &nbsp;

                            <?php
                            echo date(
                                'd M Y, H:i:s',
                                strtotime(
                                    $sale['sale_date']
                                )
                            );
                            ?>

                        </div>

                    </div>


                    <?php

                    $paymentStatus =
                        strtolower(
                            (string)$sale['payment_status']
                        );

                    $paymentClass =
                        'payment-unpaid';

                    if ($paymentStatus === 'paid') {
                        $paymentClass = 'payment-paid';
                    } elseif ($paymentStatus === 'partial') {
                        $paymentClass = 'payment-partial';
                    }

                    ?>

                    <div class="
                        payment-status
                        <?php echo e($paymentClass); ?>
                    ">

                        <?php
                        echo e(
                            $sale['payment_status']
                        );
                        ?>

                    </div>

                </div>


                <!-- CUSTOMER -->

                <div class="customer-grid">

                    <div class="customer-box">

                        <div class="customer-label">
                            Customer
                        </div>

                        <div class="customer-value">

                            <?php
                            echo e(
                                $sale['customer_name']
                                ?: 'Walk-in Customer'
                            );
                            ?>

                        </div>

                    </div>


                    <div class="customer-box">

                        <div class="customer-label">
                            Phone
                        </div>

                        <div class="customer-value">

                            <?php
                            echo e(
                                $sale['customer_phone']
                                ?: 'Not provided'
                            );
                            ?>

                        </div>

                    </div>


                    <div class="customer-box">

                        <div class="customer-label">
                            Address
                        </div>

                        <div class="customer-value">

                            <?php
                            echo e(
                                $sale['customer_address']
                                ?: 'Not provided'
                            );
                            ?>

                        </div>

                    </div>


                    <div class="customer-box">

                        <div class="customer-label">
                            Payment Method
                        </div>

                        <div class="customer-value">

                            <?php
                            echo e(
                                $sale['payment_method']
                            );
                            ?>

                        </div>

                    </div>


                    <div class="customer-box">

                        <div class="customer-label">
                            Due Date
                        </div>

                        <div class="customer-value">

                            <?php
                            echo e(
                                $sale['due_date']
                                ?: 'Not applicable'
                            );
                            ?>

                        </div>

                    </div>


                    <div class="customer-box">

                        <div class="customer-label">
                            Sale Status
                        </div>

                        <div class="customer-value">

                            <?php
                            echo e(
                                $sale['status']
                                ?: 'Not specified'
                            );
                            ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ========================================================
                 EXACT PRODUCT SOLD
            ========================================================= -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Product on This Transaction
                    </h2>

                    <span>
                        Exact sale item
                    </span>

                </div>


                <?php if ($saleItem): ?>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th class="number">
                                        Quantity
                                    </th>

                                    <th class="number">
                                        Unit Price
                                    </th>

                                    <th class="number">
                                        Buying Price
                                    </th>

                                    <th class="number">
                                        Subtotal
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <tr>

                                    <td>

                                        <strong>

                                            <?php
                                            echo e(
                                                $movement['product_name']
                                            );
                                            ?>

                                        </strong>

                                    </td>

                                    <td class="number">

                                        <?php
                                        echo qty(
                                            $saleItem['quantity']
                                        );
                                        ?>

                                    </td>

                                    <td class="number">

                                        KSh
                                        <?php
                                        echo money(
                                            $saleItem['unit_price']
                                        );
                                        ?>

                                    </td>

                                    <td class="number">

                                        KSh
                                        <?php
                                        echo money(
                                            $saleItem['buying_price']
                                        );
                                        ?>

                                    </td>

                                    <td class="number">

                                        <strong>

                                            KSh
                                            <?php
                                            echo money(
                                                $saleItem['subtotal']
                                            );
                                            ?>

                                        </strong>

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="no-sale">

                        <strong>
                            Sale item record not found.
                        </strong>

                        The stock movement points to sale
                        #<?php echo (int)$movement['reference_id']; ?>,
                        but the matching product item could not
                        be found in the sale_items table.

                    </div>

                <?php endif; ?>

            </div>


            <!-- ========================================================
                 FINANCIAL SUMMARY
            ========================================================= -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Transaction Financial Summary
                    </h2>

                </div>


                <div class="financial-grid">

                    <div>

                        <div class="financial-row">

                            <span>
                                Subtotal
                            </span>

                            <strong>
                                KSh
                                <?php
                                echo money(
                                    $sale['subtotal']
                                );
                                ?>
                            </strong>

                        </div>


                        <div class="financial-row">

                            <span>
                                Discount
                            </span>

                            <strong>
                                KSh
                                <?php
                                echo money(
                                    $sale['discount']
                                );
                                ?>
                            </strong>

                        </div>


                        <div class="financial-row">

                            <span>
                                Fuel / Delivery
                            </span>

                            <strong>
                                KSh
                                <?php
                                echo money(
                                    $sale['fuel_charge']
                                );
                                ?>
                            </strong>

                        </div>

                    </div>


                    <div>

                        <div class="financial-row">

                            <span>
                                Total Amount
                            </span>

                            <strong>
                                KSh
                                <?php
                                echo money(
                                    $sale['total_amount']
                                );
                            ?>
                            </strong>

                        </div>


                        <div class="financial-row">

                            <span>
                                Amount Paid
                            </span>

                            <strong>
                                KSh
                                <?php
                                echo money(
                                    $sale['amount_paid']
                                );
                            ?>
                            </strong>

                        </div>


                        <div class="
                            financial-row
                            financial-total
                        ">

                            <span>
                                Balance
                            </span>

                            <strong
                                class="<?php
                                    echo $balance > 0
                                        ? 'balance-due'
                                        : 'balance-clear';
                                ?>"
                            >

                                KSh
                                <?php
                                echo money(
                                    max(0, $balance)
                                );
                                ?>

                            </strong>

                        </div>

                    </div>

                </div>

            </div>


        <?php elseif (
            $movement['movement_type'] === 'sale'
        ): ?>

            <!-- ========================================================
                 SALE NOT FOUND
            ========================================================= -->

            <div class="card">

                <div class="no-sale">

                    <strong>
                        Sale transaction could not be loaded.
                    </strong>

                    This movement is marked as a sale and points to
                    sale ID
                    <?php
                    echo (int)$movement['reference_id'];
                    ?>,
                    but the corresponding record could not be found
                    in the sales table.

                </div>

            </div>

        <?php else: ?>

            <!-- ========================================================
                 NON-SALE MOVEMENT
            ========================================================= -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Movement Reference
                    </h2>

                </div>


                <div class="reference-box">

                    <div class="reference-row">

                        <span>
                            Movement Type
                        </span>

                        <strong>

                            <?php
                            echo e(
                                movementLabel(
                                    $movement['movement_type']
                                )
                            );
                            ?>

                        </strong>

                    </div>


                    <div class="reference-row">

                        <span>
                            Reference ID
                        </span>

                        <strong>

                            <?php
                            echo !empty(
                                $movement['reference_id']
                            )
                                ? (int)$movement['reference_id']
                                : 'None';
                            ?>

                        </strong>

                    </div>


                    <div class="reference-row">

                        <span>
                            Notes
                        </span>

                        <strong>

                            <?php
                            echo e(
                                $movement['notes']
                                ?: 'No notes recorded.'
                            );
                            ?>

                        </strong>

                    </div>


                    <div class="reference-row">

                        <span>
                            Recorded At
                        </span>

                        <strong>

                            <?php
                            echo date(
                                'd M Y, H:i:s',
                                strtotime(
                                    $movement['created_at']
                                )
                            );
                            ?>

                        </strong>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- ============================================================
             AUDIT REFERENCE
        ============================================================= -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Audit Reference
                </h2>

                <span>
                    Database record
                </span>

            </div>


            <div class="reference-box">

                <div class="reference-row">

                    <span>
                        Movement ID
                    </span>

                    <strong>
                        #<?php echo (int)$movement['id']; ?>
                    </strong>

                </div>


                <div class="reference-row">

                    <span>
                        Product ID
                    </span>

                    <strong>
                        #<?php echo (int)$movement['product_id']; ?>
                    </strong>

                </div>


                <div class="reference-row">

                    <span>
                        Reference ID
                    </span>

                    <strong>

                        <?php
                        echo !empty(
                            $movement['reference_id']
                        )
                            ? '#' . (int)$movement['reference_id']
                            : 'None';
                        ?>

                    </strong>

                </div>


                <div class="reference-row">

                    <span>
                        Notes
                    </span>

                    <strong>

                        <?php
                        echo e(
                            $movement['notes']
                            ?: 'No notes recorded.'
                        );
                        ?>

                    </strong>

                </div>


                <div class="reference-row">

                    <span>
                        Recorded At
                    </span>

                    <strong>

                        <?php
                        echo date(
                            'd M Y, H:i:s',
                            strtotime(
                                $movement['created_at']
                            )
                        );
                        ?>

                    </strong>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

</body>

</html>

