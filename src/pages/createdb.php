<?php
try {
    $db_path = __DIR__ . '/../../db_sqlite/bilet.sqlite';
    
    if (file_exists($dbPath)) {
        unlink($dbPath); // Deletes the file
        echo "Database deleted successfully.";
    } else {
        echo "Database file not found.";
    }
    
    mkdir(dirname($db_path), 0755, true);

    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    echo "Database created successfully at: $db_path\n";

    $sql = "
        -- ==============================
        -- Bus Companies
        -- ==============================
        CREATE TABLE IF NOT EXISTS Bus_Company (
            id TEXT PRIMARY KEY,
            name TEXT UNIQUE NOT NULL,
            logo_path TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        -- ==============================
        -- Users
        -- ==============================
        CREATE TABLE IF NOT EXISTS User (
            id TEXT PRIMARY KEY,
            full_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            password TEXT NOT NULL,
            company_id TEXT,
            balance REAL DEFAULT 800,
            gender TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES Bus_Company(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        );

        -- ==============================
        -- Coupons
        -- ==============================
        CREATE TABLE IF NOT EXISTS Coupons (
            id TEXT PRIMARY KEY,
            code TEXT NOT NULL,
            discount REAL NOT NULL,
            company_id TEXT,
            usage_limit INTEGER NOT NULL,
            expire_date TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES Bus_Company(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        );

        -- ==============================
        -- User Coupons
        -- ==============================
        CREATE TABLE IF NOT EXISTS User_Coupons (
            id TEXT PRIMARY KEY,
            coupon_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (coupon_id) REFERENCES Coupons(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (user_id) REFERENCES User(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        );

        -- ==============================
        -- Trips
        -- ==============================
        CREATE TABLE IF NOT EXISTS Trips (
            id TEXT PRIMARY KEY,
            company_id TEXT NOT NULL,
            bus_type TEXT NOT NULL DEFAULT '2+2',
            destination_city TEXT NOT NULL,
            arrival_time TEXT NOT NULL,
            departure_time TEXT NOT NULL,
            departure_city TEXT NOT NULL,
            price REAL NOT NULL,
            capacity INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES Bus_Company(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        );

        -- ==============================
        -- Tickets
        -- ==============================
        CREATE TABLE IF NOT EXISTS Tickets (
            id TEXT PRIMARY KEY,
            trip_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'ACTIVE',
            total_price REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES Trips(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (user_id) REFERENCES User(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        );

        -- ==============================
        -- Booked Seats
        -- ==============================
        CREATE TABLE IF NOT EXISTS Booked_Seats (
            id TEXT PRIMARY KEY,
            ticket_id TEXT NOT NULL,
            seat_number INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES Tickets(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        );
    ";

    $pdo->exec($sql);
    echo "✅ All tables created successfully with full cascading.\n";
    header('Location: /seed_db');

} catch (PDOException $e) {
    die('❌ Error creating database or tables: ' . $e->getMessage());
}
