<?php

require_once "../config/database.php";

$saleId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

$sale = null;
$items = [];
$error = "";

try {

    if ($saleId <= 0) {
        throw new Exception("Invalid sale.");
    }


    /*
    |--------------------------------------------------------------------------
    | Get Sale
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            receipt_number,
            sale_date,
            subtotal,
            discount,
            total_amount,
            payment_method,
            amount_paid,
            change_amount,
            status
        FROM sales
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $saleId
    ]);

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$sale) {
        throw new Exception("Sale not found.");
    }


    /*
    |--------------------------------------------------------------------------
    | Get Sale Items
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            si.id,
            si.product_id,
            si.quantity,
            si.unit_price,
            si.buying_price,
            si.subtotal,
            p.product_name,
            p.unit
        FROM sale_items si
        LEFT JOIN products p
            ON si.product_id = p.id
        WHERE si.sale_id = :sale_id
        ORDER BY si.id ASC
    ");

    $stmt->execute([
        ":sale_id" => $saleId
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

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

<title>
    <?= $sale
        ? "Sale " . htmlspecialchars($sale["receipt_number"])
        : "Sale Details"
    ?>
</title>


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
    width: 95%;
    max-width: 1100px;
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
    padding: 23px;
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
    border: none;
    padding: 10px 15px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

.btn-light {
    background: white;
    color: #1e3c72;
}

.btn-secondary {
    background: rgba(255,255,255,.15);
    color: white;
    border: 1px solid rgba(255,255,255,.3);
}

.btn-print {
    background: #1e3c72;
    color: white;
}


/*
|--------------------------------------------------------------------------
| Main Card
|--------------------------------------------------------------------------
*/

.card {
    background: white;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,.06);
    padding: 22px;
    margin-bottom: 20px;
}


/*
|--------------------------------------------------------------------------
| Sale information
|--------------------------------------------------------------------------
*/

.sale-info {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);
    gap: 15px;
}

.info-box {
    background: #f7f9fb;
    border-radius: 9px;
    padding: 15px;
}

.info-label {
    color: #777;
    font-size: 12px;
    margin-bottom: 6px;
}

.info-value {
    font-weight: bold;
    color: #1e3c72;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.status {
    display: inline-block;
    padding: 6px 10px;
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
| Items
|--------------------------------------------------------------------------
*/

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f5f6f8;
    color: #555;
    text-align: left;
    padding: 12px;
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 13px 12px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.product {
    font-weight: bold;
    color: #1e3c72;
}

.muted {
    color: #777;
    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

.summary {
    max-width: 450px;
    margin-left: auto;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 8px 0;
}

.total {
    border-top: 2px solid #222;
    margin-top: 8px;
    padding-top: 14px;
    font-size: 20px;
    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| Profit
|--------------------------------------------------------------------------
*/

.profit-box {
    background: #f5f9ff;
    border-radius: 10px;
    padding: 17px;
    margin-top: 20px;
}

.profit-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 15px;
}

.profit-label {
    color: #777;
    font-size: 12px;
    margin-bottom: 5px;
}

.profit-value {
    font-size: 18px;
    font-weight: bold;
    color: #1e3c72;
}


/*
|--------------------------------------------------------------------------
| Empty / Error
|--------------------------------------------------------------------------
*/

.empty {
    text-align: center;
    padding: 40px;
    color: #777;
}

.error {
    max-width: 600px;
    margin: 40px auto;
    background: #ffe5e5;
    color: #a00000;
    padding: 18px;
    border-radius: 8px;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 850px) {

    .sale-info {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 600px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .sale-info {
        grid-template-columns: 1fr;
    }

    .profit-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>

<div class="container">


<?php if ($error): ?>

    <div class="error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php else: ?>


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>🧾 Sale Details</h1>

            <p>
                <?= htmlspecialchars(
                    $sale["receipt_number"]
                ) ?>
            </p>

        </div>


        <div class="header-actions">

            <a
                href="history.php"
                class="button btn-secondary"
            >
                ← Sales History
            </a>


            <a
                href="receipt.php?id=<?= (int)$sale["id"] ?>"
                class="button btn-light"
            >
                🧾 Receipt
            </a>

        </div>

    </div>


    <!-- SALE INFORMATION -->

    <div class="card">

        <h2>Transaction Information</h2>

        <br>

        <div class="sale-info">


            <div class="info-box">

                <div class="info-label">
                    Receipt Number
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $sale["receipt_number"]
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Date & Time
                </div>

                <div class="info-value">

                    <?= date(
                        "d M Y, H:i",
                        strtotime(
                            $sale["sale_date"]
                        )
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Payment Method
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $sale["payment_method"]
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Status
                </div>

                <div class="info-value">

                    <span
                        class="status status-<?=
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

                </div>

            </div>

        </div>

    </div>


    <!-- ITEMS -->

    <div class="card">

        <h2>Products Sold</h2>

        <br>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Quantity
                        </th>

                        <th>
                            Selling Price
                        </th>

                        <th>
                            Buying Price
                        </th>

                        <th>
                            Subtotal
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($items)): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="empty"
                        >

                            No sale items found.

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($items as $item): ?>

                        <tr>

                            <td>

                                <div class="product">

                                    <?= htmlspecialchars(
                                        $item["product_name"]
                                        ?? "Deleted Product"
                                    ) ?>

                                </div>

                                <div class="muted">

                                    <?= htmlspecialchars(
                                        $item["unit"] ?? ""
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <?= rtrim(
                                    rtrim(
                                        number_format(
                                            (float)$item["quantity"],
                                            3,
                                            ".",
                                            ""
                                        ),
                                        "0"
                                    ),
                                    "."
                                ) ?>

                                <?= htmlspecialchars(
                                    $item["unit"] ?? ""
                                ) ?>

                            </td>


                            <td>

                                KSh
                                <?= number_format(
                                    (float)$item["unit_price"],
                                    2
                                ) ?>

                            </td>


                            <td>

                                KSh
                                <?= number_format(
                                    (float)$item["buying_price"],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    KSh
                                    <?= number_format(
                                        (float)$item["subtotal"],
                                        2
                                    ) ?>

                                </strong>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- FINANCIAL SUMMARY -->

    <div class="card">

        <h2>Financial Summary</h2>

        <br>

        <div class="summary">


            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <strong>

                    KSh
                    <?= number_format(
                        (float)$sale["subtotal"],
                        2
                    ) ?>

                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Discount
                </span>

                <strong>

                    KSh
                    <?= number_format(
                        (float)$sale["discount"],
                        2
                    ) ?>

                </strong>

            </div>


            <div class="summary-row total">

                <span>
                    Total
                </span>

                <strong>

                    KSh
                    <?= number_format(
                        (float)$sale["total_amount"],
                        2
                    ) ?>

                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Amount Paid
                </span>

                <strong>

                    KSh
                    <?= number_format(
                        (float)$sale["amount_paid"],
                        2
                    ) ?>

                </strong>

            </div>

        </div>


        <?php

        /*
        |--------------------------------------------------------------------------
        | Calculate Profit
        |--------------------------------------------------------------------------
        */

        $totalCost = 0;

        foreach ($items as $item) {

            $totalCost +=
                (float)$item["quantity"] *
                (float)$item["buying_price"];

        }

        $grossProfit =
            (float)$sale["total_amount"] -
            $totalCost;

        ?>


        <div class="profit-box">

            <div class="profit-grid">


                <div>

                    <div class="profit-label">
                        Total Cost
                    </div>

                    <div class="profit-value">

                        KSh
                        <?= number_format(
                            $totalCost,
                            2
                        ) ?>

                    </div>

                </div>


                <div>

                    <div class="profit-label">
                        Gross Profit
                    </div>

                    <div class="profit-value">

                        KSh
                        <?= number_format(
                            $grossProfit,
                            2
                        ) ?>

                    </div>

                </div>


                <div>

                    <div class="profit-label">
                        Payment
                    </div>

                    <div class="profit-value">

                        <?= htmlspecialchars(
                            $sale["payment_method"]
                        ) ?>

                    </div>

                </div>


            </div>

        </div>

    </div>


<?php endif; ?>

</div>

</body>

</html>