<?php

require_once "../config/database.php";

$today = date("Y-m-d");
$periodStart = date("Y-m-d", strtotime("-29 days"));

$todaySales = 0;
$todayCost = 0;
$todayProfit = 0;
$todayKnownRevenue = 0;
$todayTransactions = 0;

$cashSales = 0;
$mpesaSales = 0;
$bankSales = 0;

$totalInventoryValue = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

$topProducts = [];
$missingCostProducts = [];
$lowStockProducts = [];

$fastMovingProducts = [];
$profitableProducts = [];
$bestOverallProducts = [];
$ownerAlerts = [];

$error = "";

try {

    /*
    |--------------------------------------------------------------------------
    | TODAY'S COMPLETED SALES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COUNT(*) AS transactions,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_method = 'Cash'
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS cash_sales,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_method = 'M-Pesa'
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS mpesa_sales,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_method = 'Bank'
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS bank_sales

        FROM sales

        WHERE status = 'completed'

        AND DATE(sale_date) = :today
    ");

    $stmt->execute([
        ":today" => $today
    ]);

    $salesSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    $todaySales =
        (float)$salesSummary["total_sales"];

    $todayTransactions =
        (int)$salesSummary["transactions"];

    $cashSales =
        (float)$salesSummary["cash_sales"];

    $mpesaSales =
        (float)$salesSummary["mpesa_sales"];

    $bankSales =
        (float)$salesSummary["bank_sales"];


    /*
    |--------------------------------------------------------------------------
    | TODAY'S KNOWN COST AND REVENUE
    |--------------------------------------------------------------------------
    |
    | Buying price <= 0 is treated as missing.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            COALESCE(
                SUM(
                    CASE
                        WHEN si.buying_price > 0
                        THEN si.quantity * si.buying_price
                        ELSE 0
                    END
                ),
                0
            ) AS known_cost,

            COALESCE(
                SUM(
                    CASE
                        WHEN si.buying_price > 0
                        THEN si.subtotal
                        ELSE 0
                    END
                ),
                0
            ) AS known_revenue

        FROM sale_items si

        INNER JOIN sales s
            ON si.sale_id = s.id

        WHERE s.status = 'completed'

        AND DATE(s.sale_date) = :today
    ");

    $stmt->execute([
        ":today" => $today
    ]);

    $costSummary =
        $stmt->fetch(PDO::FETCH_ASSOC);

    $todayCost =
        (float)$costSummary["known_cost"];

    $todayKnownRevenue =
        (float)$costSummary["known_revenue"];

    $todayProfit =
        $todayKnownRevenue -
        $todayCost;


    /*
    |--------------------------------------------------------------------------
    | INVENTORY VALUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COALESCE(
                SUM(
                    stock_quantity *
                    buying_price
                ),
                0
            )

        FROM products

        WHERE is_active = 1
    ");

    $totalInventoryValue =
        (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | LOW STOCK COUNT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)

        FROM products

        WHERE is_active = 1

        AND stock_quantity > 0

        AND stock_quantity <= low_stock_level
    ");

    $lowStockCount =
        (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | OUT OF STOCK COUNT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)

        FROM products

        WHERE is_active = 1

        AND stock_quantity <= 0
    ");

    $outOfStockCount =
        (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TODAY'S PRODUCT PROFIT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            p.id,
            p.product_name,
            p.unit,

            SUM(si.quantity) AS quantity_sold,

            SUM(si.subtotal) AS revenue,

            SUM(
                si.quantity *
                si.buying_price
            ) AS cost,

            SUM(
                si.subtotal -
                (
                    si.quantity *
                    si.buying_price
                )
            ) AS profit

        FROM sale_items si

        INNER JOIN sales s
            ON si.sale_id = s.id

        INNER JOIN products p
            ON si.product_id = p.id

        WHERE s.status = 'completed'

        AND DATE(s.sale_date) = :today

        GROUP BY
            p.id,
            p.product_name,
            p.unit

        HAVING
            MIN(si.buying_price) > 0

        ORDER BY
            profit DESC

        LIMIT 8
    ");

    $stmt->execute([
        ":today" => $today
    ]);

    $topProducts =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | PRODUCTS WITH MISSING BUYING PRICE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            p.product_name,
            p.unit,

            SUM(si.quantity) AS quantity_sold,

            SUM(si.subtotal) AS revenue

        FROM sale_items si

        INNER JOIN sales s
            ON si.sale_id = s.id

        INNER JOIN products p
            ON si.product_id = p.id

        WHERE s.status = 'completed'

        AND DATE(s.sale_date) = :today

        AND si.buying_price <= 0

        GROUP BY
            p.id,
            p.product_name,
            p.unit

        ORDER BY
            revenue DESC

        LIMIT 8
    ");

    $stmt->execute([
        ":today" => $today
    ]);

    $missingCostProducts =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | LOW STOCK PRODUCTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            product_name,
            unit,
            stock_quantity,
            low_stock_level

        FROM products

        WHERE is_active = 1

        AND stock_quantity <= low_stock_level

        ORDER BY
            stock_quantity ASC,
            product_name ASC

        LIMIT 8
    ");

    $lowStockProducts =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | 30-DAY PRODUCT PERFORMANCE
    |--------------------------------------------------------------------------
    |
    | This is the important part.
    |
    | We collect product performance for the last 30 days.
    |
    | Products with missing B.P. are not used for profit calculations.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            p.id,
            p.product_name,
            p.unit,
            p.stock_quantity,
            p.low_stock_level,

            SUM(si.quantity) AS quantity_sold,

            SUM(
                CASE
                    WHEN si.buying_price > 0
                    THEN si.subtotal
                    ELSE 0
                END
            ) AS known_revenue,

            SUM(
                CASE
                    WHEN si.buying_price > 0
                    THEN si.quantity * si.buying_price
                    ELSE 0
                END
            ) AS known_cost,

            SUM(
                CASE
                    WHEN si.buying_price > 0
                    THEN
                        si.subtotal -
                        (
                            si.quantity *
                            si.buying_price
                        )
                    ELSE 0
                END
            ) AS known_profit,

            SUM(
                CASE
                    WHEN si.buying_price <= 0
                    THEN 1
                    ELSE 0
                END
            ) AS missing_cost_lines

        FROM sale_items si

        INNER JOIN sales s
            ON si.sale_id = s.id

        INNER JOIN products p
            ON si.product_id = p.id

        WHERE s.status = 'completed'

        AND DATE(s.sale_date)
            BETWEEN :period_start AND :today

        GROUP BY
            p.id,
            p.product_name,
            p.unit,
            p.stock_quantity,
            p.low_stock_level

        HAVING
            SUM(si.quantity) > 0

        ORDER BY
            quantity_sold DESC
    ");

    $stmt->execute([
        ":period_start" => $periodStart,
        ":today" => $today
    ]);

    $performanceProducts =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | FIND MAXIMUM VALUES
    |--------------------------------------------------------------------------
    |
    | These are used to create a fair performance score.
    |--------------------------------------------------------------------------
    */

    $maxQuantity = 0;
    $maxProfit = 0;
    $maxMargin = 0;

    foreach ($performanceProducts as $product) {

        $quantity =
            (float)$product["quantity_sold"];

        $profit =
            (float)$product["known_profit"];

        $revenue =
            (float)$product["known_revenue"];

        $margin =
            $revenue > 0
            ? ($profit / $revenue) * 100
            : 0;

        if ($quantity > $maxQuantity) {
            $maxQuantity = $quantity;
        }

        if ($profit > $maxProfit) {
            $maxProfit = $profit;
        }

        if ($margin > $maxMargin) {
            $maxMargin = $margin;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BUILD PRODUCT PERFORMANCE DATA
    |--------------------------------------------------------------------------
    */

    foreach ($performanceProducts as &$product) {

        $quantity =
            (float)$product["quantity_sold"];

        $revenue =
            (float)$product["known_revenue"];

        $profit =
            (float)$product["known_profit"];

        $stock =
            (float)$product["stock_quantity"];

        $missingCostLines =
            (int)$product["missing_cost_lines"];


        /*
        |----------------------------------------------------------------------
        | PROFIT MARGIN
        |----------------------------------------------------------------------
        */

        $margin =
            $revenue > 0
            ? ($profit / $revenue) * 100
            : 0;


        /*
        |----------------------------------------------------------------------
        | AVERAGE DAILY SALES
        |----------------------------------------------------------------------
        */

        $averageDailySales =
            $quantity / 30;


        /*
        |----------------------------------------------------------------------
        | ESTIMATED DAYS OF STOCK
        |----------------------------------------------------------------------
        */

        if ($averageDailySales > 0) {

            $daysOfStock =
                $stock / $averageDailySales;

        } else {

            $daysOfStock = 999;

        }


        /*
        |----------------------------------------------------------------------
        | PERFORMANCE SCORE
        |----------------------------------------------------------------------
        |
        | Demand       = 40%
        | Profit       = 30%
        | Margin       = 15%
        | Stock health = 15%
        |--------------------------------------------------------------------------
        */

        $demandScore =
            $maxQuantity > 0
            ? ($quantity / $maxQuantity) * 100
            : 0;

        $profitScore =
            $maxProfit > 0 && $profit > 0
            ? ($profit / $maxProfit) * 100
            : 0;

        $marginScore =
            $maxMargin > 0 && $margin > 0
            ? ($margin / $maxMargin) * 100
            : 0;


        /*
        |----------------------------------------------------------------------
        | STOCK SCORE
        |----------------------------------------------------------------------
        */

        if ($stock <= 0) {

            $stockScore = 0;

        } elseif ($daysOfStock <= 3) {

            $stockScore = 25;

        } elseif ($daysOfStock <= 7) {

            $stockScore = 50;

        } elseif ($daysOfStock <= 14) {

            $stockScore = 75;

        } else {

            $stockScore = 100;

        }


        /*
        |----------------------------------------------------------------------
        | OVERALL SCORE
        |----------------------------------------------------------------------
        */

        $overallScore =
            (
                $demandScore * 0.40
            )
            +
            (
                $profitScore * 0.30
            )
            +
            (
                $marginScore * 0.15
            )
            +
            (
                $stockScore * 0.15
            );


        /*
        |----------------------------------------------------------------------
        | SAVE CALCULATED VALUES
        |----------------------------------------------------------------------
        */

        $product["profit_margin"] =
            $margin;

        $product["average_daily_sales"] =
            $averageDailySales;

        $product["days_of_stock"] =
            $daysOfStock;

        $product["overall_score"] =
            $overallScore;

    }

    unset($product);


    /*
    |--------------------------------------------------------------------------
    | TOP 10 FAST MOVING
    |--------------------------------------------------------------------------
    */

    $fastMovingProducts =
        $performanceProducts;

    usort(
        $fastMovingProducts,
        function ($a, $b) {

            return
                $b["quantity_sold"]
                <=>
                $a["quantity_sold"];

        }
    );

    $fastMovingProducts =
        array_slice(
            $fastMovingProducts,
            0,
            10
        );


    /*
    |--------------------------------------------------------------------------
    | TOP 10 MOST PROFITABLE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Products containing missing B.P. lines are excluded.
    |--------------------------------------------------------------------------
    */

    $profitableProducts =
        array_filter(
            $performanceProducts,
            function ($product) {

                return
                    (int)$product["missing_cost_lines"]
                    === 0
                    &&
                    (float)$product["known_profit"] > 0;

            }
        );

    usort(
        $profitableProducts,
        function ($a, $b) {

            return
                $b["known_profit"]
                <=>
                $a["known_profit"];

        }
    );

    $profitableProducts =
        array_slice(
            $profitableProducts,
            0,
            10
        );


    /*
    |--------------------------------------------------------------------------
    | TOP 10 BEST OVERALL
    |--------------------------------------------------------------------------
    */

    $bestOverallProducts =
        $performanceProducts;

    usort(
        $bestOverallProducts,
        function ($a, $b) {

            return
                $b["overall_score"]
                <=>
                $a["overall_score"];

        }
    );

    $bestOverallProducts =
        array_slice(
            $bestOverallProducts,
            0,
            10
        );


    /*
    |--------------------------------------------------------------------------
    | OWNER DECISION ALERTS
    |--------------------------------------------------------------------------
    */

    foreach ($performanceProducts as $product) {

        $name =
            $product["product_name"];

        $quantity =
            (float)$product["quantity_sold"];

        $profit =
            (float)$product["known_profit"];

        $margin =
            (float)$product["profit_margin"];

        $days =
            (float)$product["days_of_stock"];

        $averageDaily =
            (float)$product["average_daily_sales"];

        $missing =
            (int)$product["missing_cost_lines"];


        /*
        |----------------------------------------------------------------------
        | REORDER ALERT
        |----------------------------------------------------------------------
        */

        if (
            $averageDaily > 0
            &&
            $days <= 3
        ) {

            $ownerAlerts[] = [

                "type" => "danger",

                "icon" => "🚨",

                "title" =>
                    "Reorder Soon",

                "product" =>
                    $name,

                "message" =>
                    "Only about "
                    .
                    number_format(
                        $days,
                        1
                    )
                    .
                    " days of stock remain based on recent sales."

            ];

        }


        /*
        |----------------------------------------------------------------------
        | FAST MOVING + LOW MARGIN
        |----------------------------------------------------------------------
        */

        if (
            $maxQuantity > 0
            &&
            $quantity >= ($maxQuantity * 0.70)
            &&
            $margin < 10
            &&
            $profit > 0
        ) {

            $ownerAlerts[] = [

                "type" => "warning",

                "icon" => "⚡",

                "title" =>
                    "Fast Moving, Low Margin",

                "product" =>
                    $name,

                "message" =>
                    "This product sells quickly but has only "
                    .
                    number_format(
                        $margin,
                        1
                    )
                    .
                    "% profit margin. Consider negotiating a better buying price."

            ];

        }


        /*
        |----------------------------------------------------------------------
        | HIGH PROFIT + LOW SALES
        |----------------------------------------------------------------------
        */

        if (
            $maxProfit > 0
            &&
            $profit >= ($maxProfit * 0.60)
            &&
            $quantity < ($maxQuantity * 0.30)
        ) {

            $ownerAlerts[] = [

                "type" => "opportunity",

                "icon" => "💎",

                "title" =>
                    "High Profit Opportunity",

                "product" =>
                    $name,

                "message" =>
                    "This product generates strong profit but moves slowly. Consider promoting it."

            ];

        }


        /*
        |----------------------------------------------------------------------
        | MISSING BUYING PRICE
        |----------------------------------------------------------------------
        */

        if ($missing > 0) {

            $ownerAlerts[] = [

                "type" => "data",

                "icon" => "⚠️",

                "title" =>
                    "Buying Price Missing",

                "product" =>
                    $name,

                "message" =>
                    "Some sales have no valid B.P. Profit information for this product may be incomplete."

            ];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | LIMIT OWNER ALERTS
    |--------------------------------------------------------------------------
    */

    $ownerAlerts =
        array_slice(
            $ownerAlerts,
            0,
            10
        );


} catch (PDOException $e) {

    $error =
        $e->getMessage();

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

<title>Penta Farm Business Intelligence</title>


<style>

/*
|--------------------------------------------------------------------------
| GLOBAL
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background:
        #f4f6f9;

    color:
        #222;
}

.container {

    width: 96%;

    max-width:
        1450px;

    margin:
        25px auto;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.header {

    background:
        linear-gradient(
            135deg,
            #1e3c72,
            #2a5298
        );

    color:
        white;

    padding:
        25px;

    border-radius:
        14px;

    margin-bottom:
        20px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        15px;
}

.header h1 {

    margin:
        0 0 6px;

}

.header p {

    margin: 0;

    opacity:
        .9;
}

.header-actions {

    display:
        flex;

    gap:
        8px;

    flex-wrap:
        wrap;
}


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.button {

    display:
        inline-block;

    text-decoration:
        none;

    padding:
        10px 15px;

    border-radius:
        8px;

    font-weight:
        bold;
}

.btn-light {

    background:
        white;

    color:
        #1e3c72;
}

.btn-dark {

    background:
        rgba(
            255,
            255,
            255,
            .15
        );

    color:
        white;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .3
        );
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.error {

    background:
        #ffe5e5;

    color:
        #a00000;

    padding:
        15px;

    border-radius:
        8px;

    margin-bottom:
        20px;
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

.stats {

    display:
        grid;

    grid-template-columns:
        repeat(
            4,
            1fr
        );

    gap:
        15px;

    margin-bottom:
        20px;
}

.stat-card {

    background:
        white;

    border-radius:
        14px;

    padding:
        20px;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            .06
        );
}

.stat-icon {

    font-size:
        23px;

    margin-bottom:
        10px;
}

.stat-title {

    color:
        #777;

    font-size:
        13px;

    margin-bottom:
        7px;
}

.stat-value {

    color:
        #1e3c72;

    font-size:
        26px;

    font-weight:
        bold;
}

.stat-note {

    color:
        #999;

    font-size:
        11px;

    margin-top:
        6px;
}


/*
|--------------------------------------------------------------------------
| SECTION TITLE
|--------------------------------------------------------------------------
*/

.section-title {

    margin:
        25px 0 12px;
}

.section-title h2 {

    margin:
        0 0 5px;

    font-size:
        22px;
}

.section-title p {

    margin: 0;

    color:
        #777;

    font-size:
        12px;
}


/*
|--------------------------------------------------------------------------
| OWNER ALERTS
|--------------------------------------------------------------------------
*/

.alert-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            1fr
        );

    gap:
        12px;

    margin-bottom:
        25px;
}

.owner-alert {

    background:
        white;

    border-radius:
        12px;

    padding:
        16px;

    border-left:
        5px solid
        #1e3c72;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            .05
        );
}

.owner-alert.danger {

    border-left-color:
        #c62828;
}

.owner-alert.warning {

    border-left-color:
        #e09b00;
}

.owner-alert.opportunity {

    border-left-color:
        #16803c;
}

.owner-alert.data {

    border-left-color:
        #806000;
}

.alert-title {

    font-weight:
        bold;

    margin-bottom:
        5px;
}

.alert-product {

    color:
        #1e3c72;

    font-weight:
        bold;

    margin-bottom:
        5px;
}

.alert-message {

    color:
        #666;

    font-size:
        12px;

    line-height:
        1.5;
}

.no-alerts {

    background:
        white;

    border-radius:
        12px;

    padding:
        20px;

    color:
        #16803c;

    margin-bottom:
        25px;
}


/*
|--------------------------------------------------------------------------
| INTELLIGENCE GRID
|--------------------------------------------------------------------------
*/

.intelligence-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap:
        18px;

    margin-bottom:
        25px;
}

.intelligence-card {

    background:
        white;

    border-radius:
        14px;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            .06
        );

    overflow:
        hidden;
}

.intelligence-header {

    padding:
        18px;

    border-bottom:
        1px solid
        #eee;
}

.intelligence-header h3 {

    margin:
        0 0 5px;

    font-size:
        17px;
}

.intelligence-header p {

    margin:
        0;

    color:
        #777;

    font-size:
        11px;
}

.intelligence-body {

    padding:
        12px 18px;
}


/*
|--------------------------------------------------------------------------
| PRODUCT INTELLIGENCE ROW
|--------------------------------------------------------------------------
*/

.intel-row {

    display:
        grid;

    grid-template-columns:
        30px
        1fr
        auto;

    gap:
        10px;

    align-items:
        center;

    padding:
        13px 0;

    border-bottom:
        1px solid
        #eee;
}

.intel-row:last-child {

    border-bottom:
        none;
}

.rank {

    font-weight:
        bold;

    color:
        #777;
}

.intel-name {

    font-weight:
        bold;

    color:
        #1e3c72;
}

.intel-unit {

    font-size:
        10px;

    color:
        #999;

    margin-top:
        3px;
}

.intel-value {

    text-align:
        right;

    font-weight:
        bold;

    white-space:
        nowrap;
}

.intel-small {

    font-size:
        10px;

    color:
        #777;

    margin-top:
        3px;

    font-weight:
        normal;
}


/*
|--------------------------------------------------------------------------
| SCORE
|--------------------------------------------------------------------------
*/

.score {

    width:
        48px;

    height:
        48px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #eef3fa;

    color:
        #1e3c72;

    font-weight:
        bold;

    font-size:
        13px;
}


/*
|--------------------------------------------------------------------------
| FINANCIAL SUMMARY
|--------------------------------------------------------------------------
*/

.profit-card {

    background:
        white;

    border-radius:
        14px;

    padding:
        20px;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            .06
        );

    margin-bottom:
        20px;
}

.profit-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap:
        15px;
}

.profit-box {

    background:
        #f7f9fb;

    padding:
        17px;

    border-radius:
        10px;
}

.profit-label {

    color:
        #777;

    font-size:
        12px;

    margin-bottom:
        6px;
}

.profit-value {

    color:
        #1e3c72;

    font-size:
        20px;

    font-weight:
        bold;
}

.profit-note {

    margin-top:
        12px;

    padding:
        10px;

    background:
        #fff8e6;

    border:
        1px solid
        #f0d98a;

    color:
        #806000;

    border-radius:
        8px;

    font-size:
        11px;
}


/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

.payment-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap:
        15px;

    margin-bottom:
        25px;
}

.payment-card {

    background:
        white;

    border-radius:
        14px;

    padding:
        20px;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            .06
        );
}

.payment-card h3 {

    margin:
        0 0 10px;

    font-size:
        15px;
}

.payment-value {

    color:
        #1e3c72;

    font-size:
        22px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| LOWER DASHBOARD
|--------------------------------------------------------------------------
*/

.dashboard-grid {

    display:
        grid;

    grid-template-columns:
        1.5fr 1fr;

    gap:
        20px;
}

.card {

    background:
        white;

    border-radius:
        14px;

    box-shadow:
        0 4px 15px
        rgba(
            0,
            0,
            0,
            .06
        );

    overflow:
        hidden;
}

.card-header {

    padding:
        20px;

    border-bottom:
        1px solid
        #eee;
}

.card-header h2 {

    margin:
        0 0 5px;

    font-size:
        19px;
}

.card-header p {

    margin:
        0;

    color:
        #777;

    font-size:
        12px;
}

.card-body {

    padding:
        20px;
}


/*
|--------------------------------------------------------------------------
| TODAY PRODUCT ROW
|--------------------------------------------------------------------------
*/

.product-row {

    display:
        grid;

    grid-template-columns:
        1.4fr
        auto
        auto
        auto
        auto;

    align-items:
        center;

    gap:
        15px;

    padding:
        14px 0;

    border-bottom:
        1px solid
        #eee;
}

.product-labels {

    display:
        grid;

    grid-template-columns:
        1.4fr
        auto
        auto
        auto
        auto;

    gap:
        15px;

    padding:
        0 0 10px;

    border-bottom:
        2px solid
        #eee;

    color:
        #888;

    font-size:
        11px;

    font-weight:
        bold;
}

.product-labels div:not(:first-child) {

    text-align:
        right;
}

.product-name {

    font-weight:
        bold;

    color:
        #1e3c72;
}

.product-unit {

    color:
        #888;

    font-size:
        11px;

    margin-top:
        3px;
}

.product-qty,
.product-revenue,
.product-cost,
.product-profit {

    white-space:
        nowrap;
}

.product-qty {

    font-weight:
        bold;
}

.product-revenue,
.product-cost,
.product-profit {

    text-align:
        right;
}

.product-revenue {

    font-weight:
        bold;
}

.product-cost {

    color:
        #777;
}

.product-profit {

    color:
        #16803c;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| MISSING COST
|--------------------------------------------------------------------------
*/

.missing-cost {

    margin-top:
        20px;

    border:
        1px solid
        #f0d98a;

    background:
        #fffdf5;

    border-radius:
        10px;

    overflow:
        hidden;
}

.missing-cost-header {

    padding:
        14px;

    background:
        #fff8e6;

    border-bottom:
        1px solid
        #f0d98a;
}

.missing-cost-header h3 {

    margin:
        0 0 4px;

    color:
        #806000;

    font-size:
        15px;
}

.missing-cost-header p {

    margin:
        0;

    color:
        #8c7330;

    font-size:
        11px;
}

.missing-cost-row {

    display:
        grid;

    grid-template-columns:
        1.5fr
        auto
        auto
        auto;

    gap:
        15px;

    align-items:
        center;

    padding:
        12px 14px;

    border-bottom:
        1px solid
        #f0ead7;
}

.missing-cost-row:last-child {

    border-bottom:
        none;
}

.missing-name {

    font-weight:
        bold;

    color:
        #665000;
}

.missing-unit {

    color:
        #8c7a4a;

    font-size:
        11px;

    margin-top:
        3px;
}

.missing-qty,
.missing-revenue {

    font-weight:
        bold;

    white-space:
        nowrap;
}

.missing-status {

    color:
        #a56a00;

    font-size:
        11px;

    font-weight:
        bold;

    text-align:
        right;

    white-space:
        nowrap;
}


/*
|--------------------------------------------------------------------------
| LOW STOCK
|--------------------------------------------------------------------------
*/

.low-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        12px;

    padding:
        13px 0;

    border-bottom:
        1px solid
        #eee;
}

.low-row:last-child {

    border-bottom:
        none;
}

.low-name {

    font-weight:
        bold;
}

.low-level {

    color:
        #777;

    font-size:
        11px;

    margin-top:
        3px;
}

.stock-number {

    font-weight:
        bold;

    text-align:
        right;
}

.stock-low {

    color:
        #a56a00;
}

.stock-out {

    color:
        #a00000;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    text-align:
        center;

    padding:
        35px 10px;

    color:
        #888;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1100px) {

    .intelligence-grid {

        grid-template-columns:
            1fr;

    }

    .stats {

        grid-template-columns:
            repeat(
                2,
                1fr
            );

    }

    .dashboard-grid {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 800px) {

    .alert-grid {

        grid-template-columns:
            1fr;

    }

    .profit-grid {

        grid-template-columns:
            1fr;

    }

    .payment-grid {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 700px) {

    .header {

        flex-direction:
            column;

        align-items:
            flex-start;

    }

    .product-labels {

        display:
            none;

    }

    .product-row {

        grid-template-columns:
            1fr 1fr;

        gap:
            8px;

    }

    .product-revenue,
    .product-cost,
    .product-profit {

        text-align:
            left;

    }

    .missing-cost-row {

        grid-template-columns:
            1fr 1fr;

        gap:
            8px;

    }

    .missing-status {

        text-align:
            left;

    }

}


@media (max-width: 500px) {

    .stats {

        grid-template-columns:
            1fr;

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
                🧠 Penta Farm Business Intelligence
            </h1>

            <p>
                Sales, profit, stock and product performance
                for management decisions
            </p>

        </div>


        <div class="header-actions">

            <a
                href="../pos/index.php"
                class="button btn-light"
            >
                🛒 POS
            </a>

            <a
                href="../stock/index.php"
                class="button btn-dark"
            >
                📦 Stock
            </a>

            <a
                href="../index.php"
                class="button btn-dark"
            >
                🏠 Dashboard
            </a>

        </div>

    </div>


    <!-- =========================================================
         ERROR
    ========================================================== -->

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         TODAY'S STATISTICS
    ========================================================== -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>

            <div class="stat-title">
                Today's Sales
            </div>

            <div class="stat-value">

                KSh
                <?= number_format(
                    $todaySales,
                    2
                ) ?>

            </div>

            <div class="stat-note">

                <?= number_format(
                    $todayTransactions
                ) ?>

                transactions

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                📈
            </div>

            <div class="stat-title">
                Today's Gross Profit
            </div>

            <div class="stat-value">

                KSh
                <?= number_format(
                    $todayProfit,
                    2
                ) ?>

            </div>

            <div class="stat-note">

                Known buying prices only

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                📦
            </div>

            <div class="stat-title">
                Inventory Value
            </div>

            <div class="stat-value">

                KSh
                <?= number_format(
                    $totalInventoryValue,
                    2
                ) ?>

            </div>

            <div class="stat-note">

                Current stock × buying price

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ⚠️
            </div>

            <div class="stat-title">
                Stock Alerts
            </div>

            <div class="stat-value">

                <?= number_format(
                    $lowStockCount +
                    $outOfStockCount
                ) ?>

            </div>

            <div class="stat-note">

                <?= $lowStockCount ?>
                low stock •
                <?= $outOfStockCount ?>
                out of stock

            </div>

        </div>


    </div>


    <!-- =========================================================
         OWNER DECISION CENTER
    ========================================================== -->

    <div class="section-title">

        <h2>
            🧠 Owner Decision Center
        </h2>

        <p>
            Automatic recommendations based on the last
            30 days of sales activity.
        </p>

    </div>


    <?php if (empty($ownerAlerts)): ?>

        <div class="no-alerts">

            ✅

            No major product decisions require attention
            based on the current 30-day data.

        </div>

    <?php else: ?>


        <div class="alert-grid">

            <?php foreach (
                $ownerAlerts
                as $alert
            ): ?>

                <div
                    class="
                        owner-alert
                        <?= htmlspecialchars(
                            $alert["type"]
                        ) ?>
                    "
                >

                    <div class="alert-title">

                        <?= htmlspecialchars(
                            $alert["icon"]
                        ) ?>

                        <?= htmlspecialchars(
                            $alert["title"]
                        ) ?>

                    </div>


                    <div class="alert-product">

                        <?= htmlspecialchars(
                            $alert["product"]
                        ) ?>

                    </div>


                    <div class="alert-message">

                        <?= htmlspecialchars(
                            $alert["message"]
                        ) ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         PRODUCT INTELLIGENCE
    ========================================================== -->

    <div class="section-title">

        <h2>
            📊 Product Intelligence — Last 30 Days
        </h2>

        <p>
            The system monitors demand, profit and margin
            to identify products that matter most.
        </p>

    </div>


    <div class="intelligence-grid">


        <!-- =====================================================
             FAST MOVING
        ====================================================== -->

        <div class="intelligence-card">

            <div class="intelligence-header">

                <h3>
                    🔥 Top 10 Fast-Moving
                </h3>

                <p>
                    Ranked by quantity sold
                </p>

            </div>


            <div class="intelligence-body">

                <?php if (
                    empty($fastMovingProducts)
                ): ?>

                    <div class="empty">
                        No sales data yet.
                    </div>

                <?php else: ?>


                    <?php
                    $rank = 1;
                    ?>


                    <?php foreach (
                        $fastMovingProducts
                        as $product
                    ): ?>


                        <div class="intel-row">


                            <div class="rank">

                                #<?= $rank ?>

                            </div>


                            <div>

                                <div class="intel-name">

                                    <?= htmlspecialchars(
                                        $product[
                                            "product_name"
                                        ]
                                    ) ?>

                                </div>

                                <div class="intel-unit">

                                    <?= htmlspecialchars(
                                        $product[
                                            "unit"
                                        ] ?? ""
                                    ) ?>

                                </div>

                            </div>


                            <div class="intel-value">

                                <?= rtrim(
                                    rtrim(
                                        number_format(
                                            (float)$product[
                                                "quantity_sold"
                                            ],
                                            2,
                                            ".",
                                            ""
                                        ),
                                        "0"
                                    ),
                                    "."
                                ) ?>

                                <div class="intel-small">
                                    units sold
                                </div>

                            </div>


                        </div>


                        <?php
                        $rank++;
                        ?>


                    <?php endforeach; ?>


                <?php endif; ?>

            </div>

        </div>


        <!-- =====================================================
             MOST PROFITABLE
        ====================================================== -->

        <div class="intelligence-card">

            <div class="intelligence-header">

                <h3>
                    💰 Top 10 Most Profitable
                </h3>

                <p>
                    Ranked by genuine profit
                </p>

            </div>


            <div class="intelligence-body">


                <?php if (
                    empty($profitableProducts)
                ): ?>

                    <div class="empty">
                        No products with reliable profit data yet.
                    </div>

                <?php else: ?>


                    <?php
                    $rank = 1;
                    ?>


                    <?php foreach (
                        $profitableProducts
                        as $product
                    ): ?>


                        <div class="intel-row">


                            <div class="rank">

                                #<?= $rank ?>

                            </div>


                            <div>

                                <div class="intel-name">

                                    <?= htmlspecialchars(
                                        $product[
                                            "product_name"
                                        ]
                                    ) ?>

                                </div>

                                <div class="intel-unit">

                                    Margin:

                                    <?= number_format(
                                        (float)$product[
                                            "profit_margin"
                                        ],
                                        1
                                    ) ?>%

                                </div>

                            </div>


                            <div class="intel-value">

                                KSh
                                <?= number_format(
                                    (float)$product[
                                        "known_profit"
                                    ],
                                    2
                                ) ?>

                                <div class="intel-small">
                                    profit
                                </div>

                            </div>


                        </div>


                        <?php
                        $rank++;
                        ?>


                    <?php endforeach; ?>


                <?php endif; ?>

            </div>

        </div>


        <!-- =====================================================
             BEST OVERALL
        ====================================================== -->

        <div class="intelligence-card">

            <div class="intelligence-header">

                <h3>
                    ⭐ Top 10 Best Overall
                </h3>

                <p>
                    Demand + profit + margin + stock
                </p>

            </div>


            <div class="intelligence-body">


                <?php if (
                    empty($bestOverallProducts)
                ): ?>

                    <div class="empty">
                        Not enough data yet.
                    </div>

                <?php else: ?>


                    <?php
                    $rank = 1;
                    ?>


                    <?php foreach (
                        $bestOverallProducts
                        as $product
                    ): ?>


                        <div class="intel-row">


                            <div class="rank">

                                #<?= $rank ?>

                            </div>


                            <div>

                                <div class="intel-name">

                                    <?= htmlspecialchars(
                                        $product[
                                            "product_name"
                                        ]
                                    ) ?>

                                </div>

                                <div class="intel-unit">

                                    Score:

                                    <?= number_format(
                                        (float)$product[
                                            "overall_score"
                                        ],
                                        0
                                    ) ?>/100

                                </div>

                            </div>


                            <div>

                                <div class="score">

                                    <?= number_format(
                                        (float)$product[
                                            "overall_score"
                                        ],
                                        0
                                    ) ?>

                                </div>

                            </div>


                        </div>


                        <?php
                        $rank++;
                        ?>


                    <?php endforeach; ?>


                <?php endif; ?>

            </div>

        </div>


    </div>


    <!-- =========================================================
         FINANCIAL SUMMARY
    ========================================================== -->

    <div class="profit-card">

        <div class="card-header">

            <h2>
                💼 Today's Financial Summary
            </h2>

            <p>
                <?= date("d M Y") ?>
            </p>

        </div>


        <div class="card-body">

            <div class="profit-grid">


                <div class="profit-box">

                    <div class="profit-label">
                        Sales Revenue
                    </div>

                    <div class="profit-value">

                        KSh
                        <?= number_format(
                            $todaySales,
                            2
                        ) ?>

                    </div>

                </div>


                <div class="profit-box">

                    <div class="profit-label">
                        Known Cost of Goods Sold
                    </div>

                    <div class="profit-value">

                        KSh
                        <?= number_format(
                            $todayCost,
                            2
                        ) ?>

                    </div>

                </div>


                <div class="profit-box">

                    <div class="profit-label">
                        Gross Profit
                    </div>

                    <div class="profit-value">

                        KSh
                        <?= number_format(
                            $todayProfit,
                            2
                        ) ?>

                    </div>

                </div>


            </div>


            <?php if (
                !empty($missingCostProducts)
            ): ?>

                <div class="profit-note">

                    ⚠️ Some products sold today have
                    missing buying prices.

                    Their sales are included in revenue,
                    but their profit is excluded until
                    a valid B.P. is available.

                </div>

            <?php endif; ?>


        </div>

    </div>


    <!-- =========================================================
         PAYMENT BREAKDOWN
    ========================================================== -->

    <div class="payment-grid">


        <div class="payment-card">

            <h3>
                💵 Cash
            </h3>

            <div class="payment-value">

                KSh
                <?= number_format(
                    $cashSales,
                    2
                ) ?>

            </div>

        </div>


        <div class="payment-card">

            <h3>
                📱 M-Pesa
            </h3>

            <div class="payment-value">

                KSh
                <?= number_format(
                    $mpesaSales,
                    2
                ) ?>

            </div>

        </div>


        <div class="payment-card">

            <h3>
                🏦 Bank
            </h3>

            <div class="payment-value">

                KSh
                <?= number_format(
                    $bankSales,
                    2
                ) ?>

            </div>

        </div>


    </div>


    <!-- =========================================================
         TODAY'S DETAILS
    ========================================================== -->

    <div class="dashboard-grid">


        <!-- =====================================================
             TODAY PROFIT
        ====================================================== -->

        <div class="card">

            <div class="card-header">

                <h2>
                    💰 Product Profit Today
                </h2>

                <p>
                    Ranked by genuine profit generated today
                </p>

            </div>


            <div class="card-body">


                <?php if (
                    empty($topProducts)
                ): ?>

                    <div class="empty">

                        🛒

                        <br><br>

                        No products with valid B.P.
                        were sold today.

                    </div>

                <?php else: ?>


                    <div class="product-labels">

                        <div>
                            Product
                        </div>

                        <div>
                            Qty
                        </div>

                        <div>
                            Revenue
                        </div>

                        <div>
                            Cost
                        </div>

                        <div>
                            Profit
                        </div>

                    </div>


                    <?php foreach (
                        $topProducts
                        as $product
                    ): ?>


                        <?php

                        $quantitySold =
                            (float)$product[
                                "quantity_sold"
                            ];

                        $revenue =
                            (float)$product[
                                "revenue"
                            ];

                        $cost =
                            (float)$product[
                                "cost"
                            ];

                        $profit =
                            (float)$product[
                                "profit"
                            ];

                        ?>


                        <div class="product-row">


                            <div>

                                <div class="product-name">

                                    <?= htmlspecialchars(
                                        $product[
                                            "product_name"
                                        ]
                                    ) ?>

                                </div>

                                <div class="product-unit">

                                    <?= htmlspecialchars(
                                        $product[
                                            "unit"
                                        ] ?? ""
                                    ) ?>

                                </div>

                            </div>


                            <div class="product-qty">

                                <?= rtrim(
                                    rtrim(
                                        number_format(
                                            $quantitySold,
                                            3,
                                            ".",
                                            ""
                                        ),
                                        "0"
                                    ),
                                    "."
                                ) ?>

                            </div>


                            <div class="product-revenue">

                                KSh
                                <?= number_format(
                                    $revenue,
                                    2
                                ) ?>

                            </div>


                            <div class="product-cost">

                                KSh
                                <?= number_format(
                                    $cost,
                                    2
                                ) ?>

                            </div>


                            <div class="product-profit">

                                KSh
                                <?= number_format(
                                    $profit,
                                    2
                                ) ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


                <!-- =================================================
                     MISSING B.P.
                ================================================== -->

                <?php if (
                    !empty($missingCostProducts)
                ): ?>


                    <div class="missing-cost">


                        <div class="missing-cost-header">

                            <h3>
                                ⚠️ Products With Missing B.P.
                            </h3>

                            <p>
                                These products were sold today
                                but their cost cannot be trusted.
                            </p>

                        </div>


                        <?php foreach (
                            $missingCostProducts
                            as $product
                        ): ?>


                            <div
                                class="missing-cost-row"
                            >


                                <div>

                                    <div class="missing-name">

                                        <?= htmlspecialchars(
                                            $product[
                                                "product_name"
                                            ]
                                        ) ?>

                                    </div>

                                    <div class="missing-unit">

                                        <?= htmlspecialchars(
                                            $product[
                                                "unit"
                                            ] ?? ""
                                        ) ?>

                                    </div>

                                </div>


                                <div class="missing-qty">

                                    Qty:

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                (float)$product[
                                                    "quantity_sold"
                                                ],
                                                3,
                                                ".",
                                                ""
                                            ),
                                            "0"
                                        ),
                                        "."
                                    ) ?>

                                </div>


                                <div class="missing-revenue">

                                    KSh
                                    <?= number_format(
                                        (float)$product[
                                            "revenue"
                                        ],
                                        2
                                    ) ?>

                                </div>


                                <div class="missing-status">

                                    COST MISSING

                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php endif; ?>


            </div>

        </div>


        <!-- =====================================================
             STOCK ALERTS
        ====================================================== -->

        <div class="card">

            <div class="card-header">

                <h2>
                    ⚠️ Stock Alerts
                </h2>

                <p>
                    Products that currently need attention
                </p>

            </div>


            <div class="card-body">


                <?php if (
                    empty($lowStockProducts)
                ): ?>

                    <div class="empty">

                        ✅

                        <br><br>

                        All products have healthy
                        stock levels.

                    </div>

                <?php else: ?>


                    <?php foreach (
                        $lowStockProducts
                        as $product
                    ): ?>


                        <?php

                        $stock =
                            (float)$product[
                                "stock_quantity"
                            ];

                        $lowLevel =
                            (float)$product[
                                "low_stock_level"
                            ];

                        $stockClass =
                            $stock <= 0
                            ? "stock-out"
                            : "stock-low";

                        ?>


                        <div class="low-row">


                            <div>

                                <div class="low-name">

                                    <?= htmlspecialchars(
                                        $product[
                                            "product_name"
                                        ]
                                    ) ?>

                                </div>

                                <div class="low-level">

                                    Alert at:

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $lowLevel,
                                                3,
                                                ".",
                                                ""
                                            ),
                                            "0"
                                        ),
                                        "."
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $product[
                                            "unit"
                                        ] ?? ""
                                    ) ?>

                                </div>

                            </div>


                            <div
                                class="
                                    stock-number
                                    <?= $stockClass ?>
                                "
                            >

                                <?= rtrim(
                                    rtrim(
                                        number_format(
                                            $stock,
                                            3,
                                            ".",
                                            ""
                                        ),
                                        "0"
                                    ),
                                    "."
                                ) ?>

                                <?= htmlspecialchars(
                                    $product[
                                        "unit"
                                    ] ?? ""
                                ) ?>


                                <div
                                    style="
                                        font-size:10px;
                                        margin-top:3px;
                                    "
                                >

                                    <?= $stock <= 0
                                        ? "OUT OF STOCK"
                                        : "LOW STOCK"
                                    ?>

                                </div>

                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>

        </div>


    </div>


</div>

</body>

</html>