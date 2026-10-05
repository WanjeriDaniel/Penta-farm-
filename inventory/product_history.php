<?php
/*
|--------------------------------------------------------------------------
| PENTA FARM — PRODUCT HISTORY
|--------------------------------------------------------------------------
| PART 1 — Main Product Movement Ledger
|
| READ ONLY
|
| Shows:
|   - Product selection
|   - Current stock
|   - Total received
|   - Total sold
|   - Returns
|   - Adjustments
|   - Reconciliation
|   - Complete chronological movement trail
|   - Running stock balance
|   - Movement details links
|
| Detailed transaction information is handled by:
|   product_history_details.php
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

function movementIcon($type)
{
    switch ($type) {

        case 'stock_in':
            return '↓';

        case 'sale':
            return '↑';

        case 'return':
            return '↩';

        case 'adjustment':
            return '±';

        default:
            return '•';
    }
}

function movementClass($type, $quantity)
{
    if ($type === 'stock_in') {
        return 'movement-in';
    }

    if ($type === 'sale') {
        return 'movement-sale';
    }

    if ($type === 'return') {
        return 'movement-return';
    }

    if ($type === 'adjustment') {

        return ((float)$quantity >= 0)
            ? 'movement-in'
            : 'movement-sale';
    }

    return '';
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$productId = isset($_GET['product_id'])
    ? (int)$_GET['product_id']
    : 0;

$movementFilter = isset($_GET['movement'])
    ? trim($_GET['movement'])
    : '';

/*
|--------------------------------------------------------------------------
| PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            product_name,
            unit,
            stock_quantity,
            selling_price,
            buying_price,
            low_stock_level
        FROM products
        WHERE is_active = 1
        ORDER BY product_name ASC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $products = [];
}

/*
|--------------------------------------------------------------------------
| SELECTED PRODUCT
|--------------------------------------------------------------------------
*/

$selectedProduct = null;

if ($productId > 0) {

    foreach ($products as $product) {

        if ((int)$product['id'] === $productId) {

            $selectedProduct = $product;

            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| MOVEMENTS
|--------------------------------------------------------------------------
*/

$movements = [];

$totalReceived = 0;
$totalSold = 0;
$totalReturns = 0;
$totalAdjustments = 0;

$ledgerEndingStock = 0;
$openingStock = 0;
$reconciliationDifference = 0;

if ($selectedProduct) {

    /*
    |--------------------------------------------------------------------------
    | LOAD ALL MOVEMENTS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We load every movement before applying the screen filter.
    |
    | This means the running stock balance remains correct even when the
    | user chooses "Sales Only", "Stock Received", etc.
    |
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                product_id,
                movement_type,
                quantity,
                buying_price,
                reference_id,
                notes,
                created_at
            FROM stock_movements
            WHERE product_id = :product_id
            ORDER BY created_at ASC, id ASC
        ");

        $stmt->execute([
            ':product_id' => $productId
        ]);

        $allMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $allMovements = [];
    }

    /*
    |--------------------------------------------------------------------------
    | TOTALS
    |--------------------------------------------------------------------------
    */

    foreach ($allMovements as $movement) {

        $quantity = (float)$movement['quantity'];

        $type = $movement['movement_type'];

        if ($type === 'stock_in') {

            $totalReceived += max(0, $quantity);

        } elseif ($type === 'sale') {

            $totalSold += abs($quantity);

        } elseif ($type === 'return') {

            $totalReturns += max(0, $quantity);

        } elseif ($type === 'adjustment') {

            $totalAdjustments += $quantity;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RUNNING STOCK BALANCE
    |--------------------------------------------------------------------------
    |
    | stock_movements.quantity is already signed:
    |
    | stock_in  = positive
    | sale      = negative
    | return    = positive
    | adjustment = positive OR negative
    |
    |--------------------------------------------------------------------------
    */

    $runningStock = 0;

    foreach ($allMovements as &$movement) {

        $quantity = (float)$movement['quantity'];

        /*
        | Stock immediately BEFORE this movement
        */
        $movement['stock_before'] = $runningStock;

        /*
        | Apply movement
        */
        $runningStock += $quantity;

        /*
        | Stock immediately AFTER this movement
        */
        $movement['stock_after'] = $runningStock;

        /*
        | Keep an explicit signed movement value for display logic.
        */
        $movement['signed_quantity'] = $quantity;
    }

    unset($movement);

    $ledgerEndingStock = $runningStock;

    /*
    |--------------------------------------------------------------------------
    | OPENING STOCK
    |--------------------------------------------------------------------------
    |
    | The current live stock is stored in products.stock_quantity.
    |
    | We derive the stock that existed before the recorded movement ledger:
    |
    | Opening Stock =
    | Live Stock - Net Recorded Movements
    |
    |--------------------------------------------------------------------------
    */

    $liveStock = (float)$selectedProduct['stock_quantity'];

    $openingStock =
        $liveStock - $ledgerEndingStock;

    /*
    |--------------------------------------------------------------------------
    | RECONCILIATION
    |--------------------------------------------------------------------------
    |
    | This should normally be zero because:
    |
    | Opening Stock + Ledger Ending = Live Stock
    |
    |--------------------------------------------------------------------------
    */

    $reconciliationDifference =
        $liveStock -
        ($openingStock + $ledgerEndingStock);

    /*
    |--------------------------------------------------------------------------
    | APPLY DISPLAY FILTER
    |--------------------------------------------------------------------------
    */

    $movements = $allMovements;

    if (
        $movementFilter !== '' &&
        in_array(
            $movementFilter,
            [
                'stock_in',
                'sale',
                'return',
                'adjustment'
            ],
            true
        )
    ) {

        $movements = array_values(
            array_filter(
                $movements,
                function ($movement) use ($movementFilter) {

                    return
                        $movement['movement_type']
                        === $movementFilter;
                }
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NEWEST FIRST FOR DISPLAY
    |--------------------------------------------------------------------------
    */

    $movements = array_reverse($movements);
}

/*
|--------------------------------------------------------------------------
| PRODUCT NAME
|--------------------------------------------------------------------------
*/

$pageProductName = $selectedProduct
    ? $selectedProduct['product_name']
    : 'Product History';

/*
|--------------------------------------------------------------------------
| STOCK STATUS
|--------------------------------------------------------------------------
*/

$stockStatus = '';
$stockStatusClass = '';

if ($selectedProduct) {

    $currentStock =
        (float)$selectedProduct['stock_quantity'];

    $lowStockLevel =
        isset($selectedProduct['low_stock_level'])
            ? (float)$selectedProduct['low_stock_level']
            : 0;

    if ($currentStock <= 0) {

        $stockStatus = 'Out of Stock';
        $stockStatusClass = 'danger';

    } elseif (
        $lowStockLevel > 0 &&
        $currentStock <= $lowStockLevel
    ) {

        $stockStatus = 'Low Stock';
        $stockStatusClass = 'warning';

    } else {

        $stockStatus = 'In Stock';
        $stockStatusClass = 'success';
    }
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
        <?php echo e($pageProductName); ?>
        — Product History | Penta Farm
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
            width: min(1450px, 96%);
            margin: 0 auto;
            padding: 28px 0 50px;
        }

        /* HEADER */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .title-area h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .title-area p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .badge-readonly {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 13px;
            border-radius: 999px;
            background: #e8f1ff;
            color: #1769aa;
            font-size: 12px;
            font-weight: 700;
        }

        /* FILTER */

        .filter-card {
            background: #ffffff;
            border: 1px solid #e4e9f0;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.04);
        }

        .filter-form {
            display: grid;
            grid-template-columns:
                minmax(250px, 1fr)
                220px
                auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
        }

        .field select,
        .field input {
            width: 100%;
            height: 44px;
            border: 1px solid #d8dee8;
            border-radius: 10px;
            padding: 0 13px;
            background: #fff;
            color: #172033;
            outline: none;
            font-size: 14px;
        }

        .field select:focus,
        .field input:focus {
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.08);
        }

        .button {
            height: 44px;
            border: 0;
            border-radius: 10px;
            padding: 0 20px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            background: #2563eb;
            color: white;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .button-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 44px;
            padding: 0 18px;
            border-radius: 10px;
            border: 1px solid #d8dee8;
            background: #fff;
            color: #374151;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        /* SUMMARY */

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #e4e9f0;
            border-radius: 15px;
            padding: 17px;
            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.035);
        }

        .summary-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .summary-value {
            margin-top: 8px;
            font-size: 24px;
            font-weight: 800;
            color: #172033;
        }

        .summary-sub {
            margin-top: 5px;
            color: #8a94a6;
            font-size: 12px;
        }

        /* PRODUCT */

        .product-card {
            background: #ffffff;
            border: 1px solid #e4e9f0;
            border-radius: 17px;
            padding: 21px;
            margin-bottom: 20px;
            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.035);
        }

        .product-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .product-name {
            font-size: 21px;
            font-weight: 800;
            margin: 0;
        }

        .product-meta {
            margin-top: 6px;
            color: #6b7280;
            font-size: 13px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status.success {
            background: #e9f8ef;
            color: #15803d;
        }

        .status.warning {
            background: #fff6df;
            color: #a16207;
        }

        .status.danger {
            background: #feecec;
            color: #b91c1c;
        }

        /* RECONCILIATION */

        .reconciliation {
            display: grid;
            grid-template-columns:
                repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .recon-box {
            padding: 14px;
            border: 1px solid #e5e9ef;
            border-radius: 12px;
            background: #fafbfd;
        }

        .recon-label {
            font-size: 11px;
            color: #737d8d;
            font-weight: 700;
            text-transform: uppercase;
        }

        .recon-value {
            margin-top: 5px;
            font-size: 17px;
            font-weight: 800;
        }

        /* SECTION */

        .section-card {
            background: #ffffff;
            border: 1px solid #e4e9f0;
            border-radius: 17px;
            padding: 21px;
            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.035);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 19px;
        }

        .section-heading span {
            color: #7a8494;
            font-size: 12px;
        }

        /* BALANCE LEGEND */

        .balance-explanation {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #f7f9fc;
            border: 1px solid #e9edf3;
            color: #687385;
            font-size: 12px;
        }

        .balance-explanation strong {
            color: #374151;
        }

        .legend-arrow {
            color: #9aa3b2;
            font-weight: 800;
        }

        /* TIMELINE */

        .timeline {
            position: relative;
        }

        .timeline::before {
            content: "";
            position: absolute;
            left: 18px;
            top: 10px;
            bottom: 10px;
            width: 2px;
            background: #e7ebf1;
        }

        .movement {
            position: relative;
            display: grid;
            grid-template-columns: 38px 1fr auto;
            gap: 15px;
            padding: 0 0 22px;
        }

        .movement:last-child {
            padding-bottom: 0;
        }

        .movement-icon {
            position: relative;
            z-index: 2;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 900;
            font-size: 17px;
            background: #fff;
            border: 2px solid #dce2eb;
        }

        .movement-in .movement-icon {
            color: #15803d;
            border-color: #9dd7af;
            background: #f0fbf4;
        }

        .movement-sale .movement-icon {
            color: #dc2626;
            border-color: #f1aaaa;
            background: #fff4f4;
        }

        .movement-return .movement-icon {
            color: #7c3aed;
            border-color: #cbb8f8;
            background: #f7f2ff;
        }

        .movement-main {
            min-width: 0;
            padding-top: 2px;
        }

        .movement-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .movement-title {
            font-size: 15px;
            font-weight: 800;
        }

        .movement-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            background: #f0f3f7;
            color: #667085;
            text-transform: uppercase;
        }

        .movement-note {
            margin-top: 5px;
            color: #687385;
            font-size: 13px;
        }

        .movement-date {
            color: #8b95a5;
            font-size: 12px;
            white-space: nowrap;
            margin-top: 3px;
        }

        /* RUNNING BALANCE */

        .stock-line {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 11px;
            font-size: 11px;
            color: #7b8493;
        }

        .stock-label {
            font-weight: 700;
            color: #687385;
        }

        .stock-pill {
            padding: 5px 8px;
            border-radius: 6px;
            background: #f4f6f9;
            color: #4b5563;
            font-weight: 800;
        }

        .stock-pill.after {
            background: #eaf2ff;
            color: #1d4ed8;
        }

        .stock-movement {
            font-weight: 900;
            font-size: 12px;
        }

        .movement-sale .stock-movement {
            color: #dc2626;
        }

        .movement-in .stock-movement {
            color: #15803d;
        }

        .movement-return .stock-movement {
            color: #7c3aed;
        }

        /* QUANTITY */

        .movement-quantity {
            text-align: right;
            min-width: 125px;
        }

        .movement-quantity strong {
            display: block;
            font-size: 17px;
        }

        .movement-quantity small {
            display: block;
            margin-top: 4px;
            color: #7b8493;
            font-size: 11px;
        }

        .movement-sale .movement-quantity strong {
            color: #dc2626;
        }

        .movement-in .movement-quantity strong {
            color: #15803d;
        }

        .movement-return .movement-quantity strong {
            color: #7c3aed;
        }

        /* DETAILS */

        .view-details {
            display: inline-flex;
            align-items: center;
            margin-top: 10px;
            padding: 7px 10px;
            border-radius: 7px;
            background: #eef4ff;
            color: #2563eb;
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
        }

        .view-details:hover {
            background: #e0ebff;
        }

        /* EMPTY */

        .empty {
            padding: 55px 20px;
            text-align: center;
            color: #7b8493;
        }

        .empty-icon {
            width: 55px;
            height: 55px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f0f3f7;
            font-size: 23px;
        }

        .empty strong {
            display: block;
            color: #374151;
            font-size: 15px;
            margin-bottom: 5px;
        }

        /* RESPONSIVE */

        @media (max-width: 1100px) {

            .summary-grid {
                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }

            .reconciliation {
                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {

            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .reconciliation {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .movement {
                grid-template-columns: 38px 1fr;
            }

            .movement-quantity {
                grid-column: 2;
                text-align: left;
            }
        }

        @media (max-width: 520px) {

            .page {
                width: 94%;
                padding-top: 18px;
            }

            .summary-grid,
            .reconciliation {
                grid-template-columns: 1fr;
            }

            .product-card-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .section-card,
            .product-card,
            .filter-card {
                padding: 15px;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <!-- HEADER -->

    <div class="header">

        <div class="title-area">

            <h1>
                Product History
            </h1>

            <p>
                Complete stock movement and transaction trail
            </p>

        </div>

        <div class="badge-readonly">
            ● Read Only Ledger
        </div>

    </div>


    <!-- FILTERS -->

    <div class="filter-card">

        <form
            method="GET"
            class="filter-form"
        >

            <div class="field">

                <label>
                    Product
                </label>

                <select
                    name="product_id"
                    required
                    onchange="this.form.submit()"
                >

                    <option value="">
                        Select product...
                    </option>

                    <?php foreach ($products as $product): ?>

                        <option
                            value="<?php echo (int)$product['id']; ?>"
                            <?php
                            echo (
                                $productId ===
                                (int)$product['id']
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo e(
                                $product['product_name']
                            );
                            ?>

                            <?php if (!empty($product['unit'])): ?>

                                —
                                <?php
                                echo e(
                                    $product['unit']
                                );
                                ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="field">

                <label>
                    Movement
                </label>

                <select name="movement">

                    <option value="">
                        All Movements
                    </option>

                    <option
                        value="stock_in"
                        <?php
                        echo $movementFilter === 'stock_in'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Stock Received
                    </option>

                    <option
                        value="sale"
                        <?php
                        echo $movementFilter === 'sale'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Sales
                    </option>

                    <option
                        value="return"
                        <?php
                        echo $movementFilter === 'return'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Returns
                    </option>

                    <option
                        value="adjustment"
                        <?php
                        echo $movementFilter === 'adjustment'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Adjustments
                    </option>

                </select>

            </div>


            <div style="display:flex;gap:8px;">

                <button
                    type="submit"
                    class="button"
                >
                    View History
                </button>

                <?php if ($productId > 0): ?>

                    <a
                        href="product_history.php?product_id=<?php echo $productId; ?>"
                        class="button-secondary"
                    >
                        Reset
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <?php if (!$selectedProduct): ?>

        <!-- EMPTY PRODUCT SELECTION -->

        <div class="section-card">

            <div class="empty">

                <div class="empty-icon">
                    🔎
                </div>

                <strong>
                    Select a product to inspect its history
                </strong>

                <span>
                    The complete stock journey will appear here.
                </span>

            </div>

        </div>

    <?php else: ?>


        <!-- PRODUCT SUMMARY -->

        <div class="product-card">

            <div class="product-card-top">

                <div>

                    <h2 class="product-name">

                        <?php
                        echo e(
                            $selectedProduct['product_name']
                        );
                        ?>

                    </h2>

                    <div class="product-meta">

                        Product ID:
                        <?php
                        echo (int)$selectedProduct['id'];
                        ?>

                        <?php if (!empty($selectedProduct['unit'])): ?>

                            &nbsp; • &nbsp;

                            Unit:
                            <?php
                            echo e(
                                $selectedProduct['unit']
                            );
                            ?>

                        <?php endif; ?>

                    </div>

                </div>


                <div
                    class="status
                    <?php echo e($stockStatusClass); ?>"
                >

                    <?php echo e($stockStatus); ?>

                </div>

            </div>


            <div class="reconciliation">

                <div class="recon-box">

                    <div class="recon-label">
                        Opening Stock
                    </div>

                    <div class="recon-value">
                        <?php echo qty($openingStock); ?>
                    </div>

                </div>


                <div class="recon-box">

                    <div class="recon-label">
                        Ledger Ending
                    </div>

                    <div class="recon-value">
                        <?php echo qty($ledgerEndingStock); ?>
                    </div>

                </div>


                <div class="recon-box">

                    <div class="recon-label">
                        Live Stock
                    </div>

                    <div class="recon-value">
                        <?php
                        echo qty(
                            $selectedProduct['stock_quantity']
                        );
                        ?>
                    </div>

                </div>


                <div class="recon-box">

                    <div class="recon-label">
                        Selling Price
                    </div>

                    <div class="recon-value">
                        KSh
                        <?php
                        echo money(
                            $selectedProduct['selling_price']
                        );
                        ?>
                    </div>

                </div>


                <div class="recon-box">

                    <div class="recon-label">
                        Difference
                    </div>

                    <div
                        class="recon-value"
                        style="
                            color:
                            <?php
                            echo
                                abs(
                                    $reconciliationDifference
                                ) < 0.001
                                    ? '#15803d'
                                    : '#dc2626';
                            ?>
                        "
                    >

                        <?php
                        echo qty(
                            $reconciliationDifference
                        );
                        ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- SUMMARY CARDS -->

        <div class="summary-grid">

            <div class="summary-card">

                <div class="summary-label">
                    Current Stock
                </div>

                <div class="summary-value">
                    <?php
                    echo qty(
                        $selectedProduct['stock_quantity']
                    );
                    ?>
                </div>

                <div class="summary-sub">
                    Live product balance
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Total Received
                </div>

                <div class="summary-value">
                    <?php echo qty($totalReceived); ?>
                </div>

                <div class="summary-sub">
                    All stock-in movements
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Total Sold
                </div>

                <div class="summary-value">
                    <?php echo qty($totalSold); ?>
                </div>

                <div class="summary-sub">
                    All POS sales
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Returns
                </div>

                <div class="summary-value">
                    <?php echo qty($totalReturns); ?>
                </div>

                <div class="summary-sub">
                    Returned quantities
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Adjustments
                </div>

                <div class="summary-value">
                    <?php echo qty($totalAdjustments); ?>
                </div>

                <div class="summary-sub">
                    Net adjustments
                </div>

            </div>

        </div>


        <!-- MOVEMENT TIMELINE -->

        <div class="section-card">

            <div class="section-heading">

                <div>

                    <h2>
                        Movement Timeline
                    </h2>

                    <span>
                        Showing
                        <?php echo count($movements); ?>
                        movement(s)
                    </span>

                </div>

                <?php if ($movementFilter !== ''): ?>

                    <span>
                        Filter:
                        <?php
                        echo e(
                            movementLabel(
                                $movementFilter
                            )
                        );
                        ?>
                    </span>

                <?php endif; ?>

            </div>


            <!-- BALANCE EXPLANATION -->

            <div class="balance-explanation">

                <strong>
                    Stock flow:
                </strong>

                <span>
                    Stock Before
                </span>

                <span class="legend-arrow">
                    →
                </span>

                <span>
                    Movement
                </span>

                <span class="legend-arrow">
                    →
                </span>

                <span>
                    Stock After
                </span>

            </div>


            <?php if (empty($movements)): ?>

                <div class="empty">

                    <div class="empty-icon">
                        📦
                    </div>

                    <strong>
                        No movements found
                    </strong>

                    <span>
                        There are no matching movements
                        for this product.
                    </span>

                </div>

            <?php else: ?>

                <div class="timeline">

                    <?php foreach ($movements as $movement): ?>

                        <?php

                        $type =
                            $movement['movement_type'];

                        $quantity =
                            (float)$movement['quantity'];

                        $movementCss =
                            movementClass(
                                $type,
                                $quantity
                            );

                        $displayQuantity =
                            $quantity > 0
                                ? '+' . qty($quantity)
                                : qty($quantity);

                        $isSale =
                            $type === 'sale';

                        $detailLabel =
                            $isSale
                                ? 'View Transaction Details →'
                                : 'View Movement Details →';

                        ?>

                        <div
                            class="movement
                            <?php echo e($movementCss); ?>"
                        >

                            <!-- ICON -->

                            <div class="movement-icon">

                                <?php
                                echo e(
                                    movementIcon($type)
                                );
                                ?>

                            </div>


                            <!-- MAIN -->

                            <div class="movement-main">

                                <div class="movement-title-row">

                                    <span class="movement-title">

                                        <?php
                                        echo e(
                                            movementLabel($type)
                                        );
                                        ?>

                                    </span>

                                    <span class="movement-tag">

                                        <?php
                                        echo e($type);
                                        ?>

                                    </span>

                                </div>


                                <?php if (
                                    !empty(
                                        $movement['notes']
                                    )
                                ): ?>

                                    <div class="movement-note">

                                        <?php
                                        echo e(
                                            $movement['notes']
                                        );
                                        ?>

                                    </div>

                                <?php endif; ?>


                                <!-- RUNNING BALANCE -->

                                <div class="stock-line">

                                    <span class="stock-label">
                                        Before
                                    </span>

                                    <span class="stock-pill">

                                        <?php
                                        echo qty(
                                            $movement['stock_before']
                                        );
                                        ?>

                                    </span>


                                    <span class="stock-movement">

                                        <?php
                                        echo e(
                                            $displayQuantity
                                        );
                                        ?>

                                    </span>


                                    <span>
                                        →
                                    </span>


                                    <span class="stock-label">
                                        After
                                    </span>

                                    <span
                                        class="stock-pill after"
                                    >

                                        <?php
                                        echo qty(
                                            $movement['stock_after']
                                        );
                                        ?>

                                    </span>

                                </div>


                                <!-- DETAILS LINK -->

                                <a
                                    class="view-details"
                                    href="product_history_details.php?movement_id=<?php echo (int)$movement['id']; ?>"
                                >

                                    <?php
                                    echo e(
                                        $detailLabel
                                    );
                                    ?>

                                </a>

                            </div>


                            <!-- RIGHT SIDE -->

                            <div class="movement-quantity">

                                <strong>

                                    <?php
                                    echo e(
                                        $displayQuantity
                                    );
                                    ?>

                                </strong>

                                <small>

                                    <?php
                                    echo date(
                                        'd M Y, H:i',
                                        strtotime(
                                            $movement['created_at']
                                        )
                                    );
                                    ?>

                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>
