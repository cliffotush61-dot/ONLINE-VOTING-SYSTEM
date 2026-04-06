<?php
require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();

require_once __DIR__ . '/db.php';

if (evote_normalize_auth_session($conn) !== 'student' || empty($_SESSION['vote_submitted'])) {
    header("Location: login.php");
    exit();
}

unset($_SESSION['vote_submitted']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Submitted - Secure Voting System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .success-box {
            background: white;
            border-radius: 8px;
            padding: 50px 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .success-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        h1 {
            color: #10b981;
            margin-bottom: 15px;
        }

        .success-box p {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        a {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: transform 0.3s;
        }

        a:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="success-box">
    <div class="success-icon">✓</div>
    <h1>Vote Submitted Successfully!</h1>
    <p>
        Your vote has been recorded and secured in our system. Thank you for participating in this election. Your vote cannot be changed after submission.
    </p>
    <div class="button-row">
        <a href="dashboard.php">Return to Dashboard</a>
        <a href="index.php">Home</a>
    </div>
</div>

</body>
</html>
