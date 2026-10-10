<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estateflow API & PostgreSQL Backend</title>
    <style>
        :root {
            --primary: #1e3a2f;
            --primary-light: #2d5444;
            --accent: #d4a373;
            --bg: #f7f9f8;
            --card-bg: #ffffff;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --code-bg: #1e293b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { background: var(--bg); color: var(--text-dark); line-height: 1.5; padding: 2rem 1rem; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 2.5rem; border-radius: 16px; margin-bottom: 2rem; box-shadow: 0 10px 25px -5px rgba(30, 58, 47, 0.2); }
        .header h1 { font-size: 2.2rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .badge { display: inline-block; background: rgba(255,255,255,0.2); padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: var(--card-bg); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card h2 { font-size: 1.25rem; color: var(--primary); margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
        .endpoint-list { list-style: none; }
        .endpoint-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #f3f4f6; }
        .endpoint-item:last-child { border-bottom: none; }
        .method { font-size: 0.75rem; font-weight: bold; padding: 0.25rem 0.5rem; border-radius: 4px; color: white; }
        .method.get { background: #3b82f6; }
        .method.post { background: #10b981; }
        .method.put { background: #f59e0b; }
        .method.delete { background: #ef4444; }
        .endpoint-link { font-family: monospace; font-size: 0.9rem; color: var(--primary); text-decoration: none; font-weight: 500; }
        .endpoint-link:hover { text-decoration: underline; }
        .status-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        pre { background: var(--code-bg); color: #e2e8f0; padding: 1rem; border-radius: 8px; font-size: 0.85rem; overflow-x: auto; margin-top: 0.75rem; }
        .btn { display: inline-block; background: var(--primary); color: white; text-decoration: none; padding: 0.65rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; transition: background 0.2s; border: none; cursor: pointer; }
        .btn:hover { background: var(--primary-light); }
        .setup-steps ol { padding-left: 1.5rem; margin-top: 0.5rem; }
        .setup-steps li { margin-bottom: 0.75rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="badge">XAMPP + PostgreSQL Ready</span>
            <h1>Estateflow REST API</h1>
            <p>PHP Backend Database Service connected to PostgreSQL for Android Real Estate Platform</p>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Live Health Check</h2>
                <p>Verify that Apache, PHP, and PostgreSQL PDO are running properly:</p>
                <div style="margin-top: 1rem;">
                    <a href="api/status.php" class="btn" target="_blank">Test Database Connection</a>
                </div>
                <div class="status-box">
                    <strong>Quick Test:</strong> Click the button above to query PostgreSQL version and table row counts in JSON.
                </div>
            </div>

            <div class="card">
                <h2>Android Emulator Settings</h2>
                <p>When running inside Android Emulator or physical device:</p>
                <pre>Base URL for Emulator:
http://10.0.2.2/estateflow-backend/api/

Base URL for Physical Device (LAN):
http://&lt;YOUR_PC_IP&gt;/estateflow-backend/api/</pre>
            </div>
        </div>

        <div class="card" style="margin-bottom: 2rem;">
            <h2>REST API Endpoints</h2>
            <ul class="endpoint-list">
                <li class="endpoint-item">
                    <div>
                        <span class="method get">GET</span>
                        <a href="api/status.php" class="endpoint-link" target="_blank">/api/status.php</a>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">System status & Postgres connection</span>
                </li>
                <li class="endpoint-item">
                    <div>
                        <span class="method get">GET</span>
                        <span class="method post">POST</span>
                        <span class="method put">PUT</span>
                        <span class="method delete">DEL</span>
                        <a href="api/properties.php" class="endpoint-link" target="_blank">/api/properties.php</a>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Listings CRUD & Favorite toggling</span>
                </li>
                <li class="endpoint-item">
                    <div>
                        <span class="method get">GET</span>
                        <span class="method post">POST</span>
                        <span class="method put">PUT</span>
                        <a href="api/appointments.php" class="endpoint-link" target="_blank">/api/appointments.php</a>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Viewing appointments & schedule bookings</span>
                </li>
                <li class="endpoint-item">
                    <div>
                        <span class="method get">GET</span>
                        <span class="method post">POST</span>
                        <span class="method put">PUT</span>
                        <a href="api/inquiries.php" class="endpoint-link" target="_blank">/api/inquiries.php</a>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Buyer inquiries & threaded chat messages</span>
                </li>
                <li class="endpoint-item">
                    <div>
                        <span class="method get">GET</span>
                        <span class="method post">POST</span>
                        <a href="api/auth.php?action=agent_profile" class="endpoint-link" target="_blank">/api/auth.php</a>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Agent & Client login, registration, profiles</span>
                </li>
                <li class="endpoint-item">
                    <div>
                        <span class="method get">GET</span>
                        <span class="method put">PUT</span>
                        <a href="api/notifications.php" class="endpoint-link" target="_blank">/api/notifications.php</a>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">System & lead activity notifications</span>
                </li>
                <li class="endpoint-item">
                    <div>
                        <span class="method get">GET</span>
                        <span class="method post">POST</span>
                        <a href="api/sync.php" class="endpoint-link" target="_blank">/api/sync.php</a>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.85rem;">Mobile bidirectional bulk sync engine</span>
                </li>
            </ul>
        </div>

        <div class="card setup-steps">
            <h2>XAMPP & PostgreSQL Setup Instructions</h2>
            <ol>
                <li><strong>Install PostgreSQL:</strong> Download and install PostgreSQL with default port <code>5432</code> and password (e.g. <code>postgres</code>).</li>
                <li><strong>Create Database:</strong> Open pgAdmin or terminal, run <code>CREATE DATABASE estateflow_db;</code>, and execute the SQL script in <code>backend/database/schema.sql</code>.</li>
                <li><strong>Enable PostgreSQL in XAMPP:</strong> Open XAMPP Control Panel &rarr; Click <em>Config</em> beside Apache &rarr; Open <code>php.ini</code>. Find and remove the semicolon <code>;</code> from:
                    <pre>;extension=pdo_pgsql
;extension=pgsql</pre>
                    Change to:
                    <pre>extension=pdo_pgsql
extension=pgsql</pre>
                </li>
                <li><strong>Restart Apache:</strong> In XAMPP Control Panel, Stop and Start Apache.</li>
                <li><strong>Place Folder:</strong> Put this <code>backend/</code> folder inside <code>C:\xampp\htdocs\estateflow-backend</code>.</li>
                <li><strong>Configure Database Password:</strong> If your PostgreSQL password is not <code>postgres</code>, edit <code>backend/config/db.php</code> to match.</li>
            </ol>
        </div>
    </div>
</body>
</html>
