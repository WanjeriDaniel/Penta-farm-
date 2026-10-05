<?php

require_once "../config/database.php";

$search = trim($_GET["search"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");

$sales = [];

$totalSales = 0;
$totalAmount = 0;
$totalCollected = 0;
$totalOutstanding = 0;
$totalGrossProfit = 0;

$error = "";

try {

    /*
    |--------------------------------------------------------------------------
    | Build query
    |--------------------------------------------------------------------------
    |
    | Gross profit is calculated from the historical buying_price stored
    | inside sale_items.
    |
    | Payment information comes from sales.amount_paid.
    |
    */

    $sql = "
        SELECT
            s.id,
            s.receipt_number,
            s.sale_date,
            s.sale_type,
            s.total_amount,
            s.payment_method,
            s.amount_paid,
            s.change_amount,
            s.payment_status,
            s.status,

            COUNT(si.id) AS item_count,

            COALESCE(
                SUM(
                    si.subtotal -
                    (si.quantity * si.buying_price)
                ),
                0
            ) AS gross_profit

        FROM sales s

        LEFT JOIN sale_items si
            ON si.sale_id = s.id

        WHERE 1 = 1
    ";

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | Receipt Search
    |--------------------------------------------------------------------------
    */

    if ($search !== "") {

    $sql .= "
        AND (
            s.receipt_number LIKE :search
            OR s.customer_name LIKE :search
            OR s.customer_phone LIKE :search
            OR CAST(s.total_amount AS CHAR) LIKE :search
        )
    ";

    $params[":search"] =
        "%" . $search . "%";
}

    /*
    |--------------------------------------------------------------------------
    | Date From
    |--------------------------------------------------------------------------
    */

    if ($dateFrom !== "") {

        $sql .= "
            AND DATE(s.sale_date) >= :date_from
        ";

        $params[":date_from"] =
            $dateFrom;
    }


    /*
    |--------------------------------------------------------------------------
    | Date To
    |--------------------------------------------------------------------------
    */

    if ($dateTo !== "") {

        $sql .= "
            AND DATE(s.sale_date) <= :date_to
        ";

        $params[":date_to"] =
            $dateTo;
    }


    /*
    |--------------------------------------------------------------------------
    | Grouping
    |--------------------------------------------------------------------------
    */

    $sql .= "
        GROUP BY
            s.id,
            s.receipt_number,
            s.sale_date,
            s.sale_type,
            s.total_amount,
            s.payment_method,
            s.amount_paid,
            s.change_amount,
            s.payment_status,
            s.status

        ORDER BY
            s.sale_date DESC,
            s.id DESC
    ";


    /*
    |--------------------------------------------------------------------------
    | Execute
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    $totalSales = count($sales);

    foreach ($sales as &$sale) {

        /*
        |----------------------------------------------------------------------
        | Normalize financial values
        |----------------------------------------------------------------------
        */

        $saleTotal =
            (float)$sale["total_amount"];

        $salePaid =
            (float)$sale["amount_paid"];

        $saleBalance =
            max(
                0,
                round(
                    $saleTotal - $salePaid,
                    2
                )
            );

        $saleGrossProfit =
            (float)$sale["gross_profit"];


        /*
        |----------------------------------------------------------------------
        | Only completed sales contribute to business totals
        |----------------------------------------------------------------------
        */

        if ($sale["status"] === "completed") {

            $totalAmount +=
                $saleTotal;

            $totalCollected +=
                min(
                    $salePaid,
                    $saleTotal
                );

            $totalOutstanding +=
                $saleBalance;

            $totalGrossProfit +=
                $saleGrossProfit;
        }


        /*
        |----------------------------------------------------------------------
        | Add calculated balance to the sale array
        |----------------------------------------------------------------------
        */

        $sale["balance"] =
            $saleBalance;


        /*
        |----------------------------------------------------------------------
        | Determine display payment status
        |----------------------------------------------------------------------
        |
        | We calculate this from amount_paid rather than relying only on
        | payment_status, so the history remains accurate even if an older
        | record has an incorrect status.
        |
        */

        if ($sale["status"] !== "completed") {

            $sale["display_payment_status"] =
                ucfirst(
                    $sale["status"]
                );

        } elseif ($salePaid <= 0) {

            $sale["display_payment_status"] =
                "Unpaid";

        } elseif ($salePaid < $saleTotal) {

            $sale["display_payment_status"] =
                "Partial";

        } else {

            $sale["display_payment_status"] =
                "Paid";
        }
    }

    unset($sale);


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

<title>Sales History - Penta Farm</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f6f9;
    font-family: Arial, sans-serif;
    color: #222;
}

.container {
    width: 96%;
    max-width: 1500px;
    margin: 25px auto;
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
    margin-bottom: 20px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 15px;
}

.header h1 {
    margin: 0 0 6px;
}

.header p {
    margin: 0;
    opacity: .9;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.button {
    display: inline-block;
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 8px;
    font-weight: bold;
}

.btn-light {
    background: white;
    color: #1e3c72;
}

.btn-dark {
    background: rgba(255,255,255,.15);
    color: white;
    border: 1px solid rgba(255,255,255,.3);
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

.search-card {
    background: white;
    padding: 20px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,.06);
    margin-bottom: 20px;
}

.search-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 12px;
    align-items: end;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 6px;
}

.form-group input {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 7px;
    font-size: 14px;
}

.search-btn {
    border: none;
    background: #1e3c72;
    color: white;
    padding: 11px 20px;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

.summary {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}

.summary-title {
    color: #666;
    font-size: 13px;
    margin-bottom: 7px;
}

.summary-value {
    color: #1e3c72;
    font-size: 22px;
    font-weight: bold;
}

.summary-card.profit .summary-value {
    color: #16702b;
}

.summary-card.outstanding .summary-value {
    color: #a15c00;
}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-card {
    background: white;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,.06);
    overflow: hidden;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1250px;
}

th {
    text-align: left;
    background: #f5f6f8;
    color: #555;
    padding: 13px;
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

tr:hover {
    background: #fafbfd;
}

.receipt-number {
    font-weight: bold;
    color: #1e3c72;
}

.amount {
    font-weight: bold;
}

.paid-amount {
    font-weight: bold;
    color: #16702b;
}

.balance-amount {
    font-weight: bold;
    color: #a15c00;
}

.profit-amount {
    font-weight: bold;
    color: #16702b;
}

.payment {
    font-weight: bold;
}

.sale-type {
    font-weight: bold;
    text-transform: capitalize;
}


/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

.actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 7px 10px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
}

.view-btn {
    background: #edf3ff;
    color: #1e3c72;
}

.receipt-btn {
    background: #eaf7ee;
    color: #16702b;
}

.payment-btn {
    background: #fff2d9;
    color: #9b6500;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.status-completed {
    background: #e3f7e8;
    color: #16702b;
}

.status-voided {
    background: #ffe5e5;
    color: #a00000;
}

.status-refunded {
    background: #fff2d9;
    color: #9b6500;
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

.payment-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.payment-paid {
    background: #e3f7e8;
    color: #16702b;
}

.payment-partial {
    background: #fff2d9;
    color: #9b6500;
}

.payment-unpaid {
    background: #ffe5e5;
    color: #a00000;
}


/*
|--------------------------------------------------------------------------
| Empty / Error
|--------------------------------------------------------------------------
*/

.empty {
    padding: 50px 20px;
    text-align: center;
    color: #777;
}

.error {
    background: #ffe5e5;
    color: #a00000;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 1200px) {

    .summary {
        grid-template-columns:
            repeat(3, 1fr);
    }

}

@media (max-width: 900px) {

    .search-grid {
        grid-template-columns: 1fr 1fr;
    }

    .summary {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 600px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .search-grid {
        grid-template-columns: 1fr;
    }

    .summary {
        grid-template-columns: 1fr;
    }
    .search-btn {
    width: 85px;
    padding: 10px 14px;
}

}

</style>

</head>

<body>

<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>🧾 Sales History</h1>

            <p>
                Track sales, collections, outstanding balances and gross profit.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="index.php"
                class="button btn-light"
            >
                🛒 New Sale
            </a>

            <a
                href="outstanding.php"
                class="button btn-dark"
            >
                💳 Outstanding
            </a>

            <a
                href="../index.php"
                class="button btn-dark"
            >
                🏠 Dashboard
            </a>

        </div>

    </div>


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- SEARCH -->

    <form
        method="GET"
        class="search-card"
    >

        <div class="search-grid">


            <div class="form-group">

                <label>
                    Receipt Number
                </label>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="e.g. PF-20260830-000001"
                    style="width: 180px;"
                >
<button type="submit">
    🔍 Search
</button>


            </div>


            <div class="form-group">

                <label>
                    From
                </label>

                <input
                    type="date"
                    name="date_from"
                    value="<?= htmlspecialchars($dateFrom) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    To
                </label>

                <input
                    type="date"
                    name="date_to"
                    value="<?= htmlspecialchars($dateTo) ?>"
                >

            </div>


            <button
                type="submit"
                class="search-btn"
            >
                🔍 Search
            </button>


        </div>

    </form>


    <!-- SUMMARY -->

    <div class="summary">


        <div class="summary-card">

            <div class="summary-title">
                Transactions
            </div>

            <div class="summary-value">

                <?= number_format($totalSales) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Sales Value
            </div>

            <div class="summary-value">

                KSh
                <?= number_format(
                    $totalAmount,
                    2
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Amount Collected
            </div>

            <div class="summary-value">

                KSh
                <?= number_format(
                    $totalCollected,
                    2
                ) ?>

            </div>

        </div>


        <div class="summary-card outstanding">

            <div class="summary-title">
                Outstanding
            </div>

            <div class="summary-value">

                KSh
                <?= number_format(
                    $totalOutstanding,
                    2
                ) ?>

            </div>

        </div>


        <div class="summary-card profit">

            <div class="summary-title">
                Gross Profit
            </div>

            <div class="summary-value">

                KSh
                <?= number_format(
                    $totalGrossProfit,
                    2
                ) ?>

            </div>

        </div>


    </div>


    <!-- SALES TABLE -->

    <div class="table-card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Receipt
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Items
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Paid
                        </th>

                        <th>
                            Balance
                        </th>

                        <th>
                            Gross Profit
                        </th>

                        <th>
                            Payment Status
                        </th>

                        <th>
                            Sale Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($sales)): ?>

                    <tr>

                        <td
                            colspan="12"
                            class="empty"
                        >

                            🧾

                            <br><br>

                            No sales found.

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($sales as $sale): ?>

                        <?php

                        $saleType =
                            strtolower(
                                trim(
                                    $sale["sale_type"] ?? "cash"
                                )
                            );

                        $paymentStatus =
                            strtolower(
                                trim(
                                    $sale["display_payment_status"]
                                )
                            );

                        ?>


                        <tr>


                            <!-- RECEIPT -->

                            <td class="receipt-number">

                                <?= htmlspecialchars(
                                    $sale["receipt_number"]
                                    ?: "—"
                                ) ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= date(
                                    "d M Y H:i",
                                    strtotime(
                                        $sale["sale_date"]
                                    )
                                ) ?>

                            </td>


                            <!-- TYPE -->

                            <td class="sale-type">

                                <?php if ($saleType === "paylater"): ?>

                                    💳 Pay Later

                                <?php elseif ($saleType === "invoice"): ?>

                                    🧾 Invoice

                                <?php else: ?>

                                    💵 Cash

                                <?php endif; ?>

                            </td>


                            <!-- PAYMENT METHOD -->

                            <td class="payment">

                                <?= htmlspecialchars(
                                    $sale["payment_method"]
                                ) ?>

                            </td>


                            <!-- ITEMS -->

                            <td>

                                <?= number_format(
                                    (int)$sale["item_count"]
                                ) ?>

                            </td>


                            <!-- TOTAL -->

                            <td class="amount">

                                KSh
                                <?= number_format(
                                    (float)$sale["total_amount"],
                                    2
                                ) ?>

                            </td>


                            <!-- PAID -->

                            <td class="paid-amount">

                                KSh
                                <?= number_format(
                                    (float)$sale["amount_paid"],
                                    2
                                ) ?>

                            </td>


                            <!-- BALANCE -->

                            <td class="balance-amount">

                                <?php if (
                                    (float)$sale["balance"] > 0
                                ): ?>

                                    KSh
                                    <?= number_format(
                                        (float)$sale["balance"],
                                        2
                                    ) ?>

                                <?php else: ?>

                                    KSh 0.00

                                <?php endif; ?>

                            </td>


                            <!-- GROSS PROFIT -->

                            <td class="profit-amount">

                                KSh
                                <?= number_format(
                                    (float)$sale["gross_profit"],
                                    2
                                ) ?>

                            </td>


                            <!-- PAYMENT STATUS -->

                            <td>

                                <?php if (
                                    $paymentStatus === "paid"
                                ): ?>

                                    <span
                                        class="payment-status payment-paid"
                                    >
                                        PAID
                                    </span>

                                <?php elseif (
                                    $paymentStatus === "partial"
                                ): ?>

                                    <span
                                        class="payment-status payment-partial"
                                    >
                                        PARTIAL
                                    </span>

                                <?php elseif (
                                    $paymentStatus === "unpaid"
                                ): ?>

                                    <span
                                        class="payment-status payment-unpaid"
                                    >
                                        UNPAID
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="payment-status payment-unpaid"
                                    >
                                        <?= htmlspecialchars(
                                            strtoupper(
                                                $sale["display_payment_status"]
                                            )
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- SALE STATUS -->

                            <td>

                                <span
                                    class="status
                                    status-<?= htmlspecialchars(
                                        $sale["status"]
                                    ) ?>"
                                >

                                    <?= ucfirst(
                                        htmlspecialchars(
                                            $sale["status"]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <a
                                        href="view_sale.php?id=<?= (int)$sale["id"] ?>"
                                        class="action-btn view-btn"
                                    >
                                        👁 View
                                    </a>


                                    <a
                                        href="receipt.php?id=<?= (int)$sale["id"] ?>"
                                        class="action-btn receipt-btn"
                                    >
                                        🧾 Receipt
                                    </a>


                                    <?php if (
                                        $sale["status"] === "completed"
                                        &&
                                        (float)$sale["balance"] > 0
                                        &&
                                        in_array(
                                            $saleType,
                                            ["invoice", "paylater"],
                                            true
                                        )
                                    ): ?>

                                        <a
                                            href="record_payment.php?id=<?= (int)$sale["id"] ?>"
                                            class="action-btn payment-btn"
                                        >
                                            💰 Payment
                                        </a>

                                    <?php endif; ?>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>