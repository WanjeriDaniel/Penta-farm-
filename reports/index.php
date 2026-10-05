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

/*
|--------------------------------------------------------------------------
| HISTORICAL BUSINESS PERFORMANCE
|--------------------------------------------------------------------------
*/

$selectedYear = $_GET["year"] ?? "all";
$selectedMonth = $_GET["month"] ?? "all";

$historicalYears = [];
$historicalData = [];
$historicalYearlyData = [];

$historicalTotalSales = 0;
$historicalTotalKnownRevenue = 0;
$historicalTotalCost = 0;
$historicalTotalProfit = 0;
$historicalTransactions = 0;

$highestSalesPeriod = null;
$highestProfitPeriod = null;

$highestSalesYear = null;
$highestProfitYear = null;

$monthNames = [
    1 => "January",
    2 => "February",
    3 => "March",
    4 => "April",
    5 => "May",
    6 => "June",
    7 => "July",
    8 => "August",
    9 => "September",
    10 => "October",
    11 => "November",
    12 => "December"
];
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
     /*
|--------------------------------------------------------------------------
| HISTORICAL BUSINESS PERFORMANCE
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| GET AVAILABLE YEARS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT DISTINCT
        YEAR(sale_date) AS sale_year

    FROM sales

    WHERE status = 'completed'

    ORDER BY
        sale_year DESC
");

$historicalYears = $stmt->fetchAll(PDO::FETCH_COLUMN);


/*
|--------------------------------------------------------------------------
| VALIDATE YEAR FILTER
|--------------------------------------------------------------------------
*/

if (
    $selectedYear !== "all"
    &&
    !in_array(
        (int)$selectedYear,
        array_map(
            "intval",
            $historicalYears
        ),
        true
    )
) {

    $selectedYear = "all";

}


/*
|--------------------------------------------------------------------------
| VALIDATE MONTH FILTER
|--------------------------------------------------------------------------
*/

if (
    $selectedMonth !== "all"
    &&
    (
        !is_numeric($selectedMonth)
        ||
        (int)$selectedMonth < 1
        ||
        (int)$selectedMonth > 12
    )
) {

    $selectedMonth = "all";

}


/*
|--------------------------------------------------------------------------
| BUILD MONTHLY FILTERS
|--------------------------------------------------------------------------
*/

$monthlyWhere = "
    WHERE s.status = 'completed'
";

$monthlyParams = [];

if ($selectedYear !== "all") {

    $monthlyWhere .= "
        AND YEAR(s.sale_date) = :history_year
    ";

    $monthlyParams[":history_year"] =
        (int)$selectedYear;

}

if ($selectedMonth !== "all") {

    $monthlyWhere .= "
        AND MONTH(s.sale_date) = :history_month
    ";

    $monthlyParams[":history_month"] =
        (int)$selectedMonth;

}


/*
|--------------------------------------------------------------------------
| MONTHLY SALES
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Sales totals are calculated separately from sale items
| so one sale is never counted multiple times.
|
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        YEAR(s.sale_date) AS sale_year,

        MONTH(s.sale_date) AS sale_month,

        COUNT(*) AS transactions,

        COALESCE(
            SUM(s.total_amount),
            0
        ) AS total_sales

    FROM sales s

    $monthlyWhere

    GROUP BY
        YEAR(s.sale_date),
        MONTH(s.sale_date)

    ORDER BY
        sale_year DESC,
        sale_month DESC
");

$stmt->execute($monthlyParams);

$monthlySalesRows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| MONTHLY KNOWN PROFIT
|--------------------------------------------------------------------------
|
| Only sale lines with a valid buying price are included.
|
|--------------------------------------------------------------------------
*/

$monthlyProfitWhere = "
    WHERE s.status = 'completed'

    AND si.buying_price > 0
";

$monthlyProfitParams = [];

if ($selectedYear !== "all") {

    $monthlyProfitWhere .= "
        AND YEAR(s.sale_date) = :profit_history_year
    ";

    $monthlyProfitParams[":profit_history_year"] =
        (int)$selectedYear;

}

if ($selectedMonth !== "all") {

    $monthlyProfitWhere .= "
        AND MONTH(s.sale_date) = :profit_history_month
    ";

    $monthlyProfitParams[":profit_history_month"] =
        (int)$selectedMonth;

}


$stmt = $pdo->prepare("
    SELECT

        YEAR(s.sale_date) AS sale_year,

        MONTH(s.sale_date) AS sale_month,

        COALESCE(
            SUM(
                si.quantity *
                si.buying_price
            ),
            0
        ) AS total_cost,

        COALESCE(
            SUM(si.subtotal),
            0
        ) AS known_revenue,

        COALESCE(
            SUM(
                si.subtotal -
                (
                    si.quantity *
                    si.buying_price
                )
            ),
            0
        ) AS known_profit

    FROM sale_items si

    INNER JOIN sales s
        ON si.sale_id = s.id

    $monthlyProfitWhere

    GROUP BY
        YEAR(s.sale_date),
        MONTH(s.sale_date)

    ORDER BY
        sale_year DESC,
        sale_month DESC
");

$stmt->execute($monthlyProfitParams);

$monthlyProfitRows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| MERGE MONTHLY SALES + PROFIT
|--------------------------------------------------------------------------
*/

$monthlyCombined = [];


/*
|--------------------------------------------------------------------------
| ADD SALES DATA
|--------------------------------------------------------------------------
*/

foreach (
    $monthlySalesRows
    as $row
) {

    $year =
        (int)$row["sale_year"];

    $month =
        (int)$row["sale_month"];

    $key =
        $year
        .
        "-"
        .
        str_pad(
            $month,
            2,
            "0",
            STR_PAD_LEFT
        );


    $monthlyCombined[$key] = [

        "year" =>
            $year,

        "month" =>
            $month,

        "period" =>
            ($monthNames[$month] ?? "Unknown")
            .
            " "
            .
            $year,

        "transactions" =>
            (int)$row["transactions"],

        "total_sales" =>
            (float)$row["total_sales"],

        "known_revenue" =>
            0,

        "total_cost" =>
            0,

        "known_profit" =>
            0,

        "margin" =>
            0

    ];

}


/*
|--------------------------------------------------------------------------
| ADD PROFIT DATA
|--------------------------------------------------------------------------
*/

foreach (
    $monthlyProfitRows
    as $row
) {

    $year =
        (int)$row["sale_year"];

    $month =
        (int)$row["sale_month"];

    $key =
        $year
        .
        "-"
        .
        str_pad(
            $month,
            2,
            "0",
            STR_PAD_LEFT
        );


    if (
        !isset(
            $monthlyCombined[$key]
        )
    ) {

        $monthlyCombined[$key] = [

            "year" =>
                $year,

            "month" =>
                $month,

            "period" =>
                ($monthNames[$month] ?? "Unknown")
                .
                " "
                .
                $year,

            "transactions" =>
                0,

            "total_sales" =>
                0,

            "known_revenue" =>
                0,

            "total_cost" =>
                0,

            "known_profit" =>
                0,

            "margin" =>
                0

        ];

    }


    $monthlyCombined[$key][
        "known_revenue"
    ] =
        (float)$row["known_revenue"];


    $monthlyCombined[$key][
        "total_cost"
    ] =
        (float)$row["total_cost"];


    $monthlyCombined[$key][
        "known_profit"
    ] =
        (float)$row["known_profit"];


    if (
        (float)$row["known_revenue"] > 0
    ) {

        $monthlyCombined[$key][
            "margin"
        ] =
            (
                (float)$row["known_profit"]
                /
                (float)$row["known_revenue"]
            )
            * 100;

    }

}


/*
|--------------------------------------------------------------------------
| SORT MONTHLY DATA
|--------------------------------------------------------------------------
*/

$historicalData =
    array_values(
        $monthlyCombined
    );

usort(
    $historicalData,
    function ($a, $b) {

        if (
            $a["year"] === $b["year"]
        ) {

            return
                $b["month"]
                <=>
                $a["month"];

        }

        return
            $b["year"]
            <=>
            $a["year"];

    }
);


/*
|--------------------------------------------------------------------------
| CALCULATE HISTORICAL TOTALS
|--------------------------------------------------------------------------
*/

foreach (
    $historicalData
    as $period
) {

    $historicalTotalSales +=
        (float)$period["total_sales"];

    $historicalTotalKnownRevenue +=
        (float)$period["known_revenue"];

    $historicalTotalCost +=
        (float)$period["total_cost"];

    $historicalTotalProfit +=
        (float)$period["known_profit"];

    $historicalTransactions +=
        (int)$period["transactions"];


    /*
    |--------------------------------------------------------------------------
    | HIGHEST SALES PERIOD
    |--------------------------------------------------------------------------
    */

    if (
        $highestSalesPeriod === null
        ||
        $period["total_sales"]
        >
        $highestSalesPeriod["total_sales"]
    ) {

        $highestSalesPeriod =
            $period;

    }


    /*
    |--------------------------------------------------------------------------
    | HIGHEST PROFIT PERIOD
    |--------------------------------------------------------------------------
    */

    if (
        $highestProfitPeriod === null
        ||
        $period["known_profit"]
        >
        $highestProfitPeriod["known_profit"]
    ) {

        $highestProfitPeriod =
            $period;

    }

}


/*
|--------------------------------------------------------------------------
| HISTORICAL OVERALL MARGIN
|--------------------------------------------------------------------------
*/

$historicalMargin =
    $historicalTotalKnownRevenue > 0
    ?
    (
        $historicalTotalProfit
        /
        $historicalTotalKnownRevenue
    ) * 100
    :
    0;


/*
|--------------------------------------------------------------------------
| YEARLY COMPARISON
|--------------------------------------------------------------------------
|
| The selected month is respected.
|
| Example:
|
| Select January.
|
| The system compares:
|
| January 2024
| January 2025
| January 2026
|
|--------------------------------------------------------------------------
*/

$yearlyWhere = "
    WHERE s.status = 'completed'
";

$yearlyParams = [];

if ($selectedMonth !== "all") {

    $yearlyWhere .= "
        AND MONTH(s.sale_date) = :yearly_month
    ";

    $yearlyParams[":yearly_month"] =
        (int)$selectedMonth;

}


/*
|--------------------------------------------------------------------------
| YEARLY SALES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        YEAR(s.sale_date) AS sale_year,

        COUNT(*) AS transactions,

        COALESCE(
            SUM(s.total_amount),
            0
        ) AS total_sales

    FROM sales s

    $yearlyWhere

    GROUP BY
        YEAR(s.sale_date)

    ORDER BY
        sale_year DESC
");

$stmt->execute($yearlyParams);

$yearlySalesRows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| YEARLY PROFIT
|--------------------------------------------------------------------------
*/

$yearlyProfitWhere = "
    WHERE s.status = 'completed'

    AND si.buying_price > 0
";

$yearlyProfitParams = [];

if ($selectedMonth !== "all") {

    $yearlyProfitWhere .= "
        AND MONTH(s.sale_date) = :yearly_profit_month
    ";

    $yearlyProfitParams[":yearly_profit_month"] =
        (int)$selectedMonth;

}


$stmt = $pdo->prepare("
    SELECT

        YEAR(s.sale_date) AS sale_year,

        COALESCE(
            SUM(
                si.quantity *
                si.buying_price
            ),
            0
        ) AS total_cost,

        COALESCE(
            SUM(si.subtotal),
            0
        ) AS known_revenue,

        COALESCE(
            SUM(
                si.subtotal -
                (
                    si.quantity *
                    si.buying_price
                )
            ),
            0
        ) AS known_profit

    FROM sale_items si

    INNER JOIN sales s
        ON si.sale_id = s.id

    $yearlyProfitWhere

    GROUP BY
        YEAR(s.sale_date)

    ORDER BY
        sale_year DESC
");

$stmt->execute($yearlyProfitParams);

$yearlyProfitRows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| MERGE YEARLY DATA
|--------------------------------------------------------------------------
*/

$yearlyCombined = [];


foreach (
    $yearlySalesRows
    as $row
) {

    $year =
        (int)$row["sale_year"];

    $yearlyCombined[$year] = [

        "year" =>
            $year,

        "transactions" =>
            (int)$row["transactions"],

        "total_sales" =>
            (float)$row["total_sales"],

        "known_revenue" =>
            0,

        "total_cost" =>
            0,

        "known_profit" =>
            0,

        "margin" =>
            0

    ];

}


foreach (
    $yearlyProfitRows
    as $row
) {

    $year =
        (int)$row["sale_year"];


    if (
        !isset(
            $yearlyCombined[$year]
        )
    ) {

        $yearlyCombined[$year] = [

            "year" =>
                $year,

            "transactions" =>
                0,

            "total_sales" =>
                0,

            "known_revenue" =>
                0,

            "total_cost" =>
                0,

            "known_profit" =>
                0,

            "margin" =>
                0

        ];

    }


    $yearlyCombined[$year][
        "known_revenue"
    ] =
        (float)$row["known_revenue"];


    $yearlyCombined[$year][
        "total_cost"
    ] =
        (float)$row["total_cost"];


    $yearlyCombined[$year][
        "known_profit"
    ] =
        (float)$row["known_profit"];


    if (
        (float)$row["known_revenue"] > 0
    ) {

        $yearlyCombined[$year][
            "margin"
        ] =
            (
                (float)$row["known_profit"]
                /
                (float)$row["known_revenue"]
            )
            * 100;

    }

}


$historicalYearlyData =
    array_values(
        $yearlyCombined
    );


usort(
    $historicalYearlyData,
    function ($a, $b) {

        return
            $b["year"]
            <=>
            $a["year"];

    }
);


/*
|--------------------------------------------------------------------------
| HIGHEST PERFORMING YEAR
|--------------------------------------------------------------------------
*/

foreach (
    $historicalYearlyData
    as $yearData
) {

    if (
        $highestSalesYear === null
        ||
        $yearData["total_sales"]
        >
        $highestSalesYear["total_sales"]
    ) {

        $highestSalesYear =
            $yearData;

    }


    if (
        $highestProfitYear === null
        ||
        $yearData["known_profit"]
        >
        $highestProfitYear["known_profit"]
    ) {

        $highestProfitYear =
            $yearData;

    }

}   


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
| HISTORICAL BUSINESS PERFORMANCE
|--------------------------------------------------------------------------
*/

.history-card {

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

    margin-bottom:
        25px;

    overflow:
        hidden;
}

.history-header {

    padding:
        20px;

    border-bottom:
        1px solid
        #eee;
}

.history-header h2 {

    margin:
        0 0 5px;

    font-size:
        20px;
}

.history-header p {

    margin:
        0;

    color:
        #777;

    font-size:
        12px;
}

.history-body {

    padding:
        20px;
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

.history-filters {

    display:
        flex;

    gap:
        12px;

    align-items:
        flex-end;

    flex-wrap:
        wrap;

    margin-bottom:
        20px;

    padding:
        15px;

    background:
        #f7f9fb;

    border-radius:
        10px;
}

.history-field {

    display:
        flex;

    flex-direction:
        column;

    gap:
        5px;

    min-width:
        150px;
}

.history-field label {

    font-size:
        11px;

    color:
        #777;

    font-weight:
        bold;
}

.history-field select {

    padding:
        10px 12px;

    border:
        1px solid
        #ddd;

    border-radius:
        8px;

    background:
        white;

    font-size:
        13px;

    outline:
        none;
}

.history-button {

    padding:
        10px 16px;

    border:
        none;

    border-radius:
        8px;

    background:
        #1e3c72;

    color:
        white;

    font-weight:
        bold;

    cursor:
        pointer;
}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.history-summary {

    display:
        grid;

    grid-template-columns:
        repeat(
            4,
            1fr
        );

    gap:
        12px;

    margin-bottom:
        20px;
}

.history-summary-box {

    background:
        #f7f9fb;

    border-radius:
        10px;

    padding:
        16px;
}

.history-summary-label {

    color:
        #777;

    font-size:
        11px;

    margin-bottom:
        6px;
}

.history-summary-value {

    color:
        #1e3c72;

    font-size:
        20px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| HIGHLIGHTS
|--------------------------------------------------------------------------
*/

.history-highlights {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            1fr
        );

    gap:
        15px;

    margin-bottom:
        25px;
}

.history-highlight {

    padding:
        18px;

    border-radius:
        10px;

    background:
        #f7f9fb;

    border-left:
        5px solid
        #1e3c72;
}

.history-highlight-title {

    color:
        #777;

    font-size:
        11px;

    margin-bottom:
        7px;
}

.history-highlight-period {

    color:
        #1e3c72;

    font-size:
        18px;

    font-weight:
        bold;

    margin-bottom:
        5px;
}

.history-highlight-value {

    font-size:
        15px;

    font-weight:
        bold;

}

.history-highlight-note {

    color:
        #777;

    font-size:
        11px;

    margin-top:
        5px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.history-table-wrapper {

    width:
        100%;

    overflow-x:
        auto;

    margin-bottom:
        25px;
}

.history-table {

    width:
        100%;

    border-collapse:
        collapse;

    min-width:
        650px;
}

.history-table th {

    background:
        #f4f6f9;

    color:
        #666;

    font-size:
        11px;

    text-align:
        left;

    padding:
        12px;

    border-bottom:
        2px solid
        #ddd;
}

.history-table td {

    padding:
        13px 12px;

    border-bottom:
        1px solid
        #eee;

    font-size:
        12px;
}

.history-table th:not(:first-child),
.history-table td:not(:first-child) {

    text-align:
        right;
}

.history-table td:first-child {

    font-weight:
        bold;

    color:
        #1e3c72;
}

.history-sales {

    font-weight:
        bold;
}

.history-profit {

    color:
        #16803c;

    font-weight:
        bold;
}

.history-badge {

    display:
        inline-block;

    padding:
        4px 7px;

    border-radius:
        5px;

    font-size:
        10px;

    font-weight:
        bold;
}

.badge-sales {

    background:
        #eef3fa;

    color:
        #1e3c72;
}

.badge-profit {

    background:
        #eaf7ef;

    color:
        #16803c;
}


/*
|--------------------------------------------------------------------------
| BEST PERIOD HIGHLIGHT
|--------------------------------------------------------------------------
*/

.history-best-sales {

    background:
        #f3f7ff;
}

.history-best-profit {

    background:
        #f2faf5;
}


/*
|--------------------------------------------------------------------------
| YEARLY SECTION
|--------------------------------------------------------------------------
*/

.yearly-section {

    margin-top:
        25px;
}

.yearly-section h3 {

    margin:
        0 0 5px;

    font-size:
        17px;
}

.yearly-section p {

    margin:
        0 0 15px;

    color:
        #777;

    font-size:
        11px;
}

.history-note {

    padding:
        12px;

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

    line-height:
        1.5;
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

}@media (max-width: 800px) {

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

    .history-summary {

        grid-template-columns:
            repeat(
                2,
                1fr
            );

    }

    .history-highlights {

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

            .history-summary {

        grid-template-columns:
            1fr;

    }

    .history-filters {

        flex-direction:
            column;

        align-items:
            stretch;

    }

    .history-field {

        width:
            100%;

    }

    .history-button {

        width:
            100%;

    }

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
     HISTORICAL BUSINESS PERFORMANCE
========================================================== -->

<div class="section-title">

    <h2>
        📅 Historical Business Performance
    </h2>

    <p>
        Compare sales and known profit across months
        and years to see when the business performs best.
    </p>

</div>


<div class="history-card">


    <div class="history-header">

        <h2>
            📈 Business History
        </h2>

        <p>
            Use the filters to investigate past business performance.
        </p>

    </div>


    <div class="history-body">


        <!-- =====================================================
             FILTERS
        ====================================================== -->

        <form
            method="get"
            class="history-filters"
        >

            <div class="history-field">

                <label>
                    Year
                </label>

                <select name="year">

                    <option
                        value="all"
                        <?= $selectedYear === "all"
                            ? "selected"
                            : "" ?>
                    >
                        All Years
                    </option>


                    <?php foreach (
                        $historicalYears
                        as $year
                    ): ?>

                        <option
                            value="<?= (int)$year ?>"
                            <?= (string)$selectedYear ===
                                (string)$year
                                ? "selected"
                                : "" ?>
                        >
                            <?= (int)$year ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="history-field">

                <label>
                    Month
                </label>

                <select name="month">

                    <option
                        value="all"
                        <?= $selectedMonth === "all"
                            ? "selected"
                            : "" ?>
                    >
                        All Months
                    </option>


                    <?php foreach (
                        $monthNames
                        as $monthNumber => $monthName
                    ): ?>

                        <option
                            value="<?= $monthNumber ?>"
                            <?= (string)$selectedMonth ===
                                (string)$monthNumber
                                ? "selected"
                                : "" ?>
                        >
                            <?= htmlspecialchars(
                                $monthName
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <button
                type="submit"
                class="history-button"
            >
                🔎 Apply Filter
            </button>

        </form>


        <!-- =====================================================
             SUMMARY
        ====================================================== -->

        <div class="history-summary">


            <div class="history-summary-box">

                <div class="history-summary-label">
                    Total Sales
                </div>

                <div class="history-summary-value">

                    KSh
                    <?= number_format(
                        $historicalTotalSales,
                        2
                    ) ?>

                </div>

            </div>


            <div class="history-summary-box">

                <div class="history-summary-label">
                    Known Gross Profit
                </div>

                <div class="history-summary-value">

                    KSh
                    <?= number_format(
                        $historicalTotalProfit,
                        2
                    ) ?>

                </div>

            </div>


            <div class="history-summary-box">

                <div class="history-summary-label">
                    Transactions
                </div>

                <div class="history-summary-value">

                    <?= number_format(
                        $historicalTransactions
                    ) ?>

                </div>

            </div>


            <div class="history-summary-box">

                <div class="history-summary-label">
                    Profit Margin
                </div>

                <div class="history-summary-value">

                    <?= number_format(
                        $historicalMargin,
                        1
                    ) ?>%

                </div>

            </div>


        </div>


        <!-- =====================================================
             HIGHEST PERFORMANCE
        ====================================================== -->

        <?php if (
            !empty($historicalData)
        ): ?>

            <div class="history-highlights">


                <div class="history-highlight">

                    <div class="history-highlight-title">

                        🏆 HIGHEST SALES PERIOD

                    </div>


                    <div class="history-highlight-period">

                        <?= htmlspecialchars(
                            $highestSalesPeriod["period"]
                        ) ?>

                    </div>


                    <div class="history-highlight-value">

                        KSh
                        <?= number_format(
                            (float)$highestSalesPeriod[
                                "total_sales"
                            ],
                            2
                        ) ?>

                        sales

                    </div>


                    <div class="history-highlight-note">

                        <?= number_format(
                            (int)$highestSalesPeriod[
                                "transactions"
                            ]
                        ) ?>

                        transactions

                        • Known profit:

                        KSh
                        <?= number_format(
                            (float)$highestSalesPeriod[
                                "known_profit"
                            ],
                            2
                        ) ?>

                    </div>

                </div>


                <div class="history-highlight">

                    <div class="history-highlight-title">

                        💰 HIGHEST PROFIT PERIOD

                    </div>


                    <div class="history-highlight-period">

                        <?= htmlspecialchars(
                            $highestProfitPeriod["period"]
                        ) ?>

                    </div>


                    <div class="history-highlight-value">

                        KSh
                        <?= number_format(
                            (float)$highestProfitPeriod[
                                "known_profit"
                            ],
                            2
                        ) ?>

                        known profit

                    </div>


                    <div class="history-highlight-note">

                        Sales:

                        KSh
                        <?= number_format(
                            (float)$highestProfitPeriod[
                                "total_sales"
                            ],
                            2
                        ) ?>

                        • Margin:

                        <?= number_format(
                            (float)$highestProfitPeriod[
                                "margin"
                            ],
                            1
                        ) ?>%

                    </div>

                </div>


            </div>

        <?php endif; ?>


        <!-- =====================================================
             MONTHLY PERFORMANCE
        ====================================================== -->

        <div class="yearly-section">

            <h3>
                📅 Monthly Performance
            </h3>

            <p>
                Historical performance by month.
            </p>

        </div>


        <?php if (
            empty($historicalData)
        ): ?>

            <div class="empty">

                📊

                <br><br>

                No historical sales data is available
                for the selected filter.

            </div>

        <?php else: ?>


            <div class="history-table-wrapper">

                <table class="history-table">

                    <thead>

                        <tr>

                            <th>
                                Period
                            </th>

                            <th>
                                Transactions
                            </th>

                            <th>
                                Sales
                            </th>

                            <th>
                                Known Profit
                            </th>

                            <th>
                                Margin
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach (
                            $historicalData
                            as $period
                        ): ?>


                            <?php

                            $isBestSales =
                                $highestSalesPeriod !== null
                                &&
                                $period["year"] ===
                                    $highestSalesPeriod["year"]
                                &&
                                $period["month"] ===
                                    $highestSalesPeriod["month"];

                            $isBestProfit =
                                $highestProfitPeriod !== null
                                &&
                                $period["year"] ===
                                    $highestProfitPeriod["year"]
                                &&
                                $period["month"] ===
                                    $highestProfitPeriod["month"];

                            $rowClass = "";

                            if ($isBestSales) {

                                $rowClass =
                                    "history-best-sales";

                            }

                            if ($isBestProfit) {

                                $rowClass =
                                    "history-best-profit";

                            }

                            if (
                                $isBestSales
                                &&
                                $isBestProfit
                            ) {

                                $rowClass =
                                    "history-best-sales";

                            }

                            ?>


                            <tr class="<?= $rowClass ?>">


                                <td>

                                    <?= htmlspecialchars(
                                        $period["period"]
                                    ) ?>


                                    <?php if (
                                        $isBestSales
                                    ): ?>

                                        <span
                                            class="
                                                history-badge
                                                badge-sales
                                            "
                                        >
                                            Highest Sales
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        $isBestProfit
                                    ): ?>

                                        <span
                                            class="
                                                history-badge
                                                badge-profit
                                            "
                                        >
                                            Highest Profit
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        $period["transactions"]
                                    ) ?>

                                </td>


                                <td class="history-sales">

                                    KSh
                                    <?= number_format(
                                        (float)$period[
                                            "total_sales"
                                        ],
                                        2
                                    ) ?>

                                </td>


                                <td class="history-profit">

                                    KSh
                                    <?= number_format(
                                        (float)$period[
                                            "known_profit"
                                        ],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        (float)$period[
                                            "margin"
                                        ],
                                        1
                                    ) ?>%

                                </td>


                            </tr>


                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php endif; ?>


        <!-- =====================================================
             YEARLY COMPARISON
        ====================================================== -->

        <div class="yearly-section">

            <h3>
                📊 Yearly Comparison
            </h3>

            <p>

                <?php if (
                    $selectedMonth !== "all"
                ): ?>

                    Comparing
                    <?= htmlspecialchars(
                        $monthNames[
                            (int)$selectedMonth
                        ]
                    ) ?>
                    across different years.

                <?php else: ?>

                    Comparing business performance
                    across different years.

                <?php endif; ?>

            </p>

        </div>


        <?php if (
            empty($historicalYearlyData)
        ): ?>

            <div class="empty">

                No yearly comparison data available.

            </div>

        <?php else: ?>


            <div class="history-table-wrapper">

                <table class="history-table">

                    <thead>

                        <tr>

                            <th>
                                Year
                            </th>

                            <th>
                                Transactions
                            </th>

                            <th>
                                Sales
                            </th>

                            <th>
                                Known Profit
                            </th>

                            <th>
                                Margin
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach (
                            $historicalYearlyData
                            as $yearData
                        ): ?>


                            <?php

                            $isBestYearSales =
                                $highestSalesYear !== null
                                &&
                                $yearData["year"] ===
                                    $highestSalesYear["year"];

                            $isBestYearProfit =
                                $highestProfitYear !== null
                                &&
                                $yearData["year"] ===
                                    $highestProfitYear["year"];

                            ?>


                            <tr>


                                <td>

                                    <strong>

                                        <?= (int)$yearData[
                                            "year"
                                        ] ?>

                                    </strong>


                                    <?php if (
                                        $isBestYearSales
                                    ): ?>

                                        <span
                                            class="
                                                history-badge
                                                badge-sales
                                            "
                                        >
                                            Highest Sales
                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        $isBestYearProfit
                                    ): ?>

                                        <span
                                            class="
                                                history-badge
                                                badge-profit
                                            "
                                        >
                                            Highest Profit
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        $yearData[
                                            "transactions"
                                        ]
                                    ) ?>

                                </td>


                                <td class="history-sales">

                                    KSh
                                    <?= number_format(
                                        (float)$yearData[
                                            "total_sales"
                                        ],
                                        2
                                    ) ?>

                                </td>


                                <td class="history-profit">

                                    KSh
                                    <?= number_format(
                                        (float)$yearData[
                                            "known_profit"
                                        ],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        (float)$yearData[
                                            "margin"
                                        ],
                                        1
                                    ) ?>%

                                </td>


                            </tr>


                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php endif; ?>


        <!-- =====================================================
             ACCOUNTING NOTE
        ====================================================== -->

        <div class="history-note">

            ⚠️ <strong>Important:</strong>

            Known Gross Profit excludes sale lines where
            the buying price was missing or zero.

            Sales revenue still includes those sales.

            This means the profit figures shown here are
            conservative and should improve as missing
            buying prices are corrected.

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