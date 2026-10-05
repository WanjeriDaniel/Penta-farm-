<?php
/*
|--------------------------------------------------------------------------
| PENTA FARM — DASHBOARD PROTOTYPE V2
|--------------------------------------------------------------------------
| Visual prototype only.
| No database connection.
| No existing Penta Farm files are modified.
|--------------------------------------------------------------------------
*/

$stats = [
    'sales_today'       => '125,450',
    'collections_today' => '108,000',
    'outstanding'       => '86,500',
    'gross_profit'      => '31,250',
    'products'          => 248,
    'low_stock'        => 14,
    'out_of_stock'      => 5,
    'transactions'      => 37,
];

$low_stock = [
    ['name' => 'Cement 50kg', 'stock' => '8 bags', 'minimum' => '15 bags'],
    ['name' => 'PVC Pipe 20mm', 'stock' => '4 pieces', 'minimum' => '10 pieces'],
    ['name' => 'Binding Wire', 'stock' => '2 rolls', 'minimum' => '8 rolls'],
    ['name' => 'Crown Paint 20L', 'stock' => '3 tins', 'minimum' => '6 tins'],
];

$out_of_stock = [
    'Tile Adhesive',
    'Roofing Nails 3"',
    'PPR Elbow 25mm',
];

$recent_sales = [
    ['receipt' => '#1048', 'customer' => 'Walk-in Customer', 'amount' => '12,500', 'time' => '14:32'],
    ['receipt' => '#1047', 'customer' => 'ABC Hardware', 'amount' => '8,400', 'time' => '13:51'],
    ['receipt' => '#1046', 'customer' => 'John Mwangi', 'amount' => '18,200', 'time' => '13:20'],
    ['receipt' => '#1045', 'customer' => 'Walk-in Customer', 'amount' => '6,750', 'time' => '12:48'],
];

$recent_activity = [
    ['icon' => '📦', 'text' => 'Cement 50kg received', 'detail' => '+100 bags', 'time' => '10:42 AM'],
    ['icon' => '📦', 'text' => 'PVC Pipe 20mm received', 'detail' => '+50 pieces', 'time' => '09:18 AM'],
    ['icon' => '💰', 'text' => 'Sale #1048 completed', 'detail' => 'KSh 12,500', 'time' => '08:51 AM'],
    ['icon' => '💳', 'text' => 'Payment recorded', 'detail' => 'KSh 15,000', 'time' => '08:25 AM'],
    ['icon' => '✏️', 'text' => 'Selling price updated', 'detail' => 'Paint 20L', 'time' => 'Yesterday'],
];

$payments = [
    ['customer' => 'ABC Hardware', 'invoice' => 'INV-1048', 'balance' => '25,000', 'due' => 'Today'],
    ['customer' => 'John Mwangi', 'invoice' => 'INV-1051', 'balance' => '8,500', 'due' => '2 days'],
    ['customer' => 'XYZ Builders', 'invoice' => 'INV-1053', 'balance' => '31,000', 'due' => 'Overdue'],
];

$chart_values = [42, 55, 48, 71, 64, 82, 96];
$chart_days   = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Today'];
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Penta Farm Dashboard Prototype V2</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Inter,
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;
            color: #1e293b;

            padding-bottom: 0;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* =========================================================
           APP
        ========================================================= */

        .app {
            min-height: 100vh;
            display: flex;
        }

        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {
            width: 255px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;

            background:
                linear-gradient(
                    180deg,
                    #10396f 0%,
                    #0a274d 100%
                );

            color: white;
            padding: 22px 15px;

            z-index: 50;
        }

        .brand {
            padding: 2px 0 4px;
            text-align: center;

            margin-bottom: 18px;
        }

        .brand-logo {
            display: block;
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin: 0 auto;
        }

        .menu-title {
            margin: 20px 10px 8px;

            font-size: 10px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 1.2px;

            color: #87a5ca;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 11px;

            padding: 10px 12px;
            margin-bottom: 4px;

            border-radius: 9px;

            color: #dbe8f8;

            font-size: 13px;

            transition: .2s;
        }

        .menu-item:hover {
            background:
                rgba(255,255,255,.08);

            color: white;
        }

        .menu-item.active {
            background: #2875bb;
            color: white;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,.12);
        }

        .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .submenu {
            margin-left: 34px;
        }

        .submenu a {
            display: block;

            padding: 7px 8px;

            color: #9eb7d5;

            font-size: 12px;

            border-radius: 6px;
        }

        .submenu a:hover {
            color: white;
            background:
                rgba(255,255,255,.06);
        }

        /* =========================================================
           MAIN
        ========================================================= */

        .main {
            width: calc(100% - 255px);
            margin-left: 255px;
        }

        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {
            height: 70px;

            background: white;

            border-bottom:
                1px solid
                #e5eaf1;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 30px;

            position: sticky;
            top: 0;

            z-index: 30;
        }

        .top-title {
            font-size: 19px;
            font-weight: 750;
            color: #17375f;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .notification {
            position: relative;

            width: 38px;
            height: 38px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f2f5f9;

            cursor: pointer;
        }

        .notification-dot {
            position: absolute;

            top: 7px;
            right: 7px;

            width: 7px;
            height: 7px;

            background: #ef4444;

            border-radius: 50%;
        }

        .admin {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 37px;
            height: 37px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            color: white;
            font-size: 13px;
            font-weight: 700;

            background:
                linear-gradient(
                    135deg,
                    #2563a8,
                    #1596d1
                );
        }

        .admin-text strong {
            display: block;
            font-size: 12px;
        }

        .admin-text span {
            display: block;

            margin-top: 2px;

            font-size: 10px;
            color: #64748b;
        }

        /* =========================================================
           CONTENT
        ========================================================= */

        .content {
            padding: 28px;
        }

        .welcome {
            margin-bottom: 23px;
        }

        .welcome h1 {
            color: #17375f;
            font-size: 25px;
            margin-bottom: 6px;
        }

        .welcome p {
            color: #64748b;
            font-size: 13px;
        }

        /* =========================================================
           KPI CARDS
        ========================================================= */

        .kpis {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 20px;
        }

        .kpi {
            background: white;

            border:
                1px solid
                #e7edf5;

            border-radius: 13px;

            padding: 18px;

            box-shadow:
                0 3px 12px
                rgba(15,23,42,.035);

            transition: .2s;
        }

        .kpi:hover {
            transform: translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(15,23,42,.07);
        }

        .kpi-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .kpi-label {
            font-size: 11px;
            color: #64748b;
        }

        .kpi-value {
            margin-top: 6px;

            font-size: 23px;
            font-weight: 800;

            color: #17375f;
        }

        .kpi-icon {
            width: 40px;
            height: 40px;

            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 18px;
        }

        .kpi-blue {
            background: #e8f2ff;
        }

        .kpi-green {
            background: #e8f8ef;
        }

        .kpi-orange {
            background: #fff4df;
        }

        .kpi-red {
            background: #ffe9e9;
        }

        .kpi-footer {
            margin-top: 12px;

            font-size: 10px;

            color: #64748b;
        }

        .positive {
            color: #159447;
            font-weight: 700;
        }

        /* =========================================================
           PRICE CHECK
        ========================================================= */

        .price-check {
            background:
                linear-gradient(
                    135deg,
                    #123d77,
                    #216aa9
                );

            border-radius: 15px;

            padding: 22px 24px;

            color: white;

            margin-bottom: 20px;

            box-shadow:
                0 8px 25px
                rgba(18,59,115,.16);
        }

        .price-header {
            margin-bottom: 15px;
        }

        .price-header h2 {
            font-size: 17px;
            margin-bottom: 4px;
        }

        .price-header p {
            color: #c6daf0;
            font-size: 11px;
        }

        .search {
            display: flex;

            max-width: 780px;

            background: white;

            border-radius: 9px;

            overflow: hidden;
        }

        .search input {
            flex: 1;

            border: none;
            outline: none;

            padding: 13px 15px;

            font-size: 13px;

            color: #1e293b;
        }

        .search button {
            border: none;

            padding:
                0 22px;

            background: #1787c5;

            color: white;

            font-size: 12px;
            font-weight: 700;

            cursor: pointer;
        }

        /* =========================================================
           GRID
        ========================================================= */

        .two-column {
            display: grid;

            grid-template-columns:
                1.35fr 1fr;

            gap: 18px;

            margin-bottom: 20px;
        }

        .panel {
            background: white;

            border:
                1px solid
                #e7edf5;

            border-radius: 13px;

            overflow: hidden;

            box-shadow:
                0 3px 12px
                rgba(15,23,42,.03);
        }

        .panel-header {
            display: flex;

            justify-content: space-between;
            align-items: center;

            padding:
                16px 18px;

            border-bottom:
                1px solid
                #edf1f6;
        }

        .panel-header h3 {
            font-size: 14px;
            color: #17375f;
        }

        .panel-link {
            color: #2472ad;
            font-size: 10px;
            font-weight: 700;
        }

        /* =========================================================
           STOCK ALERTS
        ========================================================= */

        .stock-row {
            display: flex;

            align-items: center;
            justify-content: space-between;

            padding:
                12px 18px;

            border-bottom:
                1px solid
                #f0f3f7;

            transition: .2s;
        }

        .stock-row:last-child {
            border-bottom: none;
        }

        .stock-row:hover {
            background: #f8fbff;
        }

        .stock-select {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .stock-checkbox {
            width: 17px;
            height: 17px;

            accent-color: #2875bb;

            cursor: pointer;

            flex-shrink: 0;
        }

        .stock-product {
            display: flex;
            align-items: center;
            gap: 10px;

            min-width: 0;
        }

        .stock-icon {
            width: 35px;
            height: 35px;

            border-radius: 8px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #fff5e4;

            flex-shrink: 0;
        }

        .stock-name {
            font-size: 12px;
            font-weight: 700;
        }

        .stock-min {
            margin-top: 3px;

            font-size: 9px;
            color: #94a3b8;
        }

        .stock-number {
            text-align: right;

            font-size: 11px;
            font-weight: 800;

            color: #e47a00;

            flex-shrink: 0;
        }

        /* =========================================================
           OUT OF STOCK
        ========================================================= */

        .out-list {
            padding: 4px 18px 8px;
        }

        .out-item {
            display: flex;

            align-items: center;

            gap: 10px;

            padding: 11px 0;

            border-bottom:
                1px solid
                #f0f3f7;
        }

        .out-item:last-child {
            border-bottom: none;
        }

        .out-checkbox {
            width: 17px;
            height: 17px;

            accent-color: #2875bb;

            cursor: pointer;

            flex-shrink: 0;
        }

        .out-dot {
            width: 9px;
            height: 9px;

            background: #ef4444;

            border-radius: 50%;

            flex-shrink: 0;
        }

        .out-name {
            font-size: 12px;
            font-weight: 600;
        }

        /* =========================================================
           SELECTION BAR
        ========================================================= */

        .selection-bar {
            position: fixed;

            left: 255px;
            right: 0;
            bottom: 0;

            min-height: 66px;

            background: white;

            border-top:
                1px solid
                #dfe7f0;

            box-shadow:
                0 -8px 25px
                rgba(15,23,42,.10);

            display: none;

            align-items: center;
            justify-content: space-between;

            padding:
                12px 30px;

            z-index: 100;
        }

        .selection-bar.show {
            display: flex;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .selection-count {
            width: 36px;
            height: 36px;

            border-radius: 9px;

            background: #e8f2ff;

            color: #1765a5;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 13px;
            font-weight: 800;
        }

        .selection-text strong {
            display: block;

            color: #17375f;

            font-size: 12px;
        }

        .selection-text span {
            display: block;

            margin-top: 2px;

            color: #64748b;

            font-size: 10px;
        }

        .selection-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .clear-selection {
            border: none;
            background: transparent;

            color: #64748b;

            padding: 10px 12px;

            font-size: 11px;
            font-weight: 700;

            cursor: pointer;
        }

        .clear-selection:hover {
            color: #17375f;
        }

        .add-order-button {
            border: none;

            background:
                linear-gradient(
                    135deg,
                    #1768a8,
                    #1688c7
                );

            color: white;

            padding:
                11px 18px;

            border-radius: 8px;

            font-size: 11px;
            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 5px 14px
                rgba(23,104,168,.20);

            transition: .2s;
        }

        .add-order-button:hover {
            transform: translateY(-1px);

            box-shadow:
                0 8px 18px
                rgba(23,104,168,.25);
        }

        /* =========================================================
           SALES CHART
        ========================================================= */

        .chart-wrap {
            padding: 18px;
        }

        .chart-summary {
            display: flex;

            align-items: baseline;

            gap: 8px;

            margin-bottom: 15px;
        }

        .chart-total {
            font-size: 22px;
            font-weight: 800;
            color: #17375f;
        }

        .chart-label {
            color: #64748b;
            font-size: 10px;
        }

        .chart {
            height: 180px;

            display: flex;

            align-items: flex-end;

            gap: 13px;

            border-bottom:
                1px solid
                #e5eaf1;

            padding:
                0 5px;
        }

        .bar-column {
            flex: 1;

            height: 100%;

            display: flex;
            align-items: center;
            justify-content: flex-end;

            flex-direction: column;

            gap: 7px;
        }

        .bar {
            width: 100%;
            max-width: 38px;

            border-radius:
                6px 6px 2px 2px;

            background:
                linear-gradient(
                    180deg,
                    #2685c5,
                    #155b98
                );
        }

        .bar-label {
            font-size: 9px;
            color: #64748b;
        }

        /* =========================================================
           PAYMENT TABLE
        ========================================================= */

        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-table th {
            text-align: left;

            font-size: 9px;
            font-weight: 700;

            color: #94a3b8;

            padding:
                11px 16px;

            background: #fafbfd;
        }

        .payment-table td {
            padding:
                12px 16px;

            border-top:
                1px solid
                #f0f3f7;

            font-size: 11px;
        }

        .balance {
            font-weight: 800;
            color: #17375f;
        }

        .overdue {
            color: #dc2626;
            font-weight: 700;
        }

        .due {
            color: #d97706;
        }

        /* =========================================================
           RECENT SALES
        ========================================================= */

        .sale-row {
            display: grid;

            grid-template-columns:
                70px 1fr 100px 50px;

            align-items: center;

            padding:
                12px 18px;

            border-bottom:
                1px solid
                #f0f3f7;
        }

        .sale-row:last-child {
            border-bottom: none;
        }

        .receipt {
            font-weight: 800;
            color: #2472ad;
            font-size: 11px;
        }

        .customer {
            font-size: 11px;
        }

        .amount {
            font-size: 11px;
            font-weight: 800;
            text-align: right;
        }

        .time {
            font-size: 9px;
            color: #94a3b8;
            text-align: right;
        }

        /* =========================================================
           ACTIVITY
        ========================================================= */

        .activity-row {
            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                12px 18px;

            border-bottom:
                1px solid
                #f0f3f7;
        }

        .activity-row:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 34px;
            height: 34px;

            background: #edf5ff;

            border-radius: 8px;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .activity-main {
            flex: 1;
        }

        .activity-text {
            font-size: 11px;
            font-weight: 650;
        }

        .activity-detail {
            margin-top: 3px;

            font-size: 9px;

            color: #64748b;
        }

        .activity-time {
            font-size: 9px;
            color: #94a3b8;
        }

        /* =========================================================
           BOTTOM ACTIONS
        ========================================================= */

        .quick-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

            margin-top: 20px;
        }

        .quick {
            background: white;

            border:
                1px solid
                #e7edf5;

            border-radius: 11px;

            padding: 15px;

            transition: .2s;
        }

        .quick:hover {
            border-color: #2875bb;

            transform:
                translateY(-2px);

            box-shadow:
                0 7px 18px
                rgba(15,23,42,.06);
        }

        .quick-icon {
            font-size: 21px;
            margin-bottom: 8px;
        }

        .quick strong {
            display: block;

            font-size: 11px;
        }

        .quick span {
            display: block;

            margin-top: 4px;

            font-size: 9px;

            color: #64748b;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1150px) {

            .kpis {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .two-column {
                grid-template-columns: 1fr;
            }

            .quick-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }

        @media (max-width: 760px) {

            .sidebar {
                display: none;
            }

            .main {
                width: 100%;
                margin-left: 0;
            }

            .content {
                padding: 18px;
            }

            .topbar {
                padding: 0 18px;
            }

            .kpis {
                grid-template-columns:
                    1fr 1fr;
            }

            .quick-grid {
                grid-template-columns:
                    1fr 1fr;
            }

            .admin-text {
                display: none;
            }

            .sale-row {
                grid-template-columns:
                    65px 1fr 85px;
            }

            .sale-row .time {
                display: none;
            }

            .selection-bar {
                left: 0;

                padding:
                    10px 18px;
            }

        }

        @media (max-width: 480px) {

            .kpis {
                grid-template-columns: 1fr;
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 14px;
            }

            .welcome h1 {
                font-size: 21px;
            }

            .search button {
                padding: 0 14px;
            }

            .selection-bar {
                align-items: stretch;

                flex-direction: column;

                gap: 9px;

                padding:
                    12px 14px;
            }

            .selection-actions {
                width: 100%;
            }

            .clear-selection,
            .add-order-button {
                flex: 1;
            }

        }

    </style>

</head>

<body>

<div class="app">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-logo-wrap">

                <img
                    src="/penta_farm/assets/penta-farm-logo.png"
                    alt="Penta Farm Trading Co. Ltd."
                    class="brand-logo"
                >

            </div>

        </div>


        <div class="menu-title">
            Main
        </div>

        <a href="#" class="menu-item active">

            <span class="menu-icon">🏠</span>

            Dashboard

        </a>


        <div class="menu-title">
            Inventory
        </div>

        <a href="#" class="menu-item">

            <span class="menu-icon">📦</span>

            Inventory

        </a>

        <div class="submenu">

            <a href="#">stock</a>
            <a href="#">index.php</a>
            <a href="#">Price Check</a>
            <a href="#">Receive Stock</a>
            <a href="#">Stock Receipts</a>
            <a href="#">Low Stock</a>
            <a href="#">Out of Stock</a>

        </div>


        <div class="menu-title">
            Sales
        </div>

        <a href="#" class="menu-item">

            <span class="menu-icon">💰</span>

            Sales

        </a>

        <div class="submenu">

            <a href="#">POS</a>
            <a href="#">Sales History</a>
            <a href="#">Invoices</a>
            <a href="#">Pay Later</a>
            <a href="#">Payments</a>

        </div>


        <div class="menu-title">
            Management
        </div>

        <a href="#" class="menu-item">

            <span class="menu-icon">📊</span>

            Reports

        </a>

        <a href="#" class="menu-item">

            <span class="menu-icon">👥</span>

            Customers

        </a>

        <a href="#" class="menu-item">

            <span class="menu-icon">🚚</span>

            Suppliers

        </a>


        <div class="menu-title">
            System
        </div>

        <a href="#" class="menu-item">

            <span class="menu-icon">⚙️</span>

            Settings

        </a>

    </aside>


    <!-- =========================================================
         MAIN
    ========================================================== -->

    <main class="main">


        <!-- TOP BAR -->

        <header class="topbar">

            <div class="top-title">
                Penta Farm Dashboard
            </div>

            <div class="top-actions">

                <div class="notification">

                    🔔

                    <span class="notification-dot"></span>

                </div>

                <div class="admin">

                    <div class="avatar">
                        A
                    </div>

                    <div class="admin-text">

                        <strong>
                            Administrator
                        </strong>

                        <span>
                            System Admin
                        </span>

                    </div>

                </div>

            </div>

        </header>


        <section class="content">


            <!-- WELCOME -->

            <div class="welcome">

                <h1>
                    Good afternoon 👋
                </h1>

                <p>
                    Here's what's happening at Penta Farm today.
                </p>

            </div>


            <!-- =================================================
                 KPI CARDS
            ================================================== -->

            <div class="kpis">


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Today's Sales
                            </div>

                            <div class="kpi-value">
                                KSh <?= $stats['sales_today']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-blue">
                            💰
                        </div>

                    </div>

                    <div class="kpi-footer">
                        <span class="positive">↑ 12.5%</span>
                        compared with yesterday
                    </div>

                </div>


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Collected Today
                            </div>

                            <div class="kpi-value">
                                KSh <?= $stats['collections_today']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-green">
                            💵
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Cash + M-Pesa + Bank
                    </div>

                </div>


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Outstanding
                            </div>

                            <div class="kpi-value">
                                KSh <?= $stats['outstanding']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-orange">
                            💳
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Invoices + Pay Later
                    </div>

                </div>


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Gross Profit
                            </div>

                            <div class="kpi-value">
                                KSh <?= $stats['gross_profit']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-green">
                            📈
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Based on completed sales
                    </div>

                </div>


            </div>


            <!-- =================================================
                 SECONDARY STATS
            ================================================== -->

            <div class="kpis">


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Total Products
                            </div>

                            <div class="kpi-value">
                                <?= $stats['products']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-blue">
                            📦
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Active inventory products
                    </div>

                </div>


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Low Stock
                            </div>

                            <div class="kpi-value">
                                <?= $stats['low_stock']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-orange">
                            ⚠️
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Products below minimum level
                    </div>

                </div>


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Out of Stock
                            </div>

                            <div class="kpi-value">
                                <?= $stats['out_of_stock']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-red">
                            ❌
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Products currently unavailable
                    </div>

                </div>


                <div class="kpi">

                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Transactions Today
                            </div>

                            <div class="kpi-value">
                                <?= $stats['transactions']; ?>
                            </div>

                        </div>

                        <div class="kpi-icon kpi-blue">
                            🧾
                        </div>

                    </div>

                    <div class="kpi-footer">
                        Completed transactions
                    </div>

                </div>


            </div>


            <!-- =================================================
                 PRICE CHECK
            ================================================== -->

            <div class="price-check">

                <div class="price-header">

                    <h2>
                        🔎 Product & Price Check
                    </h2>

                    <p>
                        Quickly find stock quantity, buying price and selling price.
                    </p>

                </div>

                <div class="search">

                    <input
                        type="text"
                        placeholder="Search product name..."
                    >

                    <button>
                        Search
                    </button>

                </div>

            </div>


            <!-- =================================================
                 INVENTORY ALERTS
            ================================================== -->

            <div class="two-column">


                <!-- LOW STOCK -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            ⚠️ Low Stock
                        </h3>

                        <a href="#" class="panel-link">
                            View All
                        </a>

                    </div>


                    <?php foreach ($low_stock as $index => $item): ?>

                        <div class="stock-row">

                            <div class="stock-select">

                                <input
                                    type="checkbox"
                                    class="stock-checkbox alert-checkbox"
                                    data-product="<?= htmlspecialchars($item['name']); ?>"
                                    data-type="low"
                                >

                                <div class="stock-product">

                                    <div class="stock-icon">
                                        📦
                                    </div>

                                    <div>

                                        <div class="stock-name">
                                            <?= htmlspecialchars($item['name']); ?>
                                        </div>

                                        <div class="stock-min">
                                            Minimum:
                                            <?= htmlspecialchars($item['minimum']); ?>
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="stock-number">
                                <?= htmlspecialchars($item['stock']); ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- OUT OF STOCK -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            ❌ Out of Stock
                        </h3>

                        <a href="#" class="panel-link">
                            View All
                        </a>

                    </div>


                    <div class="out-list">

                        <?php foreach ($out_of_stock as $product): ?>

                            <div class="out-item">

                                <input
                                    type="checkbox"
                                    class="out-checkbox alert-checkbox"
                                    data-product="<?= htmlspecialchars($product); ?>"
                                    data-type="out"
                                >

                                <span class="out-dot"></span>

                                <span class="out-name">
                                    <?= htmlspecialchars($product); ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 SALES CHART
            ================================================== -->

            <div class="panel" style="margin-bottom:20px;">

                <div class="panel-header">

                    <h3>
                        📈 Sales Performance
                    </h3>

                    <a href="#" class="panel-link">
                        View Reports
                    </a>

                </div>


                <div class="chart-wrap">

                    <div class="chart-summary">

                        <span class="chart-total">
                            KSh 486,250
                        </span>

                        <span class="chart-label">
                            Last 7 days
                        </span>

                    </div>


                    <div class="chart">

                        <?php foreach ($chart_values as $index => $value): ?>

                            <div class="bar-column">

                                <div
                                    class="bar"
                                    style="height: <?= $value * 1.45; ?>px;"
                                ></div>

                                <div class="bar-label">
                                    <?= $chart_days[$index]; ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 PAYMENTS
            ================================================== -->

            <div class="panel" style="margin-bottom:20px;">

                <div class="panel-header">

                    <h3>
                        💳 Payments Requiring Attention
                    </h3>

                    <a href="#" class="panel-link">
                        View Outstanding
                    </a>

                </div>


                <table class="payment-table">

                    <thead>

                        <tr>

                            <th>
                                CUSTOMER
                            </th>

                            <th>
                                INVOICE
                            </th>

                            <th>
                                BALANCE
                            </th>

                            <th>
                                DUE
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($payment['customer']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($payment['invoice']); ?>
                                </td>

                                <td class="balance">
                                    KSh <?= htmlspecialchars($payment['balance']); ?>
                                </td>

                                <td class="<?=
                                    strtolower($payment['due']) === 'overdue'
                                        ? 'overdue'
                                        : 'due';
                                ?>">
                                    <?= htmlspecialchars($payment['due']); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 RECENT SALES + ACTIVITY
            ================================================== -->

            <div class="two-column">


                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            🧾 Recent Sales
                        </h3>

                        <a href="#" class="panel-link">
                            View History
                        </a>

                    </div>


                    <?php foreach ($recent_sales as $sale): ?>

                        <div class="sale-row">

                            <div class="receipt">
                                <?= htmlspecialchars($sale['receipt']); ?>
                            </div>

                            <div class="customer">
                                <?= htmlspecialchars($sale['customer']); ?>
                            </div>

                            <div class="amount">
                                KSh <?= htmlspecialchars($sale['amount']); ?>
                            </div>

                            <div class="time">
                                <?= htmlspecialchars($sale['time']); ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            🕐 Recent Activity
                        </h3>

                        <a href="#" class="panel-link">
                            Audit Log
                        </a>

                    </div>


                    <?php foreach ($recent_activity as $activity): ?>

                        <div class="activity-row">

                            <div class="activity-icon">
                                <?= $activity['icon']; ?>
                            </div>

                            <div class="activity-main">

                                <div class="activity-text">
                                    <?= htmlspecialchars($activity['text']); ?>
                                </div>

                                <div class="activity-detail">
                                    <?= htmlspecialchars($activity['detail']); ?>
                                </div>

                            </div>

                            <div class="activity-time">
                                <?= htmlspecialchars($activity['time']); ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


            </div>


            <!-- =================================================
                 QUICK ACTIONS
            ================================================== -->

            <div
                class="section-title"
                style="margin-top:20px; margin-bottom:12px;"
            >

                <h3 style="font-size:15px;color:#17375f;">
                    ⚡ Quick Actions
                </h3>

            </div>


            <div class="quick-grid">


                <a href="#" class="quick">

                    <div class="quick-icon">
                        📦
                    </div>

                    <strong>
                        Stock Index
                    </strong>

                    <span>
                        View inventory
                    </span>

                </a>


                <a href="#" class="quick">

                    <div class="quick-icon">
                        ➕
                    </div>

                    <strong>
                        Receive Stock
                    </strong>

                    <span>
                        Add inventory
                    </span>

                </a>


                <a href="#" class="quick">

                    <div class="quick-icon">
                        🛒
                    </div>

                    <strong>
                        Open POS
                    </strong>

                    <span>
                        Create sale
                    </span>

                </a>


                <a href="#" class="quick">

                    <div class="quick-icon">
                        📊
                    </div>

                    <strong>
                        Reports
                    </strong>

                    <span>
                        Business reports
                    </span>

                </a>


            </div>


        </section>

    </main>

</div>


<!-- =============================================================
     SELECTION BAR
============================================================== -->

<div
    id="selectionBar"
    class="selection-bar"
>

    <div class="selection-info">

        <div
            id="selectionCount"
            class="selection-count"
        >
            0
        </div>

        <div class="selection-text">

            <strong>
                Products selected
            </strong>

            <span>
                Ready to add to a stock order
            </span>

        </div>

    </div>


    <div class="selection-actions">

        <button
            type="button"
            id="clearSelection"
            class="clear-selection"
        >
            Clear
        </button>

        <button
            type="button"
            id="addToOrder"
            class="add-order-button"
        >
            🛒 Add to Stock Order
        </button>

    </div>

</div>


<script>

/* ================================================================
   STOCK ALERT SELECTION PROTOTYPE
================================================================ */

(function () {

    const checkboxes =
        document.querySelectorAll(
            '.alert-checkbox'
        );

    const selectionBar =
        document.getElementById(
            'selectionBar'
        );

    const selectionCount =
        document.getElementById(
            'selectionCount'
        );

    const clearSelection =
        document.getElementById(
            'clearSelection'
        );

    const addToOrder =
        document.getElementById(
            'addToOrder'
        );


    function getSelectedProducts() {

        const selected = [];

        checkboxes.forEach(function (checkbox) {

            if (checkbox.checked) {

                selected.push({
                    name:
                        checkbox.dataset.product,

                    type:
                        checkbox.dataset.type
                });

            }

        });

        return selected;

    }


    function updateSelectionBar() {

        const selected =
            getSelectedProducts();


        selectionCount.textContent =
            selected.length;


        if (selected.length > 0) {

            selectionBar.classList.add(
                'show'
            );

        } else {

            selectionBar.classList.remove(
                'show'
            );

        }

    }


    checkboxes.forEach(function (checkbox) {

        checkbox.addEventListener(
            'change',
            updateSelectionBar
        );

    });


    clearSelection.addEventListener(
        'click',
        function () {

            checkboxes.forEach(
                function (checkbox) {

                    checkbox.checked = false;

                }
            );

            updateSelectionBar();

        }
    );


    addToOrder.addEventListener(
        'click',
        function () {

            const selected =
                getSelectedProducts();


            if (
                selected.length === 0
            ) {

                return;

            }


            /*
            --------------------------------------------------------
            PROTOTYPE ONLY
            --------------------------------------------------------
            The selected product names are temporarily passed
            through the URL so we can test the user experience.

            The real implementation will later use product IDs
            from the database.
            --------------------------------------------------------
            */


            const names =
                selected.map(
                    function (item) {

                        return item.name;

                    }
                );


            const encodedProducts =
                encodeURIComponent(
                    names.join('|')
                );


            window.location.href =
                'order.php?from_alerts=1&products=' +
                encodedProducts;

        }
    );


})();

</script>

</body>

</html>

