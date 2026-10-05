<?php

require_once "../config/database.php";

$search = trim($_GET["search"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");

$receipts = [];
$totalReceipts = 0;
$totalCost = 0;
$error = "";

try {

    /*
    |--------------------------------------------------------------------------
    | Build Query
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            sr.id,
            sr.receipt_number,
            sr.supplier_name,
            sr.receipt_date,
            sr.total_cost,
            sr.status,
            COUNT(DISTINCT sri.id) AS item_count,

            GROUP_CONCAT(
                DISTINCT p.product_name
                ORDER BY p.product_name
                SEPARATOR ', '
            ) AS product_names

        FROM stock_receipts sr

        LEFT JOIN stock_receipt_items sri
            ON sri.stock_receipt_id = sr.id

        LEFT JOIN products p
            ON sri.product_id = p.id

        WHERE 1 = 1
    ";

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | Search Receipt / Supplier / Product
    |--------------------------------------------------------------------------
    */

    if ($search !== "") {

        $sql .= "
            AND (
                sr.receipt_number LIKE :search
                OR sr.supplier_name LIKE :search
                OR p.product_name LIKE :search
            )
        ";

        $params[":search"] =
            "%" . $search . "%";
    }


    /*
    |--------------------------------------------------------------------------
    | From Date
    |--------------------------------------------------------------------------
    */

    if ($dateFrom !== "") {

        $sql .= "
            AND DATE(sr.receipt_date) >= :date_from
        ";

        $params[":date_from"] =
            $dateFrom;
    }


    /*
    |--------------------------------------------------------------------------
    | To Date
    |--------------------------------------------------------------------------
    */

    if ($dateTo !== "") {

        $sql .= "
            AND DATE(sr.receipt_date) <= :date_to
        ";

        $params[":date_to"] =
            $dateTo;
    }


    /*
    |--------------------------------------------------------------------------
    | Group & Order
    |--------------------------------------------------------------------------
    */

    $sql .= "
        GROUP BY
            sr.id,
            sr.receipt_number,
            sr.supplier_name,
            sr.receipt_date,
            sr.total_cost,
            sr.status

        ORDER BY
            sr.receipt_date DESC,
            sr.id DESC
    ";


    /*
    |--------------------------------------------------------------------------
    | Execute Query
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $receipts =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    $totalReceipts =
        count($receipts);


    foreach ($receipts as $receipt) {

        if ($receipt["status"] === "received") {

            $totalCost +=
                (float)$receipt["total_cost"];

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

<title>Stock History - Penta Farm</title>


<style>

/*
|--------------------------------------------------------------------------
| Global
|--------------------------------------------------------------------------
*/

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
    gap: 8px;
    flex-wrap: wrap;
}


/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

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

    border:
        1px solid
        rgba(255,255,255,.3);
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

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.06);

    margin-bottom: 20px;
}

.search-grid {
    display: grid;

    grid-template-columns:
        2fr 1fr 1fr auto;

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

    border:
        1px solid
        #ccc;

    border-radius: 7px;

    font-size: 14px;
}

.form-group input:focus {
    outline: none;

    border-color:
        #2a5298;
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

.search-btn:hover {
    background: #16315f;
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
        0 4px 15px
        rgba(0,0,0,.05);
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
        0 4px 15px
        rgba(0,0,0,.06);

    overflow: hidden;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}

th {
    background: #f5f6f8;

    color: #555;

    text-align: left;

    padding: 13px;

    font-size: 12px;

    text-transform: uppercase;
}

td {
    padding: 14px 13px;

    border-bottom:
        1px solid
        #eee;

    font-size: 14px;

    vertical-align: top;
}

tbody tr {
    transition:
        background .2s ease;
}

tbody tr:hover {
    background: #fafbfd;
}


/*
|--------------------------------------------------------------------------
| Product Information
|--------------------------------------------------------------------------
*/

.product-names {
    color: #1e3c72;

    font-weight: bold;

    line-height: 1.5;

    max-width: 330px;
}

.receipt-number-small {
    color: #888;

    font-size: 11px;

    margin-top: 6px;
}


/*
|--------------------------------------------------------------------------
| Amount
|--------------------------------------------------------------------------
*/

.amount {
    font-weight: bold;

    color: #222;
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

.status-received {
    background: #e3f7e8;

    color: #16702b;
}

.status-cancelled {
    background: #ffe5e5;

    color: #a00000;
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

.view-btn:hover {
    background: #dfe9ff;
}


/*
|--------------------------------------------------------------------------
| Empty / Error
|--------------------------------------------------------------------------
*/

.empty {
    text-align: center;

    padding: 50px 20px;

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
        grid-template-columns:
            1fr 1fr;
    }

}

@media (max-width: 600px) {

    .header {
        flex-direction: column;

        align-items: flex-start;
    }

    .search-grid {
        grid-template-columns:
            1fr;
    }

    .summary {
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
                📦 Stock History
            </h1>

            <p>
                Find previous stock deliveries and purchases.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="add.php"
                class="button btn-light"
            >
                + Receive Stock
            </a>


            <a
                href="index.php"
                class="button btn-dark"
            >
                📊 Stock Dashboard
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
         SEARCH
    ========================================================== -->

    <form
        method="GET"
        class="search-card"
    >

        <div class="search-grid">


            <div class="form-group">

                <label>
                    Search Receipt, Supplier or Product
                </label>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="e.g. Nails, ABC Hardware or SR-000001"
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


    <!-- =========================================================
         SUMMARY
    ========================================================== -->

    <div class="summary">


        <div class="summary-card">

            <div class="summary-title">

                Receipts Found

            </div>

            <div class="summary-value">

                <?= number_format(
                    $totalReceipts
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">

                Total Stock Cost

            </div>

            <div class="summary-value">

                KSh

                <?= number_format(
                    $totalCost,
                    2
                ) ?>

            </div>

        </div>


    </div>


    <!-- =========================================================
         RECEIPTS TABLE
    ========================================================== -->

    <div class="table-card">

        <div class="table-wrapper">

            <table>


                <thead>

                    <tr>

                        <th>
                            Products Received
                        </th>

                        <th>
                            Supplier
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Items
                        </th>

                        <th>
                            Total Cost
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


                <?php if (empty($receipts)): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >

                            📦

                            <br><br>

                            No stock receipts found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $receipts
                        as $receipt
                    ): ?>

                        <tr>


                            <!-- PRODUCTS -->

                            <td>

                                <div class="product-names">

                                    <?= htmlspecialchars(
                                        $receipt["product_names"]
                                        ?: "No product information"
                                    ) ?>

                                </div>


                                <div
                                    class="receipt-number-small"
                                >

                                    Receipt:

                                    <?= htmlspecialchars(
                                        $receipt[
                                            "receipt_number"
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <!-- SUPPLIER -->

                            <td>

                                <?= htmlspecialchars(
                                    $receipt["supplier_name"]
                                    ?: "—"
                                ) ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= date(
                                    "d M Y H:i",
                                    strtotime(
                                        $receipt["receipt_date"]
                                    )
                                ) ?>

                            </td>


                            <!-- ITEMS -->

                            <td>

                                <?= number_format(
                                    (int)$receipt[
                                        "item_count"
                                    ]
                                ) ?>

                            </td>


                            <!-- TOTAL -->

                            <td class="amount">

                                KSh

                                <?= number_format(
                                    (float)$receipt[
                                        "total_cost"
                                    ],
                                    2
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status status-<?=
                                        htmlspecialchars(
                                            $receipt["status"]
                                        )
                                    ?>"
                                >

                                    <?= ucfirst(
                                        htmlspecialchars(
                                            $receipt["status"]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <div class="actions">

                                    <a
                                        href="view_receipt.php?id=<?=
                                            (int)$receipt["id"]
                                        ?>"
                                        class="action-btn view-btn"
                                    >

                                        👁 View

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
```
