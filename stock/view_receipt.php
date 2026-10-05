<?php

require_once "../config/database.php";

$receiptId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

$receipt = null;
$items = [];
$error = "";

try {

    if ($receiptId <= 0) {
        throw new Exception("Invalid stock receipt.");
    }


    /*
    |--------------------------------------------------------------------------
    | Get Stock Receipt
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            receipt_number,
            supplier_name,
            receipt_date,
            total_cost,
            notes,
            status
        FROM stock_receipts
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $receiptId
    ]);

    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$receipt) {
        throw new Exception("Stock receipt not found.");
    }


    /*
    |--------------------------------------------------------------------------
    | Get Receipt Items + Current Stock
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            sri.id,
            sri.product_id,
            sri.quantity,
            sri.buying_price,
            sri.subtotal,

            p.product_name,
            p.unit,
            p.stock_quantity,
            p.low_stock_level,
            p.is_active

        FROM stock_receipt_items sri

        LEFT JOIN products p
            ON sri.product_id = p.id

        WHERE sri.stock_receipt_id = :receipt_id

        ORDER BY sri.id ASC
    ");

    $stmt->execute([
        ":receipt_id" => $receiptId
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
    <?= $receipt
        ? "Stock Receipt " . htmlspecialchars(
            $receipt["receipt_number"]
        )
        : "Stock Receipt"
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
    max-width: 1250px;
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

    border:
        1px solid
        rgba(255,255,255,.3);
}

.btn-print {
    background: #1e3c72;
    color: white;
}


/*
|--------------------------------------------------------------------------
| Information Cards
|--------------------------------------------------------------------------
*/

.card {
    background: white;

    padding: 22px;

    border-radius: 14px;

    box-shadow:
        0 4px 15px
        rgba(0,0,0,.06);

    margin-bottom: 20px;
}

.card h2 {
    margin-top: 0;
}


/*
|--------------------------------------------------------------------------
| Receipt Information
|--------------------------------------------------------------------------
*/

.receipt-info {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;
}

.info-box {
    background: #f7f9fb;

    padding: 15px;

    border-radius: 9px;
}

.info-label {
    color: #777;

    font-size: 12px;

    margin-bottom: 6px;
}

.info-value {
    color: #1e3c72;

    font-weight: bold;

    word-break: break-word;
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
| Table
|--------------------------------------------------------------------------
*/

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

    padding: 13px;

    text-align: left;

    font-size: 12px;

    text-transform: uppercase;
}

td {
    padding: 14px 13px;

    border-bottom:
        1px solid
        #eee;

    font-size: 14px;

    vertical-align: middle;
}

tbody tr:hover {
    background: #fafbfd;
}

.product-name {
    color: #1e3c72;

    font-weight: bold;

    margin-bottom: 4px;
}

.unit-text {
    color: #888;

    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| Quantity
|--------------------------------------------------------------------------
*/

.quantity-received {
    font-weight: bold;
}

.stock-remaining {
    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| Stock Status
|--------------------------------------------------------------------------
*/

.stock-badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

    margin-top: 5px;
}

.stock-ok {
    background: #e3f7e8;
    color: #16702b;
}

.stock-low {
    background: #fff2d9;
    color: #9b6500;
}

.stock-out {
    background: #ffe5e5;
    color: #a00000;
}

.stock-inactive {
    background: #ececec;
    color: #666;
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

.total-row {
    border-top:
        2px solid
        #222;

    margin-top: 8px;

    padding-top: 13px;

    font-size: 20px;

    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| Notes
|--------------------------------------------------------------------------
*/

.notes {
    background: #f7f9fb;

    border-left:
        4px solid
        #1e3c72;

    padding: 14px;

    border-radius: 7px;

    color: #555;
}


/*
|--------------------------------------------------------------------------
| Error
|--------------------------------------------------------------------------
*/

.error {
    max-width: 650px;

    margin: 40px auto;

    background: #ffe5e5;

    color: #a00000;

    padding: 18px;

    border-radius: 8px;

    text-align: center;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .receipt-info {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 600px) {

    .header {
        flex-direction: column;

        align-items: flex-start;
    }

    .receipt-info {
        grid-template-columns:
            1fr;
    }

    .summary {
        max-width: 100%;
    }

}


/*
|--------------------------------------------------------------------------
| Print
|--------------------------------------------------------------------------
*/

@media print {

    body {
        background: white;
    }

    .header-actions {
        display: none;
    }

    .container {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .card {
        box-shadow: none;
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


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <div class="header">

        <div>

            <h1>
                📦 Stock Receipt
            </h1>

            <p>

                <?= htmlspecialchars(
                    $receipt["receipt_number"]
                ) ?>

            </p>

        </div>


        <div class="header-actions">

            <a
                href="history.php"
                class="button btn-secondary"
            >
                ← Stock History
            </a>


            <a
                href="add.php"
                class="button btn-light"
            >
                + Receive Stock
            </a>


            <button
                type="button"
                class="button btn-light"
                onclick="window.print()"
            >
                🖨️ Print
            </button>

        </div>

    </div>


    <!-- =========================================================
         RECEIPT INFORMATION
    ========================================================== -->

    <div class="card">

        <h2>
            Delivery Information
        </h2>

        <br>

        <div class="receipt-info">


            <div class="info-box">

                <div class="info-label">
                    Receipt Number
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $receipt["receipt_number"]
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Supplier
                </div>

                <div class="info-value">

                    <?= htmlspecialchars(
                        $receipt["supplier_name"]
                        ?: "Not specified"
                    ) ?>

                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Date
                </div>

                <div class="info-value">

                    <?= date(
                        "d M Y",
                        strtotime(
                            $receipt["receipt_date"]
                        )
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

                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
         PRODUCTS
    ========================================================== -->

    <div class="card">

        <h2>
            Products Received
        </h2>

        <br>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Stock Received
                        </th>

                        <th>
                            Remaining Stock
                        </th>

                        <th>
                            Buying Price
                        </th>

                        <th>
                            Subtotal
                        </th>

                        <th>
                            Stock Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($items)): ?>

                    <tr>

                        <td
                            colspan="6"
                            style="
                                text-align:center;
                                padding:40px;
                                color:#777;
                            "
                        >

                            No products were recorded
                            for this receipt.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($items as $item): ?>


                        <?php

                        $received =
                            (float)$item["quantity"];

                        $remaining =
                            (float)(
                                $item["stock_quantity"]
                                ?? 0
                            );

                        $unit =
                            $item["unit"] ?? "";

                        $lowLevel =
                            (float)(
                                $item["low_stock_level"]
                                ?? 0
                            );

                        $isActive =
                            (int)(
                                $item["is_active"]
                                ?? 0
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Determine stock status
                        |--------------------------------------------------------------------------
                        */

                        if (!$isActive) {

                            $stockStatus =
                                "Inactive";

                            $stockClass =
                                "stock-inactive";

                        } elseif ($remaining <= 0) {

                            $stockStatus =
                                "Out of Stock";

                            $stockClass =
                                "stock-out";

                        } elseif (
                            $remaining <= $lowLevel
                        ) {

                            $stockStatus =
                                "Low Stock";

                            $stockClass =
                                "stock-low";

                        } else {

                            $stockStatus =
                                "In Stock";

                            $stockClass =
                                "stock-ok";

                        }

                        ?>


                        <tr>


                            <!-- PRODUCT -->

                            <td>

                                <div
                                    class="product-name"
                                >

                                    <?= htmlspecialchars(
                                        $item["product_name"]
                                        ?: "Deleted Product"
                                    ) ?>

                                </div>

                                <div class="unit-text">

                                    Sold by:
                                    <?= htmlspecialchars(
                                        $unit
                                    ) ?>

                                </div>

                            </td>


                            <!-- RECEIVED -->

                            <td>

                                <div
                                    class="quantity-received"
                                >

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $received,
                                                3,
                                                ".",
                                                ""
                                            ),
                                            "0"
                                        ),
                                        "."
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $unit
                                    ) ?>

                                </div>

                            </td>


                            <!-- REMAINING -->

                            <td>

                                <div
                                    class="stock-remaining"
                                >

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $remaining,
                                                3,
                                                ".",
                                                ""
                                            ),
                                            "0"
                                        ),
                                        "."
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $unit
                                    ) ?>

                                </div>

                            </td>


                            <!-- BUYING PRICE -->

                            <td>

                                KSh

                                <?= number_format(
                                    (float)$item[
                                        "buying_price"
                                    ],
                                    2
                                ) ?>

                            </td>


                            <!-- SUBTOTAL -->

                            <td>

                                <strong>

                                    KSh

                                    <?= number_format(
                                        (float)$item[
                                            "subtotal"
                                        ],
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="stock-badge
                                    <?= $stockClass ?>"
                                >

                                    <?= $stockStatus ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


    <!-- =========================================================
         FINANCIAL SUMMARY
    ========================================================== -->

    <div class="card">

        <h2>
            Purchase Summary
        </h2>

        <br>

        <div class="summary">

            <div class="summary-row">

                <span>
                    Products
                </span>

                <strong>

                    <?= number_format(
                        count($items)
                    ) ?>

                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Supplier
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $receipt["supplier_name"]
                        ?: "Not specified"
                    ) ?>

                </strong>

            </div>


            <div class="summary-row total-row">

                <span>
                    Total Cost
                </span>

                <strong>

                    KSh

                    <?= number_format(
                        (float)$receipt[
                            "total_cost"
                        ],
                        2
                    ) ?>

                </strong>

            </div>

        </div>


        <?php if (
            !empty($receipt["notes"])
        ): ?>

            <br>

            <div class="notes">

                <strong>
                    Notes:
                </strong>

                <br><br>

                <?= nl2br(
                    htmlspecialchars(
                        $receipt["notes"]
                    )
                ) ?>

            </div>

        <?php endif; ?>

    </div>


<?php endif; ?>


</div>

</body>

</html>