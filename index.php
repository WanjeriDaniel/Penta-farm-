<?php

require_once "config/database.php";

$total_products = 0;
$total_product_value = 0;
$highest_profit = 0;
$highest_profit_product = "N/A";
$recent_history = [];
$database_error = false;

try {

    /*
    |--------------------------------------------------------------------------
    | Total Products
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*) AS total
        FROM products
    ");

    $total_products = (int) $stmt->fetch()["total"];


    /*
    |--------------------------------------------------------------------------
    | Total Buying Value
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COALESCE(SUM(buying_price), 0) AS total_value
        FROM products
    ");

    $total_product_value = (float) $stmt->fetch()["total_value"];


    /*
    |--------------------------------------------------------------------------
    | Highest Unit Profit
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            product_name,
            (selling_price - buying_price) AS profit
        FROM products
        ORDER BY profit DESC
        LIMIT 1
    ");

    $profit_product = $stmt->fetch();

    if ($profit_product) {

        $highest_profit = (float) $profit_product["profit"];

        $highest_profit_product =
            $profit_product["product_name"];
    }


    /*
    |--------------------------------------------------------------------------
    | Recent Price Changes
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            ph.old_buying_price,
            ph.new_buying_price,
            ph.old_selling_price,
            ph.new_selling_price,
            ph.changed_at,
            p.product_name
        FROM price_history ph

        INNER JOIN products p
            ON ph.product_id = p.id

        ORDER BY ph.changed_at DESC

        LIMIT 5
    ");

    $recent_history = $stmt->fetchAll();


} catch (PDOException $e) {

    $database_error = true;

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

    <title>Penta Farm | Dashboard</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            font-family:
                "Segoe UI",
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f4f7f5;

            color: #26332b;

            min-height: 100vh;

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .header {

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #0d4d2b 0%,
                    #176b3a 45%,
                    #27a65b 100%
                );

            color: white;

            padding:
                28px 40px 70px;

        }


        .header::before {

            content: "";

            position: absolute;

            width: 320px;

            height: 320px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.07);

            top: -170px;

            right: -80px;

        }


        .header::after {

            content: "";

            position: absolute;

            width: 220px;

            height: 220px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.05);

            bottom: -150px;

            left: 10%;

        }


        .header-content {

            position: relative;

            z-index: 2;

            max-width: 1250px;

            margin: auto;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .brand-icon {

            width: 52px;

            height: 52px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 15px;

            background:
                rgba(255,255,255,0.16);

            font-size: 28px;

            backdrop-filter: blur(10px);

        }


        .brand h1 {

            font-size: 27px;

            letter-spacing: -0.5px;

        }


        .brand p {

            margin-top: 4px;

            font-size: 13px;

            opacity: 0.82;

        }


        .header-badge {

            padding:
                9px 15px;

            border:
                1px solid rgba(255,255,255,0.2);

            background:
                rgba(255,255,255,0.1);

            border-radius: 30px;

            font-size: 12px;

            backdrop-filter: blur(10px);

        }


        /* =====================================================
           MAIN
        ===================================================== */

        .container {

            width: 100%;

            max-width: 1250px;

            margin:
                -35px auto 0;

            padding:
                0 20px 40px;

            position: relative;

            z-index: 5;

        }


        /* =====================================================
           WELCOME CARD
        ===================================================== */

        .welcome {

            background: white;

            border-radius: 18px;

            padding:
                25px 30px;

            box-shadow:
                0 12px 35px
                rgba(24,70,43,0.10);

            margin-bottom: 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

        }


        .welcome h2 {

            color: #145d34;

            font-size: 25px;

            margin-bottom: 6px;

        }


        .welcome p {

            color: #78837d;

            font-size: 14px;

        }


        .welcome-tag {

            background:
                #e9f7ee;

            color:
                #176b3a;

            padding:
                10px 15px;

            border-radius: 30px;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;

        }


        /* =====================================================
           STATISTICS
        ===================================================== */

        .stats-grid {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 35px;

        }


        .stat-card {

            position: relative;

            overflow: hidden;

            background: white;

            padding: 25px;

            border-radius: 17px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,0.06);

            transition:
                transform 0.25s ease,
                box-shadow 0.25s ease;

        }


        .stat-card:hover {

            transform:
                translateY(-5px);

            box-shadow:
                0 15px 35px
                rgba(0,0,0,0.10);

        }


        .stat-card::after {

            content: "";

            position: absolute;

            width: 90px;

            height: 90px;

            border-radius: 50%;

            background:
                #eef8f1;

            right: -30px;

            top: -30px;

        }


        .stat-icon {

            position: relative;

            z-index: 2;

            width: 48px;

            height: 48px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                #eaf7ee;

            border-radius: 14px;

            font-size: 23px;

            margin-bottom: 17px;

        }


        .stat-title {

            color:
                #78837d;

            font-size: 13px;

            margin-bottom: 8px;

        }


        .stat-value {

            color:
                #145d34;

            font-size: 27px;

            font-weight: 700;

            letter-spacing: -0.5px;

        }


        .stat-description {

            color:
                #a0aaa4;

            font-size: 12px;

            margin-top: 8px;

        }


        /* =====================================================
           SECTION HEADERS
        ===================================================== */

        .section-heading {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 15px;

        }


        .section-title {

            color:
                #26332b;

            font-size: 19px;

            font-weight: 700;

        }


        .section-subtitle {

            color:
                #8b9690;

            font-size: 12px;

        }


        /* =====================================================
           QUICK ACTIONS
        ===================================================== */

        .actions-grid {

            display:
                grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 35px;

        }


        .action-card {

            position: relative;

            background:
                white;

            padding:
                22px;

            border-radius:
                15px;

            text-decoration:
                none;

            color:
                #333;

            box-shadow:
                0 6px 20px
                rgba(0,0,0,0.05);

            border:
                1px solid #edf1ee;

            transition:
                all 0.25s ease;

            overflow:
                hidden;

        }


        .action-card:hover {

            transform:
                translateY(-5px);

            border-color:
                #bde2c9;

            box-shadow:
                0 14px 30px
                rgba(23,107,58,0.12);

        }


        .action-card::after {

            content:
                "→";

            position:
                absolute;

            right:
                18px;

            top:
                18px;

            color:
                #b5c8bc;

            font-size:
                18px;

            transition:
                0.25s;

        }


        .action-card:hover::after {

            color:
                #176b3a;

            right:
                14px;

        }


        .action-icon {

            width:
                45px;

            height:
                45px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                13px;

            background:
                #edf8f1;

            font-size:
                22px;

            margin-bottom:
                13px;

        }


        .action-card h3 {

            color:
                #176b3a;

            font-size:
                15px;

            margin-bottom:
                5px;

        }


        .action-card p {

            color:
                #89948e;

            font-size:
                12px;

            line-height:
                1.5;

        }


        /* =====================================================
           TWO COLUMN AREA
        ===================================================== */

        .dashboard-grid {

            display:
                grid;

            grid-template-columns:
                1.6fr 0.8fr;

            gap:
                20px;

        }


        /* =====================================================
           HISTORY
        ===================================================== */

        .history-card,
        .pulse-card {

            background:
                white;

            border-radius:
                17px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,0.06);

            overflow:
                hidden;

        }


        .card-header {

            padding:
                22px 25px;

            border-bottom:
                1px solid #edf0ee;

        }


        .card-header h3 {

            color:
                #176b3a;

            font-size:
                18px;

        }


        .card-header p {

            color:
                #8b9690;

            font-size:
                12px;

            margin-top:
                5px;

        }


        .table-container {

            overflow-x:
                auto;

        }


        table {

            width:
                100%;

            border-collapse:
                collapse;

            min-width:
                650px;

        }


        th {

            background:
                #f6f9f7;

            color:
                #6e7973;

            text-align:
                left;

            padding:
                13px 16px;

            font-size:
                11px;

            text-transform:
                uppercase;

            letter-spacing:
                0.5px;

        }


        td {

            padding:
                14px 16px;

            border-bottom:
                1px solid #f0f2f1;

            font-size:
                13px;

        }


        tbody tr {

            transition:
                background 0.2s;

        }


        tbody tr:hover {

            background:
                #f8fcf9;

        }


        .product-name {

            color:
                #176b3a;

            font-weight:
                700;

        }


        .price-change {

            color:
                #46544c;

            font-weight:
                600;

        }


        .arrow {

            color:
                #2ba45c;

            margin:
                0 5px;

        }


        .date {

            color:
                #89948e;

            font-size:
                12px;

        }


        .view-history {

            display:
                block;

            padding:
                16px 20px;

            color:
                #176b3a;

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                700;

            border-top:
                1px solid #edf0ee;

            transition:
                0.2s;

        }


        .view-history:hover {

            background:
                #f7fbf8;

            padding-left:
                25px;

        }


        /* =====================================================
           PRICE PULSE
        ===================================================== */

        .pulse-content {

            padding:
                25px;

        }


        .pulse-item {

            padding:
                17px;

            border:
                1px solid #edf1ee;

            border-radius:
                13px;

            margin-bottom:
                12px;

            transition:
                0.2s;

        }


        .pulse-item:hover {

            border-color:
                #bde2c9;

            background:
                #fbfefc;

        }


        .pulse-item:last-child {

            margin-bottom:
                0;

        }


        .pulse-label {

            color:
                #8a958f;

            font-size:
                11px;

            text-transform:
                uppercase;

            letter-spacing:
                0.5px;

            margin-bottom:
                7px;

        }


        .pulse-product {

            color:
                #26332b;

            font-size:
                14px;

            font-weight:
                700;

            margin-bottom:
                5px;

        }


        .pulse-value {

            color:
                #176b3a;

            font-size:
                18px;

            font-weight:
                700;

        }


        .pulse-note {

            color:
                #8a958f;

            font-size:
                11px;

            margin-top:
                4px;

        }


        .trophy {

            font-size:
                20px;

            margin-right:
                5px;

        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty {

            padding:
                40px 25px;

            text-align:
                center;

            color:
                #89948e;

            font-size:
                13px;

        }


        .empty-icon {

            font-size:
                35px;

            margin-bottom:
                10px;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {

            text-align:
                center;

            padding:
                30px 20px;

            color:
                #9aa39e;

            font-size:
                11px;

        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 900px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .actions-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .dashboard-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 650px) {

            .header {

                padding:
                    22px 18px 60px;

            }


            .header-content {

                align-items:
                    flex-start;

            }


            .header-badge {

                display:
                    none;

            }


            .brand h1 {

                font-size:
                    23px;

            }


            .container {

                padding:
                    0 13px 30px;

            }


            .welcome {

                padding:
                    20px;

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .welcome h2 {

                font-size:
                    22px;

            }


            .stats-grid {

                grid-template-columns:
                    1fr;

            }


            .actions-grid {

                grid-template-columns:
                    1fr 1fr;

            }


            .section-heading {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap:
                    4px;

            }

        }


        @media (max-width: 430px) {

            .actions-grid {

                grid-template-columns:
                    1fr;

            }


            .stat-value {

                font-size:
                    24px;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="header">

    <div class="header-content">


        <div class="brand">

            <div class="brand-icon">
                🌱
            </div>

            <div>

                <h1>
                    Penta Farm
                </h1>

                <p>
                    Smart Product & Price Management
                </p>

            </div>

        </div>


        <div class="header-badge">
            ● System Online
        </div>


    </div>

</header>



<main class="container">


    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <section class="welcome">

        <div>

            <h2>
                Welcome to your dashboard 👋
            </h2>

            <p>
                Everything you need to manage your products
                and prices, right at your fingertips.
            </p>

        </div>


        <div class="welcome-tag">
            🌿 Penta Farm Management
        </div>

    </section>



    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon">
                📦
            </div>

            <div class="stat-title">
                Total Products
            </div>

            <div class="stat-value">

                <?php
                echo $total_products;
                ?>

            </div>

            <div class="stat-description">
                Products currently registered
            </div>

        </div>



        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>

            <div class="stat-title">
                Total Buying Value
            </div>

            <div class="stat-value">

                KSh
                <?php

                echo number_format(
                    $total_product_value,
                    2
                );

                ?>

            </div>

            <div class="stat-description">
                One unit of every registered product
            </div>

        </div>



        <div class="stat-card">

            <div class="stat-icon">
                🏆
            </div>

            <div class="stat-title">
                Highest Unit Profit
            </div>

            <div class="stat-value">

                KSh
                <?php

                echo number_format(
                    $highest_profit,
                    2
                );

                ?>

            </div>

            <div class="stat-description">

                <?php

                echo htmlspecialchars(
                    $highest_profit_product
                );

                ?>

            </div>

        </div>


    </section>



    <!-- =====================================================
         QUICK ACTIONS
    ====================================================== -->

    <div class="section-heading">

        <div>

            <h2 class="section-title">
                Quick Actions
            </h2>

            <div class="section-subtitle">
                Get things done quickly
            </div>

        </div>

    </div>



    <section class="actions-grid">


        <a
            href="products/add.php"
            class="action-card"
        >

            <div class="action-icon">
                ➕
            </div>

            <h3>
                Add Product
            </h3>

            <p>
                Register a new product and
                set its prices.
            </p>

        </a>



        <a
            href="products/list.php"
            class="action-card"
        >

            <div class="action-icon">
                📦
            </div>

            <h3>
                View Products
            </h3>

            <p>
                Browse all products currently
                in the system.
            </p>

        </a>



        <a
            href="products/search.php"
            class="action-card"
        >

            <div class="action-icon">
                🔍
            </div>

            <h3>
                Search Prices
            </h3>

            <p>
                Quickly find buying and
                selling prices.
            </p>

        </a>



        <a
            href="products/history.php"
            class="action-card"
        >

            <div class="action-icon">
                📊
            </div>

            <h3>
                Price History
            </h3>

            <p>
                See how product prices have
                changed over time.
            </p>

        </a>


    </section>



    <!-- =====================================================
         LOWER DASHBOARD
    ====================================================== -->

    <div class="dashboard-grid">


        <!-- =================================================
             RECENT HISTORY
        ================================================== -->

        <section class="history-card">


            <div class="card-header">

                <h3>
                    📈 Recent Price Activity
                </h3>

                <p>
                    Keep an eye on the latest changes.
                </p>

            </div>


            <?php if (count($recent_history) > 0): ?>


                <div class="table-container">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Buying Price
                                </th>

                                <th>
                                    Selling Price
                                </th>

                                <th>
                                    Changed
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($recent_history as $record): ?>

                                <tr>


                                    <td class="product-name">

                                        <?php

                                        echo htmlspecialchars(
                                            $record["product_name"]
                                        );

                                        ?>

                                    </td>


                                    <td class="price-change">

                                        KSh

                                        <?php

                                        echo number_format(
                                            $record["old_buying_price"],
                                            2
                                        );

                                        ?>

                                        <span class="arrow">
                                            →
                                        </span>

                                        KSh

                                        <?php

                                        echo number_format(
                                            $record["new_buying_price"],
                                            2
                                        );

                                        ?>

                                    </td>


                                    <td class="price-change">

                                        KSh

                                        <?php

                                        echo number_format(
                                            $record["old_selling_price"],
                                            2
                                        );

                                        ?>

                                        <span class="arrow">
                                            →
                                        </span>

                                        KSh

                                        <?php

                                        echo number_format(
                                            $record["new_selling_price"],
                                            2
                                        );

                                        ?>

                                    </td>


                                    <td class="date">

                                        <?php

                                        echo htmlspecialchars(
                                            $record["changed_at"]
                                        );

                                        ?>

                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


                <a
                    href="products/history.php"
                    class="view-history"
                >
                    View Complete Price History →
                </a>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">
                        📊
                    </div>

                    No price changes have been recorded yet.

                </div>


            <?php endif; ?>


        </section>



        <!-- =================================================
             PRICE PULSE
        ================================================== -->

        <section class="pulse-card">


            <div class="card-header">

                <h3>
                    ⚡ Price Pulse
                </h3>

                <p>
                    A quick look at your pricing.
                </p>

            </div>


            <div class="pulse-content">


                <div class="pulse-item">

                    <div class="pulse-label">
                        🏆 Best Unit Profit
                    </div>

                    <div class="pulse-product">

                        <span class="trophy">
                            🥇
                        </span>

                        <?php

                        echo htmlspecialchars(
                            $highest_profit_product
                        );

                        ?>

                    </div>

                    <div class="pulse-value">

                        KSh

                        <?php

                        echo number_format(
                            $highest_profit,
                            2
                        );

                        ?>

                    </div>

                    <div class="pulse-note">
                        Potential profit per unit
                    </div>

                </div>


                <?php if (count($recent_history) > 0): ?>

                    <div class="pulse-item">

                        <div class="pulse-label">
                            🕐 Latest Update
                        </div>

                        <div class="pulse-product">

                            <?php

                            echo htmlspecialchars(
                                $recent_history[0]["product_name"]
                            );

                            ?>

                        </div>

                        <div class="pulse-value">

                            Updated

                        </div>

                        <div class="pulse-note">

                            <?php

                            echo htmlspecialchars(
                                $recent_history[0]["changed_at"]
                            );

                            ?>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="pulse-item">

                        <div class="pulse-label">
                            💡 Getting Started
                        </div>

                        <div class="pulse-product">
                            Add your first product
                        </div>

                        <div class="pulse-value">
                            Let's grow 🌱
                        </div>

                        <div class="pulse-note">
                            Your dashboard will update automatically.
                        </div>

                    </div>

                <?php endif; ?>


                <div class="pulse-item">

                    <div class="pulse-label">
                        🔎 Product Search
                    </div>

                    <div class="pulse-product">
                        Need a price quickly?
                    </div>

                    <div class="pulse-value">
                        Search →
                    </div>

                    <div class="pulse-note">
                        Find a product in seconds.
                    </div>

                </div>


            </div>


        </section>


    </div>


</main>



<footer class="footer">

    © <?php echo date("Y"); ?>

    Penta Farm

    •

    Product & Price Management System

</footer>


</body>

</html>