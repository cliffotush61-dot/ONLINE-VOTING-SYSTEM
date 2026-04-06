<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary-color: #667eea;
            --primary-dark: #764ba2;
            --background: #f5f7fa;
            --surface: #ffffff;
            --text-primary: #1f2937;
            --border-color: #e5e7eb;
        }

        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            color: var(--text-primary);
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 800;
        }

        .header .nav {
            display: flex;
            gap: 20px;
        }

        .header a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            transition: background 0.3s;
        }

        .header a:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>

<div class="header">
    <h1>🗳️ Voting System</h1>
    <div class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
