<?php

require_once "../config/database.php";

$products = [];
$message = "";

try {

    $sql = "
        SELECT
            id,
            product_name,
            unit,
            buying_price,
            selling_price,
            (selling_price - buying_price) AS profit,
            updated_at
        FROM products
        ORDER BY product_name ASC
    ";

    $stmt = $pdo->query($sql);

    $products = $stmt->fetchAll();

} catch (PDOException $e) {

    $message = "Unable to load products.";

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Penta Farm | Products</title>

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

        .products-card {
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
            min-width: 900px;
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

        .price {
            font-weight: 600;
        }

        .profit {
            color: #176b3a;
            font-weight: bold;
        }

        .edit-button {
            display: inline-block;
            padding: 8px 12px;
            background: #176b3a;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .edit-button:hover {
            background: #12552e;
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
            margin-bottom: 20px;
            font-size: 14px;
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

        <h2>All Products</h2>

        <p>
            View and manage all products currently registered in Penta Farm.
        </p>

    </div>


    <?php if ($message !== ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <div class="actions">

        <a href="add.php" class="button primary">
            + Add Product
        </a>

        <a href="search.php" class="button secondary">
            🔍 Search Prices
        </a>

    </div>


    <section class="products-card">


        <div class="card-header">

            <h3>Product List</h3>

            <p>
                <?php echo count($products); ?> product(s) registered.
            </p>

        </div>


        <?php if (count($products) > 0): ?>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Product</th>
                            <th>Unit</th>
                            <th>Buying Price</th>
                            <th>Selling Price</th>
                            <th>Profit</th>
                            <th>Last Updated</th>
                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($products as $product): ?>

                            <tr>

                                <td class="product-name">
                                    <?php
                                    echo htmlspecialchars(
                                        $product["product_name"]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $product["unit"]
                                    );
                                    ?>
                                </td>

                                <td class="price">
                                    KSh
                                    <?php
                                    echo number_format(
                                        $product["buying_price"],
                                        2
                                    );
                                    ?>
                                </td>

                                <td class="price">
                                    KSh
                                    <?php
                                    echo number_format(
                                        $product["selling_price"],
                                        2
                                    );
                                    ?>
                                </td>

                                <td class="profit">
                                    KSh
                                    <?php
                                    echo number_format(
                                        $product["profit"],
                                        2
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $product["updated_at"]
                                    );
                                    ?>
                                </td>

                                <td>

                                    <a
                                        href="edit.php?id=<?php echo $product["id"]; ?>"
                                        class="edit-button"
                                    >
                                        ✏️ Edit Price
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <div class="empty">

                <h3>No Products Yet</h3>

                <p>
                    Your product inventory is currently empty.
                </p>

                <a href="add.php" class="button primary">
                    + Add Your First Product
                </a>

            </div>

        <?php endif; ?>


    </section>


</main>

</body>

</html>