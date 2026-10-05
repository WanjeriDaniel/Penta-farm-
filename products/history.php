<?php

require_once "../config/database.php";

$history = [];
$message = "";

try {

    $sql = "
        SELECT
            ph.id,
            ph.product_id,
            p.product_name,
            p.unit,
            ph.old_buying_price,
            ph.new_buying_price,
            ph.old_selling_price,
            ph.new_selling_price,
            ph.created_at
        FROM price_history ph

        INNER JOIN products p
            ON ph.product_id = p.id

        ORDER BY ph.created_at DESC
    ";

    $stmt = $pdo->query($sql);

    $history = $stmt->fetchAll();

} catch (PDOException $e) {

       $message = "Unable to load price history.";

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Penta Farm | Price History</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f6;
            color: #333;
            min-height: 100vh;
        }


        /* Header */

        .header {
            background: linear-gradient(135deg, #176b3a, #249653);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.12);
        }


        .header h1 {
            font-size: 26px;
            margin-bottom: 4px;
        }


        .header p {
            font-size: 14px;
            opacity: 0.9;
        }


        /* Main */

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }


        /* Heading */

        .page-heading {
            margin-bottom: 25px;
        }


        .page-heading h2 {
            color: #176b3a;
            font-size: 26px;
            margin-bottom: 7px;
        }


        .page-heading p {
            color: #777;
            font-size: 14px;
        }


        /* Actions */

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }


        .button {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s;
        }


        .primary {
            background: #176b3a;
            color: white;
        }


        .primary:hover {
            background: #12552e;
        }


        .secondary {
            background: white;
            color: #176b3a;
            border: 1px solid #176b3a;
        }


        .secondary:hover {
            background: #176b3a;
            color: white;
        }


        /* Card */

        .history-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }


        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
        }


        .card-header h3 {
            color: #176b3a;
            font-size: 18px;
        }


        .card-header p {
            color: #777;
            font-size: 13px;
            margin-top: 5px;
        }


        /* Table */

        .table-container {
            overflow-x: auto;
        }


        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }


        th {
            background: #f0f5f2;
            color: #444;
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }


        td {
            padding: 15px 16px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }


        tbody tr:hover {
            background: #f8fbf9;
        }


        .product-name {
            color: #176b3a;
            font-weight: bold;
        }


        .old-price {
            color: #888;
        }


        .new-price {
            color: #176b3a;
            font-weight: bold;
        }


        .arrow {
            color: #aaa;
            margin: 0 5px;
        }


        .date {
            color: #666;
            font-size: 13px;
        }


        /* Error */

        .error {
            background: #fff0f0;
            color: #c62828;
            border: 1px solid #f0bcbc;
            padding: 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }


        /* Empty */

        .empty {
            padding: 50px 20px;
            text-align: center;
        }


        .empty h3 {
            color: #555;
            margin-bottom: 8px;
        }


        .empty p {
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
        }


        /* Mobile */

        @media (max-width: 600px) {

            .header {
                padding: 18px 20px;
            }


            .header h1 {
                font-size: 22px;
            }


            .container {
                margin: 30px auto;
                padding: 0 15px;
            }


            .page-heading h2 {
                font-size: 22px;
            }


            .actions {
                flex-direction: column;
                align-items: stretch;
            }


            .button {
                text-align: center;
                width: 100%;
            }

        }

    </style>

</head>


<body>


<header class="header">

    <h1>🌱 Penta Farm</h1>

    <p>Inventory Management System</p>

</header>


<main class="container">


    <div class="page-heading">

        <h2>Price History</h2>

        <p>
            Track previous buying and selling price changes.
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <div class="actions">

        <a href="../index.php" class="button primary">
            ← Main Menu
        </a>

        <a href="list.php" class="button secondary">
            📦 View Products
        </a>

    </div>


    <section class="history-card">


        <div class="card-header">

            <h3>Price Change Records</h3>

            <p>
                <?php echo count($history); ?> price change(s) recorded.
            </p>

        </div>


        <?php if (count($history) > 0): ?>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Product</th>

                            <th>Unit</th>

                            <th>Buying Price Change</th>

                            <th>Selling Price Change</th>

                            <th>Date</th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($history as $record): ?>

                            <tr>


                                <td class="product-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $record["product_name"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $record["unit"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span class="old-price">

                                        KSh
                                        <?php
                                        echo number_format(
                                            $record["old_buying_price"],
                                            2
                                        );
                                        ?>

                                    </span>


                                    <span class="arrow">
                                        →
                                    </span>


                                    <span class="new-price">

                                        KSh
                                        <?php
                                        echo number_format(
                                            $record["new_buying_price"],
                                            2
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="old-price">

                                        KSh
                                        <?php
                                        echo number_format(
                                            $record["old_selling_price"],
                                            2
                                        );
                                        ?>

                                    </span>


                                    <span class="arrow">
                                        →
                                    </span>


                                    <span class="new-price">

                                        KSh
                                        <?php
                                        echo number_format(
                                            $record["new_selling_price"],
                                            2
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td class="date">

                                    <?php
                                    echo htmlspecialchars(
                                        $record["created_at"]
                                    );
                                    ?>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty">

                <h3>📊 No Price Changes Yet</h3>

                <p>
                    Price history will appear here whenever a product's
                    buying or selling price is updated.
                </p>

                <a href="list.php" class="button primary">
                    View Products
                </a>

            </div>


        <?php endif; ?>


    </section>


</main>


</body>

</html>
