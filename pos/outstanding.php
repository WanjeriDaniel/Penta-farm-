<?php

require_once "../config/database.php";

$sales = [];
$totalOutstanding = 0;
$totalCreditSales = 0;
$error = "";

try {

    /*
    |--------------------------------------------------------------------------
    | Outstanding Invoice / Pay Later Sales
    |--------------------------------------------------------------------------
    |
    | Original amount_paid is included because an invoice may have been
    | partially paid at the time of sale.
    |
    | Additional payments are stored in sale_payments.
    |
    */

    $sql = "
        SELECT
            s.id,
            s.receipt_number,
            s.sale_type,
            s.customer_name,
            s.customer_phone,
            s.sale_date,
            s.due_date,
            s.total_amount,
            s.amount_paid AS original_amount_paid,
            COALESCE(
                SUM(sp.amount),
                0
            ) AS additional_paid,

            (
                s.amount_paid
                +
                COALESCE(SUM(sp.amount), 0)
            ) AS current_amount_paid,

            GREATEST(
                s.total_amount -
                (
                    s.amount_paid
                    +
                    COALESCE(SUM(sp.amount), 0)
                ),
                0
            ) AS balance

        FROM sales s

        LEFT JOIN sale_payments sp
            ON sp.sale_id = s.id

        WHERE
            s.sale_type IN ('invoice', 'paylater')
            AND s.status = 'completed'

        GROUP BY
            s.id,
            s.receipt_number,
            s.sale_type,
            s.customer_name,
            s.customer_phone,
            s.sale_date,
            s.due_date,
            s.total_amount,
            s.amount_paid

        HAVING
            balance > 0

        ORDER BY
            CASE
                WHEN s.due_date IS NULL THEN 1
                WHEN s.due_date < CURDATE() THEN 0
                ELSE 2
            END,
            s.due_date ASC,
            s.sale_date ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute();

    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    $totalCreditSales = count($sales);

    foreach ($sales as $sale) {

        $totalOutstanding +=
            (float)$sale["balance"];

    }

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

<title>Outstanding Credit - Penta Farm</title>

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

    max-width: 1350px;

    margin: 25px auto;
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

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
| Summary
|--------------------------------------------------------------------------
*/

.summary {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

    margin-bottom: 20px;
}

.summary-card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.05);
}

.summary-title {

    color: #666;

    font-size: 13px;

    margin-bottom: 7px;
}

.summary-value {

    color: #1e3c72;

    font-size: 24px;

    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-card {

    background: white;

    border-radius: 14px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.06);

    overflow: hidden;
}

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1150px;
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

    border-bottom:
        1px solid #eee;

    font-size: 14px;
}

tr:hover {

    background: #fafbfd;
}


/*
|--------------------------------------------------------------------------
| Receipt
|--------------------------------------------------------------------------
*/

.receipt-number {

    font-weight: bold;

    color: #1e3c72;
}


/*
|--------------------------------------------------------------------------
| Customer
|--------------------------------------------------------------------------
*/

.customer-name {

    font-weight: bold;
}

.customer-phone {

    color: #777;

    font-size: 12px;

    margin-top: 3px;
}


/*
|--------------------------------------------------------------------------
| Sale Type
|--------------------------------------------------------------------------
*/

.sale-type {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

    text-transform: uppercase;
}

.type-invoice {

    background: #edf3ff;

    color: #1e3c72;
}

.type-paylater {

    background: #fff2d9;

    color: #9b6500;
}


/*
|--------------------------------------------------------------------------
| Amounts
|--------------------------------------------------------------------------
*/

.amount {

    font-weight: bold;
}

.paid {

    color: #16702b;

    font-weight: bold;
}

.balance {

    color: #a00000;

    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| Due Date
|--------------------------------------------------------------------------
*/

.due-date {

    font-weight: bold;
}

.overdue {

    color: #a00000;
}

.due-soon {

    color: #9b6500;
}

.not-overdue {

    color: #16702b;
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

.status-unpaid {

    background: #ffe5e5;

    color: #a00000;
}

.status-partial {

    background: #fff2d9;

    color: #9b6500;
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

.payment-btn {

    background: #eaf7ee;

    color: #16702b;
}


/*
|--------------------------------------------------------------------------
| Empty / Error
|--------------------------------------------------------------------------
*/

.empty {

    padding: 60px 20px;

    text-align: center;

    color: #777;
}

.empty-icon {

    font-size: 45px;

    margin-bottom: 10px;
}

.empty-title {

    font-size: 20px;

    font-weight: bold;

    color: #444;

    margin-bottom: 5px;
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

@media (max-width: 600px) {

    .header {

        flex-direction: column;

        align-items: flex-start;
    }

    .summary {

        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>💳 Outstanding Credit</h1>

            <p>
                Track unpaid and partially paid customer balances.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="history.php"
                class="button btn-light"
            >
                🧾 Sales History
            </a>

            <a
                href="index.php"
                class="button btn-dark"
            >
                🛒 New Sale
            </a>

        </div>

    </div>


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- SUMMARY -->

    <div class="summary">

        <div class="summary-card">

            <div class="summary-title">
                Customers / Credit Sales Outstanding
            </div>

            <div class="summary-value">

                <?= number_format(
                    $totalCreditSales
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Total Outstanding
            </div>

            <div class="summary-value">

                KSh
                <?= number_format(
                    $totalOutstanding,
                    2
                ) ?>

            </div>

        </div>

    </div>


    <!-- TABLE -->

    <div class="table-card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Receipt
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Sale Date
                        </th>

                        <th>
                            Due Date
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
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($sales)): ?>

                    <tr>

                        <td
                            colspan="10"
                            class="empty"
                        >

                            <div class="empty-icon">
                                ✅
                            </div>

                            <div class="empty-title">
                                No Outstanding Credit
                            </div>

                            <div>
                                All invoice and Pay Later
                                sales are fully settled.
                            </div>

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($sales as $sale): ?>

                        <?php

                        $balance =
                            (float)$sale["balance"];

                        $paid =
                            (float)$sale["current_amount_paid"];

                        $total =
                            (float)$sale["total_amount"];

                        $dueDate =
                            $sale["due_date"];

                        $today =
                            date("Y-m-d");

                        $dueClass =
                            "not-overdue";

                        $dueLabel =
                            "On time";


                        if ($dueDate) {

                            if ($dueDate < $today) {

                                $dueClass =
                                    "overdue";

                                $dueLabel =
                                    "OVERDUE";

                            } elseif (
                                $dueDate === $today
                            ) {

                                $dueClass =
                                    "due-soon";

                                $dueLabel =
                                    "DUE TODAY";

                            } else {

                                $daysRemaining =
                                    (
                                        strtotime($dueDate)
                                        -
                                        strtotime($today)
                                    ) / 86400;

                                if (
                                    $daysRemaining <= 3
                                ) {

                                    $dueClass =
                                        "due-soon";

                                    $dueLabel =
                                        "DUE SOON";

                                }

                            }

                        }


                        $status =
                            $paid <= 0
                                ? "unpaid"
                                : "partial";

                        ?>


                        <tr>


                            <!-- RECEIPT -->

                            <td class="receipt-number">

                                <?= htmlspecialchars(
                                    $sale["receipt_number"]
                                    ?: "—"
                                ) ?>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-name">

                                    <?= htmlspecialchars(
                                        $sale["customer_name"]
                                        ?: "Unknown Customer"
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $sale["customer_phone"]
                                    )
                                ): ?>

                                    <div class="customer-phone">

                                        <?= htmlspecialchars(
                                            $sale["customer_phone"]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- TYPE -->

                            <td>

                                <span
                                    class="sale-type
                                    type-<?=
                                        htmlspecialchars(
                                            $sale["sale_type"]
                                        )
                                    ?>"
                                >

                                    <?= htmlspecialchars(
                                        $sale["sale_type"]
                                        === "paylater"
                                            ? "Pay Later"
                                            : "Invoice"
                                    ) ?>

                                </span>

                            </td>


                            <!-- SALE DATE -->

                            <td>

                                <?= date(
                                    "d M Y",
                                    strtotime(
                                        $sale["sale_date"]
                                    )
                                ) ?>

                            </td>


                            <!-- DUE DATE -->

                            <td>

                                <?php if ($dueDate): ?>

                                    <div
                                        class="due-date
                                        <?= $dueClass ?>"
                                    >

                                        <?= date(
                                            "d M Y",
                                            strtotime(
                                                $dueDate
                                            )
                                        ) ?>

                                    </div>


                                    <div
                                        style="
                                            font-size:11px;
                                            margin-top:3px;
                                        "
                                        class="<?= $dueClass ?>"
                                    >

                                        <?= $dueLabel ?>

                                    </div>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <!-- TOTAL -->

                            <td class="amount">

                                KSh
                                <?= number_format(
                                    $total,
                                    2
                                ) ?>

                            </td>


                            <!-- PAID -->

                            <td class="paid">

                                KSh
                                <?= number_format(
                                    $paid,
                                    2
                                ) ?>

                            </td>


                            <!-- BALANCE -->

                            <td class="balance">

                                KSh
                                <?= number_format(
                                    $balance,
                                    2
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status
                                    status-<?= $status ?>"
                                >

                                    <?= strtoupper(
                                        $status
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <div class="actions">

                                    <a
                                        href="view_sale.php?id=<?= 
                                            (int)$sale["id"]
                                        ?>"
                                        class="action-btn view-btn"
                                    >
                                        👁 View
                                    </a>


                                    <a
                                        href="record_payment.php?id=<?= 
                                            (int)$sale["id"]
                                        ?>"
                                        class="action-btn payment-btn"
                                    >
                                        💰 Record Payment
                                    </a>

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