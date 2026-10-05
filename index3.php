<?php
/*
|--------------------------------------------------------------------------
| PENTA FARM — LIVE BUSINESS DASHBOARD
|--------------------------------------------------------------------------
| Live dashboard connected to the Penta Farm database.
|
| Main responsibilities:
| - Monitor today's sales and collections
| - Monitor outstanding credit
| - Monitor gross profit
| - Monitor inventory health
| - Product & Price Check
| - Low / Out-of-Stock alerts
| - 7-day sales performance
| - Recent sales
| - Recent stock/payment activity
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Africa/Nairobi');

require_once "config/database.php";

$errors = [];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function dashboardValue(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null
            ? $value
            : $default;

    } catch (PDOException $e) {

        return $default;
    }
}


function dashboardRows(
    PDO $pdo,
    string $sql,
    array $params = []
): array {

    try {

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        return [];
    }
}


function money($amount): string
{
    return number_format(
        (float)$amount,
        0
    );
}


function clean($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| PRODUCT SEARCH ENDPOINT
|--------------------------------------------------------------------------
| The dashboard itself acts as the Product & Price Check endpoint.
|--------------------------------------------------------------------------
*/

if (isset($_GET['product_search'])) {

    header('Content-Type: application/json');

    $term = trim(
        $_GET['product_search']
    );

    if ($term === '') {

        echo json_encode([]);

        exit;
    }

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                product_name,
                unit,
                stock_quantity,
                buying_price,
                selling_price,
                low_stock_level
            FROM products
            WHERE is_active = 1
              AND product_name LIKE :term
            ORDER BY product_name ASC
            LIMIT 10
        ");

        $stmt->execute([
            ':term' => '%' . $term . '%'
        ]);

        echo json_encode(
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

    } catch (PDOException $e) {

        echo json_encode([]);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| TODAY'S SALES
|--------------------------------------------------------------------------
*/

$todaySales = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(SUM(total_amount), 0)
    FROM sales
    WHERE status = 'completed'
      AND DATE(sale_date) = CURDATE()
    "
);


/*
|--------------------------------------------------------------------------
| TODAY'S TRANSACTIONS
|--------------------------------------------------------------------------
*/

$todayTransactions = (int) dashboardValue(
    $pdo,
    "
    SELECT COUNT(*)
    FROM sales
    WHERE status = 'completed'
      AND DATE(sale_date) = CURDATE()
    "
);


/*
|--------------------------------------------------------------------------
| YESTERDAY'S SALES
|--------------------------------------------------------------------------
*/

$yesterdaySales = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(SUM(total_amount), 0)
    FROM sales
    WHERE status = 'completed'
      AND DATE(sale_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    "
);


/*
|--------------------------------------------------------------------------
| SALES CHANGE
|--------------------------------------------------------------------------
*/

$salesChange = null;

if ($yesterdaySales > 0) {

    $salesChange =
        (($todaySales - $yesterdaySales) / $yesterdaySales)
        * 100;
}


/*
|--------------------------------------------------------------------------
| COLLECTIONS TODAY
|--------------------------------------------------------------------------
|
| Initial payment:
|   sales.amount_paid for sales created today
|
| Additional credit payments:
|   sale_payments recorded today
|
|--------------------------------------------------------------------------
*/

$todayInitialCollections = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM sales
    WHERE status = 'completed'
      AND DATE(sale_date) = CURDATE()
    "
);


$todayAdditionalCollections = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(SUM(amount), 0)
    FROM sale_payments
    WHERE DATE(payment_date) = CURDATE()
    "
);


$collectionsToday =
    $todayInitialCollections
    + $todayAdditionalCollections;


/*
|--------------------------------------------------------------------------
| OUTSTANDING CREDIT
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Do not join sale_payments here.
|
| save_payment.php maintains sales.amount_paid as the current
| cumulative amount paid. Joining payment rows would double count.
|--------------------------------------------------------------------------
*/

$outstanding = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(
        SUM(
            GREATEST(
                total_amount - amount_paid,
                0
            )
        ),
        0
    )
    FROM sales
    WHERE status = 'completed'
      AND sale_type IN ('invoice', 'paylater')
      AND total_amount > amount_paid
    "
);


/*
|--------------------------------------------------------------------------
| GROSS PROFIT TODAY
|--------------------------------------------------------------------------
*/

$grossProfit = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(
        SUM(
            si.subtotal -
            (si.quantity * si.buying_price)
        ),
        0
    )
    FROM sale_items si
    INNER JOIN sales s
        ON s.id = si.sale_id
    WHERE s.status = 'completed'
      AND DATE(s.sale_date) = CURDATE()
      AND si.buying_price > 0
    "
);


/*
|--------------------------------------------------------------------------
| INVENTORY
|--------------------------------------------------------------------------
*/

$totalProducts = (int) dashboardValue(
    $pdo,
    "
    SELECT COUNT(*)
    FROM products
    WHERE is_active = 1
    "
);


$lowStockCount = (int) dashboardValue(
    $pdo,
    "
    SELECT COUNT(*)
    FROM products
    WHERE is_active = 1
      AND stock_quantity > 0
      AND stock_quantity <= low_stock_level
    "
);


$outOfStockCount = (int) dashboardValue(
    $pdo,
    "
    SELECT COUNT(*)
    FROM products
    WHERE is_active = 1
      AND (
          stock_quantity IS NULL
          OR stock_quantity <= 0
      )
    "
);


/*
|--------------------------------------------------------------------------
| STOCK VALUE
|--------------------------------------------------------------------------
*/

$inventoryValue = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(
        SUM(
            COALESCE(stock_quantity, 0)
            *
            COALESCE(buying_price, 0)
        ),
        0
    )
    FROM products
    WHERE is_active = 1
    "
);


/*
|--------------------------------------------------------------------------
| STOCK RECEIVED TODAY
|--------------------------------------------------------------------------
*/

$stockReceivedToday = (float) dashboardValue(
    $pdo,
    "
    SELECT COALESCE(SUM(total_cost), 0)
    FROM stock_receipts
    WHERE status = 'received'
      AND DATE(receipt_date) = CURDATE()
    "
);


/*
|--------------------------------------------------------------------------
| LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

$lowStock = dashboardRows(
    $pdo,
    "
    SELECT
        id,
        product_name,
        unit,
        stock_quantity,
        low_stock_level
    FROM products
    WHERE is_active = 1
      AND stock_quantity > 0
      AND stock_quantity <= low_stock_level
    ORDER BY stock_quantity ASC
    LIMIT 6
    "
);


/*
|--------------------------------------------------------------------------
| OUT OF STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

$outOfStock = dashboardRows(
    $pdo,
    "
    SELECT
        id,
        product_name,
        unit
    FROM products
    WHERE is_active = 1
      AND (
          stock_quantity IS NULL
          OR stock_quantity <= 0
      )
    ORDER BY product_name ASC
    LIMIT 6
    "
);


/*
|--------------------------------------------------------------------------
| RECENT SALES
|--------------------------------------------------------------------------
*/

$recentSales = dashboardRows(
    $pdo,
    "
    SELECT
        id,
        receipt_number,
        customer_name,
        total_amount,
        sale_date
    FROM sales
    WHERE status = 'completed'
    ORDER BY sale_date DESC
    LIMIT 5
    "
);


/*
|--------------------------------------------------------------------------
| PAYMENTS REQUIRING ATTENTION
|--------------------------------------------------------------------------
*/

$payments = dashboardRows(
    $pdo,
    "
    SELECT
        id,
        receipt_number,
        customer_name,
        total_amount,
        amount_paid,
        due_date
    FROM sales
    WHERE status = 'completed'
      AND sale_type IN ('invoice', 'paylater')
      AND total_amount > amount_paid
    ORDER BY
        CASE
            WHEN due_date IS NULL THEN 2
            WHEN due_date < CURDATE() THEN 0
            ELSE 1
        END ASC,
        due_date ASC,
        sale_date ASC
    LIMIT 5
    "
);


/*
|--------------------------------------------------------------------------
| 7-DAY SALES
|--------------------------------------------------------------------------
*/

$sevenDayRows = dashboardRows(
    $pdo,
    "
    SELECT
        DATE(sale_date) AS sale_day,
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE status = 'completed'
      AND DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(sale_date)
    ORDER BY sale_day ASC
    "
);


$sevenDaySales = [];

foreach ($sevenDayRows as $row) {

    $sevenDaySales[
        $row['sale_day']
    ] = (float)$row['total'];
}


$chartValues = [];
$chartDays = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date(
        'Y-m-d',
        strtotime("-{$i} days")
    );

    $chartValues[] =
        $sevenDaySales[$date] ?? 0;

    $chartDays[] =
        $i === 0
            ? 'Today'
            : date('D', strtotime($date));
}


$sevenDayTotal = array_sum(
    $chartValues
);


$chartMax = max(
    $chartValues
);

if ($chartMax <= 0) {
    $chartMax = 1;
}


/*
|--------------------------------------------------------------------------
| RECENT STOCK RECEIPTS
|--------------------------------------------------------------------------
*/

$recentReceipts = dashboardRows(
    $pdo,
    "
    SELECT
        receipt_number,
        supplier_name,
        total_cost,
        receipt_date
    FROM stock_receipts
    WHERE status = 'received'
    ORDER BY receipt_date DESC
    LIMIT 5
    "
);


/*
|--------------------------------------------------------------------------
| RECENT PAYMENTS
|--------------------------------------------------------------------------
*/

$recentPayments = dashboardRows(
    $pdo,
    "
    SELECT
        sale_id,
        amount,
        payment_method,
        payment_date
    FROM sale_payments
    ORDER BY payment_date DESC
    LIMIT 5
    "
);


/*
|--------------------------------------------------------------------------
| RECENT ACTIVITY
|--------------------------------------------------------------------------
*/

$recentActivity = [];


/*
| Sales
*/

foreach ($recentSales as $sale) {

    $recentActivity[] = [
        'timestamp' => strtotime($sale['sale_date']),
        'icon'      => '💰',
        'text'      => 'Sale ' . $sale['receipt_number'] . ' completed',
        'detail'    => 'KSh ' . money($sale['total_amount']),
        'time'      => date('h:i A', strtotime($sale['sale_date']))
    ];
}


/*
| Stock
*/

foreach ($recentReceipts as $receipt) {

    $recentActivity[] = [
        'timestamp' => strtotime($receipt['receipt_date']),
        'icon'      => '📦',
        'text'      => 'Stock received',
        'detail'    =>
            clean($receipt['receipt_number'])
            . ' • KSh '
            . money($receipt['total_cost']),
        'time'      => date(
            'h:i A',
            strtotime($receipt['receipt_date'])
        )
    ];
}


/*
| Payments
*/

foreach ($recentPayments as $payment) {

    $recentActivity[] = [
        'timestamp' => strtotime($payment['payment_date']),
        'icon'      => '💳',
        'text'      => 'Payment recorded',
        'detail'    =>
            'KSh '
            . money($payment['amount'])
            . ' • '
            . clean($payment['payment_method']),
        'time'      => date(
            'h:i A',
            strtotime($payment['payment_date'])
        )
    ];
}


usort(
    $recentActivity,
    function ($a, $b) {
        return $b['timestamp'] <=> $a['timestamp'];
    }
);


$recentActivity = array_slice(
    $recentActivity,
    0,
    5
);


/*
|--------------------------------------------------------------------------
| GREETING
|--------------------------------------------------------------------------
*/

$hour = (int)date('G');

if ($hour < 12) {

    $greeting = 'Good morning';

} elseif ($hour < 17) {

    $greeting = 'Good afternoon';

} else {

    $greeting = 'Good evening';
}


/*
|--------------------------------------------------------------------------
| KPI DATA
|--------------------------------------------------------------------------
*/

$stats = [
    'sales_today'       => $todaySales,
    'collections_today' => $collectionsToday,
    'outstanding'       => $outstanding,
    'gross_profit'      => $grossProfit,
    'products'          => $totalProducts,
    'low_stock'         => $lowStockCount,
    'out_of_stock'      => $outOfStockCount,
    'transactions'      => $todayTransactions
];

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
        Penta Farm Dashboard
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Inter,
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;
            color: #1e293b;

            padding-bottom: 0;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input {
            font-family: inherit;
        }

        /* =========================================================
           APP
        ========================================================= */

        .app {
            min-height: 100vh;
            display: flex;
        }

        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {
            width: 255px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;

            background:
                linear-gradient(
                    180deg,
                    #10396f 0%,
                    #0a274d 100%
                );

            color: white;
            padding: 22px 15px;

            z-index: 50;
        }

        .brand {
            padding: 2px 0 4px;
            text-align: center;
            margin-bottom: 18px;
        }

        .brand-logo {
            display: block;
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin: 0 auto;
        }

        .menu-title {
            margin: 20px 10px 8px;

            font-size: 10px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 1.2px;

            color: #87a5ca;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 11px;

            padding: 10px 12px;
            margin-bottom: 4px;

            border-radius: 9px;

            color: #dbe8f8;

            font-size: 13px;

            transition: .2s;
        }

        .menu-item:hover {
            background:
                rgba(255,255,255,.08);

            color: white;
        }

        .menu-item.active {
            background: #2875bb;
            color: white;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,.12);
        }

        .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .submenu {
            margin-left: 34px;
        }

        .submenu a {
            display: block;

            padding: 7px 8px;

            color: #9eb7d5;

            font-size: 12px;

            border-radius: 6px;
        }

        .submenu a:hover {
            color: white;
            background:
                rgba(255,255,255,.06);
        }

        /* =========================================================
           MAIN
        ========================================================= */

        .main {
            width: calc(100% - 255px);
            margin-left: 255px;
        }

        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {
            height: 70px;

            background: white;

            border-bottom:
                1px solid
                #e5eaf1;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 30px;

            position: sticky;
            top: 0;

            z-index: 30;
        }

        .top-title {
            font-size: 19px;
            font-weight: 750;
            color: #17375f;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .notification {
            position: relative;

            width: 38px;
            height: 38px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f2f5f9;

            cursor: pointer;
        }

        .notification-dot {
            position: absolute;

            top: 7px;
            right: 7px;

            width: 7px;
            height: 7px;

            background: #ef4444;

            border-radius: 50%;
        }

        .admin {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 37px;
            height: 37px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            color: white;
            font-size: 13px;
            font-weight: 700;

            background:
                linear-gradient(
                    135deg,
                    #2563a8,
                    #1596d1
                );
        }

        .admin-text strong {
            display: block;
            font-size: 12px;
        }

        .admin-text span {
            display: block;

            margin-top: 2px;

            font-size: 10px;
            color: #64748b;
        }

        /* =========================================================
           CONTENT
        ========================================================= */

        .content {
            padding: 28px;
        }

        .welcome {
            margin-bottom: 23px;
        }

        .welcome h1 {
            color: #17375f;
            font-size: 25px;
            margin-bottom: 6px;
        }

        .welcome p {
            color: #64748b;
            font-size: 13px;
        }

        /* =========================================================
           KPI CARDS
        ========================================================= */

        .kpis {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 20px;
        }

        .kpi {
            background: white;

            border:
                1px solid
                #e7edf5;

            border-radius: 13px;

            padding: 18px;

            box-shadow:
                0 3px 12px
                rgba(15,23,42,.035);

            transition: .2s;
        }

        .kpi:hover {
            transform: translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(15,23,42,.07);
        }

        .kpi-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .kpi-label {
            font-size: 11px;
            color: #64748b;
        }

        .kpi-value {
            margin-top: 6px;

            font-size: 23px;
            font-weight: 800;

            color: #17375f;
        }

        .kpi-icon {
            width: 40px;
            height: 40px;

            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 18px;
        }

        .kpi-blue {
            background: #e8f2ff;
        }

        .kpi-green {
            background: #e8f8ef;
        }

        .kpi-orange {
            background: #fff4df;
        }

        .kpi-red {
            background: #ffe9e9;
        }

        .kpi-footer {
            margin-top: 12px;

            font-size: 10px;

            color: #64748b;
        }

        .positive {
            color: #159447;
            font-weight: 700;
        }

        .negative {
            color: #dc2626;
            font-weight: 700;
        }

        /* =========================================================
           PRICE CHECK
        ========================================================= */

        .price-check {
            position: relative;

            background:
                linear-gradient(
                    135deg,
                    #123d77,
                    #216aa9
                );

            border-radius: 15px;

            padding: 22px 24px;

            color: white;

            margin-bottom: 20px;

            box-shadow:
                0 8px 25px
                rgba(18,59,115,.16);
        }

        .price-header {
            margin-bottom: 15px;
        }

        .price-header h2 {
            font-size: 17px;
            margin-bottom: 4px;
        }

        .price-header p {
            color: #c6daf0;
            font-size: 11px;
        }

        .search {
            display: flex;

            max-width: 780px;

            background: white;

            border-radius: 9px;

            overflow: hidden;
        }

        .search input {
            flex: 1;

            border: none;
            outline: none;

            padding: 13px 15px;

            font-size: 13px;

            color: #1e293b;
        }

        .search button {
            border: none;

            padding:
                0 22px;

            background: #1787c5;

            color: white;

            font-size: 12px;
            font-weight: 700;

            cursor: pointer;
        }

        .search-results {
            position: absolute;

            left: 24px;
            right: 24px;
            top: 106px;

            max-width: 780px;

            background: white;

            border-radius: 9px;

            overflow: hidden;

            box-shadow:
                0 15px 35px
                rgba(15,23,42,.20);

            display: none;

            z-index: 20;
        }

        .search-result {
            padding: 12px 15px;

            color: #1e293b;

            border-bottom:
                1px solid
                #edf1f6;

            cursor: pointer;
        }

        .search-result:last-child {
            border-bottom: none;
        }

        .search-result:hover {
            background: #f5f9fd;
        }

        .result-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        .result-name {
            font-size: 12px;
            font-weight: 800;
        }

        .result-stock {
            font-size: 10px;
            font-weight: 700;
        }

        .result-bottom {
            display: flex;
            gap: 20px;

            margin-top: 5px;

            font-size: 10px;

            color: #64748b;
        }

        /* =========================================================
           GRID
        ========================================================= */

        .two-column {
            display: grid;

            grid-template-columns:
                1.35fr 1fr;

            gap: 18px;

            margin-bottom: 20px;
        }

        .panel {
            background: white;

            border:
                1px solid
                #e7edf5;

            border-radius: 13px;

            overflow: hidden;

            box-shadow:
                0 3px 12px
                rgba(15,23,42,.03);
        }

        .panel-header {
            display: flex;

            justify-content: space-between;
            align-items: center;

            padding:
                16px 18px;

            border-bottom:
                1px solid
                #edf1f6;
        }

        .panel-header h3 {
            font-size: 14px;
            color: #17375f;
        }

        .panel-link {
            color: #2472ad;
            font-size: 10px;
            font-weight: 700;
        }

        /* =========================================================
           STOCK ALERTS
        ========================================================= */

        .stock-row {
            display: flex;

            align-items: center;
            justify-content: space-between;

            padding:
                12px 18px;

            border-bottom:
                1px solid
                #f0f3f7;

            transition: .2s;
        }

        .stock-row:last-child {
            border-bottom: none;
        }

        .stock-row:hover {
            background: #f8fbff;
        }

        .stock-select {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .stock-checkbox,
        .out-checkbox {
            width: 17px;
            height: 17px;

            accent-color: #2875bb;

            cursor: pointer;

            flex-shrink: 0;
        }

        .stock-product {
            display: flex;
            align-items: center;
            gap: 10px;

            min-width: 0;
        }

        .stock-icon {
            width: 35px;
            height: 35px;

            border-radius: 8px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #fff5e4;

            flex-shrink: 0;
        }

        .stock-name {
            font-size: 12px;
            font-weight: 700;
        }

        .stock-min {
            margin-top: 3px;

            font-size: 9px;
            color: #94a3b8;
        }

        .stock-number {
            text-align: right;

            font-size: 11px;
            font-weight: 800;

            color: #e47a00;

            flex-shrink: 0;
        }

        /* =========================================================
           OUT OF STOCK
        ========================================================= */

        .out-list {
            padding: 4px 18px 8px;
        }

        .out-item {
            display: flex;

            align-items: center;

            gap: 10px;

            padding: 11px 0;

            border-bottom:
                1px solid
                #f0f3f7;
        }

        .out-item:last-child {
            border-bottom: none;
        }

        .out-dot {
            width: 9px;
            height: 9px;

            background: #ef4444;

            border-radius: 50%;

            flex-shrink: 0;
        }

        .out-name {
            font-size: 12px;
            font-weight: 600;
        }

        .empty-state {
            padding: 22px 18px;

            text-align: center;

            color: #94a3b8;

            font-size: 11px;
        }

        /* =========================================================
           SELECTION BAR
        ========================================================= */

        .selection-bar {
            position: fixed;

            left: 255px;
            right: 0;
            bottom: 0;

            min-height: 66px;

            background: white;

            border-top:
                1px solid
                #dfe7f0;

            box-shadow:
                0 -8px 25px
                rgba(15,23,42,.10);

            display: none;

            align-items: center;
            justify-content: space-between;

            padding:
                12px 30px;

            z-index: 100;
        }

        .selection-bar.show {
            display: flex;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .selection-count {
            width: 36px;
            height: 36px;

            border-radius: 9px;

            background: #e8f2ff;

            color: #1765a5;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 13px;
            font-weight: 800;
        }

        .selection-text strong {
            display: block;

            color: #17375f;

            font-size: 12px;
        }

        .selection-text span {
            display: block;

            margin-top: 2px;

            color: #64748b;

            font-size: 10px;
        }

        .selection-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .clear-selection {
            border: none;
            background: transparent;

            color: #64748b;

            padding: 10px 12px;

            font-size: 11px;
            font-weight: 700;

            cursor: pointer;
        }

        .add-order-button {
            border: none;

            background:
                linear-gradient(
                    135deg,
                    #1768a8,
                    #1688c7
                );

            color: white;

            padding:
                11px 18px;

            border-radius: 8px;

            font-size: 11px;
            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 5px 14px
                rgba(23,104,168,.20);

            transition: .2s;
        }

        .add-order-button:hover {
            transform: translateY(-1px);
        }

        /* =========================================================
           SALES CHART
        ========================================================= */

        .chart-wrap {
            padding: 18px;
        }

        .chart-summary {
            display: flex;

            align-items: baseline;

            gap: 8px;

            margin-bottom: 15px;
        }

        .chart-total {
            font-size: 22px;
            font-weight: 800;
            color: #17375f;
        }

        .chart-label {
            color: #64748b;
            font-size: 10px;
        }

        .chart {
            height: 180px;

            display: flex;

            align-items: flex-end;

            gap: 13px;

            border-bottom:
                1px solid
                #e5eaf1;

            padding:
                0 5px;
        }

        .bar-column {
            flex: 1;

            height: 100%;

            display: flex;
            align-items: center;
            justify-content: flex-end;

            flex-direction: column;

            gap: 7px;
        }

        .bar {
            width: 100%;
            max-width: 38px;

            border-radius:
                6px 6px 2px 2px;

            background:
                linear-gradient(
                    180deg,
                    #2685c5,
                    #155b98
                );

            min-height: 2px;
        }

        .bar-label {
            font-size: 9px;
            color: #64748b;
        }

        /* =========================================================
           PAYMENT TABLE
        ========================================================= */

        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-table th {
            text-align: left;

            font-size: 9px;
            font-weight: 700;

            color: #94a3b8;

            padding:
                11px 16px;

            background: #fafbfd;
        }

        .payment-table td {
            padding:
                12px 16px;

            border-top:
                1px solid
                #f0f3f7;

            font-size: 11px;
        }

        .balance {
            font-weight: 800;
            color: #17375f;
        }

        .overdue {
            color: #dc2626;
            font-weight: 700;
        }

        .due {
            color: #d97706;
        }

        /* =========================================================
           RECENT SALES
        ========================================================= */

        .sale-row {
            display: grid;

            grid-template-columns:
                85px 1fr 100px 65px;

            align-items: center;

            padding:
                12px 18px;

            border-bottom:
                1px solid
                #f0f3f7;
        }

        .sale-row:last-child {
            border-bottom: none;
        }

        .receipt {
            font-weight: 800;
            color: #2472ad;
            font-size: 11px;
        }

        .customer {
            font-size: 11px;
        }

        .amount {
            font-size: 11px;
            font-weight: 800;
            text-align: right;
        }

        .time {
            font-size: 9px;
            color: #94a3b8;
            text-align: right;
        }

        /* =========================================================
           ACTIVITY
        ========================================================= */

        .activity-row {
            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                12px 18px;

            border-bottom:
                1px solid
                #f0f3f7;
        }

        .activity-row:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 34px;
            height: 34px;

            background: #edf5ff;

            border-radius: 8px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;
        }

        .activity-main {
            flex: 1;
            min-width: 0;
        }

        .activity-text {
            font-size: 11px;
            font-weight: 650;
        }

        .activity-detail {
            margin-top: 3px;

            font-size: 9px;

            color: #64748b;
        }

        .activity-time {
            font-size: 9px;
            color: #94a3b8;

            white-space: nowrap;
        }

        /* =========================================================
           BOTTOM ACTIONS
        ========================================================= */

        .quick-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

            margin-top: 20px;
        }

        .quick {
            background: white;

            border:
                1px solid
                #e7edf5;

            border-radius: 11px;

            padding: 15px;

            transition: .2s;
        }

        .quick:hover {
            border-color: #2875bb;

            transform:
                translateY(-2px);

            box-shadow:
                0 7px 18px
                rgba(15,23,42,.06);
        }

        .quick-icon {
            font-size: 21px;
            margin-bottom: 8px;
        }

        .quick strong {
            display: block;

            font-size: 11px;
        }

        .quick span {
            display: block;

            margin-top: 4px;

            font-size: 9px;

            color: #64748b;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1150px) {

            .kpis {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .two-column {
                grid-template-columns: 1fr;
            }

            .quick-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }

        @media (max-width: 760px) {

            .sidebar {
                display: none;
            }

            .main {
                width: 100%;
                margin-left: 0;
            }

            .content {
                padding: 18px;
            }

            .topbar {
                padding: 0 18px;
            }

            .kpis {
                grid-template-columns:
                    1fr 1fr;
            }

            .quick-grid {
                grid-template-columns:
                    1fr 1fr;
            }

            .admin-text {
                display: none;
            }

            .sale-row {
                grid-template-columns:
                    65px 1fr 85px;
            }

            .sale-row .time {
                display: none;
            }

            .selection-bar {
                left: 0;

                padding:
                    10px 18px;
            }

        }

        @media (max-width: 480px) {

            .kpis {
                grid-template-columns: 1fr;
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 14px;
            }

            .welcome h1 {
                font-size: 21px;
            }

            .search button {
                padding: 0 14px;
            }

            .selection-bar {
                align-items: stretch;

                flex-direction: column;

                gap: 9px;

                padding:
                    12px 14px;
            }

            .selection-actions {
                width: 100%;
            }

            .clear-selection,
            .add-order-button {
                flex: 1;
            }

        }

    </style>

</head>

<body>

<div class="app">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <aside class="sidebar">

        <div class="brand">

            <img
                src="/penta_farm/assets/penta-farm-logo.png"
                alt="Penta Farm Trading Co. Ltd."
                class="brand-logo"
            >

        </div>


        <div class="menu-title">
            Main
        </div>

        <a
            href="index.php"
            class="menu-item active"
        >
            <span class="menu-icon">🏠</span>
            Dashboard
        </a>


        <div class="menu-title">
            Stock
        </div>

        <a
            href="stock/index.php"
            class="menu-item"
        >
            <span class="menu-icon">📦</span>
            Inventory
        </a>

        <div class="submenu">

            <a href="stock/index.php">
                Inventory
            </a>

            <a href="stock/order.php">
                Stock Orders
            </a>

            <a href="stock/add.php">
                Receive Stock
            </a>

            <a href="stock/index.php?filter=low">
                Low Stock
            </a>

            <a href="stock/index.php?filter=out">
                Out of Stock
            </a>

        </div>


        <div class="menu-title">
            sales
        </div>

        <a
            href="pos/index.php"
            class="menu-item"
        >
            <span class="menu-icon">💰</span>
            Sales
        </a>

        <div class="submenu">

            <a href="pos/index.php">
                POS
            </a>

            <a href="pos/history.php">
                Sales History
            </a>

            <a href="pos/outstanding.php">
                Invoices / Pay Later
            </a>

            <a href="pos/outstanding.php">
                Payments
            </a>

        </div>


        <div class="menu-title">
            Business Intelligence
        </div>

        <!--
        Replace reports.php with the actual BI filename
        if your existing BI file has another filename.
        -->

        <a
            href="reports/index.php"
            class="menu-item"
        >
            <span class="menu-icon">📊</span>
            Reports
        </a>

        <a
            href="pos/outstanding.php"
            class="menu-item"
        >
            <span class="menu-icon">💳</span>
            Credit
        </a>


        <div class="menu-title">
            System
        </div>

        <a
            href="#"
            class="menu-item"
        >
            <span class="menu-icon">⚙️</span>
            Settings
        </a>

    </aside>


    <!-- =========================================================
         MAIN
    ========================================================== -->

    <main class="main">


        <!-- TOP BAR -->

        <header class="topbar">

            <div class="top-title">
                Penta Farm Dashboard
            </div>

            <div class="top-actions">

                <div class="notification">

                    🔔

                    <?php if ($lowStockCount > 0 || $outOfStockCount > 0): ?>

                        <span class="notification-dot"></span>

                    <?php endif; ?>

                </div>

                <div class="admin">

                    <div class="avatar">
                        A
                    </div>

                    <div class="admin-text">

                        <strong>
                            Administrator
                        </strong>

                        <span>
                            System Admin
                        </span>

                    </div>

                </div>

            </div>

        </header>


        <section class="content">


            <!-- WELCOME -->

            <div class="welcome">

                <h1>
                    <?= clean($greeting); ?> 👋
                </h1>

                <p>
                    Here's what's happening at Penta Farm today.
                </p>

            </div>


            <!-- =================================================
                 KPI CARDS
            ================================================== -->

            <div class="kpis">


                <!-- SALES -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Today's Sales
                            </div>

                            <div class="kpi-value">
                                KSh <?= money($stats['sales_today']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-blue">
                            💰
                        </div>

                    </div>

                    <div class="kpi-footer">

                        <?php if ($salesChange !== null): ?>

                            <span class="<?= $salesChange >= 0 ? 'positive' : 'negative'; ?>">

                                <?= $salesChange >= 0 ? '↑' : '↓'; ?>

                                <?= number_format(abs($salesChange), 1); ?>%

                            </span>

                            compared with yesterday

                        <?php else: ?>

                            No sales recorded yesterday

                        <?php endif; ?>

                    </div>

                </div>


                <!-- COLLECTIONS -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Collected Today
                            </div>

                            <div class="kpi-value">
                                KSh <?= money($stats['collections_today']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-green">
                            💵
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Sales + additional credit payments
                    </div>

                </div>


                <!-- OUTSTANDING -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Outstanding
                            </div>

                            <div class="kpi-value">
                                KSh <?= money($stats['outstanding']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-orange">
                            💳
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Invoices + Pay Later
                    </div>

                </div>


                <!-- PROFIT -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Gross Profit
                            </div>

                            <div class="kpi-value">
                                KSh <?= money($stats['gross_profit']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-green">
                            📈
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Based on completed sales with known cost
                    </div>

                </div>


            </div>


            <!-- =================================================
                 SECONDARY STATS
            ================================================== -->

            <div class="kpis">


                <!-- PRODUCTS -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Total Products
                            </div>

                            <div class="kpi-value">
                                <?= number_format($stats['products']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-blue">
                            📦
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Active inventory products
                    </div>

                </div>


                <!-- LOW STOCK -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Low Stock
                            </div>

                            <div class="kpi-value">
                                <?= number_format($stats['low_stock']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-orange">
                            ⚠️
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Products below minimum level
                    </div>

                </div>


                <!-- OUT -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Out of Stock
                            </div>

                            <div class="kpi-value">
                                <?= number_format($stats['out_of_stock']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-red">
                            ❌
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Products currently unavailable
                    </div>

                </div>


                <!-- TRANSACTIONS -->

                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Transactions Today
                            </div>

                            <div class="kpi-value">
                                <?= number_format($stats['transactions']); ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-blue">
                            🧾
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Completed transactions
                    </div>

                </div>


            </div>


            <!-- =================================================
                 PRICE CHECK
            ================================================== -->

            <div class="price-check">

                <div class="price-header">

                    <h2>
                        🔎 Product & Price Check
                    </h2>

                    <p>
                        Quickly find stock quantity, buying price and selling price.
                    </p>

                </div>

                <div class="search">

                    <input
                        type="text"
                        id="productSearch"
                        autocomplete="off"
                        placeholder="Search product name..."
                    >

                    <button
                        type="button"
                        id="searchButton"
                    >
                        Search
                    </button>

                </div>

                <div
                    id="searchResults"
                    class="search-results"
                ></div>

            </div>


            <!-- =================================================
                 INVENTORY ALERTS
            ================================================== -->

            <div class="two-column">


                <!-- LOW STOCK -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            ⚠️ Low Stock
                        </h3>

                        <a
                            href="stock/index.php?filter=low"
                            class="panel-link"
                        >
                            View All
                        </a>

                    </div>


                    <?php if (count($lowStock) > 0): ?>

                        <?php foreach ($lowStock as $item): ?>

                            <div class="stock-row">

                                <div class="stock-select">

                                    <input
                                        type="checkbox"
                                        class="stock-checkbox alert-checkbox"
                                        data-product-id="<?= (int)$item['id']; ?>"
                                        data-product="<?= clean($item['product_name']); ?>"
                                        data-type="low"
                                    >

                                    <div class="stock-product">

                                        <div class="stock-icon">
                                            📦
                                        </div>

                                        <div>

                                            <div class="stock-name">
                                                <?= clean($item['product_name']); ?>
                                            </div>

                                            <div class="stock-min">

                                                Minimum:

                                                <?= money($item['low_stock_level']); ?>

                                                <?= clean($item['unit']); ?>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <div class="stock-number">

                                    <?= money($item['stock_quantity']); ?>

                                    <?= clean($item['unit']); ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            No low-stock products right now.
                        </div>

                    <?php endif; ?>

                </div>


                <!-- OUT OF STOCK -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            ❌ Out of Stock
                        </h3>

                        <a
                            href="stock/index.php?filter=out"
                            class="panel-link"
                        >
                            View All
                        </a>

                    </div>


                    <div class="out-list">

                        <?php if (count($outOfStock) > 0): ?>

                            <?php foreach ($outOfStock as $product): ?>

                                <div class="out-item">

                                    <input
                                        type="checkbox"
                                        class="out-checkbox alert-checkbox"
                                        data-product-id="<?= (int)$product['id']; ?>"
                                        data-product="<?= clean($product['product_name']); ?>"
                                        data-type="out"
                                    >

                                    <span class="out-dot"></span>

                                    <span class="out-name">

                                        <?= clean($product['product_name']); ?>

                                    </span>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="empty-state">
                                No out-of-stock products.
                            </div>

                        <?php endif; ?>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 SALES CHART
            ================================================== -->

            <div
                class="panel"
                style="margin-bottom:20px;"
            >

                <div class="panel-header">

                    <h3>
                        📈 Sales Performance
                    </h3>

                    <a
                        href="pos/history.php"
                        class="panel-link"
                    >
                        View Sales
                    </a>

                </div>


                <div class="chart-wrap">

                    <div class="chart-summary">

                        <span class="chart-total">
                            KSh <?= money($sevenDayTotal); ?>
                        </span>

                        <span class="chart-label">
                            Last 7 days
                        </span>

                    </div>


                    <div class="chart">

                        <?php foreach ($chartValues as $index => $value): ?>

                            <?php

                            $height = $value > 0
                                ? max(
                                    4,
                                    ($value / $chartMax) * 145
                                )
                                : 2;

                            ?>

                            <div class="bar-column">

                                <div
                                    class="bar"
                                    style="height: <?= $height; ?>px;"
                                    title="KSh <?= money($value); ?>"
                                ></div>

                                <div class="bar-label">
                                    <?= clean($chartDays[$index]); ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 PAYMENTS
            ================================================== -->

            <div
                class="panel"
                style="margin-bottom:20px;"
            >

                <div class="panel-header">

                    <h3>
                        💳 Payments Requiring Attention
                    </h3>

                    <a
                        href="pos/outstanding.php"
                        class="panel-link"
                    >
                        View Outstanding
                    </a>

                </div>


                <table class="payment-table">

                    <thead>

                        <tr>

                            <th>
                                CUSTOMER
                            </th>

                            <th>
                                INVOICE
                            </th>

                            <th>
                                BALANCE
                            </th>

                            <th>
                                DUE
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (count($payments) > 0): ?>

                            <?php foreach ($payments as $payment): ?>

                                <?php

                                $balance =
                                    max(
                                        (float)$payment['total_amount']
                                        -
                                        (float)$payment['amount_paid'],
                                        0
                                    );


                                $dueText = 'No due date';
                                $dueClass = 'due';


                                if (!empty($payment['due_date'])) {

                                    $dueDate =
                                        strtotime(
                                            $payment['due_date']
                                        );

                                    $todayStart =
                                        strtotime(
                                            date('Y-m-d')
                                        );


                                    if ($dueDate < $todayStart) {

                                        $dueText = 'Overdue';
                                        $dueClass = 'overdue';

                                    } elseif ($dueDate === $todayStart) {

                                        $dueText = 'Today';

                                    } else {

                                        $days =
                                            (int)(
                                                (
                                                    $dueDate
                                                    -
                                                    $todayStart
                                                )
                                                /
                                                86400
                                            );

                                        if ($days === 1) {

                                            $dueText =
                                                'Tomorrow';

                                        } else {

                                            $dueText =
                                                $days
                                                . ' days';

                                        }

                                    }

                                }

                                ?>

                                <tr>

                                    <td>
                                        <?= clean(
                                            $payment['customer_name']
                                            ?: 'Unnamed Customer'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= clean(
                                            $payment['receipt_number']
                                        ); ?>
                                    </td>

                                    <td class="balance">
                                        KSh <?= money($balance); ?>
                                    </td>

                                    <td class="<?= $dueClass; ?>">
                                        <?= clean($dueText); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="4"
                                    style="
                                        text-align:center;
                                        color:#94a3b8;
                                        padding:20px;
                                    "
                                >
                                    No outstanding payments requiring attention.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 RECENT SALES + ACTIVITY
            ================================================== -->

            <div class="two-column">


                <!-- RECENT SALES -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            🧾 Recent Sales
                        </h3>

                        <a
                            href="pos/history.php"
                            class="panel-link"
                        >
                            View History
                        </a>

                    </div>


                    <?php if (count($recentSales) > 0): ?>

                        <?php foreach ($recentSales as $sale): ?>

                            <a
                                href="pos/view_sale.php?id=<?= (int)$sale['id']; ?>"
                                class="sale-row"
                            >

                                <div class="receipt">

                                    <?= clean(
                                        $sale['receipt_number']
                                    ); ?>

                                </div>

                                <div class="customer">

                                    <?= clean(
                                        $sale['customer_name']
                                        ?: 'Walk-in Customer'
                                    ); ?>

                                </div>

                                <div class="amount">

                                    KSh <?= money(
                                        $sale['total_amount']
                                    ); ?>

                                </div>

                                <div class="time">

                                    <?= date(
                                        'H:i',
                                        strtotime($sale['sale_date'])
                                    ); ?>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            No sales have been recorded yet.
                        </div>

                    <?php endif; ?>

                </div>


                <!-- RECENT ACTIVITY -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            🕐 Recent Activity
                        </h3>

                        <a
                            href="pos/history.php"
                            class="panel-link"
                        >
                            View Activity
                        </a>

                    </div>


                    <?php if (count($recentActivity) > 0): ?>

                        <?php foreach ($recentActivity as $activity): ?>

                            <div class="activity-row">

                                <div class="activity-icon">
                                    <?= $activity['icon']; ?>
                                </div>

                                <div class="activity-main">

                                    <div class="activity-text">
                                        <?= clean(
                                            $activity['text']
                                        ); ?>
                                    </div>

                                    <div class="activity-detail">
                                        <?= clean(
                                            $activity['detail']
                                        ); ?>
                                    </div>

                                </div>

                                <div class="activity-time">

                                    <?= clean(
                                        $activity['time']
                                    ); ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            No recent activity.
                        </div>

                    <?php endif; ?>

                </div>


            </div>


            <!-- =================================================
                 QUICK ACTIONS
            ================================================== -->

            <div
                class="section-title"
                style="
                    margin-top:20px;
                    margin-bottom:12px;
                "
            >

                <h3
                    style="
                        font-size:15px;
                        color:#17375f;
                    "
                >
                    ⚡ Quick Actions
                </h3>

            </div>


            <div class="quick-grid">


                <a
                    href="stock/index.php"
                    class="quick"
                >

                    <div class="quick-icon">
                        📦
                    </div>

                    <strong>
                        Stock Index
                    </strong>

                    <span>
                        View inventory
                    </span>

                </a>


                <a
                    href="stock/add.php"
                    class="quick"
                >

                    <div class="quick-icon">
                        ➕
                    </div>

                    <strong>
                        Receive Stock
                    </strong>

                    <span>
                        Add inventory
                    </span>

                </a>


                <a
                    href="pos/index.php"
                    class="quick"
                >

                    <div class="quick-icon">
                        🛒
                    </div>

                    <strong>
                        Open POS
                    </strong>

                    <span>
                        Create sale
                    </span>

                </a>


                <a
                    href="pos/history.php"
                    class="quick"
                >

                    <div class="quick-icon">
                        📊
                    </div>

                    <strong>
                        Sales Reports
                    </strong>

                    <span>
                        Review business sales
                    </span>

                </a>


            </div>


        </section>

    </main>

</div>


<!-- =============================================================
     SELECTION BAR
============================================================== -->

<div
    id="selectionBar"
    class="selection-bar"
>

    <div class="selection-info">

        <div
            id="selectionCount"
            class="selection-count"
        >
            0
        </div>

        <div class="selection-text">

            <strong>
                Products selected
            </strong>

            <span>
                Ready to add to a stock order
            </span>

        </div>

    </div>


    <div class="selection-actions">

        <button
            type="button"
            id="clearSelection"
            class="clear-selection"
        >
            Clear
        </button>

        <button
            type="button"
            id="addToOrder"
            class="add-order-button"
        >
            🛒 Add to Stock Order
        </button>

    </div>

</div>


<script>

/* ================================================================
   STOCK ALERT SELECTION
================================================================ */

(function () {

    const checkboxes =
        document.querySelectorAll(
            '.alert-checkbox'
        );

    const selectionBar =
        document.getElementById(
            'selectionBar'
        );

    const selectionCount =
        document.getElementById(
            'selectionCount'
        );

    const clearSelection =
        document.getElementById(
            'clearSelection'
        );

    const addToOrder =
        document.getElementById(
            'addToOrder'
        );


    function getSelectedProducts() {

        const selected = [];


        checkboxes.forEach(
            function (checkbox) {

                if (checkbox.checked) {

                    selected.push({

                        id:
                            checkbox.dataset.productId,

                        name:
                            checkbox.dataset.product,

                        type:
                            checkbox.dataset.type

                    });

                }

            }
        );


        return selected;

    }


    function updateSelectionBar() {

        const selected =
            getSelectedProducts();


        selectionCount.textContent =
            selected.length;


        if (selected.length > 0) {

            selectionBar.classList.add(
                'show'
            );

        } else {

            selectionBar.classList.remove(
                'show'
            );

        }

    }


    checkboxes.forEach(
        function (checkbox) {

            checkbox.addEventListener(
                'change',
                updateSelectionBar
            );

        }
    );


    clearSelection.addEventListener(
        'click',
        function () {

            checkboxes.forEach(
                function (checkbox) {

                    checkbox.checked = false;

                }
            );


            updateSelectionBar();

        }
    );


    addToOrder.addEventListener(
        'click',
        function () {

            const selected =
                getSelectedProducts();


            if (selected.length === 0) {

                return;

            }


            /*
            --------------------------------------------------------
            REAL IMPLEMENTATION
            --------------------------------------------------------
            Pass product IDs rather than product names.
            --------------------------------------------------------
            */

            const ids =
                selected.map(
                    function (item) {

                        return item.id;

                    }
                );


            const encodedProducts =
                encodeURIComponent(
                    ids.join('|')
                );


            window.location.href =
                'stock/order.php?from_alerts=1&products=' +
                encodedProducts;

        }
    );

})();


/* ================================================================
   PRODUCT & PRICE CHECK
================================================================ */

(function () {

    const input =
        document.getElementById(
            'productSearch'
        );

    const button =
        document.getElementById(
            'searchButton'
        );

    const results =
        document.getElementById(
            'searchResults'
        );


    let timer = null;


    function hideResults() {

        results.style.display =
            'none';

        results.innerHTML =
            '';

    }


    function searchProducts() {

        const term =
            input.value.trim();


        if (term.length === 0) {

            hideResults();

            return;

        }


        fetch(
            'products/searh.php?product_search=' +
            encodeURIComponent(term),
            {
                headers: {
                    'Accept':
                        'application/json'
                }
            }
        )
        .then(
            function (response) {

                return response.json();

            }
        )
        .then(
            function (products) {

                results.innerHTML =
                    '';


                if (
                    !products ||
                    products.length === 0
                ) {

                    results.innerHTML =
                        '<div class="empty-state">' +
                        'No matching products found.' +
                        '</div>';

                    results.style.display =
                        'block';

                    return;

                }


                products.forEach(
                    function (product) {

                        const row =
                            document.createElement(
                                'div'
                            );

                        row.className =
                            'search-result';


                        const stock =
                            Number(
                                product.stock_quantity || 0
                            );


                        let stockText =
                            stock +
                            ' ' +
                            (
                                product.unit || ''
                            );


                        if (stock <= 0) {

                            stockText =
                                'Out of stock';

                        }


                        row.innerHTML =

                            '<div class="result-top">' +

                                '<span class="result-name">' +

                                    escapeHtml(
                                        product.product_name
                                    ) +

                                '</span>' +

                                '<span class="result-stock">' +

                                    escapeHtml(
                                        stockText
                                    ) +

                                '</span>' +

                            '</div>' +

                            '<div class="result-bottom">' +

                                '<span>' +
                                    'Buying: KSh ' +
                                    formatMoney(
                                        product.buying_price
                                    ) +
                                '</span>' +

                                '<span>' +
                                    'Selling: KSh ' +
                                    formatMoney(
                                        product.selling_price
                                    ) +
                                '</span>' +

                            '</div>';


                        row.addEventListener(
                            'click',
                            function () {

                                input.value =
                                    product.product_name;

                                results.innerHTML =

                                    '<div class="search-result">' +

                                        '<div class="result-top">' +

                                            '<span class="result-name">' +

                                                escapeHtml(
                                                    product.product_name
                                                ) +

                                            '</span>' +

                                            '<span class="result-stock">' +

                                                escapeHtml(
                                                    stockText
                                                ) +

                                            '</span>' +

                                        '</div>' +

                                        '<div class="result-bottom">' +

                                            '<span>' +
                                                'Buying: KSh ' +
                                                formatMoney(
                                                    product.buying_price
                                                ) +
                                            '</span>' +

                                            '<span>' +
                                                'Selling: KSh ' +
                                                formatMoney(
                                                    product.selling_price
                                                ) +
                                            '</span>' +

                                        '</div>' +

                                    '</div>';


                                results.style.display =
                                    'block';

                            }
                        );


                        results.appendChild(
                            row
                        );

                    }
                );


                results.style.display =
                    'block';

            }
        )
        .catch(
            function () {

                results.innerHTML =
                    '<div class="empty-state">' +
                    'Unable to search products.' +
                    '</div>';

                results.style.display =
                    'block';

            }
        );

    }


    function formatMoney(value) {

        return Number(
            value || 0
        ).toLocaleString(
            'en-KE',
            {
                maximumFractionDigits: 0
            }
        );

    }


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


    input.addEventListener(
        'input',
        function () {

            clearTimeout(timer);


            timer =
                setTimeout(
                    searchProducts,
                    250
                );

        }
    );


    button.addEventListener(
        'click',
        searchProducts
    );


    document.addEventListener(
        'click',
        function (event) {

            if (
                !event.target.closest(
                    '.price-check'
                )
            ) {

                hideResults();

            }

        }
    );

})();

</script>

</body>

</html>