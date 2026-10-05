<?php

require_once "../config/database.php";

$search = trim($_GET["search"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");

$sales = [];
$totalSales = 0;
$totalAmount = 0;
$error = "";

try {

    /*
    |--------------------------------------------------------------------------
    | Build query
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            s.id,
            s.receipt_number,
            s.sale_date,
            s.total_amount,
            s.payment_method,
            s.amount_paid,
            s.change_amount,
            s.status,
            COUNT(si.id) AS item_count
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
            AND s.receipt_number LIKE :search
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


    $sql .= "
        GROUP BY
            s.id,
            s.receipt_number,
            s.sale_date,
            s.total_amount,
            s.payment_method,
            s.amount_paid,
            s.change_amount,
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

    foreach ($sales as $sale) {

        if ($sale["status"] === "completed") {

            $totalAmount +=
                (float)$sale["total_amount"];

        }

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
    max-width: 1350px;
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
    grid-template-columns: repeat(2, 1fr);
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
    box-shadow: 0 4px 15px rgba(0,0,0,.06);
    overflow: hidden;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
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

.payment {
    font-weight: bold;
}

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

@media (max-width: 900px) {

    .search-grid {
        grid-template-columns: 1fr 1fr;
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
                Find previous transactions and receipts quickly.
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
                >

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
                Transactions Found
            </div>

            <div class="summary-value">

                <?= number_format($totalSales) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Completed Sales Value
            </div>

            <div class="summary-value">

                KSh
                <?= number_format(
                    $totalAmount,
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
                            Payment
                        </th>

                        <th>
                            Items
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Status
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
                            colspan="7"
                            class="empty"
                        >

                            🧾

                            <br><br>

                            No sales found.

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($sales as $sale): ?>

                        <tr>


                            <td class="receipt-number">

                                <?= htmlspecialchars(
                                    $sale["receipt_number"]
                                    ?: "—"
                                ) ?>

                            </td>


                            <td>

                                <?= date(
                                    "d M Y H:i",
                                    strtotime(
                                        $sale["sale_date"]
                                    )
                                ) ?>

                            </td>


                            <td class="payment">

                                <?= htmlspecialchars(
                                    $sale["payment_method"]
                                ) ?>

                            </td>


                            <td>

                                <?= number_format(
                                    (int)$sale["item_count"]
                                ) ?>

                            </td>


                            <td class="amount">

                                KSh
                                <?= number_format(
                                    (float)$sale["total_amount"],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="status
                                    status-<?=
                                        htmlspecialchars(
                                            $sale["status"]
                                        )
                                    ?>"
                                >

                                    <?= ucfirst(
                                        htmlspecialchars(
                                            $sale["status"]
                                        )
                                    ) ?>

                                </span>

                            </td>


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
                                        href="receipt.php?id=<?=
                                            (int)$sale["id"]
                                        ?>"
                                        class="action-btn receipt-btn"
                                    >
                                        🧾 Receipt
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